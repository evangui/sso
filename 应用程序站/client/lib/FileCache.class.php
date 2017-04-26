<?php

define('DIR_MODE_PLAIN', 1);
define('DIR_MODE_HASH', 2);
define('DIR_MODE_MIX', 3);
define('CACHE_FILENAME_SUFFIX', '.php');

define('EXPIRE_MODE_INTEVAL', 0);
define('EXPIRE_MODE_YEAR', 1);
define('EXPIRE_MODE_MONTH', 2);
define('EXPIRE_MODE_WEEK', 3);
define('EXPIRE_MODE_DAY', 4);
define('EXPIRE_MODE_HOUR', 5);
define('EXPIRE_MODE_MINUTE', 6);

class FileCache
{
	static private $_cacheDir;
	static private $_fileName;
	static private $_additionalDirStrategy;
	static private $_additionalDirMode = DIR_MODE_PLAIN;
	
	static private $_expireTime = 86400000; //缓存的时间间隔 或 被分析的时间段中的某一秒时间撮
	static private $_expStrategyHandle = null;
	static private $_expireMode = EXPIRE_MODE_INTEVAL;
	
	static public function get($name, $func_name='', $args=array()) {
		$full_file_path = self::_getFullPath($name, self::$_additionalDirMode);
		if (self::needRefresh($full_file_path)) {
			if (empty($func_name)) {
				return false;	//注意：如果过期了，但没有回调函数也返回false.
			}
			
			$args_num = count($args);
			switch ($args_num) {
				case 0 : $data = call_user_func($func_name); break;				
				case 1 : $data = call_user_func($func_name, $args[0]); break;				
				case 2 : $data = call_user_func($func_name, $args[0], $args[1]); break;				
				case 3 : $data = call_user_func($func_name, $args[0], $args[1], $args[2]); break;				
				case 4 : $data = call_user_func($func_name, $args[0], $args[1], $args[2], $args[3]); break;				
				case 5 : $data = call_user_func($func_name, $args[0], $args[1], $args[2], $args[3], $args[4]); break;				
				case 6 : $data = call_user_func($func_name, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]); break;				
				default: $data = call_user_func($func_name); break;						
			}
			if ($data != false) {
				self::_cacheVariable($full_file_path, $data);
			}
			return $data;
		} else {
			include($full_file_path);
			return $_CACHE;	
		}
	}
	
	static public function set($name, $data) {
		$full_file_path = self::_getFullPath($name, self::$_additionalDirMode);
		return self::_cacheVariable($full_file_path, $data);
	}
	
	/**
	 * needRefresh
	 * 
	 * judge if the asked cached file do not exist 
	 *  or has expired in terms of specified expiring strategy.
	 * 
	 */
	static public function needRefresh($full_file_path) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		if (!empty(self::$_expStrategyHandle)) {
			return call_user_func(self::$_expStrategyHandle, $full_file_path);
		} else {
			return ExpireStrategy::judge($full_file_path, self::$_expireMode, self::$_expireTime);
		}
	}
	
	static public function bindExpStrategy($func_name) {
		self::$_expStrategyHandle = $func_name;
	}
	
	
	
	static public function getPath($name) {
		return $full_file_path = self::_getFullPath($name, self::$_additionalDirMode);
	}
	
	static public function setCacheDir($cache_dir) {
		self::$_cacheDir = $cache_dir;
	}
	
	
	static public function setAdditionalDirMode($mode) {
		self::$_additionalDirMode = $mode;
	}
	
	static public function setExpireMode($mode, $time_param) {
		self::$_expireMode = $mode;
		self::$_expireTime = intval($time_param);
	}
	
	static public function setTime($time_param) {
		self::$_expireTime = intval($time_param);
	}
	
	static private function _getFullPath($name, $mode) {
		return self::$_cacheDir . RelativeCachePath::get($mode, $name);
	}
	
	static private function _cacheVariable($full_file_path, $in_array = null) {
		$fcontent = "<?php\r\n\r\n";
		$fcontent .= "// cache file：" . $full_file_path . "\r\n";
		$fcontent .= "// Created on ：" . date('Y-m-d H:i:s') . "\r\n";
		$fcontent .= "// DO NOT modify me!\r\n\r\n";
		$fcontent .= "\$_CACHE = ";
		$fcontent .= var_export($in_array, true);
		$fcontent .= ";\r\n\r\n\$num = " . count($in_array) . ";";
		$fcontent .= "\r\n\r\n?>";
		$dir = dirname($full_file_path);
		if(!is_dir($dir)) {
			self::_mkdirs($dir, 0777);
		}
//		$fname = self::$_cacheDir . 'cache_' . $name . '.php';
		self::_writeover($full_file_path, $fcontent);
	}
	
	
	
	/**
	 * 建立多级目录
	 */
	static private function _mkdirs($dir,$mode=0664){
		if(!is_dir($dir)){
			self::_mkdirs(dirname($dir), $mode);
			@mkdir($dir,$mode);
		}
		return ;
	}
	
	static private function _writeover($filename, $data, $method="rb+", $iflock=1, $chmod=1) {
		touch($filename);
		$handle = fopen($filename, $method);
		$iflock && flock($handle, LOCK_EX);
		fwrite($handle, $data);
		if($method == "rb+") ftruncate($handle, strlen($data));
		fclose($handle);
		$chmod && @chmod($filename, 0777);
	}
}


