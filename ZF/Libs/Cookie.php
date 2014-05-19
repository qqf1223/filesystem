<?php
/**
* @name      ZF_Libs_Cookie.php
* @describe  cookie 
* @author    qinqf
* @todo       
* @changelog  
*/

class ZF_Libs_Cookie
{

    /********* public ***************/
	public static $cookie_varpre = 'he';
    public static $cookie_expire = 3600;
    public static $cookie_domain = '';
    public static $cookie_path   = '';

	/********* __construct ***************/
	public function __construct() {
	   //null
	   self::init();
	}

	public function __destruct() {
	   //null
	}

	/********* init ******/
	public static function init() {
        global $_cookiearr; 
		self::$cookie_varpre  = $_cookiearr['cookie_varpre'];
        self::$cookie_expire  = $_cookiearr['cookie_expire'];
        self::$cookie_domain  = $_cookiearr['cookie_domain'];
        self::$cookie_path    = $_cookiearr['cookie_path'];
	}

    // get cookie value
    public static function get($name) {
        self::init(); 
        $prefix  = self::$cookie_varpre; 
        $value   = @$_COOKIE[$prefix.$name]; 
        //$value   =  unserialize(base64_decode($value)); var_dump($value);
        return $value;
    }

    // set cookie value
    public static function set($name,$value,$expire='',$path='',$domain='') {
        self::init(); 
        if($expire=='') {
            $expire =   self::$cookie_expire;
        }
        if(empty($path)) {
            $path   = self::$cookie_path;
        }
        if(empty($domain)) {
            $domain =   self::$cookie_domain;
        }
        $expire  =  !empty($expire) ? time() + $expire : 0;
        //$value   =  base64_encode(serialize($value));
        
        $prefix  = self::$cookie_varpre;

        setcookie($prefix.$name,$value,$expire,$path,$domain);
        $_COOKIE[$prefix.$name]  = $value;
    }

    // del cookie value
    public static function del($name) {
        self::init();
        self::set($name,'',-3600);
        $prefix  = self::$cookie_varpre;
        unset($_COOKIE[$prefix.$name]);
    }

    // clear cookie value
    public static function clear() {
        unset($_COOKIE);
    }

}