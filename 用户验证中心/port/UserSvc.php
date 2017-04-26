<?php
/**
 * =---------------------------------------------------------------------------=
 * 					port/UserSvc.php 
 * (https://hostname/port/UserSvc.php?wsdl)
 * =---------------------------------------------------------------------------=
 * 用于对外提供xxx用户的相关数据接口
 * 
 * 关于单点登陆机制的说明：
 * 1. 单点登陆采用保存统一而私密的session id在服务端的方式进行，
 * 	  这个session id只在用户已登陆站点通过cookie方式记录，以保留存根，
 *    来与用户认证中心（用户中心）交互，以进行身份验证；
 * 
 * 分站在成功登陆后，必须通过W3C标准的script 调用用户中心设置session id和是否记住登陆账号的cookie 
 * 
 * 2. 对于所有子应用站点，每次请求都必须传递session id到用户中心，如果子站点取不到这个session id，
 *    则要到用户中心预设的取session id脚本进行session id的获取，后面再与正常通信。
 * 
 * 3. 综合以上两点，session id的cookie设置，没有采用全站cookie方式，只在需要时获取设置本站cookie。
 * 
 * Copyright(c) 2008 by 桂桂. All rights reserved.
 * @author 桂桂 <evan_gui@163.com> 
 * @version $Id: UserSvc.php, v 1.0 2008/7/14 $
 * @package systen
 * @link http://www.guigui8.com/index.php/archives/34.html
 * 
 * @history 
 * 		-  添加单点登陆登出等的处理(桂桂 on 2009.03.04)
 * 
 */
