<?php
	error_reporting(E_ALL^E_NOTICE^E_WARNING);
	require_once "Crawler.class.php";
	require_once "mysqlTool.php";
	if(!isset($_REQUEST['action'])||!isset($_REQUEST['username'])||!isset($_REQUEST['password']))
		exit;
	$action=strtolower($_REQUEST['action']);//user
	$user=$_REQUEST['username'];
	$pwd=$_REQUEST['password'];
	
	
	$time_limit=0;
	$function="";
	switch ($action)//get type,time_limit from value of action
	{
		case "score":{
			$function="Score";
			$time_limit=60*60*24;//1day
		}break;
		case "course":{
			$function="Course";
			$time_limit=60*60*24*15;//15days
		}break;
		default :
			$function="Score";break;
	}
	
	$res=mysql_query("select * from $table where user='$user' and pwd='$pwd' and type='$action'") or die(mysql_error());
	$row=mysql_fetch_assoc($res);
	mysql_free_result($res);
	
	$result=$row;
	if(is_array($result))//缓存
	{
		//var_dump($result);
		$delta=time()-$result['timestamp'];
		if($delta<$time_limit)
		{
			echo $result['cache'];//"cache".
			exit;
		}
		else
		{
			//echo "delete";
			mysql_query("delete from $table where id='$result[id]'") or die(mysql_error());
		}
	}//echo "not cache";
	//
	$c0=new Crawler(array(
		"UsernameId"=>"UserCode",
		"PwdId"=>"UserPwd",
		"VificationCodeId"=>"Validate",
		"Username"=>$user,//
		"Password"=>$pwd,//
		"Method"      =>"get",
		"Url"=>array(
			"http://jwc.wyu.edu.cn/student/body.htm",
			"http://jwc.wyu.edu.cn/student/rndnum.asp"
		)
	));//填写信息
	$function="get".$function."TableJson";
	$json=json_encode($c0->$function());
	echo $json;
	$vars=array(
			$user,
			addslashes($json),
			time(),
			$action,
			md5($json),
			$pwd
	);
	$sql="insert into cache (user,cache,timestamp,type,contentMD5,pwd) values('$vars[0]','$vars[1]','$vars[2]','$vars[3]','$vars[4]','$vars[5]')";
	mysql_query($sql);
	unset($c0);
	unset($vars);
?>