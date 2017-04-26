<?php
/**
 * api/logout_user.php
 *
 * 单点登出的各站回调页面脚本
 * （本脚本只要负责处理本站的登出即可）
 * 
 * Copyright(c) 2008 by guiyj. All rights reserved.
 * @author guiyj <evan_gui@163.com>
 * @version $Id: port/logout.php, v 1.0 2009/03/05 $
 * @package systen
 */
header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
$cookiedomain = ''; 			           // cookie 作用域
$cookiepath = '/';			               // cookie 作用路径

logout_user();

function logout_user() {
	global $cookiepath;
	!isset($_SESSION) && session_start();
	setcookie('mmo_user[username]', '', -86400, $cookiepath);
	setcookie('mmo_user[password]', '', -86400, $cookiepath);
	$_SESSION['mmo_user'] = array();
}
?>