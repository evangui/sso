<?php
/**
 +-------------------------------------------------------------------------------------------
 * 单点登陆测试脚本
 * by guiyj on 2009.02.02
 +-------------------------------------------------------------------------------------------
 */
error_reporting(7);
require_once('../common.php');
require_once(SITE_ROOT . 'include/UCenterSSO.class.php');
require_once(SITE_ROOT . 'include/core.inc.php');
include_once(SITE_ROOT . 'user/client/lib/UClientSSO.class.php');

if (0) {
	$r = UCenterSSO::loginUCenter('evangui', UClientSSO::simpleEncPass('111111'), '192.168.51.153', 1);
	var_dump($r);die;
}

$act = trim($_GET['a']);
if ('login' == $act) {
	//单点登陆首战处理
	$res = UClientSSO::loginSSO('evangui', '111111', 1);	
	echo $script = UClientSSO::getSynloginScript();
	
} else if ('check' == $act) {
	//其他站检测登陆用户的信息
//	$res = UClientSSO::checkUserLogin();

	//获取所有详细资料
	$res = UClientSSO::getOnlineUserDetail();
	//获取座右铭 和 喜欢的音乐
//	$res = UClientSSO::getOnlineUserDetail('fav_music,fav_quote');
	
	//获取喜爱的游戏类型
//	$res = UClientSSO::getOnlineUserDetail('fav_gametype');
	
} else if ('logout' == $act){
	//单点登出
	$res = UClientSSO::logoutSSO();
	echo $script = UClientSSO::getSynlogoutScript();
}

var_dump($res);



//$res = UClientSSO::loginSSO('evangui', '111111', 1);
//echo $script = UClientSSO::getSynloginScript();
//$res = UClientSSO::checkUserLogin();
//$res = UClientSSO::logoutSSO();
//var_dump($res);
?>

