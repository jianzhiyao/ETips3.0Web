<?php
	include "class.MySQL.php";
	$database="ETipsCache";//database
	$username="root";
	$password="123456";
	$hostname="localhost";
	$table="cache";
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