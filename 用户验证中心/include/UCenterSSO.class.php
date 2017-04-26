<?php
/**
 *=-------------------------------------------------------------------------------=
 *                         UCenterSSO.class.php
 *=-------------------------------------------------------------------------------=
 * 
 * 用户单点登陆处理 的服务端类文件
 *  
 * 本文件中的UCenterSSO类，主要用于提供给应用程序子站点 
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
 * @version $Id: UCenterSSO.class.php, v 1.0 2009/03/04 $
 * @package systen
 * @link http://www.guigui8.com/index.php/archives/34.html 
 * 
 * @history 
 *   1. create the class. (by 桂桂 on 2009.03.04)
 */

define('_UC_LOGGED_SITE_SKEY', 'logged_sites');

class UCenterSSO 
{
	static private  $_ucSessKey = '_uc_user';	
	static private  $_sitesMap = array();
	static private  $_coSitesInfo = array();
	
	/**
	 * 用户验证中心 登陆用户处理
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $ip
	 * @param string $checksum
	 * @return array
	 */
	static public function loginUCenter($username, $password, $ip, $siteFlag, $remember=false) {
		self::_init();
		session_start();
		$ret = array();
		$arr_login_res     = uc_user_login($username, $password, $ip);
		$res_login         = $arr_login_res['status'];		//
		$ret['resultFlag'] = $res_login;
		
		if ($res_login < 1) {
			//登陆失败
		} else {
			//登陆成功
			$_SESSION[self::$_ucSessKey] = $arr_login_res;
			
			$_SESSION[self::$_ucSessKey]['salt'] = 
				self::_getUserPassSalt($_SESSION[self::$_ucSessKey]['username'], $_SESSION[self::$_ucSessKey]['password']);

			$ret['userinfo'] = $_SESSION[self::$_ucSessKey];
			$ret['sessID'] = session_id();

			//合作中心站回调登陆接口(设置用户中心的统一session id)
			self::_createCoSitesInfo();
			$uinfo = array();
			$_timestamp = time();
			$_rawCode = array(
				'action' => 'setSid',
				'sid'    => $ret['sessID'],
				'time'   => $_timestamp,
			);
			if ($remember) {
				$uinfo = array(
					'remember' => 1,
					'username' => $username,
					'password' => $password
				);
			}
			
			$ret['script'] = '';
			$_rawStr = http_build_query(array_merge($_rawCode, $uinfo));
			
			//合作站点的全域cookie设置脚本地址
			foreach ((array)self::$_coSitesInfo as $_siteInfo) {
				$_code = self::authcode($_rawStr, 'ENCODE', $_siteInfo['key']);
				$_src = $_siteInfo['url'] . '?code=' . $_code . '&time=' . $_timestamp;
				$ret['script'] .= urlencode('<script type="text/javascript" src="' . $_src . '"></script>');
			}
			
			//记住已登陆战
			self::registerLoggedSite($siteFlag, $ret['sessID']);
			
			unset($ret['userinfo']['salt']);
		}
		
		return $ret;
	}
		
	/**
	 * 根据sid，获取当前登陆的用户信息
	 *
	 * @param string $sessId
	 * @return array
	 */
	static public function getOnlineUser($sessId, $siteFlag) {
		self::_init();
		session_id(trim($sessId));
		session_start();
		
		$ret = array();
		@$_userinfo = $_SESSION[self::$_ucSessKey];
		if (isset($_userinfo['username']) && isset($_userinfo['password']) && 
			self::_getUserPassSalt($_userinfo['username'], $_userinfo['password'])) {
			$ret['resultFlag'] = "1";
			$ret['userinfo'] = $_userinfo;
			//记住已登陆战
			self::registerLoggedSite($siteFlag, $sessId);
			unset($ret['userinfo']['salt']);
		} else {
			$ret['resultFlag'] = "0";
		}
		
		return ($ret);
	}		
	
	/**
	 * 根据sid，获取当前登陆的用户信息
	 *
	 * @param unknown_type $sessId
	 * @return unknown
	 */
	static public function getOnlineUserDetail($sessId, $siteFlag, $fields) {
		self::_init();
		session_id(trim($sessId));
		session_start();
		
		$ret = array();
		@$_userinfo = $_SESSION[self::$_ucSessKey];
		if (isset($_userinfo['username']) && isset($_userinfo['password']) && 
			self::_getUserPassSalt($_userinfo['username'], $_userinfo['password'])) {
			$ret['resultFlag'] = "1";
			$ret['userinfo'] = uc_user_detail_get($_userinfo['username'], 0, $fields);
			//记住已登陆战
			self::registerLoggedSite($siteFlag, $sessId);
			unset($ret['userinfo']['salt']);
		} else {
			$ret['resultFlag'] = "0";
		}
		
		return ($ret);
	}	
	
