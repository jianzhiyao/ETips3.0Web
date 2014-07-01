<?php

/**
 * @author	xiaozhao
 * @email	270656184@qq.com
 * @blog	http://zhaoyl.sinaapp.com/
 * @date	20130907
 * @copyright 2013
 */

class httpclient {
	protected $use_cookie;
	protected $cookie;
	private $request_header = array(
		'Host'				=>	'',
		'Connection'		=>	'keep-alive',
		'User-Agent'		=>	'PHP_HTTP/5.2 (compatible; Chrome; MSIE; Firefox; Opera; Safari; QQ:270656184)',
		//'User-Agent'		=>	'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Sicent1; Sicent1)',
		//'User-Agent'		=>	'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.79 Safari/535.11',
		'Accept'			=>	'*/*',
		'Accept-Encoding'	=>	'gzip,deflate',
		'Accept-Language'	=>	'zh-cn',
		'Cookie'			=>	'',
		'Set-Cookie'        =>  '',
	);
	
	public $debug;
	public $enable_redirect = true;
	private $url;
	private $url_info;
	protected $referer;
	protected $timeout = 5;
	
	protected $header;
	protected $recv;
	protected $status;
	protected $mime;
	protected $length;
	
	public function __construct($use_cookie = true){
		$this->use_cookie = (bool)$use_cookie;
	}
	
