<?php
/**
 *=-------------------------------------------------------------------------------=
 *                         UClientSSO.class.php
 *=-------------------------------------------------------------------------------=
 * 
 * 用户单点登陆处理 的客户端类文件
 *  
 * 本文件中的UClientSSO类，主要用于提供给应用程序子站点 
 * 单点登陆，全站登陆检测，单点登出 的基本服务。
 * 
 *  登陆流程说明：
 *  1. 用户从打开浏览器开始，第一个登陆的子站点，必须调用UClientSSO::loginSSO()方法。该方法返回
 *     全站唯一的随机id用于标识该用户。该随机id在UClientSSO::loginSSO()中已通过本站cookie保存，
 *     即该子站点保留了用户已登陆标识的存根于本站。
 * 
 *     注意：
 * 		全站登陆，依赖于登陆客户端设置用户中心的这个随机id,即必须调用
 * 	    UClientSSO::getSynloginScript()方法获取W3C标准的script，在页面输出。
 * 
 *  2. 本站登陆成功后，进行本地化的用户登陆处理，其后验证用户是否登陆只在本地验证。
 * 	  （本地存取登陆用户状态的信息，请设置为关闭浏览器就退出）
 * 
 *  3. 当检测用户登陆状态时，请先调用本地的验证处理，若本地验证不通过，再调用
 * 		UClientSSO::checkUserLogin()方法到用户中心检测用户的登陆状态。
 * 
 *  4. 单点登出时，请调用UClientSSO::logoutSSO()方法。调用成功后，如需其他已登陆站立即登出，请调用
 *     UClientSSO::getSynloginScript()方法获取W3C标准的script，在页面输出。
 * 
 * 
 * Copyright(c) 2008 by 桂桂. All rights reserved.
 * @author 桂桂 <evan_gui@163.com>
 * @version $Id: IP2Country.class.php, v 1.0 2009/03/04 $
 * @package systen
 * @link http://www.guigui8.com/index.php/archives/34.html 
 * 
 * @history 
 *   1. create the class. (by 桂桂 on 2009.03.04)
 *  
 *   2. 修改在本站未登陆状态下，获取用户全站唯一id的方式。将跳转用户中心获取id的方式 改为设置相应域名的
 *      全域cookie来记录，这样避免在ajax等请求时，跳转不正常带来的问题。
 * 		(by 桂桂 on 2009.03.16)
 * 
 *   3. 增加获取当前在线用户详细信息 处理的接口：UClientSSO::getOnlineUserDetail();
 *      (by 桂桂 on 2009.03.25)
 */
include_once dirname(__FILE__).'/xml.class.php';

//获取站点主机后缀(.com, .tmc or .local etc)
$_aHttpHost  = explode('.', $_SERVER['HTTP_HOST']);
$_siteSurfix = $_aHttpHost[count($_aHttpHost) - 1];

//设置全站处理需要用到的cookie键名，域名
define('_UC_USER_COOKIE_NAME', 'cookiexxxxxxx'); 				 //永久记住用户信息的的cookie键名
define('_UC_SID_NAME', 'sidxxxxxxxxx');		  					 //全站sid cookie键名
define('_UC_USER_COOKIE_DOMAIN', '.hostname.' . $_siteSurfix);	 //全域cookie域名

define('_UC_KEY', 'xxxxxx');


/**
 *=------------------------------------------------------------------------=
 * class UClientSSO
 *=------------------------------------------------------------------------=
 * 
 * 用户单点登陆，登出处理 的客户端类
 * 
 * Copyright(c) 2008 by 桂桂. All rights reserved.
 * @author 桂桂 <evan_gui@163.com>
 * @version $Id: Model/OrderMgr.php, v 1.0 2008/4/21 $
 * @package systen
 */
class UClientSSO 
{
	/**
	 * 站点标志
	 *
	 * @var integer
	 * @access protected
	 */
	protected static $site = 0;
	