	/**
	 * 将已经成功获取sid的站点，记录到对应的session中，以便后面针对性的登出站点
	 *
	 * @param unknown_type $site_flag
	 * @param - 全站唯一session id，用做ticket
	 */
	static public function registerLoggedSite($site_flag, $sessId) {
		if (!in_array($site_flag, $_SESSION[_UC_LOGGED_SITE_SKEY])) {
			$_SESSION[_UC_LOGGED_SITE_SKEY][] = $site_flag;
		}
	}
	
	/**
	 * 登出全站处理
	 *
	 * @param string - 全站唯一session id，用做ticket
	 * @return boolean
	 */
	static public function logoutUCenter($sessId) {
		self::_init();
		session_id(trim($sessId));
		session_start();

		$_SESSION = array();
		return empty($_SESSION) ? true : false;
	}
	
	
	/**
	 * 获取登出全站的脚本（需要在客户端输出）
	 *
	 * @param unknown_type $sessId
	 * @return unknown
	 */
	static public function fetchLogoutScript($sessId, $siteFlag) {
		self::_init();
		session_id($sessId);
		session_start();
		self::_createSitesMap();
		$sOUt = '';
		foreach ((array)$_SESSION[_UC_LOGGED_SITE_SKEY] as $_siteId) {
			if ($_siteId != $siteFlag) {
				$_url = self::$_sitesMap[intval($_siteId)]['logoutUrl'];
				$sOUt .= '<script type="text/javascript" src="' . $_url . '"></script>';
			}
		}
		
		//合作中心站回调登出接口(设置用户中心的统一session id)
		self::_createCoSitesInfo();
		$uinfo = array();
		$_timestamp = time();
		$_rawCode = array(
			'action' => 'removeSid',
			'time'   => $_timestamp,
		);
		$_rawStr = http_build_query($_rawCode);

		//合作站点的全域cookie设置脚本地址
		foreach ((array)self::$_coSitesInfo as $_siteInfo) {
			$_code = self::authcode($_rawStr, 'ENCODE', $_siteInfo['key']);
			$_src = $_siteInfo['url'] . '?code=' . $_code . '&time=' . $_timestamp;
			$sOUt .= '<script type="text/javascript" src="' . $_src . '"></script>';
		}
		
		return urlencode($sOUt);
	}
	
	static private function _getUserPassSalt($username, $password) {
		return md5($username . $password . 'xxxx');
	}
	
	static private function _init() {
		session_write_close();
		ini_set('session.cookie_lifetime',  0);
		ini_set('session.gc_maxlifetime',   3600);
	}
	
	function getOnlineList() {}
	
	function getUserCount() {}
	
	function remove() {}

	/**
	 * 可逆的加解密函数
	 *
	 * @param unknown_type $string
	 * @param unknown_type $operation
	 * @param unknown_type $key
	 * @param unknown_type $expiry
	 * @return unknown
	 */
	static public function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
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
	 * 初始化合作站点 的全域cookie 的sid设置地址
	 *
	 */
	static private function _createCoSitesInfo() {
		$_a = explode('.', $_SERVER['HTTP_HOST']);
		$_siteSurfix = $_a[count($_a) - 1];
		
		self::$_coSitesInfo = array(
			0 => array(
				'url' => 'http://' . $_SERVER['HTTP_HOST'] . '/port/cookie_mgr.php',
				'key' => 'xxxxxxx',
			),

			
		);
		
	}
	
	/**
	 * 建立分站点信息和站点标识的映射关系表
	 *
	 */
	static private function _createSitesMap() {
		self::$_sitesMap = array(
			//子站1
			1 => array(
				'logoutUrl' => 'http://hostname1/user/api/logout_user.php',
			),
			//子站2
			1 => array(
				'logoutUrl' => 'http://hostname2/user/api/logout_user.php',
			),
			//子站3
			1 => array(
				'logoutUrl' => 'http://hostname3/user/api/logout_user.php',
			),
			
			
		);
		
	}//end of function
	
}//end of class

?>