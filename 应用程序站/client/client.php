<?php
/**
 *=-------------------------------------------------------------------------------=
 *                         uc_client/client.php
 *=-------------------------------------------------------------------------------=
 * 
 * 本文件提供mmosite用户中心服务的客户端 api 
 * 
 * 目前用户中心对外的服务包括：
 *  用户登陆处理，用户短消息处理， 用户mygame信息处理， 用户myfriend信息处理
 *  用户附属资料（用户详细资料， 用户联系信息）处理
 * 
 * Copyright(c) 2008 tm.inc All rights reserved.
 * @author tm.inc <evan_gui@163.com>
 * @version $Id: uc_client/client.php, v 1.0 2008/4/21 $
 * @package systen
 */

if(!defined('UC_API')) {
	exit('Access denied');
}
error_reporting(0);

define('IN_UC', TRUE);
define('UC_VERSION', '1.0.0');
define('UC_RELEASE', '20080429');

define('UC_ROOT', substr(__FILE__, 0, -10));

define('UC_DATADIR', UC_ROOT.'./data/');
define('UC_DATAURL', UC_API.'/data');


/**
 * 定义ucenter的密码salt串，继承旧中新的$cfg["db"]["password_key"] 
 */
define('PASSKEY_SALT', '*63$^@');

define('UC_API_FUNC', UC_CONNECT == 'mysql' ? 'uc_api_mysql' : 'uc_api_post');
$GLOBALS['uc_controls'] = array();

function uc_addslashes($string, $force = 0, $strip = FALSE) {
	!defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
	if(!MAGIC_QUOTES_GPC || $force) {
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = uc_addslashes($val, $force, $strip);
			}
		} else {
			$string = addslashes($strip ? stripslashes($string) : $string);
		}
	}
	return $string;
}

function uc_stripslashes($string) {
	!defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
	if(MAGIC_QUOTES_GPC) {
		return stripslashes($string);
	} else {
		return $string;
	}
}

function uc_api_post($module, $action, $arg = array()) {
	$s = $sep = '';
	foreach($arg as $k => $v) {
		if(is_array($v)) {
			$s2 = $sep2 = '';
			foreach($v as $k2=>$v2) {
				$s2 .= "$sep2{$k}[$k2]=".urlencode(uc_stripslashes($v2));
				$sep2 = '&';
			}
			$s .= $sep.$s2;
		} else {
			$s .= "$sep$k=".urlencode(uc_stripslashes($v));
		}
		$sep = '&';
	}
	$postdata = uc_api_requestdata($module, $action, $s);
	return uc_fopen2(UC_API.'/index.php', 500000, $postdata, '', TRUE, UC_IP, 20);
}

function uc_api_requestdata($module, $action, $arg='', $extra='') {
	$input = uc_api_input($arg);
	$post = "m=$module&a=$action&inajax=2&input=$input&appid=".UC_APPID.$extra;
	return $post;
}

function uc_api_url($module, $action, $arg='', $extra='') {
	$url = UC_API.'/index.php?'.uc_api_requestdata($module, $action, $arg, $extra);
	return $url;
}

function uc_api_input($data) {
	$s = urlencode(uc_authcode($data.'&agent='.md5($_SERVER['HTTP_USER_AGENT'])."&time=".time(), 'ENCODE', UC_KEY));
	return $s;
}

function uc_api_mysql($model, $action, $args=array()) {
	global $uc_controls;
	
	if(empty($uc_controls[$model])) {
		include_once UC_ROOT.'./lib/db.class.php';
		include_once UC_ROOT.'./model/base.php';
		include_once UC_ROOT."./control/$model.php";
		eval("\$uc_controls['$model'] = new {$model}control();");
	}
	
	if($action{0} != '_') {
		$args = uc_addslashes($args, 1, TRUE);
		$action = 'on'.$action;
		return $uc_controls[$model]->$action($args);
	} else {
		return '';
	}
}

function uc_serialize($arr, $htmlon = 0) {
	include_once UC_ROOT.'./lib/xml.class.php';
	return xml_serialize($arr, $htmlon);
}

function uc_unserialize($s) {
	include_once UC_ROOT.'./lib/xml.class.php';
	return xml_unserialize($s);
}

