<?php
/**
* @name      Login.php
* @describe  登录类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Login extends C_Controller
{
    static $param = array();
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
    }

    /**
    * 登录
    * 
    */
    function doDefault() {

        $this->setTemplate('login');

    }

    /**
    * 验证用户
    * 
    */
    function doValidform(){
        $rs = $this->returnmsg(M_Login::verify($_POST));
        exit($rs);
    }

    /**
    * 退出系统
    * 
    */
    function doLoginout(){
        ZF_Libs_Cookie::set('auth', '');
        ZF_Libs_Cookie::del('auth');
        header("Location:  {$base_path}index.php?c=login");
    }

    /**
    * 验证用户角色
    * 
    */
    function doConfirminfo(){
        $rs = $this->returnmsg(M_Login::confirminfo($_POST));
        exit($rs);
    }


}

