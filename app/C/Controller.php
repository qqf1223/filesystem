<?php
/**
* @name      Controller.php
* @describe  控制类基类
* @author    qinqf
* @todo       
* @changelog  
*/

abstract class C_Controller extends ZF_Core_Controller 
{

    public $login_user_info;
    private $s_uid;
    private $s_uname;
    function __construct() 
    {
        parent::__construct();
        $this->c = $c = ZF_Core_Request::getController();
        global $js_path, $images_path, $css_path, $base_path, $ext_path;
        $this->js_path = $js_path;
        $this->images_path = $images_path;
        $this->css_path = $css_path;
        $this->ext_path = $ext_path;
        $this->base_path = $base_path;

        #用户登录状态处理
        $login_user_info = array();
        if($c!='Login') {        
            $user_auth = ZF_Libs_Cookie::get('auth'); 
            if($c=='Upload' && empty($user_auth)){
                 $user_auth = $_GET['auth'];  
            }
            if(!empty($user_auth)){
                $login_info = !empty($user_auth) ? explode("\t", ZF_Libs_String::authcode($user_auth, 'DECODE')): array();
                list($login_user_info['u_id'], $login_user_info['u_email'], $login_user_info['u_name'], $login_user_info['u_parent'], $login_user_info['u_grade'], $login_user_info['u_targetgroup']) = $login_info;
                $this->login_user_info = $login_user_info;
                
                //获取用户信息
                if(!empty($this->login_user_info)){
                    #将用户信息转变成带下标的，在页面上使用
                    $login_user['u_id'] = $login_user_info['u_id'];
                    $login_user['u_email'] = $login_user_info['u_email'];
                    $login_user['u_name'] = $login_user_info['u_name'];
                    $login_user['u_group'] = $login_user_info['u_parent'];  
                    $login_user['u_grade'] = $login_user_info['u_grade'];
                    $login_user['u_gradename'] = M_Usergroup::getuserrole($login_user_info['u_grade']);
                    $login_user['u_targetgroup'] = $login_user_info['u_targetgroup'];
                    $this->login_user = json_encode($login_user);

                    $power = M_Usergroup::getpower($login_user_info['u_grade']);
                    $this->power = json_encode($power);
                    
                    #判断当前用户的共享文件夹创建情况
                    $ishaveshare = M_Sharedocument::ishaveshare($login_user_info['u_parent']);
                    $this->ishaveshare = $ishaveshare;
                    
                }else{
                    header("Location:  {$base_path}index.php?c=login");
                }
                //$this->s_uid = $s_uid;
                //$this->s_uname = $s_uname;
            }else{ 
                header("Location:  {$base_path}index.php?c=login");
            }
        }
    }


    public function returnmsg($data){
        return json_encode($data);
    }

}
