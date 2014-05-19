<?php
    /**
    * @name      M_Login.php
    * @describe  登录Model类
    * @author    qinqf
    * @version   1.0 
    * @todo 验证邮箱正确性      
    * @changelog  
    */  
    class M_Login extends M_Model{

        static $db;
        static $table_name = '';
        /*** 初始化操作 */
        public static function init(){
            self::$db = parent::init();     
        }

        /**
        * 登录验证方法
        * 
        */
        public static function verify($data) {
            self::init();
            $useremail = !empty($data['useremail']) ? $data['useremail'] : '';
            $userpwd = isset($data['userpwd']) ? $data['userpwd'] : '';
            $grade = isset($data['grade']) ? intval($data['grade']) : '';
            $judgerole = false;
            $authkey='auth';
            if(!$useremail){
                $rs['success'] = false;
                $rs['msg'] = '邮箱必填！';
                return $rs;
            }
            if(!$userpwd){
                $rs['success'] = false;
                $rs['msg'] = '密码必填！';
                return $rs;
            }                  
            $rs = array();
            $sql = "SELECT * FROM fs_user where u_email='{$useremail}' ";
            $userinfo = self::$db->get_row($sql);

            if(!empty($userinfo)){
                set_time_limit(0);
                #判断用户角色是否为多个

                $user_grade = explode(',', $userinfo['u_grade']);
                if(count($user_grade)>1){
                    $judgerole = true;
                    $authkey = 'authtmp';
                }
                /*
                jiancha@cmecfs.com
                guanli@cmecfs.com 
                admin@admin.com
                以上三个通过密码验证， 其他的通过邮箱验证
                */
                $sysUser = array('jiancha@cmecfs.com', 'guanli@cmecfs.com', 'admin@admin.com', 'chensy@mail.cmec.com','chenxh@mail.cmec.com');
                if(in_array($useremail, $sysUser)){ // 系统管理员 和 系统监察员 用系统密码登录
                    if(md5($userpwd) == $userinfo['u_pwd']){
                        $rs['success'] = true; 
                        $rs['msg'] = '登录成功';
                        $rs['info'] = $userinfo;
                        $rs['grade'] = self::getUserrole($userinfo['u_grade']);
                        $rs['judge'] = $judgerole;
                        //写入COOKIE
                        ZF_Libs_Cookie::set($authkey, ZF_Libs_String::authcode("{$userinfo['u_id']}\t{$userinfo['u_email']}\t{$userinfo['u_name']}\t{$userinfo['u_parent']}\t{$userinfo['u_grade']}\t{$userinfo['u_targetgroup']}", 'ENCODE'));
                        #记住用户登录用户名
                        ZF_Libs_Cookie::set('useremail', $userinfo['u_email'], 86400*7);
                        if(!$judgerole){
                            M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$userinfo['u_email'], 'desc'=>" 成功登入系统"));
                        }
                        return $rs;
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '密码错误';
                        M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$userinfo['u_email'], 'desc'=>"输入密码错误"));
                    }
                    return $rs;
                } else{  /*todo 开始验证邮箱是否正确**/
                    $o = new ZF_Libs_SocketPOPClient($userinfo['u_email'], $userpwd, EMAIL_SERVER, '110');
                    //$response = $o->getResponse();
                    if($o){
                        if($o->popLogin()){
                            //if(false !== strpos($response, EMAIL_RESPONSE)){
                            $rs['success'] = true; 
                            $rs['msg'] = $userinfo;
                            $rs['info'] = $userinfo;
                            $rs['grade'] = self::getUserrole($userinfo['u_grade']);
                            $rs['judge'] = $judgerole;
                            //写入COOKIE
                            ZF_Libs_Cookie::set($authkey, ZF_Libs_String::authcode("{$userinfo['u_id']}\t{$userinfo['u_email']}\t{$userinfo['u_name']}\t{$userinfo['u_parent']}\t{$userinfo['u_grade']}\t{$userinfo['u_targetgroup']}", 'ENCODE'));
                            #记住用户登录用户名
                            ZF_Libs_Cookie::set('useremail', $userinfo['u_email'], 86400*7);
                            if(!$judgerole){
                                M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$userinfo['u_email'], 'desc'=>"成功登入系统"));
                            }
                            return $rs;
                        } else {
                            $rs['success'] = false;
                            $rs['msg'] = '密码错误';
                            M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$useremail, 'desc'=>"用户尝试输入密码失败"));
                            return $rs; 
                        }
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '请确认网络连接是否正确';
                        return $rs;
                        M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$useremail, 'desc'=>"用户登录时网络连接失败"));  
                    }
                }
            } else {
                $rs['success'] = false;
                $rs['msg'] = '邮箱地址错误';
                return $rs;
                M_Log::systemlog(array('login_user_name'=>'未知用户', 'login_user_email'=>$useremail, 'desc'=>"输入错误邮箱")); 
            }
        }


        /**
        * put your comment there...
        * 
        * @param mixed $data
        */
        function confirminfo($data){
            $user_grade = intval($data['grade']);

            $user_auth = ZF_Libs_Cookie::get('authtmp'); 
            $userinfo_cookie = !empty($user_auth) ? explode("\t", ZF_Libs_String::authcode($user_auth, 'DECODE')): array();
            $rs = array();
            if(!empty($userinfo_cookie)){
                list($userinfo['u_id'],$userinfo['u_email'],$userinfo['u_name'],$userinfo['u_parent'],$userinfo['u_grade'], $userinfo['u_targetgroup']) = $userinfo_cookie;
                if(!in_array($user_grade, explode(',', $userinfo['u_grade']))){
                    $rs['success'] = false;
                    $rs['msg'] = '请选择角色';
                    return $rs;
                }else{
                    $rs['success'] = true; 
                    $rs['msg'] = '登录成功';
                    //写入COOKIE
                    ZF_Libs_Cookie::set('auth', ZF_Libs_String::authcode("{$userinfo['u_id']}\t{$userinfo['u_email']}\t{$userinfo['u_name']}\t{$userinfo['u_parent']}\t{$user_grade}\t{$userinfo['u_targetgroup']}", 'ENCODE'));
                    ZF_Libs_Cookie::del('authtmp');
                    #记住用户登录用户名
                    ZF_Libs_Cookie::set('useremail', $userinfo['u_email'], 86400*7);
                    M_Log::systemlog(array('login_user_name'=>$userinfo['u_name'], 'login_user_email'=>$userinfo['u_email'], 'desc'=>"成功登入系统")); 
                }

            }
            return $rs;

        }


        /**
        * 登录时用户角色选择
        * 
        * @param mixed $grade
        */
        public static function getUserrole($grade){
            $rs = array();
            $usergrade = explode(',', $grade);
            $role = array('0'=>'普通组员', '1'=>'组文件管理员', '2'=>'工作组领导', '3'=>'部门负责人', '4'=>'项目部负责人', '98'=>'系统监察员', '99'=>'系统管理员', '100'=>'超级管理员');
            if(!empty($usergrade)){
                foreach($usergrade as $k=>$v){
                    $rs[$k]['boxLabel'] = $role[$v];
                    $rs[$k]['name'] = 'grade';
                    $rs[$k]['inputValue'] = $v;
                }
            }
            return $rs;
        }
    }
?>