	/**
	 * SOAP通信协议[http=>'';https=>'ssl://';]
	 *
	 * @var string
	 * @access protected
	 */
	protected static $scheme = '';

	/**
	 * SOAP主机地址
	 *
	 * @var string
	 * @access protected
	 */
	protected static $host = 'hostname';
	
	/**
	 * SOAP通信端口[http=>80;https=>443;]
	 *
	 * @var integer
	 * @access protected
	 */
	protected static $port = 80;
	
	/**
	 * SOAP请求CGI
	 *
	 * @var string
	 * @access protected
	 */
	protected static $cgi = '/port/testMmoUser.php';
	
	/**
	 * SOAP请求限时
	 *
	 * @var integer
	 * @access protected
	 */
	protected static $stamp = 30;
	
	/**
	 * 本站向用户中心获取与设置全站id的链接地址
	 *
	 * @var unknown_type
	 */
	static private  $_getSessIdUrl;

	static private  $_ucSessKey = '_uc_user';	
	
	/**
	 * 与用户验证中心进行webservice请求通信的全局校验串
	 *
	 * @var unknown_type
	 */
	static private  $_mcComunicationKey = 'xxxxx';	
	
	/**
	 * 本站与用户中心 通过W3C标准，来获取与设置全站唯一id的通信私钥
	 *
	 * @var unknown_type
	 */
	static private  $_authcodeKey = 'xxxxx';	
	
	/**
	 * 全站登陆script串
	 *
	 * @var unknown_type
	 */
	static private  $_synloginScript = null;	

	/**
	 * 全站登出script串
	 *
	 * @var unknown_type
	 */
	static private  $_synlogoutScript = null;	
	
	/**
	 * 当前时间撮
	 *
	 * @var unknown_type
	 */
	static private  $_timestamp;	
	
	/**
	 * 本站是否安装有php soap模块的标记
	 *
	 * @var unknown_type
	 */
	static private  $_hasSoapModule;	
	
	
	/**
	 *=-------------------------------------------------------------------=
	 *=-------------------------------------------------------------------=
	 *		             一. 公共方法定义
	 *=-------------------------------------------------------------------=
	 *=-------------------------------------------------------------------=
	 */
	/**
	 * 用户验证中心 登陆用户处理
	 *
	 * @param string $username      - 用户名
	 * @param string $password      - 用户原始密码
	 * @param boolean $remember     - 是否永久记住登陆账号
	 * @param boolean $alreadyEnc   - 传入的密码是否已经经过simpleEncPass加密过
	 * 
	 * @return array   - integer $return['status'] 大于 0:返回用户 ID，表示用户登录成功
	 *                                                -1:用户不存在，或者被删除
	 *                                                -2:密码错
	 * 												 -11：验证码错误
	 * 					 string $return['username']     : 用户名
	 * 					 string $return['password']     : 密码
	 * 					 string $return['email']        : Email
	 */
	static public function loginSSO($username, $password, $remember=false, $alreadyEnc=false) {
		self::_init();
		self::_removeLocalSid();
		$ret = array();
		
		//
		//1. 处理传入webservice接口的参数
		//
		$_params  = array(
			'username' => $username,
			'password' => $alreadyEnc ? trim($password) : self::simpleEncPass(trim($password)),
			'ip'       => self::onlineip(),
			'siteFlag' => self::$site,
			'remember' => $remember
		);
		$_params['checksum'] = self::_getCheckSum($_params['username'] . $_params['password'] . 
			$_params['ip'] . $_params['siteFlag'] . $_params['remember']);
		
		//
		// 2.调用webservice接口，进行登陆处理
		//
		$aRet = self::_callSoap('loginUCenter', $_params);
		
		if (intval($aRet['resultFlag']) > 0 && $aRet['sessID']) {
			//成功登陆
			//设置本地session id
			self::_setLocalSid($aRet['sessID']);
			
			//设置用户中心的统一session id脚本路径
			self::$_synloginScript = urldecode($aRet['script']);
			
			$ret = $aRet['userinfo'];	
		} else {
			
			$ret['status'] = $aRet['resultFlag'];
		}
			
		return $ret;
	}
	