//now it just assistes 5 mode strategy
//note that: delay method should be added into this class.
class ExpireStrategy
{
	
	static public function judge($file_path, $mode, $time_param) {
		switch (intval($mode)) {
			case EXPIRE_MODE_INTEVAL:
				return self::_intevalExpire($file_path, $time_param);
				break;
			case EXPIRE_MODE_YEAR:
				return self::_yearExpire($file_path, $time_param);
				break;	
			case EXPIRE_MODE_MONTH:
				return self::_monthExpire($file_path, $time_param);
				break;	
			case EXPIRE_MODE_WEEK:
				return self::_weekExpire($file_path, $time_param);
				break;	
			case EXPIRE_MODE_DAY:
				return self::_dayExpire($file_path, $time_param);
				break;
			case EXPIRE_MODE_HOUR:
				return self::_hourExpire($file_path, $time_param);
				break;
			case EXPIRE_MODE_MINUTE:
				return self::_minuteExpire($file_path, $time_param);
				break;
			default:
				return self::_intevalExpire($file_path, $time_param);
				break;
		}
	}
	
	
	static private function _yearExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		//计算年最后一秒时间
		$last_timestamp = mktime(23, 59, 59, 12, 31, date('Y', $timestamp));
		if (filemtime($full_file_path) <= $week_last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	//$timestamp 为一月中任一时间点（即为被分析月数据的任一时间）
	static private function _monthExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		//计算这个月最后一秒时间
		$last_timestamp = mktime(23, 59, 59, 
							date('m', $timestamp), date('t', $timestamp), date('Y', $timestamp));
		if (filemtime($full_file_path) <= $week_last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	//$timestamp 为一周中任一时间点（即为被分析周数据的任一时间）
	static private function _weekExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		//计算这个周最后一秒时间
		$first = 0; //一周以星期一还是星期天开始，0为星期天，1为星期一
		$w = date("w", $timestamp);//取得一周的第几天,星期天开始0-6
		$dn = $w ? 6 - ($w - $first) : 0;//要加上的天数
		$gdate = date("Y-m-d", $timestamp);
		$week_last_timestamp = strtotime("$gdate +".$dn." days") + 86399;
		if (filemtime($full_file_path) <= $week_last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	static private function _dayExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		$last_timestamp = mktime(23, 59, 59, 
			 				date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp));
		if (filemtime($full_file_path) <= $last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	static private function _hourExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		$last_timestamp = mktime(date('H', $timestamp), 59, 59, 
							 date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp));
		if (filemtime($full_file_path) <= $last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	static private function _minuteExpire($full_file_path, $timestamp) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		$last_timestamp = mktime(date('H', $timestamp),date('i', $timestamp), 59,
							date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp));
		if (filemtime($full_file_path) <= $last_timestamp) {
			return true;
		} else {
			return false;
		}
	}
	
	static private function _intevalExpire($full_file_path, $expire_inteval) {
		if (!file_exists($full_file_path)) {
			return true;
		}
		$cache_mtime = filemtime($full_file_path);
		if ($cache_mtime + $expire_inteval < time()) {
			return true;
		} else {
			return false;
		}
	}
	
}


/**
 * CacheFilePath
 * 
 * 缓存路径策略处理类(策略较简单，所以只用静态类实现)
 * （后面再补充2008.10.27, 目前只把简单路径的返回放到get方法中，后面请以该类为抽象工厂生产策略对象，get接口保留）
 *
 */
class RelativeCachePath
{
	static public function get($flag, $filename) {
		$len = strlen(CACHE_FILENAME_SUFFIX);
		if (substr($filename, -$len) != CACHE_FILENAME_SUFFIX) {
			$filename = $filename . CACHE_FILENAME_SUFFIX;
		}
		switch ($flag) {
			case DIR_MODE_PLAIN:
				return $filename;
				break;
			case DIR_MODE_HASH:
				$hased_filename = md5($filename);
				return substr($hased_filename, 0, 2) . DIRECTORY_SEPARATOR 
					. substr($hased_filename, 2, 2) . DIRECTORY_SEPARATOR
					. substr($hased_filename, 4, 2) . DIRECTORY_SEPARATOR
					. basename($filename);
				break;
			case DIR_MODE_MIX:
				$has_filename_with_dir = false;
				if ( (strstr($filename, '/') != false) || (strstr($filename, "\\") != false)) {
					$has_filename_with_dir = true;
				}
				$hased_filename = md5($filename);
				return substr($hased_filename, 0, 2) . DIRECTORY_SEPARATOR 
					. substr($hased_filename, 2, 2) . DIRECTORY_SEPARATOR
					. substr($hased_filename, 4, 2) . DIRECTORY_SEPARATOR
					. $filename;
				break;	
			default:
				return $filename;
				break;	
		}
	}
}


//设置默认的缓存文件目录
FileCache::setCacheDir(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);
//设置默认的缓存路径策略
FileCache::setAdditionalDirMode(DIR_MODE_PLAIN);
//FileCache::setExpireMode(EXPIRE_MODE_DAY, strtotime('2008-11-01'));

?>