	public function set_header($name,$value){
		$this->request_header[$name] = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
	
	public function debug($var,$name = ''){
		if ($this->debug && $var){
			echo '<div style="border: 1px solid red; padding: 8px; margin: 8px;">',
				 '<strong>&lt;<font color="red">',$name,'</font>&gt; - http debug</strong> ';
			$content = htmlspecialchars(print_r($var,true));
			echo '<pre>'.$content.'</pre>';
			echo '</div>';
		}
	}
	
	public function get($url, $form='', $referer=''){
		//准备工作
		$this->prepare($url,$referer);
		
		//处理表单数据
		$query = self::build_query($form);
		if(!empty($query)) $this->url_info['query'] = '?'.$query;
		
		//打开socket
		$fp = $this->getfp();
		if(!$fp)return false;
		
		//发送请求
		$header = $this->build_request('get');
		if($this->debug)$this->debug($header,'Request Header');
		fputs($fp,$header);
		
		//接收数据
		$this->receive_data($fp);
		fclose($fp);
		$this->process_header();
		
		if($this->debug)$this->debug($this->recv,'Content');
		return $this->recv;
	}
	
	public function post($url, $form='', $referer='', $content_type='') {
		//准备工作
		$this->prepare($url,$referer);
		
		//处理表单数据
		$query = self::build_query($form);
		
		//打开socket
		$fp = $this->getfp();
		if(!$fp) return false;
		
		//发送请求
		$content_type = empty($content_type)?'application/x-www-form-urlencoded':$content_type;
		$header = $this->build_request('post',$content_type,strlen($query));
		if($this->debug){
			$this->debug($header,	'Request Header');
			$this->debug($query,	'Form Data');
		}
		fputs($fp,$header);
		fputs($fp,$query);
		
		//接收数据
		$this->receive_data($fp);
		fclose($fp);
		$this->process_header();
		
		if($this->debug)$this->debug($this->recv,'Content');
		return $this->recv;
	}
	
	public function post2($url,$form='',$files=array(),$referer=''){
		//准备工作
		$this->prepare($url,$referer);
		
		//处理表单数据
		$post_data = '';
		
		$boundary = '----Boundary'.md5(microtime());
		$format = "--{$boundary}\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n";
		$form_data = array();
		if(is_string($form)) parse_str($form,$form);
		foreach($form as $k=>$v){
			if(is_array($v)){
				$form_data = array_merge($form_data,self::kv_form($v,$k));
			}else{
				$form_data[$k] = $v;
			}
		}
		foreach($form_data as $k=>$v){
			$post_data .= sprintf($format,$k,$v);
		}
		
		$format	= "--{$boundary}\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n%s\r\n";
		foreach($files as $file) {
			$basename = basename($file['file']);
			$extname = strrchr($basename,'.');
			$content_type = self::file_type($extname);
			$content = isset($file['content']) ? $file['content'] : file_get_contents($file['file']);
			$post_data .= sprintf($format,$file['name'],$basename,$content_type,$content);
		}
		$post_data .= "--{$boundary}--\r\n";
		
		//打开socket
		$fp = $this->getfp();
		if(!$fp)return false;
		
		//发送请求
		$content_type = 'multipart/form-data; boundary='.$boundary;
		$content_length = strlen($post_data);
		$header = $this->build_request('post',$content_type,$content_length);
		if($this->debug){
			$this->debug($header,'Request Header');
			$this->debug($post_data,'form data');
		}
		fputs($fp,$header);
		fputs($fp,$post_data);
		
		//接收数据
		$this->receive_data($fp);
		fclose($fp);
		$this->process_header();
		
		if($this->debug)$this->debug($this->recv,'Content');
		return $this->recv;
	}
	
	private function prepare($url,$referer){
		$this->error = '';
		$this->referer = $referer=='auto' ? $this->url : (string)$referer;
		$this->url = $url;
		$this->url_info = $this->parse_url($url);
		
		$this->header = '';
		$this->recv = '';
		$this->status = 0;
		$this->mime = '';
		$this->length = 0;
	}
	
	private function getfp(){
		$ui = $this->url_info;
		if( !in_array($ui['scheme'],array('http','https')) ) {
			$this->error = '不支持的协议！';
			return false;
		}
		
		if( $ui['scheme']=='http' )
			$fp = fsockopen($ui['host'],$ui['port'],$errno,$errstr,$this->timeout);
		else
			$fp = fsockopen('ssl://'.$ui['host'],$ui['port'],$errno,$errstr,$this->timeout);
					
		stream_set_timeout($fp,$this->timeout);
		if(!$fp) $this->error = $errno.':'.$errstr;
		return $fp;
	}
	
	public static function parse_url($url){
		$url = parse_url($url);
		if(!isset($url['port'])) $url['port'] = ($url['scheme'] == 'https' ? 443 : 80);
		$url['query'] = isset($url['query']) ? ('?'.$url['query']) : '';
		if(!isset($url['path'])){$url['path'] = '/';}
		return $url;
	}
	
	public static function build_query($form){
		//处理附加数据
		return ( is_array($form) || is_object($form) ) ? http_build_query($form) : (string)$form ;
	}
	
	private function build_request($method, $content_type='', $content_length=''){
		$ui = $this->url_info;
		
		$header = strtoupper($method)." {$ui['path']}{$ui['query']} HTTP/1.1\r\n";
		
		//修改默认请求头
		$headers = $this->request_header;
		if( !empty($this->referer) ) 
			$headers['Referer'] = $this->referer;
		
		$headers['Host'] = $ui['host'];
		$headers['Content-Length']	= $content_length;
		$headers['Content-Type']	= $content_type;
		
		if($this->use_cookie && $cookie = $this->get_cookie($ui['host'],$ui['path']))
			$headers['Cookie'] = $cookie;
		foreach($headers as $k=>$v) {
			if( strlen($v)>0 ) $header .= "{$k}: {$v}\r\n";
		}
		$header .= "\r\n";
		
		return $header;
	}
	
	public static function fread_my($fp,$length){
		$data = '';
		while( strlen($data) < $length && !feof($fp) ) {
			$meta_data = stream_get_meta_data($fp);
			if ( $meta_data['timed_out'] ) {
				if ( $this->debug ) {
					$this->debug("连接超时","timeout");
				}
				break;
			}
			$data .= fread($fp, $length - strlen($data));
		}
		
		return array('data'=>$data,'timeout'=>$meta_data['timed_out']);
	}
	
	private function receive_data($fp){
		$this->receive_header($fp);
		if( strlen($this->header) == 0 ) return ;
		
		//chunk模式
		if(preg_match('|transfer-encoding:\s*?chunked|i',$this->header)){
			while( !feof($fp) && ($pack_len = hexdec(fgets($fp))) ) {
				$data = self::fread_my($fp,$pack_len);
				$this->recv .= $data['data'];
				if ( $data['timeout'] ) break;
				fgets($fp);//读取\r\n
			}
			fgets($fp);//读取\r\n
			return;
		}
		
		//keep-alive 方式的处理;
		$connection = $this->request_header['Connection'];
		if(strtolower($connection) == 'keep-alive') {
	 		if(preg_match('|content-length:\s*?([0-9]{1,})|i',$this->header,$length)){
				$length=(int)$length[1];
				$this->length = $length;
				$data = self::fread_my($fp, $length);
				$this->recv = $data['data'];
				return;
			}else if(preg_match('|Connection:\s*?Close|i',$this->header)){
				//服务器强制使用close方式
				NULL;//这里没有return;以便继续接收数据
			}else if(stripos($this->header,'HTTP/1.1 100 continue')!==false){
				$this->header = '';
				$this->receive_data($fp);
				return;
			}else{
				$this->error = '无法继续接收数据！';
				return;
			}
		}
		
		while(!feof($fp))
			$this->recv .= fread($fp,8192);
	}
	
	private function receive_header($fp){
		do{
			$this->header .= fgets($fp);
		}while(!strpos($this->header,"\r\n\r\n") && !feof($fp));
		if($this->debug)$this->debug($this->header,'Response Header');
		
		//截取响应头
		$this->header = substr($this->header,0,-4);
	}
	
	private function process_header(){
		$http_headers = explode("\r\n",$this->header);
		
		preg_match('|HTTP/1.[10]\s*([0-9]{3})\s*(.*)$|i',$http_headers[0],$match);
		$this->status = (int)$match[1];
		unset($http_headers[0]);
		
		foreach($http_headers as $header){
			list($header_name,$header_content) = explode(':',$header,2);
			$header_name	=	strtolower(trim($header_name));
			$header_content	=	trim($header_content);
			
			switch($header_name){
				case 'content-type':
					$mime = explode(';',$header_content,2);
					$this->mime = trim($mime[0]);
					unset($mime);
					break;
				
				case 'set-cookie':
					if($this->use_cookie)
						$this->parse_cookie($header_content);
					break;
				
				case 'content-length':
					$this->length = (int)$header_content;
					break;
				
				case 'content-encoding':
					if($header_content == 'gzip')
						$this->recv = gzinflate(substr($this->recv,10,-8));
					elseif($header_content == 'deflate')
						$this->recv = gzinflate($this->recv);
					$decode_length = strlen($this->recv);
					break;
				
				case 'location':
					$location = self::convert_url($header_content,$this->url);
					break;
				
				default:
					continue;
			}
		}
		
		if(isset($decode_length))
			$this->length = $decode_length;
		
		if($this->enable_redirect && in_array($this->status,array(302,301)) && !empty($location) )
			$this->get($location,'',$this->referer);
		return ;
	}
	
	private function parse_cookie($header){
		//匹配cookie键名和值
		preg_match('|([^=]*?)=([^;]*)|',$header,$_cookie);
		
		$name = trim($_cookie[1]);
		$value = trim($_cookie[2]);
		
		//从header中匹配域名
		if(preg_match('|domain=([^;]*)|i',$header,$domain)){
			$domain = $domain[1];
			$hostonly = false;
		}else{
			$domain = $this->url_info['host'];
			$hostonly = true;
		}
		
		//从header中匹配路径
		if(preg_match('|path=([^;]*)|i',$header,$path)){
			$path = $path[1];
		}else{
			$path = $this->url_info['path'];
			$path = substr($path,0,strrpos($path,'/'));
			if(empty($path))$path = '/';
		}
		
		//从header中匹配时间
		if(preg_match('|expires=([^;]*)|i',$header,$expires)){
			$expires = $expires[1];
			
			//32位机时间戳溢出修正
			if(preg_match('|\d{2}-[a-z]{3,4}-(\d*)|i',$expires,$match)){
				if($match[1] >= 38 && $match[1] < 100) $expires = '2038-01-18';
			}
		}else{
			$expires = '2038-01-18';
		}
		$expires = strtotime($expires);
		$this->_set_cookie($name,$value,$expires,$path,$domain,$hostonly);
	}
	
	public function set_cookie($name,$value,$expire,$path,$domain,$secure = '',$hostonly = false){
		if(empty($name)||empty($value)){
			return false;
		}else{
			$name = rawurlencode($name);
			$value = rawurlencode($value);
		}
		if(empty($expire)) return false;
		if(empty($path))$path = '/';
		if(empty($domain)) return false; else $domain = strtolower($domain);
		
		$this->_set_cookie($name,$value,$expire,$path,$domain,$hostonly);
		return true;
	}
	private function _set_cookie($name,$value,$expire,$path,$domain,$hostonly = false){
		$domain = explode('.',$domain);
		$count = count($domain);
		$pCookie = &$this->cookie;
		for($i=$count-1;$i>=0;$i--){
			$subdomain = $domain[$i];
			if(trim($subdomain) == '')continue;
			$pCookie = &$pCookie[$subdomain];
		}
		
		if($hostonly)
			$pCookie = &$pCookie['..'];
		else
			$pCookie = &$pCookie['.'];
			
		$pCookie = &$pCookie[$path];
		
		if($expire<time())
			unset($pCookie[$name]);
		else
			$pCookie[$name] = $value;
	}
	
	public function get_cookie($domain = '',$path = '/'){
		if(empty($domain)) $domain = $this->url_info['host'];
		if(empty($path)) $path = '/';
		
		$domain = explode('.',$domain);
		$count = count($domain);
		
		$pCookie = $this->cookie;
		$Cookie = '';
		for($i=$count-1;$i>=0;$i--){
			//遍历各级域名
			$subdomain = $domain[$i];
			if(trim($subdomain) == '')continue;
			
			if(isset($pCookie[$subdomain]))
				$pCookie = &$pCookie[$subdomain];
			else
				{unset($pCookie);break;}
			
			if( isset($pCookie['.']) && is_array($pCookie['.']) ){
				foreach($pCookie['.'] as $_path=>$_cookie){
					//遍历路径
					if( $_cookie && strpos($path,$_path)===0 ){
						foreach($_cookie as $key=>$val){
							if(empty($key))contine;
							$Cookie .= $key.'='.$val.';';
						}
					}
				}
			}
		}
		
		if( $i==-1 && isset($pCookie) && isset($pCookie['..']) && is_array($pCookie['..']) ){
			foreach($pCookie['..'] as $_path=>$_cookie){
				if( $_cookie && strpos($path,$_path)===0 ){
					foreach($_cookie as $key=>$val){
						if(empty($key))contine;
						$Cookie .= $key.'='.$val.';';
					}
				}
			}
		}
		
		return $Cookie;
	}
	public function bind_cookie(&$ptr){
		$this->cookie = &$ptr;
	}
	public function export_cookie(){
		return $this->cookie;
	}
	public function import_cookie($cookie){
		$this->cookie = $cookie;
	}
	
	/**
	 * url相对路径转绝对路径
	 **/
	public static function convert_url($url,$pos){
		if(empty($url) || strpos($url,'#') ===0 )
			return $pos;
		elseif(strpos($url,'http://') === 0 || strpos($url,'https://')===0)
			return $url;
		else{
			$p = parse_url($pos);
			$prefix  = $p['scheme'].'://'.$p['host'];
			$prefix .= (isset($p['port']) && $p['port']!='80' ? ':'.$p['port'] : '');
			
			if(strpos($url,'/')===0)
				//绝对路径
				return $prefix.$url;
			elseif(strpos($url,'?')===0){
				//以问号开始,将$url作为参数附加到$pos上
				return $prefix.'/'.$p['path'].$url;
			}else{
				//相对路径
				$p1 = (empty($p['path']) || $p['path'] == '/')? array() : explode('/',substr($p['path'],1));
				array_pop($p1);
				
				@list($p2,$q) = explode('?',$url,2);
				$p2 = explode('/',$p2);
				while(($e = array_shift($p2)) !== NULL){
					if($e == '.')
						continue;
					elseif($e == '..')
						array_pop($p1);
					else
						array_push($p1,$e);
				}
				$path = join('/',$p1);
				return $prefix . '/' . $path . ($q ? '?'.$q : '') ;
			}
		}
	}
	public static function kv_form($array,$prefix){
		$res = array();
		foreach($array as $k=>$v){
			$key = $prefix.'['.$k.']';
			if(is_array($v)){
				$res = array_merge($res,self::kv_form($v,$key));
			}else{
				$res[$key] = $v;
			}
		}
		return $res;
	}
	public static function file_type($ext){
		static $types = array(
			'' => 'application/octet-stream',
			'acx' => 'application/internet-property-stream',
			'ai' => 'application/postscript',
			'aif' => 'audio/x-aiff',
			'aifc' => 'audio/x-aiff',
			'aiff' => 'audio/x-aiff',
			'asp' => 'text/plain',
			'aspx' => 'text/plain',
			'asf' => 'video/x-ms-asf',
			'asr' => 'video/x-ms-asf',
			'asx' => 'video/x-ms-asf',
			'au' => 'audio/basic',
			'avi' => 'video/x-msvideo',
			'axs' => 'application/olescript',
			'bas' => 'text/plain',
			'bcpio' => 'application/x-bcpio',
			'bin' => 'application/octet-stream',
			'bmp' => 'image/bmp',
			'c' => 'text/plain',
			'cat' => 'application/vnd.ms-pkiseccat',
			'cdf' => 'application/x-cdf',
			'cer' => 'application/x-x509-ca-cert',
			'class' => 'application/octet-stream',
			'clp' => 'application/x-msclip',
			'cmx' => 'image/x-cmx',
			'cod' => 'image/cis-cod',
			'cpio' => 'application/x-cpio',
			'crd' => 'application/x-mscardfile',
			'crl' => 'application/pkix-crl',
			'crt' => 'application/x-x509-ca-cert',
			'csh' => 'application/x-csh',
			'css' => 'text/css',
			'dcr' => 'application/x-director',
			'der' => 'application/x-x509-ca-cert',
			'dir' => 'application/x-director',
			'dll' => 'application/x-msdownload',
			'dms' => 'application/octet-stream',
			'doc' => 'application/msword',
			'dot' => 'application/msword',
			'dvi' => 'application/x-dvi',
			'dxr' => 'application/x-director',
			'eps' => 'application/postscript',
			'etx' => 'text/x-setext',
			'evy' => 'application/envoy',
			'exe' => 'application/octet-stream',
			'fif' => 'application/fractals',
			'flr' => 'x-world/x-vrml',
			'flv' => 'video/x-flv',
			'gif' => 'image/gif',
			'gtar' => 'application/x-gtar',
			'gz' => 'application/x-gzip',
			'h' => 'text/plain',
			'hdf' => 'application/x-hdf',
			'hlp' => 'application/winhlp',
			'hqx' => 'application/mac-binhex40',
			'hta' => 'application/hta',
			'htc' => 'text/x-component',
			'htm' => 'text/html',
			'html' => 'text/html',
			'htt' => 'text/webviewhtml',
			'ico' => 'image/x-icon',
			'ief' => 'image/ief',
			'iii' => 'application/x-iphone',
			'ins' => 'application/x-internet-signup',
			'isp' => 'application/x-internet-signup',
			'jfif' => 'image/pipeg',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'js' => 'application/x-javascript',
			'latex' => 'application/x-latex',
			'lha' => 'application/octet-stream',
			'lsf' => 'video/x-la-asf',
			'lsx' => 'video/x-la-asf',
			'lzh' => 'application/octet-stream',
			'm13' => 'application/x-msmediaview',
			'm14' => 'application/x-msmediaview',
			'm3u' => 'audio/x-mpegurl',
			'man' => 'application/x-troff-man',
			'mdb' => 'application/x-msaccess',
			'me' => 'application/x-troff-me',
			'mht' => 'message/rfc822',
			'mhtml' => 'message/rfc822',
			'mid' => 'audio/mid',
			'mny' => 'application/x-msmoney',
			'mov' => 'video/quicktime',
			'movie' => 'video/x-sgi-movie',
			'mp2' => 'video/mpeg',
			'mp3' => 'audio/mpeg',
			'mpa' => 'video/mpeg',
			'mpe' => 'video/mpeg',
			'mpeg' => 'video/mpeg',
			'mpg' => 'video/mpeg',
			'mpp' => 'application/vnd.ms-project',
			'mpv2' => 'video/mpeg',
			'ms' => 'application/x-troff-ms',
			'mvb' => 'application/x-msmediaview',
			'nws' => 'message/rfc822',
			'oda' => 'application/oda',
			'p10' => 'application/pkcs10',
			'p12' => 'application/x-pkcs12',
			'p7b' => 'application/x-pkcs7-certificates',
			'p7c' => 'application/x-pkcs7-mime',
			'p7m' => 'application/x-pkcs7-mime',
			'p7r' => 'application/x-pkcs7-certreqresp',
			'p7s' => 'application/x-pkcs7-signature',
			'pbm' => 'image/x-portable-bitmap',
			'pdf' => 'application/pdf',
			'pfx' => 'application/x-pkcs12',
			'pgm' => 'image/x-portable-graymap',
			'php' => 'text/plain',
			'pko' => 'application/ynd.ms-pkipko',
			'pma' => 'application/x-perfmon',
			'pmc' => 'application/x-perfmon',
			'pml' => 'application/x-perfmon',
			'pmr' => 'application/x-perfmon',
			'pmw' => 'application/x-perfmon',
			'png' => 'image/png',
			'pnm' => 'image/x-portable-anymap',
			'pot,' => 'application/vnd.ms-powerpoint',
			'ppm' => 'image/x-portable-pixmap',
			'pps' => 'application/vnd.ms-powerpoint',
			'ppt' => 'application/vnd.ms-powerpoint',
			'prf' => 'application/pics-rules',
			'ps' => 'application/postscript',
			'pub' => 'application/x-mspublisher',
			'qt' => 'video/quicktime',
			'ra' => 'audio/x-pn-realaudio',
			'ram' => 'audio/x-pn-realaudio',
			'ras' => 'image/x-cmu-raster',
			'rgb' => 'image/x-rgb',
			'rmi' => 'audio/mid',
			'roff' => 'application/x-troff',
			'rtf' => 'application/rtf',
			'rtx' => 'text/richtext',
			'scd' => 'application/x-msschedule',
			'sct' => 'text/scriptlet',
			'setpay' => 'application/set-payment-initiation',
			'setreg' => 'application/set-registration-initiation',
			'sh' => 'application/x-sh',
			'shar' => 'application/x-shar',
			'sit' => 'application/x-stuffit',
			'snd' => 'audio/basic',
			'spc' => 'application/x-pkcs7-certificates',
			'spl' => 'application/futuresplash',
			'src' => 'application/x-wais-source',
			'sst' => 'application/vnd.ms-pkicertstore',
			'stl' => 'application/vnd.ms-pkistl',
			'stm' => 'text/html',
			'svg' => 'image/svg+xml',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc' => 'application/x-sv4crc',
			'swf' => 'application/x-shockwave-flash',
			't' => 'application/x-troff',
			'tar' => 'application/x-tar',
			'tcl' => 'application/x-tcl',
			'tex' => 'application/x-tex',
			'texi' => 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tgz' => 'application/x-compressed',
			'tif' => 'image/tiff',
			'tiff' => 'image/tiff',
			'tr' => 'application/x-troff',
			'trm' => 'application/x-msterminal',
			'tsv' => 'text/tab-separated-values',
			'txt' => 'text/plain',
			'uls' => 'text/iuls',
			'ustar' => 'application/x-ustar',
			'vcf' => 'text/x-vcard',
			'vrml' => 'x-world/x-vrml',
			'wav' => 'audio/x-wav',
			'wcm' => 'application/vnd.ms-works',
			'wdb' => 'application/vnd.ms-works',
			'wks' => 'application/vnd.ms-works',
			'wmf' => 'application/x-msmetafile',
			'wmv' => 'video/x-ms-wmv',
			'wps' => 'application/vnd.ms-works',
			'wri' => 'application/x-mswrite',
			'wrl' => 'x-world/x-vrml',
			'wrz' => 'x-world/x-vrml',
			'xaf' => 'x-world/x-vrml',
			'xbm' => 'image/x-xbitmap',
			'xla' => 'application/vnd.ms-excel',
			'xlc' => 'application/vnd.ms-excel',
			'xlm' => 'application/vnd.ms-excel',
			'xls' => 'application/vnd.ms-excel',
			'xlt' => 'application/vnd.ms-excel',
			'xlw' => 'application/vnd.ms-excel',
			'xof' => 'x-world/x-vrml',
			'xpm' => 'image/x-xpixmap',
			'xwd' => 'image/x-xwindowdump',
			'z' => 'application/x-compress',
			'zip' => 'application/zip',
		);
		$ext = substr($ext,1);
		if(!array_key_exists($ext,$types)) $ext = '';
		return $types[$ext];
	}
}
?>
