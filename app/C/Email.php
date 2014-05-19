<?php
/**
* @name      Email.php
* @describe  email操作类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Email extends C_Controller
{
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
    }

    /**
    * 邮件移动操作
    * 
    */
    function doDefault() {
        $rs = $this->returnmsg(M_Email::moveemail($_REQUEST, $this->login_user_info));
        exit($rs);                                 
    }
    
    /**
    * 邮件移动操作
    * 
    */
    function doTosharedocument() {
        $rs = $this->returnmsg(M_Email::moveemailtoshare($_REQUEST, $this->login_user_info));
        exit($rs);                                 
    }


}

