<?php
/**
* @name      Request.php
* @describe  request
* @author    qinqf
* @version   1.0 
* @copyright qinqf
* @todo       
* @changelog  
*/

class ZF_Core_Request
{  

    /**
     * 是否是Ssl
     */
    public function isSsl()
    {
        return $_SERVER['HTTPS'] == 'on' || $_SERVER['SERVER_PORT'] == 443;
    }
    
    /**
     * 是否是GET请求
     */
    public function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }
    
    
    /**
     * 是否是POST请求
     */
    public function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }
    
    
    /**
     * 获取控制器名称
     */
    public static function getController()
    {
        $conName = ucfirst(strip_tags(@$_REQUEST['c']));
        return $conName ? $conName : 'Default';
    }
    
    /**
     * 获取动作
     */
    public static function getAction()
    {
        return isset($_REQUEST['a']) ?  ucfirst(strip_tags(@$_REQUEST['a'])) : 'Default';
    }
    
    /**
     * 获取动作
     */
    public static function getExecName()
    {
        return isset($_REQUEST['e']) ?  ucfirst(strip_tags(@$_REQUEST['e'])) : '';
    }
    
    /**
     * 魔术方法
     */
    function __call($name, $args)
    {
        echo "对不起，你还未实现{$name}()方法";
    }
}
