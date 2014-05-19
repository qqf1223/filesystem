<?php
/**
* @name      Log.php
* @describe  日志类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Log extends C_Controller
{
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
    }

    /**
    *  
    * 
    */
    function doDefault() {

        //$this->setTemplate('workgroup');
    }

    function doSyslogdata(){
        $rs = $this->returnmsg(M_Log::showsyslog($_GET, $this->login_user_info));
        exit($rs);
    }    
    
    function doDoclogdata(){
        $rs = $this->returnmsg(M_Log::showdoclog($_GET, $this->login_user_info));
        exit($rs);
    }
    
    function doShowsyslog(){
        $this->setTemplate('syslog');
    }
}

