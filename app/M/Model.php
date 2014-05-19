<?php
/**
* @name      Model.php
* @describe  模型类基类
* @author    qinqf
* @todo       
* @changelog  
*/

abstract class M_Model 
{
    public static $newdb=null;

    public static function init() 
    {
        /*
        * mysql db
        *
        */
        if(!self::$newdb){
            include APP_PATH . DS . "include" . DS . "connect.inc.php";

            self::$newdb=new DB_Local_Read();

            self::$newdb->query("set names " . self::$newdb->CharSet); 
        }
        return self::$newdb;       

    }

    /**
    * 返回格式处理
    * 
    * @param mixed $data
    * @return string
    */
    public static function returnmsg($data){
        return json_encode($data);
    }

    /**
    * 生成HASHNAME
    * 
    * @param mixed $array
    * @return string
    */
    public static function hashname($array){
        if(!is_array($array)){
            $rs = substr(md5($array.time()), 9, 16);
        }else{
            $rs = substr(md5(implode('', $array).time()), 9, 16);
        }
        return $rs;
    }


}