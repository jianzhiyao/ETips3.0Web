<?php
	include "class.MySQL.php";
	$database="ETipsCache";//database
	$username="数据库用户名";
	$password="数据库密码";
	$hostname="服务器名";
	$table="数据表";
	$port=3306;
// 	$database=SAE_MYSQL_DB;//database
// 	$username=SAE_MYSQL_USER;
// 	$password=SAE_MYSQL_PASS;
// 	$hostname=SAE_MYSQL_HOST_M;
// 	$table="cache";
// 	$port=SAE_MYSQL_PORT;
	$database=new MySQL($database, $username, $password, $hostname, $port);
	mysql_query("set names utf8");
?>