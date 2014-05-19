<?php
/**
* @name      Default.php
* @describe  默认入口类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Default extends C_Controller
{
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
        //self::$param['ct'] = htmlspecialchars(trim($request->ct));
    }

    /**
    * 首页
    * 
    */
    function doDefault() {
        $this->setTemplate('default');
    }


}

