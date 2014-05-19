<?php
/**
* @name      Usergroup.php
* @describe  用户及工作组类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Usergroup extends C_Controller
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
    *  
    * 
    */
    function doDefault() {

        $this->setTemplate('workgroup');

    }

    /**
    * 添加工作组
    * 
    */
    function doAddworkgroup(){
        if(!empty($_POST)){
            $rs = $this->returnmsg(M_Usergroup::addworkgroup($_POST, $this->login_user_info));
            exit($rs);
        }else{
            $this->setTemplate('workgroup_add');
        }
    }

    /**
    * 编辑工作组
    * 
    */
    function doEditgroup(){
        $rs = $this->returnmsg(M_Usergroup::editworkgroup($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 工作组列表
    * 
    */
    function doListworkgroup(){
        $rs = $this->returnmsg(M_Usergroup::listworkgroup($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 添加工作组成员
    * 
    */
    function doAddgroupuser(){
        if(!empty($_POST)){
            $rs = $this->returnmsg(M_Usergroup::addgroupuser($_POST, $this->login_user_info));
            exit($rs);
        }else{
            $this->setTemplate('groupuser_add');
        }
    }

    /**
    * 根据分组ID列出组成员
    *  
    */
    function doListgroupuser(){
        $rs = $this->returnmsg(M_Usergroup::listgroupuser($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 根据分组ID列出组成员
    *  
    */
    function doListgroupusergrid(){
        $rs = $this->returnmsg(M_Usergroup::listGroupuserGrid($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 删除工作组组员
    * 
    */
    function doDelgroupuser(){
        $rs = $this->returnmsg(M_Usergroup::delgroupuser($_POST, $this->login_user_info));
        exit($rs);
    }


    /**
    * 编辑用户信息
    * 
    */
    function doEditgroupuser(){   
        $rs = $this->returnmsg(M_Usergroup::editgroupuser($_POST, $this->login_user_info));
        exit($rs);
    }

    
    function doAlterpwd(){
        $rs = $this->returnmsg(M_Usergroup::alterpwd($_POST, $this->login_user_info));
        exit($rs);
    }

    function doListusertree(){
        $rs = $this->returnmsg(M_Document::listusertree($_GET, $this->login_user_info));
        exit($rs);
    }
    
    
    function doalter(){
        $rs = $this->returnmsg(M_Usergroup::alterUsertargetDoc());
        exit($rs);
    }

}

