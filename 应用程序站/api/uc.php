<?php
/**
 * api/uc.php 
 *
 * UCenter 应用程序开发 API Example
 *
 * 此文件为 api/uc.php 文件的开发样例，用户处理 UCenter 通知给应用程序的任务
 * (注：主要是用来操作一些应用程序端与服务器端数据的同步过程，如全站登入和登出等)
 *  - 服务器端的同步请求分发到各个应用程序端的uc.php文件，请根据需要修改该文件对
 *    服务端请求的响应操作。
 * 
 */

define('UC_VERSION', '1.0.0');		//UCenter 版本标识

define('API_DELETEUSER', 1);		//用户删除 API 接口开关
define('API_RENAMEUSER', 1);		//用户改名 API 接口开关
define('API_UPDATEPW', 1);		//用户改密码 API 接口开关
define('API_GETTAG', 1);		//获取标签 API 接口开关
define('API_SYNLOGIN', 1);		//同步登录 API 接口开关
define('API_SYNLOGOUT', 1);		//同步登出 API 接口开关
define('API_UPDATEBADWORDS', 1);	//更新关键字列表 开关
define('API_UPDATEHOSTS', 1);		//更新域名解析缓存 开关
define('API_UPDATEAPPS', 1);		//更新应用列表 开关
define('API_UPDATECLIENT', 1);		//更新客户端缓存 开关
define('API_UPDATECREDIT', 1);		//更新用户积分 开关
define('API_GETCREDITSETTINGS', 1);	//向 UCenter 提供积分设置 开关
define('API_UPDATECREDITSETTINGS', 1);	//更新应用积分设置 开关

define('API_RETURN_SUCCEED', '1');
define('API_RETURN_FAILED', '-1');
define('API_RETURN_FORBIDDEN', '-2');

error_reporting(7);

define('UC_CLIENT_ROOT', DISCUZ_ROOT.'./client/');
chdir('../');
require_once './config.inc.php';

$code = $_GET['code'];
parse_str(authcode($code, 'DECODE', UC_KEY), $get);
if(MAGIC_QUOTES_GPC) {
	$get = dstripslashes($get);
}

if(time() - $get['time'] > 3600) {
	exit('Authracation has expiried');
}
if(empty($get)) {
	exit('Invalid Request');
}
$action = $get['action'];
$timestamp = time();

if($action == 'test') {

	exit(API_RETURN_SUCCEED);

} elseif($action == 'deleteuser') {

	!API_DELETEUSER && exit(API_RETURN_FORBIDDEN);

	//用户删除 API 接口
	include './include/db_mysql.class.php';
	$db = new dbstuff;
	$db->connect($dbhost, $dbuser, $dbpw, $dbname, $pconnect);
	unset($dbhost, $dbuser, $dbpw, $dbname, $pconnect);

	$uids = $get['ids'];
	$query = $db->query("DELETE FROM {$tablepre}members WHERE uid IN ($uids)");

	exit(API_RETURN_SUCCEED);

} elseif($action == 'renameuser') {

	!API_RENAMEUSER && exit(API_RETURN_FORBIDDEN);

	//用户改名 API 接口
	$uid = $get['uid'];
	$usernamenew = $get['newusername'];

	$db->query("UPDATE {$tablepre}members SET username='$usernamenew' WHERE uid='$uid'");

	exit(API_RETURN_SUCCEED);

} elseif($action == 'updatepw') {

	!API_UPDATEPW && exit(API_RETURN_FORBIDDEN);

	//更改用户密码
	exit(API_RETURN_SUCCEED);

} elseif($action == 'gettag') {

	!API_GETTAG && exit(API_RETURN_FORBIDDEN);

	//获取标签 API 接口
	$return = array($name, array());
	echo uc_serialize($return, 1);

} elseif($action == 'synlogin' && $_GET['time'] == $get['time']) {
//file_put_contents('rr.txt', 'write by uc.php');
	!API_SYNLOGIN && exit(API_RETURN_FORBIDDEN);

	//同步登录 API 接口
//	include './include/db_mysql.class.php';
//	$db = new dbstuff;
//	$db->connect($dbhost, $dbuser, $dbpw, $dbname, $pconnect);
//	unset($dbhost, $dbuser, $dbpw, $dbname, $pconnect);

	$uid = intval($get['uid']);
	$username = trim($get['username']);
//	file_put_contents('ffff.txt', $uid);
	//...判断该用户在本地是否存在
	header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
	dsetcookie('Example_auth', authcode($uid."\t".$username, 'ENCODE'), 86400 * 365);

	/*
	$query = $db->query("SELECT uid, username FROM {$tablepre}members WHERE uid='$uid'");
	if($member = $db->fetch_array($query)) {
		header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
		dsetcookie('Example_auth', authcode($member['uid']."\t".$member['username'], 'ENCODE'), 86400 * 365);
	}
	*/

} elseif($action == 'synlogout') {

	!API_SYNLOGOUT && exit(API_RETURN_FORBIDDEN);

	//同步登出 API 接口
	header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
	dsetcookie('Example_auth', '', -86400 * 365);

} elseif($action == 'updatebadwords') {

	!API_UPDATEBADWORDS && exit(API_RETURN_FORBIDDEN);

	//更新关键字列表
	exit(API_RETURN_SUCCEED);

} elseif($action == 'updatehosts') {

	!API_UPDATEHOSTS && exit(API_RETURN_FORBIDDEN);

	//更新HOST文件
	exit(API_RETURN_SUCCEED);

} elseif($action == 'updateapps') {

	!API_UPDATEAPPS && exit(API_RETURN_FORBIDDEN);

	//更新应用列表
	exit(API_RETURN_SUCCEED);

} elseif($action == 'updateclient') {

	!API_UPDATECLIENT && exit(API_RETURN_FORBIDDEN);

	//更新客户端缓存
	exit(API_RETURN_SUCCEED);

} elseif($action == 'updatecredit') {

	!UPDATECREDIT && exit(API_RETURN_FORBIDDEN);

	//更新用户积分
	exit(API_RETURN_SUCCEED);

} elseif($action == 'getcreditsettings') {

	!GETCREDITSETTINGS && exit(API_RETURN_FORBIDDEN);

	//向 UCenter 提供积分设置
	echo uc_serialize($credits);

} elseif($action == 'updatecreditsettings') {

	!API_UPDATECREDITSETTINGS && exit(API_RETURN_FORBIDDEN);

	//更新应用积分设置
	exit(API_RETURN_SUCCEED);

} else {

	exit(API_RETURN_FAILED);

}

function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {

	$ckey_length = 4;

	$key = md5($key ? $key : UC_KEY);
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

function dsetcookie($var, $value, $life = 0, $prefix = 1) {
	global $cookiedomain, $cookiepath, $timestamp, $_SERVER;
	setcookie($var, $value,
		$life ? $timestamp + $life : 0, $cookiepath,
		$cookiedomain, $_SERVER['SERVER_PORT'] == 443 ? 1 : 0);
}

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

function uc_serialize($arr, $htmlon = 0) {
	include_once UC_CLIENT_ROOT.'./lib/xml.class.php';
	return xml_serialize($arr, $htmlon);
}

function uc_unserialize($s) {
	include_once UC_CLIENT_ROOT.'./lib/xml.class.php';
	return xml_unserialize($s);
}