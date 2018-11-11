## 简单的调用示例，见uc_sso_test.php
## 注意：各个子站的用户登陆有效时间，请务必设置成 关闭浏览器就登出的形式。

# 1. 摘要   
  本文主要介绍了利用webservice,session,cookie技术，来进行通用的单点登录系统的分析与设计。具体实现语言为PHP。单点登录，英文名为Single Sign On，简称为 SSO，是目前企业，网络业务的用户综合处理的重要组成部分。而SSO的定义，是在多个应用系统中，用户只需要登陆一次就可以访问所有相互信任的应用系统。

# 2. 动机   
  用过uctenter的全站登录方式的朋友，应该都知道这是典型的观察者模式的解决方案。用户中心作为subject, 其所属observer的注册和删除统一在ucenter的后台进行。而各个子应用站点都对应一个observer。每次用户中心的登录动作，都会触发js脚本回调w3c标准的子站登录接口(api/uc.php)。 
  这种方式的缺点，本人认为主要是两点:1. 子站点过多时，回调接口相应增多，这个在分布子站的量的限制上，如何控制来使登录效率不会太低，不好把握; 2. 当某个子站回调接口出现问题时，默认的登录过程会卡住(可以限制登录程序的执行时间，但相应出现问题子站后面的子站的回调接口就调不到了。
  基于以上问题，在实际开发过程中，本人设计了另一套单点登录系统。

# 3. 登陆原理说明         
  单点登录的技术实现机制:当用户第一次访问应用系统1的时候，因为还没有登录，会被引导到认证系统中进行登录;根据用户提供的登录信息，认证系统进行身份效验，如果通过效验，应该返回给用户一个认证的凭据--ticket;用户再访问别的应用的时候，就会将这个ticket带上，作为自己认证的凭据，应用系统接受到请求之后会把ticket送到认证系统进行效验，检查ticket的合法性。如果通过效验，用户就可以在不用再次登录的情况下访问应用系统2和应用系统3了。
     可以看出，要实现SSO，需要以下主要的功能:
* 所有应用系统共享一个身份认证系统;    
* 所有应用系统能够识别和提取ticket信息;
* 应用系统能够识别已经登录过的用户，能自动判断当前用户是否登录过，从而完成单点登录的功能

基于以上基本原则，本人用php语言设计了一套单点登录系统的程序，目前已投入正式生成服务器运行。本系统程序，将ticket信息以全系统唯一的 session id作为媒介，从而获取当前在线用户的全站信息(登陆状态信息及其他需要处理的用户全站信息)。

# 3. 过程说明:        
## 3.1 登陆流程:
### 3.1.1 第一次登陆某个站:
+ 用户输入用户名+密码,向用户验证中心发送登录请求
+ 当前登录站点，通过webservice请求,用户验证中心验证用户名，密码的合法性。如果验证通过，则生成ticket，用于标识当前会话的用户，并将当前登陆子站的站点标识符记录到用户中心，最后
+ 将获取的用户数据和ticket返回给子站。如果验证不通过，则返回相应的错误状态码。
+ 根据上一步的webservice请求返回的结果，当前子站对用户进行登陆处理:如状态码表示成功的话，则当前站点通过本站cookie保存 ticket，并本站记录用户的登录状态。状态码表示失败的话，则给用户相应的登录失败提示。

### 3.1.2 登陆状态下，用户转到另一子:
+ 通过本站cookie或session验证用户的登录状态:如验证通过，进入正常本站处理程序;否则户中心验证用户的登录状态(发送ticket到用户验证中心)，如验证通过，则对返回的用户信息进行本地的登录处理，否则表明用户未登录。

## 3.2 登出流程
+ 当前登出站清除用户本站的登录状态 和 本地保存的用户全站唯一的随机id
+ 通过webservice接口，清除全站记录的全站唯一的随机id。webservice接口会返回，登出其他已登录子站的javascript代码，本站输出此代码。
+ js代码访问相应站W3C标准的登出脚本

![Image text](https://raw.githubusercontent.com/evangui/sso/master/Mmosite%E7%94%A8%E6%88%B7%E5%8D%95%E7%82%B9%E7%99%BB%E9%99%86.jpg)

# 4. 代码说明:        
 
## 4.1 登陆流程:
用户从打开浏览器开始，第一个登陆的子站点，必须调用UClientSSO::loginSSO()方法。该方法返回全站唯一的随机id用于标识该用户。该随机id在UClientSSO::loginSSO()中已通过本站cookie保存，即该子站点保留了用户已登陆标识的存根于本站。
### 4.1.1 UClientSSO::loginSSO()方法如下:
```
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
*                                                                                                  -11:验证码错误
*                                          string $return['username']     : 用户名
*                                          string $return['password']     : 密码
*                                          string $return['email']        : Email
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
}//end of function 
```

### 4.1.2 用户验证中心的webservice服务程序，接收到登陆验证请求后，调用UCenter::loginUCenter()方法来处理登陆请求。
```
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
        $arr_login_res     = login_user($username, $password, $ip);
        $res_login         = $arr_login_res['status'];                //
        $ret['resultFlag'] = $res_login;

        if ($res_login < 1) {
                //登陆失败
        } else {
                //登陆成功
                $_SESSION[self::$_ucSessKey] = $arr_login_res;

                $_SESSION[self::$_ucSessKey]['salt'] =
                        self::_getUserPassSalt($_SESSION[self::$_ucSessKey]['username'], $_SESSION[self::$_ucSessKey]['password']);

                $ret['userinfo'] = $_SESSION[self::$_ucSessKey];
                $ret['sessID']   = session_id();        //生成全站的唯一session id，作为ticket全站通行

                //
                //合作中心站回调登陆接口(设置用户中心的统一session id)
                //
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

                //
                // 合作站点的全域cookie设置脚本地址
                //
                foreach ((array)self::$_coSitesInfo as $_siteInfo) {
                        $_code = self::authcode($_rawStr, 'ENCODE', $_siteInfo['key']);
                        $_src = $_siteInfo['url'] . '?code=' . $_code . '&time=' . $_timestamp;
                        $ret['script'] .= urlencode('');
                }

                //
                // 记住已登陆战
                //
                self::registerLoggedSite($siteFlag, $ret['sessID']);

                unset($ret['userinfo']['salt']);
        }

        return $ret;
}
```

## 4.2 本站登陆成功后，进行本地化的用户登陆处理，其后验证用户是否登陆只在本地验证。(本地存取登陆用户状态的信息，请设置为关闭浏览器就退出)

## 4.3 当检测用户登陆状态时，请先调用本地的验证处理，若本地验证不通过，再调用UClientSSO::checkUserLogin()方法到用户中心检测用户的登陆状态。
### 4.3.1 UClientSSO::checkUserLogin()方法如下:
```
/**
* 用户单点登陆验证函数
*
* @return array   - integer $return['status'] 大于 0:返回用户 ID，表示用户登录成功
*                                                                                                    0:用户没有在全站登陆
*                                                -1:用户不存在，或者被删除
*                                                -2:密码错
*                                                -3:未进行过单点登陆处理
*                                                                                                  -11:验证码错误
*                                          string $return['username']     : 用户名
*                                          string $return['password']     : 密码
*                                          string $return['email']        : Email
*/
public static function checkUserLogin(){
        self::_init();
        $ret = array();
        $_sessId = self::_getLocalSid();
        if (empty($_sessId)) {
                //永久记住账号处理
                if(isset($_COOKIE[_UC_USER_COOKIE_NAME]) && !empty($_COOKIE[_UC_USER_COOKIE_NAME])) {
                        //
                        // 根据cookie里的用户名和密码判断用户是否已经登陆。
                        //
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
```

### 4.3.2 用户验证中心的webservice服务程序，接收到检验登陆的请求后，调用UCenter::getOnlineUser()方法来处理登陆请求:
```
/**
* 根据sid，获取当前登陆的用户信息
*
* @param string $sessId        - 全站唯一session id，用做ticket
* @return array
*/
/**
* 根据sid，获取当前登陆的用户信息
*
* @param string $sessId        - 全站唯一session id，用做ticket
* @return array
*/
static public function getOnlineUser($sessId, $siteFlag) {
        self::_init();
        session_id(trim($sessId));
        session_start();

        $ret = array();
        $_userinfo = $_SESSION[self::$_ucSessKey];

        if (isset($_userinfo['username']) && isset($_userinfo['password']) &&
                self::_getUserPassSalt($_userinfo['username'], $_userinfo['password'])) {
                $ret['resultFlag'] = "1";
                $ret['userinfo'] = $_userinfo;

                self::registerLoggedSite($siteFlag, $sessId);                //记住已登陆战
                unset($ret['userinfo']['salt']);
        } else {
                $ret['resultFlag'] = "0";
        }

        return ($ret);
} 
```

## 4.4 单点登出时代码
调用UClientSSO::logoutSSO()方法。调用成功后，如需其他已登陆站立即登出，请调用 UClientSSO::getSynloginScript()方法获取W3C标准的script，在页面输出。
### 4.4.1 UClientSSO::logoutSSO()方法如下:          
```
/**
* 全站单点登出
*  - 通过webservice请求注销掉用户的全站唯一标识
*
* @return integer    1: 成功
*                                     -11:验证码错误
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
                self::_removeLocalSid();                //移除本站记录的sid存根
                self::$_synlogoutScript = urldecode($aRet['script']);
                $ret = 1;
        } else {
                $ret = $aRet['resultFlag'];
        }
        return intval($ret);
}
```

### 4.4.2 用户验证中心的webservice服务程序，接收到全站登出请求后，调用UCenter::loginUCenter()方法来处理登陆请求
```
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
```

# 5. 代码部署:          
## 5.1 用户验证中心设置                 
+ 用户验证中心向分站提供的webservice服务接口文件，即UserSvc.php部署在hostname/webapps/port/ UserSvc.php中。查看wsdl内容，请访问https://hostname/port/ UserSvc.php?wsdl
+ 用户中心用户单点服务类文件为UCenterSSO.class.php，文件路径为在hostname/webapps/include /UCenterSSO.class.php。该文件为用户单点登陆处理 的服务端类，被hostname/webapps/port/ UserSvc.php调用。用于获取用户的登陆信息，是否单点登陆的状态信息，单点登出处理等。
+ 用户验证中心通过W3C标准，利用cookie方式记录，删除全站统一的用户唯一随机id 的脚本文件为hostname/webapps/port/cookie_mgr.php.
       
## 5.2 子站点设置                 
+ 各子站点请将，UClientSSO.class.php部署在用户中心服务客户端目录下。部署好后，请修改最后一行的UClientSSO::setSite('1'); 参数值为用户验证中心统一分配给各站的标识id.
+ 在部署的用户中心服务客户端包下的api目录下下，请将logout_sso.php脚本转移到此处，并编写进行本站登出的处理脚本。
+ 在子站点验证用户登陆状态的代码部分，额外增加到用户中心的单点登陆验证的处理。    
  即在首先通过本站验证用户的登陆状态，如果未通过验证，则去用户中心验证。验证操作要调用UClientSSO::checkUserLogin();接口，接口含义请查看代码注释。
+ 在分站的登出处理脚本中，通过UClientSSO::getSynlogoutScript();获取script串输出即可。

# 6. 扩展功能:       
## 6.1 记录跟踪所有在线用户
  因为所有用户的登录都要经过用户验证中心，所有用户的ticket都在验证中心生成，可以将用户和该ticket(session id)在内存表中建立一个映射表。得到所有在线用户的记录表。
  后期如有必要跟踪用户状态来实现其他功能，只要跟踪这个映射表就可以了。其他功能可以为: 获取在线用户列表，判断用户在线状态，获取在线用户人数等。

## 6.2 特殊统计处理
  因为整个系统登录登出要经过用户验证中心，所以可以针对用户的特殊统计进行处理。如用户每天的登录次数，登陆时间，登陆状态失效时间，各时段的在线用户人数走势等。

# 7. 其他事项:       
## 7.1. 本站登陆状态有效时间问题:                  
  全站要求用户登陆状态在关闭浏览器时就失效。要求各分站对session或cookie的处理方式按照如下进行:
### 7.1.1 Session方式记录用户登陆状态的站点
  请在站点公用脚本开始处，添加以下代码
```
session_write_close();
ini_set('session.auto_start', 0);                    //关闭session自动启动
ini_set('session.cookie_lifetime', 0);            //设置session在浏览器关闭时失效
ini_set('session.gc_maxlifetime', 3600);  //session在浏览器未关闭时的持续存活时间        
```
### 7.1.2 cookie方式记录用户登陆状态的站点
  请在设置用户登陆状态的cookie时，设置cookie有效时间为null.

## 7.2 其他:   
