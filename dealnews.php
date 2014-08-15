<?php
	session_start();
	require_once "mysqlTool.php";
	isset($_REQUEST['action']) or die("no action");
	$action=strtolower($_REQUEST['action']);
	switch($action){
		case "add":{
			($_SESSION['status']=="yes") or die("非法登录");
			isset($_REQUEST['title']) or die("no title");
			isset($_REQUEST['content']) or die("no content");
			$title=$_REQUEST['title'];
			$content=$_REQUEST['content'];
			$sql="insert into news (title,content) values('$title','$content')";
			mysql_query($sql) or die(mysql_error());
		};break;
		case "delete":{
			($_SESSION['status']=="yes") or die("非法登录");
			isset($_REQUEST['id']) or die("no id");
			$id=$_REQUEST['id'];
			$sql="delete from news where id='$id'";
			mysql_query($sql) or die(mysql_error());
		};break;
		case "getnewslist":{
			$sql="select * from news order by id desc";
			$return=array();
			$res=mysql_query($sql) or die(mysql_error());
			while($row=mysql_fetch_assoc($res)){
				unset($row['content']);
				$return[count($return)]=$row;
			}
			echo json_encode($return);exit;
		};break;
		case "getnewscontent":{
			isset($_REQUEST['id']) or die("no id");
			$id=$_REQUEST['id'];
			$sql="select * from news where id='$id'";
			$res=mysql_query($sql) or die(mysql_error());
			$row=array();
			if($row=mysql_fetch_assoc($res)){
				echo json_encode($row);
				mysql_query("update news set click_amount=click_amount+1 where id='$id'");
				exit;
			}
			echo json_encode($row);exit;
		};break;
		default:die('无效指令');break;
	}
	echo '<script>
		history.go(-1);
	</script>';
?>