define('SITE_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
include_once(SITE_ROOT . 'user/config.inc.php');
include_once(SITE_ROOT . 'user/client/lib/xml.class.php');
include_once(SITE_ROOT. 'include/UCenterSSO.class.php');
include_once(SITE_ROOT . 'lib/nusoap.php');

ini_set("soap.wsdl_cache_enabled", 0);

define('CHECK_KEY', 'xxxxxxxxxxxxx');
define('CHECK_KEY_ERROR', '-11');
$server=new soap_server();     //生成对象
$server->configureWSDL("_user_wsdl", "urn:_user_wsdl");
$server->wsdl->schemaTargetNamespace="urn:_user_wsdl";


/**
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 *		    一 注册webservice方法
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 */
$server->register("getUser", //方法名
	array(
	"username"=>"xsd:string",
	),//输入参数
	array(
	"return"=>"xsd:string",
	)
);

$server->register("loginUCenter", //方法名
	array(
	"username"=>"xsd:string",
	"password"=>"xsd:string",
	"ip"=>"xsd:string",
	"siteFlag"=>"xsd:string",
	"remember"=>"xsd:string",
	"checksum"=>"xsd:string",
	),//输入参数
	array(
	"return"=>"xsd:string",
	)
);

$server->register("getOnlineUser", //方法名
	array(
	"sessId"=>"xsd:string",
	"siteFlag"=>"xsd:string",
	"checksum"=>"xsd:string",
	),//输入参数
	array(
	"return"=>"xsd:string",
	)
);

$server->register("getOnlineUserDetail", //方法名
	array(
	"sessId"=>"xsd:string",
	"siteFlag"=>"xsd:string",
	"fields"=>"xsd:string",
	"checksum"=>"xsd:string",
	),//输入参数
	array(
	"return"=>"xsd:string",
	)
);

$server->register("logoutUCenter", //方法名
	array(
	"sessId"=>"xsd:string",
	"checksum"=>"xsd:string",
	),//输入参数
	array(
	"return"=>"xsd:string",
	)
);

/**
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 *		    二. 定义公开的webservice方法
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 */

/**
 * 用户中心 登陆用户处理
 *
 * @param unknown_type $username
 * @param unknown_type $password
 * @param unknown_type $ip
 * @param unknown_type $checksum
 * @return unknown
 */
function loginUCenter($username, $password, $ip, $siteFlag, $remember, $checksum) {
	$ret = array();
	if (!isValidChecksum($username . $password . $ip . $siteFlag . $remember, $checksum)) {
		$ret['resultFlag'] = CHECK_KEY_ERROR;
		return xml_serialize($ret);
	}
	
	$ret = UCenterSSO::loginUCenter($username, $password, $ip, $siteFlag, $remember);
	
	return xml_serialize($ret);
}

/**
 * 获取当前在线的某个用户(需要通过客户端保存的session id存根 来获取)
 *
 * @param string $sessId
 * @param string $checksum
 * @return string
 */
function getOnlineUser($sessId, $siteFlag, $checksum) {
	$ret = array();
	if (!isValidChecksum($sessId . $siteFlag, $checksum)) {
		$ret['resultFlag'] = CHECK_KEY_ERROR;
		return xml_serialize($ret);
	}
	$ret = UCenterSSO::getOnlineUser($sessId, $siteFlag);
	return xml_serialize($ret);
}

/**
 * 获取当前在线的某个用户(需要通过客户端保存的session id存根 来获取)
 *
 * @param string $sessId
 * @param string $checksum
 * @return string
 */
function getOnlineUserDetail($sessId, $siteFlag, $fields, $checksum) {
	$ret = array();
	if (!isValidChecksum($sessId . $siteFlag, $checksum)) {
		$ret['resultFlag'] = CHECK_KEY_ERROR;
		return xml_serialize($ret);
	}
	$ret = UCenterSSO::getOnlineUserDetail($sessId, $siteFlag, $fields);
	return xml_serialize($ret);
}


/**
 * 单点登出处理
 *
 * @param string $sessId     - 客户端保存的session id存根
 * @param string $checksum   
 * @return string
 */
function logoutUCenter($sessId, $siteFlag, $checksum) {
	$ret = array();
	if (!isValidChecksum($sessId . $siteFlag, $checksum)) {
		$ret['resultFlag'] = CHECK_KEY_ERROR;
		return xml_serialize($ret);
	}
	
	$ret['resultFlag'] = 1;
	$ret['script'] = UCenterSSO::fetchLogoutScript($sessId, $siteFlag);
	UCenterSSO::logoutUCenter($sessId);
	return xml_serialize($ret);
}

/**
 * 根据用户名获取用户密码，邮箱，性别，生日，地址
 *
 * @param string $username         - 用户名
 * @param string $checksum         - key与用户名的md5加密串
 * @return string                  - 结果数组的xml串(结果状态在resultFlag字段中)
 *                                   1 ： 成功
 *                                   -1: key的验证不通过
 *                                   -2: 用户名格式有误
 *                                   -3: 获取用户资料失败
 *                                   
 */
function getUser($username, $checksum) {
	$ret = array();
	if (!isValidChecksum($username, $checksum)) {
		$ret['resultFlag'] = "-1";
		return xml_serialize($ret);
	}
	if (!isValidUsername($username)) {
		$ret['resultFlag'] = "-2";
		return xml_serialize($ret);
	}
	$res_get = uc_get_user($username, 'username, password, email, sex, userbirthday, location');
	//don't exist this user
	if ($res_get == 0) {
		$ret['resultFlag'] = "-3";
		return xml_serialize($ret);
	}
	$ret = $res_get;
	$ret['resultFlag'] = "1";
	return xml_serialize($ret);
}


/**
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 *		    三. 其他相关功能函数
 *=-------------------------------------------------------------------=
 *=-------------------------------------------------------------------=
 */
/**
 * 通行证用户名合法性检验
 *
 * @param unknown_type $uName
 * @return unknown
 */
function isValidUsername($uName) {
	return preg_match("/^[a-z0-9][\w]{2,19}$/i",$uName);
}

/**
 * 检验合法的校验码
 *
 * @param unknown_type $src
 * @param unknown_type $checksum
 * @return unknown
 */
function isValidChecksum($src, $checksum) {
	if(md5($src . CHECK_KEY) == $checksum) {
		return true;
	} else {
		vlog("\tchecksum error");
		return false;
	}
}

/**
 * 日志记录
 *
 * @param unknown_type $msg
 */
function vlog($msg) {
	$fp = fopen("MmoUser.log","a+");
	fwrite($fp,date("Y-m-d h:i:s")." - ".$msg."\r\n");
	fflush($fp);
	fclose($fp);
}



//Use the request to (try to) invoke the service
$HTTP_RAW_POST_DATA=isset($HTTP_RAW_POST_DATA)?$HTTP_RAW_POST_DATA:"";
$server->service($HTTP_RAW_POST_DATA);


?>