<?php
	include "httpclient.php";
	require_once "phpDOMpaser/simple_html_dom.php";
	define("USERNAME","一定对的子系统用户名");
	define("PASSWORD","一定对的密码");
	class Crawler{
		private $Url;
		private $State;
		private $ErrorMessage;
		private $UsernameId;
		private $PwdId;
		private $VificationCodeId;
		private $Username;
		private $Password;
		private $VificationCode;
		private $hc;//httpclient
		private $Method;
		private $Header;
		private $Cookie;
		private $Content;
		private $CourseContent;
		private $ScoreContent;
		public function __construct($AssocArray){
			$this->Method="get";
			foreach($AssocArray as $k =>$v)
			{
				$this->$k=$v;
				if($k=="Method")
					$this->$k=strtolower($v);
			}
			$this->hc=new httpclient(true);
			$this->getLogonNumber();
			$this->login("http://jwc.wyu.edu.cn/student/logon.asp");
		}
		public function __destruct(){
			unset($this);
		}
		private function getLogonNumber(){//提交请求,返回验证码
			$method="";
			$method=$this->Method;
			if(!is_array($this->Url))
				$this->hc->$method($this->Url);
			else
				foreach($this->Url as $k=> $v)
				{
					$this->hc->$method($v);
				}
			$this->Header=$this->hc->__get("header");
			$this->VificationCode=substr($this->Header, stripos($this->Header,"LogonNumber")+12,4);	
		}
		private function login($Url){
			$method="post";
			$form=array(
				$this->UsernameId=>$this->Username,
				$this->PwdId=>$this->Password,
				$this->VificationCodeId=>$this->VificationCode
			);
			$this->hc->post($Url,$form,"http://jwc.wyu.edu.cn/student/body.htm");
			if(strlen($this->Header=$this->hc->__get("header"))<=190)
			{
				$form=array(//用正确的账号密码登陆，防止ip被封
					$this->UsernameId=>USERNAME,
					$this->PwdId=>PASSWORD,
					$this->VificationCodeId=>$this->VificationCode
				);
				$this->hc->post($Url,$form,"http://jwc.wyu.edu.cn/student/body.htm");
				die();
			}
		}
		private function getCourse($Url)
		{
			$this->hc->get($Url,"","http://jwc.wyu.edu.cn/student/body.htm");//GBK
			return $this->CourseContent=iconv("GBK//IGNORE","UTF-8",$this->hc->__get("recv"));//gbk->utf-8
		}
		private function getScore($Url)
		{
			$this->hc->get($Url,"","http://jwc.wyu.edu.cn/student/menu.asp");//GBK
			return $this->ScoreContent=iconv("GBK//IGNORE","UTF-8", $this->hc->__get("recv"));//gbk->utf-8
		}
		public function getCourseTableJson()
		{
			$this->getCourse("http://jwc.wyu.edu.cn/student/f3.asp");//请求课程返回值内容,this->ScoreContent从这个函数获得
			//
			$html=new simple_html_dom($this->CourseContent);
			//$html->firstChild()->childNodes(1)->childNodes(1)->childNodes(2)->innertext;人物信息
			$courseTable=$html->firstChild()->childNodes(1)->childNodes(1)->childNodes(5)->firstChild()->childNodes(0);//课程表
			$course;
			$course=paserCourseDOM($courseTable);
			unset($courseTable);
			unset($html);
			return $course;	
		}
		public function getScoreTableJson()
		{
			$this->getScore("http://jwc.wyu.edu.cn/student/f4_myscore.asp");//获取分数页面内容,this->ScoreContent从这个函数获得
			//
			$html=new simple_html_dom($this->ScoreContent);
			$ScoreTable=$html->firstChild()->childNodes(1)->childNodes(2);//分数表
			$ScoreArray=paserSorceDOM($ScoreTable);
			unset($html);
			unset($ScoreTable);
			return $ScoreArray;
		}
		static function getCetScoreTable($zkzh,$xm){
			$hc=new httpclient(true);
			$hc->get("http://www.chsi.com.cn/cet/query",array("zkzh"=>$zkzh,"xm"=>$xm),"http://www.chsi.com.cn/cet/");
			$content=$hc->__get("recv");
			$html=new simple_html_dom($content);
			$table=$html->getElementsByTagName("table",1);
			$returnString=$table->__toString();
			if(strpos($returnString,"考试类别"))
				return $table->__toString();
			else
				return "";
		}
	}
	function paserCourseDOM($root){	
		$i=0;
		$course;
		for($i=0;$i<6;$i++)
		{
			$n=$root->childNodes($i);
			for($j=0;$j<8;$j++)
			{
				$nn=$n->childNodes($j);
				$course[$i][$j]=trim(strip_tags(str_replace(array("&nbsp;","<br>")," ",$nn->innertext)));
			}
		}
		return $course;
	}
	function paserSorceDOM($root)
	{
		$node=$root->childNodes(1);
			//echo $root->innertext;
			$nodeA=array();
			$corse=array();
			for($j=0,$i=1;strpos($node->innertext,"第 二 课 堂 学 分")<=0;$i++)
			{
// 				if($i%3==2)
// 					echo $node->innertext."<hr>";//学期信息
// 				if($i%3==0)
// 					echo $node->innertext."<hr>";//分数table
				$nodeA[$j++]=$node;
				$node=$root->childNodes($i+1);
			}
			$storeI=$i;
			$return=array("score"=>array(),"second"=>array());
			$i=0;
			$num=0;
			foreach($nodeA as $k=>$v)
			{
				//echo $v->innertext."<br>$k<hr>";
				if($k%3==1&&strpos($nodeA[$k]->innertext,"期")>0)//成绩table每三个为一组
				{
					$return["score"][$num][0]=strip_tags($nodeA[$k]->innertext);
					$nn=$nodeA[$k+1];
					$nnn=$nn->childNodes(0);
					for($i=0;$nnn;$i++)
					{
						//echo $nnn->innertext."<br>$i<hr>";
						for($j=0;$nnn->childNodes($j);$j++)
						{
							$nnnn=$nnn->childNodes($j) ;
							//echo $nnnn->innertext."$num||$i||$j<hr>";
							for($ii=0;$nnnn->childNodes($ii);$ii++)
							{
								$nnnnn=$nnnn->childNodes($ii);
								if($nnnnn->firstChild())
								{
									$return["score"][$num][1][$i][$j][$ii]= strip_tags($nnnnn->firstChild()->innertext);
								}
								else
								{
									$return["score"][$num][1][$i][$j][$ii]= strip_tags($nnnnn->innertext);
								}
							}	
						}
						$nnn=$nn->childNodes($i+1);
					}
					//echo '<br>《br》<br>';
					$num++;
				}
				$nodeA[$k]->__destruct();
			}
			unset($nodeA);
			//$i=$storeI+1;
			//$node=$root->childNodes($storeI+1);//课表table节点
			//echo $node->innertext;
			//for($i=0;$node->childNodes($i);$i++)
		//	{
			//	$nn=$node->childNodes($i);
		//		for($j=0;$nn->childNodes($j);$j++)
		//		{
		//			$return['second'][$i][$j]=trim(strip_tags($nn->childNodes($j)->innertext));	
		//		}
		//	}
			unset($node);
			return $return;
	}
	
	
?>