<?php
/**
* @name      ZF.php
* @describe  zf
* @author    qinqf
* @version   1.0 
* @todo       
* @changelog  
*/

/* APP_NAME */
if(!defined('APP_NAME')) {
    exit('Access Denied');
}

/* ZF_ROOT */
define('ZF_ROOT',substr(dirname(__FILE__), 0, -3)); 

/**
* main
*/
class ZF
{

    /**
    * namespace
    */
    private static $_namespace = array();

    /**
    * app object
    */
    private static $_apps;


    /**
    * load
    */
    public static function autoload($name)
    {
        if (trim($name) == '') {
            echo "No class or interface named for loading\n<br>";
        }
        if (@class_exists($name, false)) {
            return;
        }

        $classPathArr = explode('_', $name);
        $namespace = $classPathArr[0];
        if (is_array($classPathArr)) {
            foreach ($classPathArr as $key=>$classRow) {
                $classPathArr[$key] = strtoupper($classRow);
            }
        }

        /* root */
        if ($namespace == 'ZF') {
            $file = ZF_ROOT . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $name) . '.php';
        } elseif (array_key_exists($namespace, self::$_namespace)) {
            $file = self::$_namespace[$namespace] . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $name) . '.php';
        } else{
            echo "The namespace config have problem: '{$name}'\n<br>";
        }

        if (!empty($file)){
            if (! file_exists($file)) {
                echo "The file dose not exist: '{$file}'\n<br>";
            }
            if (! is_readable($file)) {
                echo "The file can not read: '{$file}'\n<br>";
            }
        }

        @include $file;
    }

    /**
    * namespace
    *
    * @param string $path
    */
    public static function setNameSpace($path)
    {
        if (empty($path)) {
            echo "No class or interface named for loading\n<br>";
        }
        $namespace = substr(strrchr($path, DIRECTORY_SEPARATOR), 1);
        $namespacePath = substr($path, 0, strlen($path) - strlen($namespace) - 1);
        if (!isset(self::$_namespace[$namespace])) {
            self::$_namespace[$namespace] = $namespacePath;
        } else if (self::$_namespace[$namespace] != $namespacePath) {
                echo "class\n<br>";
                die();
            } else {
                echo "Class or interface does not exist in loaded file\n<br>";
        }
    }

}

/* spl_autoload_register */
if(!function_exists('spl_autoload_register')) {
    function __autoload($className)
    {
        ZF::autoload($className);
    }
} else {
    spl_autoload_register(array('ZF','autoload'));
}

ZF::setNameSpace(ZF_ROOT . DS.'ZF'.DS.'Core');   
ZF::setNameSpace(ZF_ROOT . DS.'ZF'.DS.'Libs');   /* ZF_Libs_String::setS() */


/**
* 工厂类
* 
*/
class F
{

    /**
    * 用于放单例的实例化对象
    * 类中实例化比较麻烦第次需调用实例化方法
    * 所以为了简化就使用这种方式
    */
    private static $_objects = array();

    /**
    * 单例模式生成对象
    * 
    * 全称：getSingleTon
    */
    public static function S($className, $args=array())
    {
        if (!defined ('APP_PATH')) {
            die('未定义应用根目录');
        }
        if ( $className ) {
            if (isset(self::$_objects[$className]) && self::$_objects[$className]) {
                return self::$_objects[$className];
            }else{
                if( empty($args) ){
                    $newObj = new $className();
                }elseif( 1==count($args) ){
                    $newObj    = new $className($args[0]);
                }else{
                    $argsStr  = F::arrayToStr($args);
                    $newObj    = eval("return new $className($argsStr);");
                }
                self::$_objects[$className]	= $newObj;
                return $newObj;
            }
        } else {
            trigger_error("未指定要调的类");
        }
    }

    /**
    * 正常的new操作
    *
    * @param string $className 类名
    * @param array $args 参数
    * @return object
    */
    public static function N($className, $args=array())
    {
        if (!defined ('APP_PATH')) {
            die('未定义应用根目录');
        }
        if ( $className ) {
            if( empty($args) ){
                try{
                    $obj = new $className();
                }catch(Exception $e){
                    $e->getMessage();
                }
            }elseif( 1==count($args) ){
                $obj    = new $className($args[0]);
            }else{
                $argsStr  = F::arrayToStr($args);
                $obj    = eval("return new $className($argsStr);");
            }
            return $obj;
        } else {
            trigger_error("未指定要调的类");
        }
    }

    /**
    * 数组转化为查询所需的字符串,安全性在外部处理
    *
    * @param 数组 $col
    * @return string
    */
    private static function arrayToStr($col = array()){

        $str    = "";
        if( is_array($col) ){
            foreach ($col as $item){
                $str    .= "'".$item."',";
            }

            $str		= substr($str,0,strlen($str)-1);

            return $str;
        }else{
            return $col;
        }
    }
}


/*
* base
*/
class Base
{  
    /**
    * 入口函数
    */
    public static function run (){   

        //获取控制器及动作
        $ctlName   = ZF_Core_Request::getController();
        $ctlAction = 'do' . ZF_Core_Request::getAction();

        //页面请求内容
        $controller = F::N('C_'.$ctlName);
        
        //页面请求验证
        if(method_exists($controller, 'prepare')){
            $controller->prepare((object)$_REQUEST);
        }else{
            exit('prepare function is not found, please add function prepare in '. $controller);
        }
        if (method_exists($controller, $ctlAction)) {
            //如果是表单提交，映射到相应的处理方法
            if ($controller->exe) {
                $controller->submitMap();
            }

            //控制器action
            $controller->$ctlAction(); 
            $controller->display();
        } else {
            die('参数错误');
        }
    }
}
