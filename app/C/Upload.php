<?php
/**
* @name      Upload.php
* @describe  文件上传类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Upload extends C_Controller
{
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
    }

    /**
    * 文件上传操作
    * 
    */
    function doDefault() {
        $rs = M_Upload::upload($this->login_user_info);
        return $rs;                                 
    }


    /**
    * 验证文件是否存在
    * 
    */
    function doCheck(){
        $rs = $this->returnmsg(M_Document::checkSamedoc($_POST['fs_name'], $_POST['fs_parent'],0));
        exit($rs);
    }


    function doGetfilecode(){
        $rs = $this->returnmsg(M_Document::getMaxfilecode($_POST));
        exit($rs);
    }


    /**
    * 共享文件上传操作
    * 
    */
    function doUploadsharedoc() {
        $rs = M_Uploadshare::upload($this->login_user_info);
        return $rs;                                 
    }


    /**
    * 验证共享文件是否存在
    * 
    */
    function doSharecheck(){
        $rs = $this->returnmsg(M_Sharedocument::checkSamedoc($_POST['fs_name'], $_POST['fs_parent'],0));
        exit($rs);
    }

    /**
    * 上传共享文件文件自动编号
    * 
    */
    function doGetsharefilecode(){
        $rs = $this->returnmsg(M_Sharedocument::getMaxfilecode($_POST));
        exit($rs);
    }


}