function uc_authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {

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

function uc_fopen2($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE) {
	$__times__ = isset($_GET['__times__']) ? intval($_GET['__times__']) + 1 : 1;
	if($__times__ > 2) {
		return '';
	}
	$url .= (strpos($url, '?') === FALSE ? '?' : '&')."__times__=$__times__";
	return uc_fopen($url, $limit, $post, $cookie, $bysocket, $ip, $timeout, $block);
}

function uc_fopen($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE) {
	$return = '';
	$matches = parse_url($url);
	$host = $matches['host'];
	$path = $matches['path'] ? $matches['path'].($matches['query'] ? '?'.$matches['query'] : '') : '/';
	$port = !empty($matches['port']) ? $matches['port'] : 80;
	if($post) {
		$out = "POST $path HTTP/1.0\r\n";
		$out .= "Accept: */*\r\n";
		//$out .= "Referer: $boardurl\r\n";
		$out .= "Accept-Language: zh-cn\r\n";
		$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
		$out .= "Host: $host\r\n";
		$out .= 'Content-Length: '.strlen($post)."\r\n";
		$out .= "Connection: Close\r\n";
		$out .= "Cache-Control: no-cache\r\n";
		$out .= "Cookie: $cookie\r\n\r\n";
		$out .= $post;
	} else {
		$out = "GET $path HTTP/1.0\r\n";
		$out .= "Accept: */*\r\n";
		//$out .= "Referer: $boardurl\r\n";
		$out .= "Accept-Language: zh-cn\r\n";
		$out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
		$out .= "Host: $host\r\n";
		$out .= "Connection: Close\r\n";
		$out .= "Cookie: $cookie\r\n\r\n";
	}
	$fp = @fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
	if(!$fp) {
		return '';
	} else {
		stream_set_blocking($fp, $block);
		stream_set_timeout($fp, $timeout);
		@fwrite($fp, $out);
		$status = stream_get_meta_data($fp);
		if(!$status['timed_out']) {
			while (!feof($fp)) {
				if(($header = @fgets($fp)) && ($header == "\r\n" ||  $header == "\n")) {
					break;
				}
			}

			$stop = false;
			while(!feof($fp) && !$stop) {
				$data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
				$return .= $data;
				if($limit) {
					$limit -= strlen($data);
					$stop = $limit <= 0;
				}
			}
		}
		@fclose($fp);
		return $return;
	}
}

function uc_app_ls() {
	$return = call_user_func(UC_API_FUNC, 'app', 'ls', array());
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_feed_add($icon, $uid, $username, $title_template='', $title_data='', $body_template='', $body_data='', $body_general='', $target_ids='', $images = array()) {
	call_user_func(UC_API_FUNC, 'feed', 'add',
		array(  'icon'=>$icon,
			'appid'=>UC_APPID,
			'uid'=>$uid,
			'username'=>$username,
			'title_template'=>$title_template,
			'title_data'=>$title_data,
			'body_template'=>$body_template,
			'body_data'=>$body_data,
			'body_general'=>$body_general,
			'target_ids'=>$target_ids,
			'image_1'=>$images[0]['url'],
			'image_1_link'=>$images[0]['link'],
			'image_2'=>$images[1]['url'],
			'image_2_link'=>$images[1]['link'],
			'image_3'=>$images[2]['url'],
			'image_3_link'=>$images[2]['link'],
			'image_4'=>$images[3]['url'],
			'image_4_link'=>$images[3]['link']
		)
	);
}

function uc_feed_get($limit = 100) {
	$return = call_user_func(UC_API_FUNC, 'feed', 'get', array('limit'=>$limit));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_friend_add($uid, $friendid, $comment='') {
	return call_user_func(UC_API_FUNC, 'friend', 'add', array('uid'=>$uid, 'friendid'=>$friendid, 'comment'=>$comment));
}

function uc_friend_delete($uid, $friendids) {
	return call_user_func(UC_API_FUNC, 'friend', 'delete', array('uid'=>$uid, 'friendids'=>$friendids));
}

function uc_friend_totalnum($uid, $direction = 0) {
	return call_user_func(UC_API_FUNC, 'friend', 'totalnum', array('uid'=>$uid, 'direction'=>$direction));
}

function uc_friend_ls($uid, $page = 1, $pagesize = 10, $totalnum = 10, $direction = 0) {
	$return = call_user_func(UC_API_FUNC, 'friend', 'ls', array('uid'=>$uid, 'page'=>$page, 'pagesize'=>$pagesize, 'totalnum'=>$totalnum, 'direction'=>$direction));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

/**
 * integer uc_user_register(array $arr_user_info)
 * 
 * 新用户注册.
 *  - 用户名、密码、Email 为一个用户在 UCenter 的基本数据，提交后 UCenter 会按照注册设置和词语过滤的规则检测用户名和 Email 的格式
 *    是否正确合法，如果正确则返回注册后的用户 ID，否则返回相应的错误信息.
 *
 * @param array $arr_user_info    - 包含用户注册的基本资料信息。以key=>value的联合数组形式存放。
 * @return integer                - 大于 0:返回用户 ID，表示用户注册成功
 * 					                   -1:用户名不合法
 * 					                   -2:包含不允许注册的词语
 * 					                   -3:用户名已经存在
 * 					                   -4:Email 格式有误
 * 					                   -5:Email 不允许注册
 * 					                   -6:该 Email 已经被注册
 * 
 * 用法：
 * <code>
  		$arr_user_info = array();
  		$arr_user_info['username'] = 'user1';
  		$arr_user_info['password'] = md5('111111');		//请确认password是原始密码经md5加密过的
 		$arr_user_info['email'] = 'user1@test.com';
  		//以上3个参数为必须项，下面的为可选
  		$arr_user_info['nickname'] = 'user1';
  		$arr_user_info['face'] = 'http://image.91.com/m/face1.gif';
  		$arr_user_info['sex'] = '1';
  		$arr_user_info['location'] = 'China-Hubei-WuHan';
  		$arr_user_info['game'] = 'war3';
  		$arr_user_info['gameid'] = '2';
  		$arr_user_info['referralname'] = 'superMan1';
  		$arr_user_info['userbirthday'] = '1984-01-01';
  		$arr_user_info['gold'] = '50';
  		
  		$uid = uc_user_register($_POST['username'], $_POST['password'], $_POST['email']);
		if($uid <= 0) {
			if($uid == -1) {
				echo '用户名不合法';
			} elseif($uid == -2) {
				echo '包含要允许注册的词语';
			} elseif($uid == -3) {
				echo '用户名已经存在';
			} elseif($uid == -4) {
				echo 'Email 格式有误';
			} elseif($uid == -5) {
				echo 'Email 不允许注册';
			} elseif($uid == -6) {
				echo '该 Email 已经被注册';
			} else {
				echo '未定义';
			}
		} else {
			echo '注册成功';
		}
 * 
 * </code>
 */
function uc_user_register($arr_user_info) {
	$arr_user_info['password'] = generate_pass($arr_user_info['password']);
	return call_user_func(UC_API_FUNC, 'user', 'register', $arr_user_info);
}

/**
 * array uc_user_login(string username , string password [, bool isuid])
 * 
 * 用户登录
 * - 本接口函数用于用户的登录验证，用户名及密码正确无误则返回用户在 UCenter 的基本数据，否则返回相应的错误信息。如果应用程序是升级过来
 *   的，并且当前登录用户和已有用户重名，那么返回的数组中 [4] 的值将返回 1
 * @param string  $username		- 用户名 / 用户 ID
 * @param string  $password		- 密码
 * @param bool  $isuid			- 是否使用用户 ID登录(1:使用用户 ID登录;0:(默认值) 使用用户名登录)
 * @return array                - integer $return['status'] 大于 0:返回用户 ID，表示用户登录成功
 *                                                             -1:用户不存在，或者被删除
 *                                                             -2:密码错
 * 							      string $return['username']     : 用户名
 * 							      string $return['password']     : 密码
 * 							      string $return['email']        : Email
 * 							      bool $return['merge']          : 用户名是否重名
 */
function uc_user_login($username, $password, $ip, $isuid=0, $all_mode=0) {
	$isuid = intval($isuid);
	$password = generate_hash_pass($password);
	$arr = array('username'=>$username, 'password'=>$password, 'ip'=>trim($ip), 'isuid'=>$isuid, 'all_mode'=>$all_mode);
	$return = call_user_func(UC_API_FUNC, 'user', 'login', $arr);
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}


function uc_get_user_num() {
	return call_user_func(UC_API_FUNC, 'user', 'get_user_num', array());
}

/**
 * string uc_user_synlogin(integer uid)
 * 
 * 同步登录
 * - (如果当前应用程序在 UCenter 中设置允许同步登录，那么本接口函数会通知其他设置了同步登录的应用程序登录，把返回的 HTML 输出在页面中即可完成对其它
 *    应用程序的通知。输出的 HTML 中包含执行远程的 javascript 脚本，请让页面在此脚本运行完毕后再进行跳转操作，否则可能会导致无法同步登录成功。
 *    同时要保证同步登录的正确有效，请保证其他应用程序的 Cookie 域和 Cookie 路径设置正确)
 * 注意：请根据各自应用程序需要，自行更改api/uc.php中的$action == 'synlogin'段的处理代码。
 *
 * 用法：
 * <code>
 *  list($uid, $username, $password, $email) = uc_user_login($_POST['username'], $_POST['password']);
	if($uid > 0) {
		echo '登录成功';
		echo uc_user_synlogin($uid);		//同步登陆
	} elseif($uid == -1) {
		echo '用户不存在,或者被删除';
	} elseif($uid == -2) {
		echo '密码错';
	} else {
		echo '未定义';
	}
 * </code>
 * 
 * @param integer $uid     - 用户 ID
 * @return string		  - 同步登录的 HTML 代码
 */
function uc_user_synlogin($uid, $remember=false) {
    return  uc_api_post('user', 'synlogin', array('uid'=>$uid, 'remember'=>intval($remember)));
}

/**
 * string uc_user_synlogout()
 *
 * 同步退出
 * 
 * @return string       - 同步退出的 HTML 代码
 */
function uc_user_synlogout() {
	return  uc_api_post('user', 'synlogout', array());
}

/**
 * integer uc_user_edit(string $arr_user_info [, bool ignoreoldpw])
 * 
 * 用于更新用户资料。更新资料需验证用户的原密码是否正确，除非指定 ignoreoldpw 为 1。
 * 如果只修改 Email 不修改密码，可让 newpw 为空；同理如果只修改密码不修改 Email，可让 email 为空
 *
 * @param array $arr_user_info
 * @param bool $ignoreoldpw
 * @return integer 		 1:更新成功
						 0:没有做任何修改
						-1:旧密码不正确
						-4:Email 格式有误
						-5:Email 不允许注册
						-6:该 Email 已经被注册
						-7:没有做任何修改
						-8:该用户受保护无权限更改
 */
function uc_user_edit($arr_user_info, $ignoreoldpw = 0) {
	$arr_user_info['ignoreoldpw'] = $ignoreoldpw;
	if (isset($arr_user_info['password'])) {
		$arr_user_info['password'] = generate_pass($arr_user_info['password']);
	} 
	return call_user_func(UC_API_FUNC, 'user', 'edit', $arr_user_info);
}

/**
 * 给指定数字类型字段加上相应值
 * - (如没必要，请将$ignorepw置为1，以提高查询速度)
 *
 * 用法：
 * <code>
 * 	$res_add_gold = uc_user_add_fieldnum('guiyj', md5('111111'), 'gold', 39, 1);
 * </code>
 * 
 * @param string $username
 * @param string $password
 * @param string $field_name
 * @param integer $num
 * @param integer $ignorepw
 * @return integer				 1:更新成功
						 		 0:没有做任何修改
								-1:密码不正确
								-2:更新失败
 */
function uc_user_add_fieldnum($username, $password, $field_name, $num = 0, $ignorepw = 0) {
	if ($num === 0)
		return 0;
	$param = array(
		'username'   => $username, 
		'password'   => generate_pass($password), 
		'field_name' => $field_name, 
		'num'        => intval($num),
		'ignorepw'   => $ignorepw
	);
	$res = call_user_func(UC_API_FUNC, 'user', 'add_fieldnum', $param);
	return intval($res);
}

function uc_user_sub_fieldnum($username, $password, $field_name, $num = 0, $ignorepw = 0) {
	if ($num === 0)
		return 0;
	$param = array(
		'username'   => $username, 
		'password'   => generate_pass($password), 
		'field_name' => $field_name, 
		'num'        => intval($num),
		'ignorepw'   => $ignorepw
	);
	$res = call_user_func(UC_API_FUNC, 'user', 'sub_fieldnum', $param);
	return intval($res);
}
/**
 * integer uc_user_add_gold($username, $password[, integer $gold])
 *
 * 增加用户在用户中心的金币数
 * 
 * @param string $username      - 用户名
 * @param string $password		- 经过md5加密过的用户密码
 * @param integer $gold			- 需要增加的金币数
 * @return integer		   		 1:更新成功
						 		 0:没有做任何修改
								-1:密码不正确
								-2:更新失败
 * 
 */
function uc_user_add_gold($username, $password, $gold = 0) {
	if ($gold === 0)
		return 0;
	$password = generate_pass($password);	
	$res = call_user_func(UC_API_FUNC, 'user', 'add_gold', array('username'=>$username, 'password'=>$password, 'gold'=>intval($gold)));
	return intval($res);
}

function uc_user_sub_gold($username, $password, $gold = 0) {
	if ($gold === 0)
		return 0;
	$password = generate_pass($password);	
	$res = call_user_func(UC_API_FUNC, 'user', 'sub_gold', array('username'=>$username, 'password'=>$password, 'gold'=>intval($gold)));
	return intval($res);
}
/**
 * uc_user_pwd_edit
 * 
 * 更新用户密码
 *
 * @param unknown_type $username
 * @param unknown_type $oldpw
 * @param unknown_type $newpw
 * @param unknown_type $ignoreoldpw
 * @return unknown
 */
function uc_user_pwd_edit($username, $oldpw, $newpw, $ignoreoldpw = 0) {
	$oldpw = generate_pass($oldpw);
	$newpw = generate_pass($newpw);
	return call_user_func(UC_API_FUNC, 'user', 'edit_pwd', array('username'=>$username, 'oldpw'=>$oldpw, 'newpw'=>$newpw, 'ignoreoldpw'=>$ignoreoldpw));
}

function uc_user_delete($uid) {
	return call_user_func(UC_API_FUNC, 'user', 'delete', array('uid'=>$uid));
}
/**
 * integer uc_user_checkname(string username)
 * 
 * 检查用户输入的用户名的合法性
 *
 * @param string $username
 * @return integer  		 1:成功
							-1:用户名不合法
							-2:包含要允许注册的词语
							-3:用户名已经存在
 */
function uc_user_checkname($username) {
	return call_user_func(UC_API_FUNC, 'user', 'check_username', array('username'=>$username));
}

/**
 * integer uc_user_nickname(string $nickname)
 * 
 * 检查用户输入的用户呢称的合法性
 *
 * @param string $username
 * @return integer  		 1:成功
							-1:用户呢称不合法
							-2:包含要允许注册的词语
							-3:呢称已经存在
 */
function uc_user_nickname($nickname) {
	return call_user_func(UC_API_FUNC, 'user', 'check_nickname', array('nickname'=>$nickname));
}

/**
 * integer uc_user_checkemail(string email)
 * 
 * 检查用户输入的 Email 的合法性。 
 *
 * @param string $email
 * @return integer  		 1:成功
							-4:Email 格式有误
							-5:Email 不允许注册
							-6:该 Email 已经被注册
 */
function uc_user_checkemail($email) {
	return call_user_func(UC_API_FUNC, 'user', 'check_email', array('email'=>$email));
}

function uc_user_addprotected($username, $admin='') {
	return call_user_func(UC_API_FUNC, 'user', 'addprotected', array('username'=>$username, 'admin'=>$admin));
}

function uc_user_deleteprotected($username) {
	return call_user_func(UC_API_FUNC, 'user', 'deleteprotected', array('username'=>$username));
}

function uc_user_getprotected() {
	$return = call_user_func(UC_API_FUNC, 'user', 'getprotected', array('1'=>1));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

/**
 * array uc_get_user(string username [, string $query_fields] [, bool isuid])
 * 
 * 获取用户信息(如用户不存在，返回值为 integer 的数值 0)
 * 
 * 用法：
 * <code>
 * 		$username = 'guiyj';
 * 		if($data = uc_get_user($username)) {
 * 			echo "用户$username的相关信息如下：</br>";
 * 			echo "邮箱：" . $data['email'] . "</br>";
 * 			echo "推荐人：" . $data['referralname'] . "</br>";
 * 		} else {
 * 			echo '用户不存在';
 * 		}
 * </code>
 * 
 * @param string $username				- 用户名
 * @param string $query_fields			- 需要获取的用户信息字段（默认为获取所有，否则以'field1, field2, field3'的形式设置此参数）
 * @param integer $isuid			    - 是否使用用户 ID获取(1:使用用户 ID获取;2:用邮箱获取；3：用昵称获取 0:(默认值) 使用用户名获取)
 * @return array					    - 以数据库中用户表字段名为键名，相应字段值为键值的联合数组。
 */
function uc_get_user($username, $query_fields='*', $isuid=0) {
	empty($query_fields) && $query_fields = '*';
	$return = call_user_func(UC_API_FUNC, 'user', 'get_user', array('username'=>$username, 'query_fields'=>$query_fields, 'isuid'=>$isuid));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_user_merge($oldusername, $newusername, $uid, $password, $email) {
	return call_user_func(UC_API_FUNC, 'user', 'merge', array('oldusername'=>$oldusername, 'newusername'=>$newusername, 'uid'=>$uid, 'password'=>$password, 'email'=>$email));
}

///////////////////////////////////////////////////////////////////////////////////////////////////////
//                      
//                            PM 相关接口
//////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * uc_pm_location
 *
 * @param string $username           - 邮箱所属用户的用户名
 * @param integer $newpm             - 新邮件数
 */
function uc_pm_location($username, $newpm = 0) {
	$hashed_uid = simple_md5($username);
	$apiurl = uc_api_url('pm_client', 'ls', "uid=$hashed_uid&username=$username", ($newpm ? '&folder=newbox' : ''));
//	$apiurl = uc_api_url('pm_client', 'ls', "uid=$uid", ($newpm ? '&folder=newbox' : ''));
	@header("Expires: 0");
	@header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
	@header("Pragma: no-cache");
	@header("location: $apiurl");
}
/**
 * uc_pm_location_send
 *
 * @param string $username           - 邮箱所属用户的用户名
 * @param string $msgto              - 接收人用户名(暂时只支持单人)
 */
function uc_pm_location_send($username, $msgto = '', $fromsite='') {
	$hashed_uid = simple_md5($username);
	$args = "&from=$fromsite" . ($msgto ? "&msgto=$msgto" : "&msgto=");
	$apiurl = uc_api_url('pm_client', 'send', "uid=$hashed_uid&username=$username", $args);
	
//	$apiurl = uc_api_url('pm_client', 'send', "uid=$hashed_uid&username=$username", ($msgto ? "&msgto=$msgto" : "&msgto="));
	echo "<script>window.location.href='" . $apiurl . "'</script>";
	exit();
	@header("Expires: 0");
	@header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
	@header("Pragma: no-cache");
	@header("location: $apiurl");
}
/**
 * uc_pm_checknew
 * 
 * 获取新消息的条数
 *
 * @param string $username      - 用户名
 * @return unknown
 */
function uc_pm_checknew($username) {
	$uid = trim(simple_md5($username));
	return call_user_func(UC_API_FUNC, 'pm', 'check_newpm', array('uid'=>$uid));
}

/**
 * uc_pm_send
 *
 * @param string $msgfrom        - 发送消息用户的用户名
 * @param string $msgto          - 接收消息用户的用户名，多个用户中间用','分隔
 * @param string $subject        - 消息主题
 * @param string $message        - 消息内容
 * @param integer $instantly     - 是否立即发送
 * @param integer $replypmid     - 回复的消息 ID
									大于 0:回复指定的短消息
									    0:(默认值) 发送新的短消息
 * @return mixed
 */
function uc_pm_send($msgfrom, $msgto, $subject, $message, $instantly = 1, $replypmid = 0) {
	if($instantly) {
		$replypmid = @is_numeric($replypmid) ? $replypmid : 0;
		return call_user_func(UC_API_FUNC, 'pm', 'sendpm', array('msgfrom'=>$msgfrom, 'msgto'=>$msgto, 'subject'=>$subject, 'message'=>$message, 'replypmid'=>$replypmid, 'isusername'=>1));
	} else {
		$msgfrom = urlencode($msgfrom);
		$subject = urlencode($subject);
		$msgto = urlencode($msgto);
		$message = urlencode($message);
		$replypmid = @is_numeric($replypmid) ? $replypmid : 0;
		$replyadd = $replypmid ? "&pmid=$replypmid&do=reply" : '';
		$hashed_uid = simple_md5($msgfrom);
		$apiurl = uc_api_url('pm_client', 'send', "uid=$hashed_uid", "&msgto=$msgto&subject=$subject&message=$message$replyadd");
		@header("Expires: 0");
		@header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
		@header("Pragma: no-cache");
		@header("location: ".$apiurl);
	}
}

/**
 * uc_pm_delete
 *
 * 删除指定用户指定信箱的消息
 * 
 * @param string $username     - 用户名
 * @param string $folder
 * @param unknown_type $pmids
 * @return unknown
 */
function uc_pm_delete($username, $folder, $pmids) {
	$uid = simple_md5(trim($username));
	return call_user_func(UC_API_FUNC, 'pm', 'delete', array('uid'=>$uid, 'folder'=>$folder, 'pmids'=>$pmids));
}

/**
 * uc_pm_change_info
 *
 * @param string $pmids  - 更新的pmid参数, 多个请用逗号分隔     
 * @param array $arr_updated - 被更新的内容
 * @return unknown
 */
function uc_pm_change_info($pmids, $arr_updated) {
	$return = call_user_func(UC_API_FUNC, 'pm', 'changeinfo', array('pmids'=>$pmids, 'pm_info'=>$arr_updated));
	return intval($return);
	
}

/**
 * uc_pm_get_num
 *
 * @param string $username        - 用户名
 * @param string $folder          - 短消息所在的文件夹
									newbox:新件箱
									inbox:(默认值) 收件箱
									outbox:发件箱
									trashbox:垃圾箱
 * @param string $filter          - 过滤方式
 *                                  newpm:(默认值) 未读消息，folder 为 inbox 和 outbox 时使用
									systempm:系统消息，folder 为 inbox 时使用
									announcepm:公共消息，folder 为 inbox 时使用
 * @return integer                - 消息条数
 */
function uc_pm_get_num($username, $folder = 'inbox', $filter = 'newpm') {
	$uid = trim(simple_md5($username));
	$return = call_user_func(UC_API_FUNC, 'pm', 'get_num', array('uid'=>$uid, 'folder'=>$folder, 'filter'=>$filter));
	return intval($return);
}

function uc_pm_list($username, $page = 1, $pagesize = 10, $folder = 'inbox', $filter = 'newpm', $msglen = 0) {
	$uid = trim(simple_md5($username));
	$page = intval($page);
	$pagesize = intval($pagesize);
	$return = call_user_func(UC_API_FUNC, 'pm', 'ls', array('uid'=>$uid, 'page'=>$page, 'pagesize'=>$pagesize, 'folder'=>$folder, 'filter'=>$filter, 'msglen'=>$msglen));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_pm_ignore($username) {
	$uid = trim(simple_md5($username));
	return call_user_func(UC_API_FUNC, 'pm', 'ignore', array('uid'=>$uid));
}

function uc_pm_view($username, $pmid) {
	$uid = trim(simple_md5($username));
	$pmid = @is_numeric($pmid) ? $pmid : 0;
	$return = call_user_func(UC_API_FUNC, 'pm', 'view', array('uid'=>$uid, 'pmid'=>$pmid));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_pm_viewnode($username, $type = 0, $pmid = 0) {
	$uid = trim(simple_md5($username));
	$pmid = @is_numeric($pmid) ? $pmid : 0;
	$return = call_user_func(UC_API_FUNC, 'pm', 'viewnode', array('uid'=>$uid, 'pmid'=>$pmid, 'type'=>$type));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

/**
 * 在trashbox和inbox间转移pm
 *
 * @param array $pms  - 被转移的pm信息数组，每一pm为一字符串，包括3部分的信息，中间用'|'分隔。
 * 						这3部分信息依次为：pmid,原pm的trash_status，pm信息是否为该用户发送（即pm是否是在该用户收件箱和垃圾响间转移）
 *                        eg: $pms = array('41|1|1', '42|2|0');
 * @param boolean $in    - 是否从其他邮箱转到垃圾箱，true表示是，false表示否，即将邮箱转到inbox或outbox
 * @return integer       - 被影响的记录条数,其中-1表示$pms格式有误
 */
function uc_pm_move_trashbox($pms, $go_to_trash=true) {
//	$pmids = trim($pmids);
	if (!is_array($pms) || empty($pms)) { return -1; }
	$return = call_user_func(UC_API_FUNC, 'pm', 'move_trashbox', array('pms'=>$pms, 'go_to_trash'=>$go_to_trash));
	return intval($return);
}

/**
 * Enter description here...
 *
 * @param unknown_type $uid
 * @return unknown
 */
function uc_pm_blackls_get($uid) {
	$uid = intval($uid);
	return call_user_func(UC_API_FUNC, 'pm', 'blackls_get', array('uid'=>$uid));
}

function uc_pm_blackls_set($uid, $blackls) {
	$uid = intval($uid);
	return call_user_func(UC_API_FUNC, 'pm', 'blackls_set', array('uid'=>$uid, 'blackls'=>$blackls));
}

function uc_domain_ls() {
	$return = call_user_func(UC_API_FUNC, 'domain', 'ls', array('1'=>1));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_credit_exchange_request($uid, $from, $to, $toappid, $amount) {
	$uid = intval($uid);
	$from = intval($from);
	$toappid = intval($toappid);
	$to = intval($to);
	$amount = intval($amount);
	return uc_api_post('credit', 'request', array('uid'=>$uid, 'from'=>$from, 'to'=>$to, 'toappid'=>$toappid, 'amount'=>$amount));
}

function uc_tag_get($tagname, $nums = 0) {
	$return = call_user_func(UC_API_FUNC, 'tag', 'gettag', array('tagname'=>$tagname, 'nums'=>$nums));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

function uc_avatar($uid) {
	$uid = intval($uid);
	$uc_input = uc_api_input("uid=$uid");
	$uc_avatarflash = UC_API.'/image/camera.swf?inajax=1&appid='.UC_APPID.'&input='.$uc_input.'&agent='.md5($_SERVER['HTTP_USER_AGENT']).'&ucapi='.urlencode(UC_API);
	return '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9" width=447 height=477 id="mycamera"><param name="movie" value="'.$uc_avatarflash.'"><param name="quality" value="high"><param name="menu" value="false"><embed src="'.$uc_avatarflash.'" quality="high" menu="false"  width="447" height="477" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" name="mycamera" swLiveConnect="true"></embed></object>';
}

///////////////////////////////////////////////////////////////////////////////////////////////////////
//                      
//                           用户联系信息接口
//////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * integer uc_user_concact_info_add(array $arr_user_info)
 * 
 * 添加用户的附属联系资料（资料参数以联合数组形式传递)，不需要添加的字段可以不填。
 * 
 * 用法：
 * <code>
 * 	$arr_user_info = array(
 * 		'uid' => 502,
 * 		'uname' => 'guiyj',
 * 		'first_name' => 'gui',
 * 		'last_name' => 'yajun',
 * 		'address' => 'FuZhou, FuJian, China',
 * 		'postcode' => '650010',
 * 		'mobile_phone' => '13056856561',
 * 		'hostpage' => 'http://www.test.com',
 * 		'interest' => 'reading book, sleeping...',
 * 		'msn' => '545565',
 * 		'icq' => '545565',
 * 		'aim' => '545565',
 * 		'skype' => '545565',
 * 		'google_talk' => '545565'
 *  );
 * $ret_id = uc_user_concact_info_add($arr_user_info);
 * if($ret_id <= 0) {
 *		if($uid == -1) {
 *			echo '用户id不合法';
 *		} elseif($uid == -2) {
 *			echo '包含要允许注册的词语';
 *		} else {
 *			echo '未定义';
 *		}
 * } else {
 *		echo '添加成功';
 * }
 * </code>
 * @param array $arr_user_info
 * @return integer  >0  返回附属资料记录项id
 * 					-1  用户id有误
 *                  -2  已经存在id为$arr_user_info['uid']的记录。 
 */
function uc_user_concact_info_add($arr_user_info) {
	$res = call_user_func(UC_API_FUNC, 'user', 'add_concact_info', $arr_user_info);
	return intval($res);
}

/**
 * integer uc_user_concact_info_update(array $arr_user_info)
 * 
 * 添加用户的附属联系资料（资料参数以联合数组形式传递）
 * 
 * 用法：
 * <code>
 * 	$arr_user_info = array(
 * 		'uid' => 502,
 * 		'first_name' => 'gui',
 * 		'last_name' => 'yajun',
 * 		'address' => 'FuZhou, FuJian, China',
 * 		'postcode' => '650010',
 * 		'mobile_phone' => '13056856561',
 * 		'hostpage' => 'http://www.test.com',
 * 		'interest' => 'reading book, sleeping...',
 * 		'msn' => '545565',
 * 		'icq' => '545565',
 * 		'aim' => '545565',
 * 		'skype' => '545565',
 * 		'google_talk' => '545565'
 *  );
 * $ret_id = uc_user_concact_info_update($arr_user_info);
 * if($ret_id == 0) {
 *		echo "更新失败";
 * } elseif ($ret_id == 1) {
 * 		echo "更新成功";
 * } elseif ($ret_id == -1) {
 * 		echo "用户id不合法";
 * } else {
 * 		echo "未定义";
 * }
 * </code>
 * @param array $arr_user_info		//需要更新的用户资料信息
 * @return integer   0  更新失败
 * 					 1  更新成功
 * 					-1  用户id有误
 */
function uc_user_concact_info_update($arr_user_info) {
	$res = call_user_func(UC_API_FUNC, 'user', 'update_concact_info', $arr_user_info);
	return intval($res);
}

/**
 * uc_user_concact_info_get
 * 
 * 获取给定用户id号或用户名的用户的附属的联系信息资料
 *
 * @param mixed $uname         - 用户id号或用户名
 * @param integer $isuid        - 第一参数是否用户id，默认为否
 * @return array               - 用户的第一类附属资料
 */
function uc_user_concact_info_get($uname, $isuid=0) {
	$return = call_user_func(UC_API_FUNC, 'user', 'get_concact_info', array('uname'=>$uname, 'isuid'=>$isuid));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

///////////////////////////////////////////////////////////////////////////////////////////////////////
//                      
//                             用户详细信息接口
//////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * integer uc_user_detail_add(array $arr_user_info)
 * 
 * 添加用户的附属详细资料（资料参数以联合数组形式传递）
 * 
 * 用法：
 * <code>
 * 	$arr_user_info = array(
 * 		'uid' => 502,
 * 		'uname' => 'guiyj',					
 * 		'hight' => '173',					//身高（单位cm）
 * 		'weight' => '60.18',				//体重（单位kg，精确到小数点后2位）
 * 		'blood_type' => 'AB',
 * 		'living_situation' => '0',			//结婚状况 0=未结婚；1=已结婚
 * 		'character' => 'unknown',			//性格特征	
 * 		'is_smoking' => '1',				//是否吸烟 0=否；1=是
 * 		'is_drinking' => '1',				//是否喝酒 0=否；1=是
 * 		'education' => 'bachelor',			//教育程度
 * 		'career' => 'worker',				//职业
 * 		'income' => '5555',					//月收入
 * 		'fav_music' => 'hiphop',			//喜欢的音乐
 * 		'fav_tv' => 'double heart',			//喜欢的电视剧
 * 		'fav_movie' => 'lalal',				//喜欢的电影
 * 		'fav_book' => '545565',				//喜欢的书籍 
 * 		'fav_quote' => 'moveon or fallback'	//喜欢的座右铭
 *  );
 * $ret_id = uc_user_detail_add($arr_user_info);
 * if($ret_id <= 0) {
 *		if($uid == -1) {
 *			echo '用户id不合法';
 *		} elseif($uid == -2) {
 *			echo '包含要允许注册的词语';
 *		} else {
 *			echo '未定义';
 *		}
 * } else {
 *		echo '添加成功';
 * }
 * 		
 * </code>
 * @param array $arr_user_info
 * @return integer  >0  返回附属资料记录项id
 * 					-1  用户id有误
 *                  -2  已经存在id为$arr_user_info['uid']的记录。 
 */
function uc_user_detail_add($arr_user_info) {
	$res = call_user_func(UC_API_FUNC, 'user', 'add_user_detail', $arr_user_info);
	return intval($res);
}

/**
 * integer uc_user_detail_update(array $arr_user_info)
 * 
 * 更新用户的用户详细资料x信息（资料参数以联合数组形式传递）
 * 
 * 用法：
 * <code>
 * 	$arr_user_info = array(
 * 		'uid' => 502,
 * 		'uname' => 'guiyj',					
 * 		'hight' => '173',					//身高（单位cm）
 * 		'weight' => '60.18',				//体重（单位kg，精确到小数点后2位）
 * 		'blood_type' => 'AB',
 * 		'living_situation' => '0',			//结婚状况 0=未结婚；1=已结婚
 * 		'character' => 'unknown',			//性格特征	
 * 		'is_smoking' => '1',				//是否吸烟 0=否；1=是
 * 		'is_drinking' => '1',				//是否喝酒 0=否；1=是
 * 		'education' => 'bachelor',			//教育程度
 * 		'career' => 'worker',				//职业
 * 		'income' => '5555',					//月收入
 * 		'fav_music' => 'hiphop',			//喜欢的音乐
 * 		'fav_tv' => 'double heart',			//喜欢的电视剧
 * 		'fav_movie' => 'lalal',				//喜欢的电影
 * 		'fav_book' => '545565',				//喜欢的书籍 
 * 		'fav_quote' => 'moveon or fallback'	//喜欢的座右铭
 *  );
 * $ret_id = uc_user_detail_update($arr_user_info);
 * if($ret_id == 0) {
 *		echo "更新失败";
 * } elseif ($ret_id == 1) {
 * 		echo "更新成功";
 * } elseif ($ret_id == -1) {
 * 		echo "用户id不合法";
 * } else {
 * 		echo "未定义";
 * }
 * </code>
 * @param array $arr_user_info		//需要更新的用户资料信息
 * @return integer   0  更新失败
 * 					 1  更新成功
 * 					-1  用户id有误
 */
function uc_user_detail_update($arr_user_info) {
	$res = call_user_func(UC_API_FUNC, 'user', 'update_user_detail', $arr_user_info);
	return intval($res);
}

/**
 * uc_user_detail_get
 * 
 * 获取给定用户id号或用户名的用户的附属详细信息
 *
 * @param mixed                - 用户名或用户id
 * @param integer $isuid       - 第一个参数是否为用户id, 默认为否，即根据用户名获取用户详细信息
 * @return array               - 用户的附属详细信息
 */
function uc_user_detail_get($uname, $isuid=0) {
	$return = call_user_func(UC_API_FUNC, 'user', 'get_user_detail', array('uname'=>$uname, 'isuid'=>$isuid));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

//mygame
/**
 * integer uc_mygame_add(array $arr_game_info)
 *
 * 添加相关用户的游戏信息。（用户名放在做为参数的联合数组$arr_game_info中） 
 * 用法：
 * <code>
 * $arr_game_info = array(
 * 		'user' => 'guiyj',
 * 		'gameid' => '2',
 * 		'gamename' => 'world of warcraft',
 * 		'server' => 'dianxin1',
 * 		'nickname' => 'daemon',
 * 		'class' => 'westen',
 * 		'level' => '70',
 * 		'link' => 'http://www.wowchina.com',
 * 		'type' => 'Played',
 * 		'status' => 'normal'
 * );
 * $ret_id = uc_mygame_add($arr_game_info);
 * echo ($ret_id > 0) ? "添加成功" : "添加失败";
 * </code>
 * 
 * @param array $arr_game_info	- 记录游戏信息的联合数组
 * @return integer				- >1 添加成功,返回新增记录项id
 *                                -1 用户名有误
 *                                -2 游戏名有误 
 * 							       0 添加失败 
 */
function uc_mygame_add($arr_game_info) {
	$res = call_user_func(UC_API_FUNC, 'mygame', 'add', $arr_game_info);
	return intval($res);
}

/**
 * integer uc_mygame_update(array $arr_game_info)
 *
 * 添加相关用户的游戏信息。（必须字段$arr_game_info['id']） 
 * 用法：
 * <code>
 * $arr_game_info = array(
 * 		'id' => 51,
 * 		'gameid' => '2',
 * 		'gamename' => 'world of warcraft',
 * 		'server' => 'dianxin1',
 * 		'nickname' => 'daemon',
 * 		'class' => 'westen',
 * 		'level' => '70',
 * 		'link' => 'http://www.wowchina.com',
 * 		'type' => 'Played',
 * 		'status' => 'normal'
 * );
 * $ret_id = uc_mygame_update($arr_game_info);
 * echo ($ret_id > 0) ? "更新成功" : "更新失败";
 * </code>
 * 
 * @param array $arr_game_info	-  需要更新的游戏信息的联合数组
 * @return integer			    - 大于0 更新成功的记录条数
 *                                -1 用户名有误
 *                                -2 游戏名有误 
 * 							       0 添加失败
 */
function uc_mygame_update($arr_game_info) {
	$res = call_user_func(UC_API_FUNC, 'mygame', 'update', $arr_game_info);
	return intval($res);
}

/**
 * integer uc_mygame_delete(string $mygameids)
 *
 * 删除相关的游戏信息。
 * 用法：
 * <code>
 * $mygameids = "5,11,12";
 * $res_delete = uc_mygame_delete($mygameids);
 * echo ($res_delete == 1) ? "删除成功" : "删除失败";
 * </code>
 * 
 * @param string $mygameids  	- 需要删除的游戏记录项id串
 * @return integer				- 1 删除成功
 * 							      0 删除失败 
 */
function uc_mygame_delete($mygameids) {
	$res = call_user_func(UC_API_FUNC, 'mygame', 'delete', array('mygameids'=>$mygameids));
	return intval($res);
}

/**
 * integer uc_mygame_totalnum(array $arr)
 * 
 * 获取指定用户的游戏数目
 *  - 注：$arr参数为查询条件数组
 *       'key'=>'value'将被解释为对应的sql语句为： where `key1` = 'value1' and `key2` = 'value2'...
 *
 * @param array $arr        - 需要查找的where子句条件数组： 如获取'lean'的所有游戏，则$arr = array('user'=>simple_md5('lean'))
 * @return integer          - 获取到的记录条数
 */
function uc_mygame_totalnum($arr = null) {
	$where_conditions = ' 1=1 ';
	if (is_array($arr) && !empty($arr)) {
		foreach ($arr as $key => $value) {
			$where_conditions .= " and `$key`='$value'";
		}
	}
	$res = call_user_func(UC_API_FUNC, 'mygame', 'totalnum', array('where_conditions'=>$where_conditions));
	return intval($res);
}


/**
 * uc_mygame_ls
 * 
 * 获取指定查询条件，指定起始条数与需要条数的游戏信息。
 *  - 注：
 *     $arr参数为查询条件数组
 *       'key'=>'value'将被解释为对应的sql语句为： where `key1` = 'value1' and `key2` = 'value2'...
 *    
 *  
 * 
 * @param integer $page     - 列表显示的当前页码
 * @param integer $pagesize - 列表当前页需要显示的记录条数
 * @param integer $totalnum - 所有记录条数
 * @param array $arr        - 需要查找的where子句条件数组： 如获取'lean'的所有游戏，则$arr = array('user'=>simple_md5('lean'))
 * 
 * @return array            - 查询到的游戏信息
 */
function uc_mygame_ls($page = 1, $pagesize = 10, $totalnum = 10, $arr = null) {
	$where_conditions = ' 1=1 ';
	if (is_array($arr) && !empty($arr)) {
		foreach ($arr as $key => $value) {
			$where_conditions .= " and `$key`='$value'";
		}
	}
	$return = call_user_func(UC_API_FUNC, 'mygame', 'ls', array('page'=>$page, 'pagesize'=>$pagesize, 'totalnum'=>$totalnum, 'where_conditions'=>$where_conditions));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}


/**
 * uc_mygame_super_ls
 *
 * @param string $select_fields        - sql语句select部分的内容
 * @param string $where_conditions     - sql语句where部分的内容
 * @param string $group_by             - sql语句group by部分的内容
 * @param string $order_by             - sql语句order by部分的内容
 * @param string $limit                - sql语句limit部分的内容
 * @return array
 * 
 * <code>
   // 取得指定游戏(gameid为651)的服务器列表
   $res = uc_mygame_super_ls('id, user, gamename, server', "`gameid`='651'", 'gamename', 'gamename desc', '0,5');
	var_dump($res);
 * </code>
 */
function uc_mygame_super_ls($select_fields='*', $where_conditions='1', $group_by='', $order_by='id', $limit='0,10') {
	$return = call_user_func(UC_API_FUNC, 'mygame', 'super_ls', 
			array('select_fields'=>$select_fields, 'where_conditions'=>$where_conditions,
				'group_by'=>$group_by, 'order_by'=>$order_by, 'limit'=>$limit));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}


//friend_list
/**
 * integer uc_friendlist_add(array $arr_friends_info)
 *
 * 添加相关用户的好友信息。
 *  注：加用户为黑名单，用该函数处理即可。
 * 
 * 用法：
 * <code>
 * $arr_friend_info = array(
 * 		'user' => 'a916e4bec77b81a51c6ebaa052520306',	//经过hash处理过的用户名
 * 		'fuser' => 'ee40b7c379f37620d59ac0853ab80e35',	//经过hash处理过的好友名
 * 		'fname' => 'friend1',
 * 		'fdomain_name' => 'friend1',
 * 		'state' => 'accept',
 * 		'intro' => 'lalaal'
 * );
 * $ret_id = uc_friendlist_add($arr_friend_info);
 * echo ($ret_id > 0) ? "添加成功" : "添加失败";
 * </code>
 * 
 * @param array $arr_friends_info	- 需要添加的好友信息的联合数组
 * @return integer				- >1 添加成功,返回新增记录项id
 *                                -1 用户名有误
 *                                -2 好友用户名(fuser)有误 
 *                                -3 user用户已经加fuser为好友了 
 * 							       0 添加失败 
 */
function uc_friendlist_add($arr_friend_info) {
	$res = call_user_func(UC_API_FUNC, 'friendlist', 'add', $arr_friend_info);
	return intval($res);
}

/**
 * integer uc_friendlist_update(array $arr_friends_info)
 *
 * 添加相关用户的好友信息。（必须字段uc_friendlist_update['fid']） 
 * 
 * 用法：
 * <code>
 * $arr_friend_info = array(
 * 		'fid' => '51,52',	//好友记录id串，如一次修改多条记录，请将多个fid用逗号分隔做为此参数
 * 		'state' => 'accept',
 * );
 * $ret_id = uc_friendlist_update($arr_friend_info);
 * echo ($ret_id == 1) ? "更新成功" : "更新失败";
 * </code>
 * 
 * @param array $arr_game_info	-  需要更新的游戏信息的联合数组
 * @return integer				- （如果$arr_friend_info[fid]串中只有一个id， 则返回操作是否失败：1表示成功，0表示失败）
 * 									如过一次更新多条数据，则retrun含义如下：
 *                             大于0 更新成功的记录条数 
 *                                -1 用户名有误
 *                                -2 好友用户名(fuser)有误 
 * 							       0 更新失败 
 */
function uc_friendlist_update($arr_friend_info) {
	$res = call_user_func(UC_API_FUNC, 'friendlist', 'update', $arr_friend_info);
	return intval($res);
}

/**
 * integer uc_friendlist_delete(string $fids)
 *
 * 删除相关的好友信息。
 * 
 * 用法：
 * <code>
 * $fids = "5,11,12";
 * $res_delete = uc_friendlist_delete($fids);
 * echo ($res_delete == 1) ? "删除成功" : "删除失败";
 * </code>
 * 
 * @param string $fids  	- 需要删除的好友记录项id串
 * @return integer			- 1 删除成功
 * 							  0 删除失败 
 */
function uc_friendlist_delete($fids) {
	$res = call_user_func(UC_API_FUNC, 'friendlist', 'delete', array('fids'=>$fids));
	return intval($res);
}

/**
 * uc_friendlist_totalnum
 * 
 * 根据指定查询条件获取好友数量。
 *  - 注：$arr参数为查询条件数组
 *       'key'=>'value'将被解释为对应的sql语句为： where `key1` = 'value1' and `key2` = 'value2'...
 * 
 * 用法：
 * <code>
 * 	$arr = array(
 *  	'state' => 'black',
 *  	'user' => simple_md5('lean')
 *  );
 * $num_friends = uc_friendlist_totalnum($arr);
 * </code>
 *
 * @param array $arr		- 查询条件数组（以'查询字段'=>'查询的值'的索引形式表示）
 * @return integer
 */
function uc_friendlist_totalnum($arr = null) {
	$where_conditions = ' 1=1 ';
	if (is_array($arr) && !empty($arr)) {
		foreach ($arr as $key => $value) {
			$where_conditions .= " and `$key`='$value'";
		}
	}
	$res = call_user_func(UC_API_FUNC, 'friendlist', 'totalnum', array('where_conditions'=>$where_conditions));
	return intval($res);
}

/**
 * uc_friendlist_ls
 * 
 * 获取指定查询条件的好友信息。
 *  - 注：$arr参数为查询条件数组
 *       'key'=>'value'将被解释为对应的sql语句为： where `key1` = 'value1' and `key2` = 'value2'...
 *
 * @param integer $page     - 列表显示的当前页码
 * @param integer $pagesize - 列表当前页需要显示的记录条数
 * @param integer $totalnum - 所有记录条数
 * @param array $arr        - 需要查找的where子句条件数组： 如获取'lean'的好友，则$arr = array('user'=>simple_md5('lean'), 'state'=>'friend')
 * 
 * @return array
 */
function uc_friendlist_ls($page = 1, $pagesize = 10, $totalnum = 10, $arr = null) {
	$where_conditions = ' 1=1 ';
	if (is_array($arr) && !empty($arr)) {
		foreach ($arr as $key => $value) {
			$where_conditions .= " and `$key`='$value'";
		}
	}
	$return = call_user_func(UC_API_FUNC, 'friendlist', 'ls', array('page'=>$page, 'pagesize'=>$pagesize, 'totalnum'=>$totalnum, 'where_conditions'=>$where_conditions));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

/**
 * uc_friendlist_totalnum_direction
 * 
 * 获取给定用户的好友数
 * 
 * @param string $user         - 用户名
 * @param integer $direction   - 被查询方向
 * 								0：反向，即查找添加该用户为好友的人
 * 								1：正向，即查找该用户已经添加的好友
 * 								2：双向，即查找被该用户添加的人，且这个人也添加了该用户为好友
 * 
 * @return integer             - 查询到的好友数
 */
function uc_friendlist_totalnum_direction($user, $direction=2) {
	$user = simple_md5($user);
	$res = call_user_func(UC_API_FUNC, 'friendlist', 'totalnum_with_direction', array('user'=>trim($user)));
	return intval($res);
}

/**
 * uc_friendlist_ls_direction
 * 
 * 获取给定用户的好友列表
 * 
 * @param string $user
 * @param integer $page
 * @param integer $pagesize
 * @param integer $totalnum
 * @param integer $direction
 * @return unknown
 */
function uc_friendlist_ls_direction($user, $page = 1, $pagesize = 10, $totalnum = 10, $direction=2) {
	$user = simple_md5($user);
	$return = call_user_func(UC_API_FUNC, 'friendlist', 'ls_width_direction', array('page'=>$page, 'pagesize'=>$pagesize, 'totalnum'=>$totalnum, 'user'=>trim($user)));
	return UC_CONNECT == 'mysql' ? $return : uc_unserialize($return);
}

if (!function_exists('simple_md5')) {
	function simple_md5($str){
		$str = strtolower(trim($str));
		return md5($str.'f#i=4a-8&rZz=0T'.substr($str,0,1).strlen($str));
	}
}

function generate_pass($password) {
	return md5(md5(strtolower(trim($password))) . PASSKEY_SALT);
}

/**
 *
 * 此函数针对generate_pass修正
 * 差别在generate_pass传入的是明文密码串，此函数传入md5(strtolower($password))的hash串
 */
function generate_hash_pass($hash) {
	return md5($hash . PASSKEY_SALT);
}
?>