	/**
	 * 获取设置全站登陆的scirpt代码（获取后，请在页面中输出）
	 * 
	 * @return unknown
	 */
	public static function getSynloginScript(){
		return self::$_synloginScript;
	}
	
	/**
	 * 用户单点登陆验证函数
	 *
	 * @return array   - integer $return['status'] 大于 0:返回用户 ID，表示用户登录成功
	 * 												   0:用户没有在全站登陆
	 *                                                -1:用户不存在，或者被删除
	 *                                                -2:密码错
	 *                                                -3:未进行过单点登陆处理
	 * 												 -11：验证码错误
	 * 					 string $return['username']     : 用户名
	 * 					 string $return['password']     : 密码
	 * 					 string $return['email']        : Email
	 */
	public static function checkUserLogin(){
		self::_init();
		$ret = array();
		$_sessId = self::_getLocalSid();
		if (empty($_sessId)) {
			//永久记住账号处理
			if(isset($_COOKIE[_UC_USER_COOKIE_NAME]) && !empty($_COOKIE[_UC_USER_COOKIE_NAME])) 
			{

				//3. 根据cookie里的用户名和密码判断用户是否已经登陆。
				$_userinfo = explode('|g|', self::authcode($_COOKIE[_UC_USER_COOKIE_NAME], 'DECODE', self::$_authcodeKey));
				
				$username = $_userinfo[0];
				$password = isset($_userinfo[1]) ? $_userinfo[1] : '';
				if (empty($password)) {
					$ret['status'] = -3;
				} else {
					return self::loginSSO($username, $password, true, true);
				}
				
			} else {
				$ret['status'] = -3;
			}
			
		} else {
			//
			//本站原先已经登陆过，通过保留的sesson id存根去用户中心验证
			//
			$_params  = array(
				'sessId'   => $_sessId,
				'siteFlag' => self::$site,
				'checksum' => md5($_sessId . self::$site . self::$_mcComunicationKey)
			);
			$aRet = self::_callSoap('getOnlineUser', $_params);
			if (intval($aRet['resultFlag']) > 0) {
				//成功登陆
				$ret = $aRet['userinfo'];	
			} else {
				$ret['status'] = $aRet['resultFlag'];
			}
		}
		
		return $ret;
	}

	
	/**
	 * 全站单点登出
	 *  - 通过webservice请求注销掉用户的全站唯一标识
	 *
	 * @return integer    1: 成功
	 * 				    -11：验证码错误
	 */
	public static function logoutSSO(){
		self::_init();
		$_sessId = self::_getLocalSid();
		//
		//本站没有登陆的话，不让同步登出其他站
		//
		if (empty($_sessId)) {
			self::_initSess(true);
			return false;
		}
		$_params  = array(
			'sessId'   => $_sessId,
			'siteFlag' => self::$site,
			'checksum' => md5($_sessId . self::$site . self::$_mcComunicationKey)
		);

		$aRet = self::_callSoap('logoutUCenter', $_params);
		if (intval($aRet['resultFlag']) > 0) {
			//成功登出
			self::_removeLocalSid();		//移除本站记录的sid存根
			self::$_synlogoutScript = urldecode($aRet['script']);
			$ret = 1;
		} else {
			$ret = $aRet['resultFlag'];
		}
		return intval($ret);
	}

	/**
	 * 获取立即登出其他站的script代码
	 * 注意：如果用UClientSSO::logoutSSO()成功登出,且要立即登出其他站，
	 * 		必须调用本方法来获取登出代码，在页面中输出。
	 * 
	 * @return unknown
	 */
	public static function getSynlogoutScript(){
		return self::$_synlogoutScript;
	}
	
