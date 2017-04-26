<?php
/**
 * Application_Interface_path/config.inc.php
 * （该文件放到应用程序端接口目录的根目录下） 
 *
 * 该文件为MmoUcenter应用程序端的配置文件。分两栏
 * 1 数据库相关：目前未采用，如要启用该方式，请确保client目录有完整的control和model层的代码。
 * 2 通信相关  ：该栏配置包括 服务器端接口URL和通信密钥以及应用程序在服务器端的标识号。
 * 
 */

define('UC_CONNECT', NULL);			      	// 连接 UCenter 的方式: mysql/NULL, 默认为空时为 fscoketopen()
							                // mysql 是直接连接的数据库, 为了效率, 建议采用 mysql

//通信相关
define('UC_KEY', 'fdsid=34n&rlzfs=1T');			// 与 UCenter 的通信密钥, 要与 UCenter 保持一致
define('UC_API', 'http://xxxx.mmosite.com/xxxx');	// UCenter 的 URL 地址, 在调用头像时依赖此常量
define('UC_CHARSET', 'utf-8');				// UCenter 的字符集
define('UC_IP', '');				    	// UCenter 的 IP, 当 UC_CONNECT 为非 mysql 方式时, 并且当前应用服务器解析域名有问题时, 请设置此值
define('UC_APPID', 6);					    // 当前应用的 ID


//同步登录 Cookie 设置
$cookiedomain = ''; 			           // cookie 作用域
$cookiepath = '/';			               // cookie 作用路径
