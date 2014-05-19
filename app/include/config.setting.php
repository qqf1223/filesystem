<?php
/**
* @name      config.setting.php
* @describe  setting
* @author    qinqf
* @version   1.0 
* @copyright qinqf 
* @todo       
* @changelog  
*/

/* include judge */
error_reporting(7);
date_default_timezone_set('Asia/Shanghai');
define('APP_NAME','app');
define('DS', DIRECTORY_SEPARATOR);

/* define constant */
$pathlen = strlen(APP_NAME)+8;
define('ROOT_PATH',   substr(dirname(__FILE__), 0, -$pathlen));
define('APP_PATH',   ROOT_PATH.APP_NAME);
define('CACHE_PATH',   APP_PATH.DS.'cache');

#文件访问服务器地址
define('DOC_SERVER', APP_PATH);
define('PROJECT_DOC_PATH', DOC_SERVER.DS. 'project'); //项目文档存放目录
//define('PROJECT_DOC_URL', 'http://192.168.110.136/project'); //项目文档下载使用的URL
define('FILE_BACKUP_PATH', APP_PATH.DS.'bak'); //文件备份路径
define('LOG_PATH', APP_PATH.DS.'log');

/* base file */
$base_path = "http://192.168.199.128/"; 
#$base_path = "http://27.0.0.1/"; 
$tempurl = $base_path == '/' ? '' : $base_path;
$css_path = $tempurl."css/";
$images_path = $tempurl."image/";
$js_path = $tempurl."js/";
$ext_path = $tempurl."js/ext/";

/* cookie */
$_cookiearr = array(
    "cookie_varpre" => "s_",
    "cookie_expire" => 0,
    "cookie_domain" => "", // .xx.com
    "cookie_path" => "/",
);
/* get cookie */
//@extract($_COOKIE);

#APP
define('EMAIL_SERVER', 'pop.mail.cntv.cn');
define('EMAIL_RESPONSE', '+OK');

#
define('AUTH_KEY', 'zhonguojixie!@#$%^');
$login_user = array();