	/**
	 * 用户单点登陆验证函数 + 获取用户详细资料的处理
	 * 
	 * 注意：获取用户详细资料的处理 是在单点登陆成功后才会有信息返回，否则
	 *
	 * @return array   - integer $return['status']     1:表示资料获取成功
	 * 												   0:用户没有在全站登陆
	 *                                                -1:用户不存在，或者被删除
	 *                                                -3:未进行过单点登陆处理
	 * 												 -11：验证码错误
	 * 					 string $return[$specified_field]  : 所有指定的field字段内容
	 */
	public static function getOnlineUserDetail($fields = null){
		self::_init();
		$ret = array();
		$_sessId = self::_getLocalSid();
		if (empty($_sessId)) {
			//永久记住账号处理
			if(isset($_COOKIE[_UC_USER_COOKIE_NAME]) && !empty($_COOKIE[_UC_USER_COOKIE_NAME])) 
			{

				//3. 根据cookie里的用户名和密码判断用户是否已经登陆。
				$_userinfo = explode('|g|', self::authcode($_COOKIE[_UC_USER_COOKIE_NAME], 'DECODE', self::$_authcodeKey));
				
				$username = $_userinfo[0];
				$password = isset($_userinfo[1]) ? $_userinfo[1] : '';
				if (empty($password)) {
					$ret['status'] = -3;
				} else {
					$_loginRes = self::loginSSO($username, $password, true, true);
					
					//登陆成功，重新向用户中心发送获取详细信息的请求
					if ($_loginRes['status'] > 0) {
						$_sessId = self::_getLocalSid();
						if (empty($_sessId)) {
							$ret['status'] = -3;
						} else {
							return self::getOnlineUserDetail($fields);
						}
						
					} else {
						$ret['status'] = -3;
					}
					
				}// end of remember's login check
				
			} else {
				$ret['status'] = -3;
			}
			
		} else {
			//
			//本站原先已经登陆过，通过保留的sesson id存根去用户中心验证
			//
			$_params  = array(
				'sessId'   => $_sessId,
				'siteFlag' => self::$site,
				'fields'   => $fields,
				'checksum' => md5($_sessId . self::$site . self::$_mcComunicationKey)
			);
			$aRet = self::_callSoap('getOnlineUserDetail', $_params);
			if (intval($aRet['resultFlag']) > 0) {
				//成功登陆
				$ret = $aRet['userinfo'];	
				$ret['status'] = 1;
			} else {
				$ret['status'] = $aRet['resultFlag'];
			}
		}
		
		return $ret;
	}
	
	public static function getOnlineList() {}
	
	public static function getUserCount() {}
	
	public static function remove() {}

	/**
	 * 与用户中心通信的可逆 加密方法
	 *
	 * @param unknown_type $string
	 * @param unknown_type $operation
	 * @param unknown_type $key
	 * @param unknown_type $expiry
	 * @return unknown
	 */
	public static function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
		$string = str_replace(' ', '+', $string);
		$ckey_length = 4;
	
		$key = md5($key ? $key : _UC_KEY);
		$keya = md5(substr($key, 0, 16));
		$keyb = md5(substr($key, 16, 16));
		$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	
		$cryptkey = $keya.md5($keya.$keyc);
		$key_length = strlen($cryptkey);
	
		$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
		$string_length = strlen($string);
	
		$result = '';
		$box = range(0, 255);
	
		$rndkey = array();
		for($i = 0; $i <= 255; $i++) {
			$rndkey[$i] = ord($cryptkey[$i % $key_length]);
		}
	
		for($j = $i = 0; $i < 256; $i++) {
			$j = ($j + $box[$i] + $rndkey[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}
	
		for($a = $j = $i = 0; $i < $string_length; $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp;
			$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
		}
	
		if($operation == 'DECODE') {
			if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
				return substr($result, 26);
			} else {
				return '';
			}
		} else {
			return $keyc.str_replace('=', '', base64_encode($result));
		}
	}
	
