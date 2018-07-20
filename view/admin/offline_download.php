<?php view::layout('layout')?>
<?php view::begin('content');?>
<div class="mdui-container-fluid">

	<div class="mdui-typo">
	  <h1> 离线下载 <small>在服务器中下载后上传至Onedrive</small></h1>
	</div>

	<div class="mdui-row">
	  <div class="mdui-typo">
		  <p><mark><?php echo $message;?></mark></p>
	  </div>
	  <form action="" method="post">
		  <div class="mdui-col-xs-7">
			<div class="mdui-textfield">
			  <label class="mdui-textfield-label">下载Url（可用回车分隔多个Url）</label>
			  <textarea name="url" class="mdui-textfield-input" type="text"> </textarea>
			</div>
		  </div>
		  <div class="mdui-col-xs-3">
			<div class="mdui-textfield">
			  <label class="mdui-textfield-label">远程目录</label>
			  <input name="remote" class="mdui-textfield-input" type="text" value="/upload/"/>
			</div>
		  </div>
		  <div class="mdui-col-xs-2" style="padding-top: 34px;">
				<button type="submit" name="upload" value="1" class="mdui-btn mdui-btn-block mdui-color-green-600 mdui-ripple">
		      		<i class="mdui-icon material-icons">&#xe2c3;</i>
					上传
				</button>
		  </div>
	  </form>
	</div>
	<br>

	<div class="mdui-typo">
	  <h5>下载进度 <small></small></h5>
	</div>

	<div class="mdui-table-fluid">
	  <table class="mdui-table">
	    <thead>
	      <tr>
	        <th>Url</th>
	        <th>状态</th>
	        <th>操作</th>
	      </tr>
	    </thead>
	    <tbody>
		  <form action="" method="post">
		  <?php foreach( (array)$offline_download as $i => $task ):?>
		      <tr>
		        <td><?php echo $task['remotepath'];?></td>
		        <td><?php echo onedrive::human_filesize($task['speed']).'/s';?></td>
		        <td><?php echo @floor($task['offset']/$task['filesize']*100).'%'; ?></td>
		        <?php if( $task['update_time'] == 0 ):?>
		        	<td>
			        	等待上传中
		        	</td>
		        	<td>
			        	<button name="begin_task"  class="mdui-btn mdui-color-green-600 mdui-ripple" type="submit" name="remotepath" value="<?php echo $task['remotepath'];?>">上传</button>
		        	</td>
		        <?php elseif(time() > ($task['update_time']+60)):?>
		        	<td>已暂停</td>
		        	<td>
			        	<button name="begin_task"  class="mdui-btn mdui-color-green-600 mdui-ripple" type="submit" name="remotepath" value="<?php echo $task['remotepath'];?>">上传</button>
		        	</td>
		        <?php else:?>
		        	<td>上传中</td>
		        	<td>
			        	<button name="delete_task" class="mdui-btn mdui-color-red mdui-ripple" type="submit" name="remotepath" value="<?php echo $task['remotepath'];?>">删除</button>
		        	</td>
		        <?php endif;?>
		      </tr>
		  <?php endforeach;?>
		  </form>
	    </tbody>
	  </table>
	</div>

</div>
<script>
$('button[name=refresh]').on('click',function(){$('center').html('正在重建缓存，请耐心等待...');});
</script>
<?php view::end('content');?>