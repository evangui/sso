<?php
/**
 *=---------------------------------------------------------------------------=
 *                    port/cookie_mgr.php
 *=---------------------------------------------------------------------------=
 *
 * 用户中心 通过子站点请求设置，获取与移除 当前用户全站的唯一标识符（即session id）。
 * 
 * Copyright(c) 2008 by 桂桂. All rights reserved.
 * @author 桂桂 <evan_gui@163.com>
 * @version $Id: cookie_mgr.php, v 1.0 2009/03/04 $
 * @package systen
 * @link http://www.guigui8.com/index.php/archives/34.html
 * 
 * @history 
 *   1. create the script. (by 桂桂 on 2009.03.04)
 */
require_once '../common.php';
include_once(SITE_ROOT . 'user/config.inc.php');

define('API_RETURN_SUCCEED', '1');
define('API_RETURN_FAILED', '-1');
define('API_RETURN_FORBIDDEN', '-2');
define('API_SID_EMPTY_ERROR', '-3');

define('API_SET_SID', 1);		//同步登录 API 接口开关
define('API_GET_SID', 1);		//同步登录 API 接口开关


//获取站点主机后缀(.com, .xxx or .local etc)
$_aHttpHost  = explode('.', $_SERVER['HTTP_HOST']);
$_siteSurfix = $_aHttpHost[count($_aHttpHost) - 1];

//设置全站处理需要用到的cookie键名，域名
define('_UC_USER_COOKIE_NAME', 'cookiexxxxxxx'); //永久记住用户信息的的cookie键名
define('_UC_SID_NAME', 'sidxxxxxxxxx');		   //全站sid cookie键名
define('_UC_USER_COOKIE_DOMAIN', '.hostname.' . $_siteSurfix);	//全域cookie域名

define('_UC_KEY', 'xxxxxx');

/**
 * 1. 参数解析
 */
$code = trim($_GET['code']);
parse_str(UCenterSSO::authcode($code, 'DECODE', _UC_KEY), $get);

if(MAGIC_QUOTES_GPC) { $get = dstripslashes($get); }
(time() - $get['time'] > 3600) && exit('Authracation has expiried');
empty($get) && exit('Invalid Request');

$action = $get['action'];
$timestamp = time();
/**
 * 2. 获取统一的session id
 */
if ($action == 'getSid' && $_GET['time'] == $get['time']) {
	!API_GET_SID && exit(API_RETURN_FORBIDDEN);
	//
	// 2.1 获取session id, 如果未获取到但是有cookie永久记住的记录，则对用户进行第一次单点登陆处理
	//
	
	header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
	$back_url = $get['back_url'];
	$_sessId = isset($_COOKIE[$__UC_SID_NAME]) ? trim($_COOKIE[$__UC_SID_NAME]) : '';
	
	if (empty($_sessId)) {
		if(isset($_COOKIE[$__UC_USER_COOKIE_NAME][_UC_USER_COOKIE_USERNAME_KEYNAME], 
			$_COOKIE[$__UC_USER_COOKIE_NAME][_UC_USER_COOKIE_PASSWORD_KEYNAME])) {
				
			//3. 根据cookie里的用户名和密码判断用户是否已经登陆。
			$username = UCenterSSO::authcode($_COOKIE[$__UC_USER_COOKIE_NAME][_UC_USER_COOKIE_USERNAME_KEYNAME], 
				'DECODE', _UC_KEY);
			$password = UCenterSSO::authcode($_COOKIE[$__UC_USER_COOKIE_NAME][_UC_USER_COOKIE_PASSWORD_KEYNAME], 
				'DECODE', _UC_KEY);
			$ip = _onlineip();
			
			
			$ret = UCenterSSO::loginUCenter($username, $password, $ip);
			if ($ret['resultFlag'] > 0) {
				$_sessId = $ret['sessID'];
			}
		}
	}
	
	//
	// 2.2 如果成功获取了session id，则将该站注册成已登陆站点
	// 说明：除第一个直接登陆站外，其他站的登陆验证必须通过这里获取session id
 	//
 	//$_sessId登出的时候，并没有注销掉。。。。。。。。。
	if ($_sessId && isset($get['site_flag']) && (0 !== intval($get['site_flag'])))  {
		UCenterSSO::registerLoggedSite($get['site_flag'], $_sessId);
	}
	
	$back_url = urldecode($back_url);
	if (false === strstr($back_url, '?')) {
		$back_url .= "?code=" . UCenterSSO::authcode("fromuc=true&sessId=" . $_sessId, 'ENCODE', _UC_KEY); 
	} else {
		$back_url .= "&code=" . UCenterSSO::authcode("fromuc=true&sessId=" . $_sessId, 'ENCODE', _UC_KEY); 
	}
	
	echo "<script>window.location.href='{$back_url}'</script>";
	exit();

} elseif($action == 'setSid' && $_GET['time'] == $get['time']) {

/**
 * 3. 设置统一的session id
 */
	!API_SET_SID && exit(API_RETURN_FORBIDDEN);
	
	header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
	
	$sessId   = $get['sid'];
	empty($sessId) && exit(API_SID_EMPTY_ERROR);
	setcookie(_UC_SID_NAME, $sessId, null, '/', _UC_USER_COOKIE_DOMAIN);
	$remember = isset($get['remember']) ? intval($get['remember']) : 0;
	
	//永久记住登陆账号
	if (1 === $remember) {
		$username = isset($get['username']) ? trim($get['username']) : '';
		$password = isset($get['password']) ? trim($get['password']) : '';
		setcookie(_UC_USER_COOKIE_NAME, '', -1, '/', _UC_USER_COOKIE_DOMAIN);
		
		setcookie(_UC_USER_COOKIE_NAME, UCenterSSO::authcode($username.'|g|'.$password, 'ENCODE', _UC_KEY),
			 $timestamp + 86400*365, '/', _UC_USER_COOKIE_DOMAIN);
	}
	
	exit();

} elseif($action == 'removeSid' && $_GET['time'] == $get['time']) {
	
/**
 * 4. 移除统一的session id，和永久记住登陆账号的cookie信息
 */
	!API_SET_SID && exit(API_RETURN_FORBIDDEN);
	header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');

	setcookie(_UC_SID_NAME, '', -1, '/');
	setcookie(_UC_USER_COOKIE_NAME, '', -1, '/', _UC_USER_COOKIE_DOMAIN);
	
	exit();
		
} else {
	
	exit(API_RETURN_FAILED);
}


/**
 * 数组的递归stripslashes实现
 *
 * @param mixed $string
 * @return mixed
 */
function dstripslashes($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = dstripslashes($val);
		}
	} else {
		$string = stripslashes($string);
	}
	return $string;
}


/**
 * 获取用户ip
 *
 * @return string
 */
function _onlineip(){
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


?>