<?php 
define('VIEW_PATH', ROOT.'view/admin/');
class OfflineDownloadController{

	function index(){
		if($_POST['upload'] == 1){
			$url_input = realpath($_POST['url']);
			$remotepath = $_POST['remote'];
			$urls = $url_input.split('\n');
			$count = 0;
			foreach ($urls as $url) {
				if(filter_var($url, FILTER_VALIDATE_URL)){
					$this->add_task($url, $remotepath);
					$count += 1;
				}
			}
			if ($count == 0) {
				$message = "没有找到可以下载的Url。";
			} else {
				$message = $count."个链接已添加到了下载队列。";
			}
			$request = $this->task_request();
			$request['url'] = substr($request['url'],0,-4).'run';
			fetch::post($request);
		}elseif(!empty($_POST['begin_task'])){
			$this->task($_POST['begin_task']);
		}elseif(!empty($_POST['delete_task'])){
			//unset($_POST['delete_task']);
			//config('@offline_download', (array)$uploads);
		}elseif(!empty($_POST['empty_uploaded'])){
			config('@offline_download', array());
		}
		$uploading = config('@offline_download');
		$uploaded = array_reverse((array)config('@offline_downloaded'));
		return view::load('offline_download')->with('offline_download', $offline_download)->with('offline_downloaded', $offline_downloaded)->with('message', $message);
	}

	private function add_task($url, $remotefile){
	    $task = array(
			'url'=>$url,
			'remotepath' => $remotefile,
			'filesize'=>0,
			'upload_type'=>'web',
			'update_time'=>0,
	    );

	    $offline_download = (array)config('@offline_download');
	    if(empty($offline_download[$url])){
		    $offline_download[$url] = $task;
		    config('@offline_download', $offline_download);
	    }
	}

	//运行队列中的任务
	function run(){
		$offline_download = (array)config('@offline_download');
		$time = time();
		$runing = 0;
		foreach($offline_download as $task){
			if($time < ($task['update_time']+60) AND $task['type']=='web' ){
				$runing = $runing +1;
			}
			if($runing > 5)break;
		}
		
		foreach($offline_download as $url=>$task){
			if($time < ($task['update_time']+60) OR !is_array($task) ){
				continue;
			}
			$runing = $runing +1;
			fetch::post($this->task_request($url));
			if($runing > 5)break;
		}

		if(count($offline_download) > 5){
			set_time_limit(100);
			sleep(60);
			$request = $this->task_request();
			$request['url'] = substr($request['url'],0,-4).'run';
			fetch::get($request);
		}
	}

	private function task_request($url=''){
		$request['headers'] = "Cookie: admin=".md5(config('password').config('refresh_token')).PHP_EOL;
		$request['headers'] .= "Host: ".$_SERVER['HTTP_HOST'];
		$request['curl_opt']=[CURLOPT_CONNECTTIMEOUT => 1,CURLOPT_TIMEOUT=>1,CURLOPT_FOLLOWLOCATION=>true];
		$http_type = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
		$request['url'] = $http_type.'127.0.0.1'.get_absolute_path(dirname($_SERVER['PHP_SELF'])).'?/admin/offline_download/task';
		$request['post_data'] = 'url='.urlencode($url);
		return $request;
	}
	
	//执行任务
	function task($remotepath=null){
		$remotepath = is_null($remotepath)?$_POST['url']:$remotepath;
		//file_put_contents('log.txt',$remotepath.PHP_EOL, FILE_APPEND);
		$offline_download = config('@offline_download');
		$task = $offline_download[$remotepath];

		if(empty($task)){
			return;
		}

		$tempfile = tmpfile();
		try 
		{
			copyfile_chunked($task['url'], $tempfile);
			$offline_download = config('@offline_download');
			unset($offline_download[$remotepath]);
				
			config('@offline_download', (array)$offline_download);
			config($remotepath.'@offline_downloaded','success');

			$request = $this->task_request();
			$request['url'] = substr($request['url'], 0, -21).'upload/';
			$request['post_data'] = 'upload=1&local='.$tempfile.'&remote='.$task['remotepath'];
			fetch::post($request);
		}
		catch (Exception $e) {
			print($e->getMessage());
			unlink($tempfile);
		}
	}

	/**
	 * Copy remote file over HTTP one small chunk at a time.
	 *
	 * @param $infile The full URL to the remote file
	 * @param $outfile The path where to save the file
	 */
	function copyfile_chunked($infile, $outfile) {
		$chunksize = 10 * (1024 * 1024); // 10 Megs

		/**
		 * parse_url breaks a part a URL into it's parts, i.e. host, path,
		 * query string, etc.
		 */
		$parts = parse_url($infile);
		$i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
		$o_handle = fopen($outfile, 'wb');

		if ($i_handle == false || $o_handle == false) {
			return false;
		}

		if (!empty($parts['query'])) {
			$parts['path'] .= '?' . $parts['query'];
		}

		/**
		 * Send the request to the server for the file
		 */
		$request = "GET {$parts['path']} HTTP/1.1\r\n";
		$request .= "Host: {$parts['host']}\r\n";
		$request .= "User-Agent: Mozilla/5.0\r\n";
		$request .= "Keep-Alive: 115\r\n";
		$request .= "Connection: keep-alive\r\n\r\n";
		fwrite($i_handle, $request);

		/**
		 * Now read the headers from the remote server. We'll need
		 * to get the content length.
		 */
		$headers = array();
		while(!feof($i_handle)) {
			$line = fgets($i_handle);
			if ($line == "\r\n") break;
			$headers[] = $line;
		}

		/**
		 * Look for the Content-Length header, and get the size
		 * of the remote file.
		 */
		$length = 0;
		foreach($headers as $header) {
			if (stripos($header, 'Content-Length:') === 0) {
				$length = (int)str_replace('Content-Length: ', '', $header);
				break;
			}
		}

		/**
		 * Start reading in the remote file, and writing it to the
		 * local file one chunk at a time.
		 */
		$cnt = 0;
		while(!feof($i_handle)) {
			$buf = '';
			$buf = fread($i_handle, $chunksize);
			$bytes = fwrite($o_handle, $buf);
			if ($bytes == false) {
				return false;
			}
			$cnt += $bytes;

			/**
			 * We're done reading when we've reached the conent length
			 */
			if ($cnt >= $length) break;
		}

		fclose($i_handle);
		fclose($o_handle);
		return $cnt;
	}
}