	/**
	 * 取得用户IP
	 */
	public static function onlineip(){
		if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
			$onlineip = getenv('HTTP_CLIENT_IP');
		} elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
			$onlineip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
			$onlineip = getenv('REMOTE_ADDR');
		} elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
			$onlineip = $_SERVER['REMOTE_ADDR'];
		}
		return preg_replace("/^([\d\.]+).*/", "\\1", $onlineip);
	}
	
	
	public static function simpleEncPass($pass) {
		return md5(strtolower($pass));
	}
	
	public static function setSite($site){
		self::$site = intval($site);
	}
	
	public static function setScheme($scheme){
		self::$scheme = ('HTTP' == strtoupper($scheme)) ? '' : $scheme;
	}
	
	public static function setHost($host){
		self::$host = $host;
	}
	
	public static function setPort($port){
		self::$port = $port;
	}
	
	public static function setCGI($cgi){
		self::$cgi = $cgi;
	}
	
	public static function setStamp($stamp){
		self::$stamp = $stamp;
	}
	
	/**
	 *=-------------------------------------------------------------------=
	 *=-------------------------------------------------------------------=
	 *		             一. 私有方法定义
	 *=-------------------------------------------------------------------=
	 *=-------------------------------------------------------------------=
	 */
	/**
	 * 用cookie方式记录 用户全站的唯一标识
	 *
	 * @param unknown_type $sid
	 */
	private static function _setLocalSid($sid) {
		//本地多余设置一次全域cookie，主要是兼容checkUserLogin 中的cookie记录账号 的处理
		setcookie(_UC_SID_NAME, $sid, null, '/', _UC_USER_COOKIE_DOMAIN);
	}

	/**
	 * 获取用cookie方式记录 的 用户全站的唯一标识
	 *
	 * @return unknown
	 */
	private static function _getLocalSid() {
		return isset($_COOKIE[_UC_SID_NAME]) ? $_COOKIE[_UC_SID_NAME] : '';
	}
	
	/**
	 * 获取用cookie方式记录 的 用户全站的唯一标识
	 *
	 * @return unknown
	 */
	private static function _removeLocalSid() {
		setcookie(_UC_SID_NAME, '', -1, '/', _UC_USER_COOKIE_DOMAIN);
//		setcookie(_UC_USER_COOKIE_NAME, '', -1, '/', _UC_USER_COOKIE_DOMAIN);
	}
	
	private static function _getUserPassSalt($username, $password) {
		return md5($username . $password . '#@g!&yS%j');
	}
	
	/**
	 * 本静态类 部分方法需要调用的初始化方法
	 *
	 */
	private static function _init() {
		self::$_timestamp     = time();
		self::$_getSessIdUrl  = 'http://' . self::$host . '/port/cookie_mgr.php';
		self::$_hasSoapModule = class_exists('SoapClient') ? true : false;
		//检测站点域名是否和用户中心域名一致，如一致而且开启了sesion，则会导致webservice调用失败。
		self::_initSess();
	}
	
	/**
	 * 检测站点域名是否和用户中心域名一致，如一致而且开启了sesion，则会导致webservice调用失败
	 *
	 */
	private static function _initSess($start=false) {
			if (!$start && isset($_SESSION)) {
				session_write_close();
			} else if ($start) {
				@session_start();
			} else { }
			return;
	}
	
	/**
	 * 获取与用户中心webservice通信用的加密校验码
	 *
	 * @param unknown_type $str
	 * @param unknown_type $flag
	 * @return unknown
	 */
	private static function _getCheckSum($str, $flag=0) {
		return md5($str . self::$_mcComunicationKey);
	}
	
	/**
	 * 简单的soap处理方法封装 
	 *
	 * @param unknown_type $funcName
	 * @param unknown_type $params
	 * @return unknown
	 */
	private static function _callSoap($funcName, $params=array()) {
		if (self::$_hasSoapModule) {
			
			$params = array_values($params);
			$_c     = count($params);
			$_wsdl  = (empty(self::$scheme) ? 'http://' : 'https://') . self::$host . ':'. self::$port . self::$cgi . '?wsdl';
			$_wsdl .= '&t=' . self::$_timestamp;
			
			try {
				$client = new SoapClient(
					$_wsdl,
					array('encoding'=>'UTF-8', 'exceptions' => true, 'trace' => 1)
				);
				switch ($_c) {
					case 0 : $xmlStr = $client->$funcName();break;
					case 1 : $xmlStr = $client->$funcName($params[0]);break;
					case 2 : $xmlStr = $client->$funcName($params[0], $params[1]);break;
					case 3 : $xmlStr = $client->$funcName($params[0], $params[1], $params[2]);break;
					case 4 : $xmlStr = $client->$funcName($params[0], $params[1], $params[2], $params[3]);break;
					case 5 : $xmlStr = $client->$funcName($params[0], $params[1], $params[2], $params[3], $params[4]);break;
					case 6 : $xmlStr = $client->$funcName($params[0], $params[1], $params[2], $params[3], $params[4], $params[5]);break;
					default: $xmlStr = '';break;
				}
			} catch (SoapFault $e) {
				print "Sorry an error was caught executing your request: {$e->getMessage()}";
				$xmlStr = '';
			}
			$aRet = xml_unserialize($xmlStr);
			
		} else {
			$aRet = self::soap($funcName, array(), $params);
		}
		
		self::_initSess(true);
		return $aRet;
		
	}
	
	/**
	 * SOAP函数
	 *
	 * @param unknown_type $op
	 * @param unknown_type $head
	 * @param unknown_type $body
	 * @return unknown
	 */
	private static function soap($op, $head, $body) {
		$fp = fsockopen((self::$scheme . self::$host), self::$port, $errno, $errstr, self::$stamp);
		$return = array(
			'flag' => 0,
			'message' => 'Connect to host error！'
		);
		if ($fp) {
			$_xml = '';
			$_xml .= '<?xml version="1.0" encoding="utf-8"?>';
			$_xml .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$_xml .= '<soap:Header>';
			$_xml .= '<UserNameToken xmlns="http://tempuri.org/">';
			if (is_array($head)) {
				foreach ($head as $key => $value)
					$_xml .= '<' . $key . '>' . $value . '</' . $key . '>';
			}
			$_xml .= '</UserNameToken>';
			$_xml .= '</soap:Header>';
			$_xml .= '<soap:Body>';
			$_xml .= '<' . $op . ' xmlns="http://tempuri.org/">';
			if (is_array($body)) {
				foreach ($body as $key => $value)
					$_xml .= '<' . $key . '>' . $value . '</' . $key . '>';
			}
			$_xml .= '</' . $op . '>';
			$_xml .= '</soap:Body>';
			$_xml .= '</soap:Envelope>';

			$_msg = "POST " . self::$cgi . " HTTP/1.1\r\n";
			$_msg .= "Host: " . self::$host . "\r\n";
			$_msg .= "Content-Type: text/xml; charset=utf-8\r\n";
			$_msg .= "Content-Length: " . strlen($_xml) . "\r\n";
			$_msg .= "Connection: Close \r\n";
			$_msg .= "SOAPAction: \"http://tempuri.org/{$op}\"\r\n\r\n";
			$_rs = '';
			if(false !== fwrite($fp, ($_msg . $_xml))){
				while (!feof($fp)) {
					$_rs .= fgets($fp, 1024);
				}
			}
			fclose($fp);
			
			$_rs = htmlspecialchars_decode($_rs);
			if(!empty($_rs)){
				preg_match("/<.{1,4}({$op}Response)\s*?.*?><return xsi:type=[^>]{4,20}>(.+?)<\/return><\/.{1,4}\\1>/", $_rs, $_matches);
				$return = (xml_unserialize($_matches[2]));
			}
		}
		return $return;
	}
}

if ($_siteSurfix == 'com') {
	UClientSSO::setScheme('ssl://');
	UClientSSO::setPort(443);
} else {
	UClientSSO::setScheme('');
	UClientSSO::setPort(80);
}

UClientSSO::setHost('hostname');
UClientSSO::setCGI('/port/UserSvc.php');
UClientSSO::setSite('1');

?>