<?php
	require_once "Crawler.class.php";
	isset($_REQUEST['zkzh']) or die("no zkzh");
	isset($_REQUEST['xm']) or die("no xm");
	echo Crawler::getCetScoreTable($_REQUEST['zkzh'],$_REQUEST['xm']);
?>