<?php
    /**
    * @name      M_Usergroup.php
    * @describe   用户及工作组Model类
    * @author    qinqf
    * @version   1.0 
    * @todo       
    * @changelog  
    */  
    class M_Usergroup extends M_Model{

        static $db;
        static $usergroup_table='fs_user';
        static $document_table = 'fs_tree';
        static $user_share_document = 'fs_user_sharedoc';
        /*** 初始化操作 */
        public static function init(){
            self::$db = parent::init();     
        }

        /**
        * 工作组列表
        * 
        * @param mixed $data
        * @return object
        */
        public static function listworkgroup($data, $login_user_info){
            $uid= $login_user_info['u_id']; 
            $getall = isset($data['getall']) ? intval($data['getall']) : 0;
            $needalltag = isset($data['needalltag']) ? 1 : 0;
            self::init();
            if($getall){
                $sql = "SELECT * from ".self::$usergroup_table." WHERE u_isgroup='1'";
            }else{
                if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98|| $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){
                    $sql = "SELECT * from ".self::$usergroup_table." WHERE u_isgroup='1'";
                }elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){ //组管理员
                    $sql = "SELECT * from ".self::$usergroup_table." WHERE u_id='{$login_user_info['u_targetgroup']}' and u_isgroup='1'";
                }else{
                    $sql = "SELECT * from ".self::$usergroup_table." WHERE u_id='{$login_user_info['u_parent']}' and u_isgroup='1'";
                }
            }//echo $sql;
            $res = self::$db->get_results($sql);
            if($res){
                if(!empty($res)){
                    foreach($res as &$v){
                        $v['text'] = $v['u_name'];
                        $v['id'] = $v['u_id']; 
                        $v['leaf'] = true;
                        if($v['u_isgroup'] || $v['u_id']==1){
                            $v['leaf'] = false;
                        }
                        if(isset($data['type']) && $data['type']=='checkbox'){
                            $v['checked'] = false;
                        }
                        //$v['leaf'] = $v['u_isgroup']? false : ($v['u_id']==1?false:true);
                    }
                } else {
                    $res = array();
                }
            }else {
                $res = array();
            }
            if($needalltag){
                $r = array('u_id'=>0, 'u_parent'=>0, 'u_name'=>'请选择工作组', 'u_email'=>'', 'u_isgroup'=>'', 'u_grade'=>'', 'u_pwd'=>'','u_targetgroup' =>0, 'text'=>'请选择工作组','id'=>0, 'leaf'=>false);
                array_unshift($res, $r);
            }
            return $res;
        }

        /**
        * 添加工作组方法
        * 
        */
        public static function addworkgroup($data, $login_user_info) {
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'addworkgroup', '');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $workgroupname = strip_tags(trim($data['workgroupname']));
            if(!$workgroupname){
                $rs['success'] = false;
                $rs['msg'] = '工作组名称不能为空';
                return $rs;
            }

            self::init();
            $u_name = mysql_real_escape_string($workgroupname);
            #判断工作组名称是否已经存在
            $sql = "SELECT * FROM ".self::$usergroup_table." WHERE u_name='{$u_name}' and u_isgroup=1";
            $hasgroup = self::$db->get_row($sql);
            if(false !== $hasgroup){
                $rs['success'] = false;
                $rs['msg'] = '工作组已存在！';
                return $rs;
            }

            $sql = "INSERT INTO ".self::$usergroup_table." SET u_parent=1, u_name='{$u_name}', u_isgroup=1";
            $res = self::$db->query($sql);
            if(!empty($res)){
                $rs['msg'] = '添加工作组'.$workgroupname. '成功';
                $rs['success'] = true;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加工作组'.$workgroupname. '成功'));
            } else {
                $rs['msg'] = '操作失败！';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加工作组'.$workgroupname. '失败'));
            }
            return $rs;
        }

        /**
        * 根据工作组ID修改工作组信息
        * 
        * @param int $workgroup_id  工作组ID
        */
        public static function editworkgroup($data, $login_user_info){ 
            self::init();
            $workgroup_id = intval($data['workgroup_id']); 
            $workgroup_oldname = strip_tags(trim($data['u_oldname']));
            $workgroup_name = addslashes(strip_tags(trim($data['workgroup_name'])));
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editworkgroup', $workgroup_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $hasworkgroup = self::checkSameGroupuser($workgroup_name,  $workgroup_id);
            if(!$hasworkgroup['success']){
                $rs['success'] = false;
                $rs['msg'] = '工作组已存在！';
                return $rs; 
            }

            $sql = "UPDATE ".self::$usergroup_table." SET u_name='{$workgroup_name}' WHERE u_id='{$workgroup_id}'";
            $res = self::$db->query($sql);
            if(!empty($res)){
                $rs['msg'] = '操作成功';
                $rs['success'] = true;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'将工作组"'.$workgroup_oldname.'"修改为"'.$workgroup_name. '" 操作成功'));
            } else {
                $rs['msg'] = '操作失败';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'将工作组"'.$workgroup_oldname.'"修改为"'.$workgroup_name. '" 操作失败'));
            }
            return $rs;
        }

        /**
        * 根据工作组ID获取工作组下的成员
        * 
        * @param int $workgroup_id
        */
        public static function listgroupuser($data, $login_user_info){
            self::init();
            $workgroup_id = isset($data['groupid']) ? intval($data['groupid']) : 1;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'showgroupuser', $workgroup_id); //var_dump($verify);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(!$workgroup_id){
                //$rs['success'] = false;
                //$rs['msg'] = '工作组ID不正确';
                $rs = array();
                return $rs;
            }
            #查询目录共享给的用户
            $fs_share_uid = array();
            if(isset($data['type']) && $data['type']=='checkbox' && $fs_id){
                $sql = "select u_id from " . self::$user_share_document. " where fs_id='{$fs_id}'";
                $fs_share_uid = self::$db->get_col($sql);
                $fs_share_uid = $fs_share_uid ? $fs_share_uid : array();
            }
            #查询分组下的用户信息
            $sql = "SELECT * FROM fs_user WHERE u_parent='{$workgroup_id}' and u_isgroup=0";
            $res = self::$db->get_results($sql);
            if(false !== $res){
                foreach($res as $k=>$val){
                    $res[$k]['text'] = $res[$k]['u_name'];
                    $res[$k]['id'] = $res[$k]['u_id'];
                    $res[$k]['leaf'] = true;
                    if(isset($data['type']) && $data['type']=='checkbox' && $res[$k]['u_id']!=$login_user_info['u_id']){
                        if(!in_array($res[$k]['u_id'], $fs_share_uid)){
                            $res[$k]['checked'] = false;
                        }else{
                            $res[$k]['checked'] = true;
                        }
                    }
                }
                $rs=$res;
            } else{
                $rs = array();
            }
            return $rs;    
        }

        /**
        * 根据工作组ID获取分页显示工作组下的成员
        * 
        * @param int $data
        */
        public static function listGroupuserGrid($data, $login_user_info){
            self::init();
            $workgroup_id = !empty($data['workgroup_id']) ? strip_tags(trim($data['workgroup_id'])) : '';
            $pagesize = !empty($data['limit']) ? intval($data['limit']) : 20;
            $start = !empty($data['start']) ? intval($data['start']) : 0;
            $page = !empty($data['page'])?intval($data['page']):1;
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'showgroupuser', $workgroup_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $u_parent_where = !empty($workgroup_id) ? " and u_parent='{$workgroup_id}' ": "";
            #记录系统操作日志
            #M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'查看工作组 '.$workgroup_id.' 下的成员'));
            $limit = " limit " . $start . ",".$pagesize;  
            $sql = "SELECT COUNT(*) FROM " . self::$usergroup_table . " WHERE 1 ".$u_parent_where." and u_isgroup=0";
            $count_arr = self::$db->get_col($sql);

            $sql = "SELECT * FROM fs_user WHERE 1 ".$u_parent_where." and u_isgroup=0 " . $limit;
            $res = self::$db->get_results($sql);
            if($res){
                foreach($res as $k=>$val){
                    $res[$k]['text'] = $res[$k]['u_name'];
                    $res[$k]['id'] = $res[$k]['u_id'];
                    $res[$k]['leaf'] = true;
                }
                $rs=$res;
            } else{
                $rs = array();
            }
            $return_rs['rows'] = $rs; 
            $return_rs['total'] = $count_arr[0];
            return  $return_rs;
        }

        /**
        * 添加工作组成员
        * 
        * @param mixed $data
        */
        public static function addgroupuser($data, $login_user_info){
            self::init();
            $workgroup_id = intval($data['workgroup_id']);
            $workgroup_name = addslashes($data['workgroup_name']); //记录日志使用
            $grade = $data['grade'];
            $username = addslashes(strip_tags(trim($data['username'])));
            $email = strip_tags(trim($data['email']));
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'addgroupuser', $workgroup_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }

            if(!$workgroup_id){
                $rs['success'] = false;
                $rs['msg'] = '请选择工作组！';
                return $rs;
            }
            if(!$username){
                $rs['success'] = false;
                $rs['msg'] = '请输入姓名！';
                return $rs;
            }
            $email_regex = '/^(\w+)([\-+.][\w]+)*@(\w[\-\w]*\.){1,5}([A-Za-z]){2,6}$/';
            if(!empty($email)){
                if(!preg_match($email_regex, $email)){
                    $rs['success'] = false;
                    $rs['msg'] = '请输入正确的邮箱！';
                    return $rs;
                }
            }else{
                $rs['success'] = false;
                $rs['msg'] = '请输入邮箱！';
                return $rs;
            }
            if(empty($grade)){
                $rs['success'] =false ;
                $rs['msg'] = '请给用户添加权限！';
                return $rs;
            }
            $grade = implode(',', $grade);
            #查看组中组员是否已经存在
            $sql = "SELECT * FROM ".self::$usergroup_table." WHERE u_email='{$email}'";
            $hasuser = self::$db->get_row($sql);
            if(false !== $hasuser){
                $rs['success'] = false;
                $rs['msg'] = '该用户已经存在！';
                return $rs;
            }
            /* 初始系统管理员密码 */
            $sysmanager_init = '';
            if(in_array('99', $data['grade']) || in_array('98', $data['grade'])){
                $uwd = md5('111111');
                $sysmanager_init = ", u_pwd='$uwd' "; 
            }
            //开始执行插入数据库操作
            $sql = "INSERT INTO ".self::$usergroup_table." SET u_parent='{$workgroup_id}', u_name='{$username}', u_email='{$email}', u_isgroup=0, u_grade='{$grade}'".$sysmanager_init;
            $res = self::$db->query($sql);
            if(!empty($res)){
                $rs['msg'] = '添加成功';
                $rs['success'] = true;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在工作组 '.$workgroup_name.' 中添加成员 '.$username. ' 操作成功'));
            } else {
                $rs['msg'] = '添加失败';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在工作组 '.$workgroup_name.' 中添加成员 '.$username. ' 操作失败'));
            }
            return $rs;
        }

        public static function getuserinfo($uid){
            self::init();
            $uid = intval($uid);
            if(!$uid){
                return array();
            }
            //判断用户级别 
            $sql = "select * from ".self::$usergroup_table." where u_id='{$uid}' and u_isgroup=0";
            $userinfo = self::$db->get_row($sql);
            if(false!==$userinfo){
                return $userinfo; 
            } else {
                return array(); 
            }
        }

        /**
        * 删除组用户
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function delgroupuser($data, $login_user_info){
            $uid = intval($data['uid']);
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'delgroupuser', $uid);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(!$uid){
                $rs['success'] = false;
                $rs['msg'] = '请选择要删除的员工！';
                return $rs; 
            }
            self::init();
            $sql = "SELECT * FROM ".self::$usergroup_table." WHERE u_id='{$uid}' AND u_isgroup=0";
            $userinfo = self::$db->get_row($sql);
            $sql = "DELETE FROM ".self::$usergroup_table." where u_id='{$uid}' and u_isgroup=0";
            $res = self::$db->query($sql);
            if($res){
                $rs['success'] = true;
                $rs['msg'] = '员工 '.$userinfo['u_name'].' 删除成功！';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除员工 '.$userinfo['u_name'].' 操作成功！'));
            }else{
                $rs['success'] = false;
                $rs['msg'] = '员工 '.$userinfo['u_name'].' 删除失败！';
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除员工 '.$userinfo['u_name'].' 操作失败！'));
            }
            return $rs;  
        }

        /**
        * 编辑用户信息
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function editgroupuser($data, $login_user_info){
            self::init();
            $workgroup_id = isset($data['workgroup_id']) ? intval($data['workgroup_id']) : '';
            $user_id = isset($data['user_id']) ? intval($data['user_id']) : '';
            $username = isset($data['username']) ? addslashes(strip_tags(trim($data['username']))) : '';
            $email = isset($data['email']) ? addslashes(strip_tags(trim($data['email']))) : '';
            $targetgroup_id = isset($data['targetgroup_id']) ? intval($data['targetgroup_id']) : '';
            $grade_arr = $data['grade'];

            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editgroupuser', $user_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(!$workgroup_id){
                $rs['msg'] = '请选择工作组';
                $rs['success'] = false;
                return $rs; 
            }
            if(!$username){
                $rs['msg'] = '请填写用户姓名';
                $rs['success'] = false;
                return $rs; 
            } 
            if(!empty($email)){
                $email_regex = '/^(\w+)([\-+.][\w]+)*@(\w[\-\w]*\.){1,5}([A-Za-z]){2,6}$/';  
                if(!preg_match($email_regex, $email)){
                    $rs['success'] = false;
                    $rs['msg'] = '请输入正确的邮箱';
                    return $rs;
                }
            }else{
                $rs['success'] = false;
                $rs['msg'] = '请输入邮箱';
                return $rs;
            }
            if(empty($grade_arr)){
                $rs['success'] = false;
                $rs['msg'] = '请选择用户权限';
                return $rs; 
            }

            $grade = implode(',', $grade_arr);
            if($targetgroup_id){
                $sql = "update ".self::$usergroup_table." set u_name='{$username}', u_email='{$email}', u_parent='{$workgroup_id}', u_grade='{$grade}', u_targetgroup='{$targetgroup_id}' where u_id='{$user_id}'";
            }else{
                $sql = "update ".self::$usergroup_table." set u_name='{$username}', u_email='{$email}', u_parent='{$workgroup_id}', u_grade='{$grade}' where u_id='{$user_id}'"; 
            }
            if(self::$db->query($sql)){
                $doc_sql = "update ".self::$document_table." set fs_group='{$workgroup_id}' where fs_user='{$user_id}'";
                self::$db->query($doc_sql);
                $rs['success'] = true;
                $rs['msg'] = '操作成功';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'编辑员工信息 '.$username.' 操作成功！'));
                return $rs; 
            } else{
                $rs['success'] = false;
                $rs['msg'] = '操作失败';
                return $rs; 
            }

        }
        /**
        * 判断用户权限
        * 
        * @param mixed $login_user_info
        * @param mixed $op
        */
        public static function verify($login_user_info, $op='', $opobj_id='', $type='document'){ 
            self::init();  
            if($type=='document'){
                self::$document_table = 'fs_tree';
            }elseif($type=='share'){
                self::$document_table = 'fs_share_tree';
            } 
            if(empty($login_user_info)){
                return false;
            }
            if($op==''){
                return false;
            }
            $grade = $login_user_info['u_grade'];
            if($grade == 100){ //超级管理员
                return true;    
            }

            $sysmanager = array('adddocument', 'editdocument', 'readdocument', 'movedocument', 'deldocument', 'recoverdocument', 'readworkgroup', 'addworkgroup', 'editworkgroup', 'addgroupuser', 'delgroupuser', 'editgroupuser','showgroupuser', 'uploadfile', 'downloadfile', 'updatefile', 'powersetting', 'readsyslog', 'readdoclog', 'lookuphistory', 'sharesetting', 'copydocumentstruct'); //
            $supervisor = array('readdocument', 'readworkgroup', 'readsyslog', 'readdoclog', 'showgroupuser', 'downloadfile', 'lookuphistory', 'sharesetting');
            $groupmanager = $rs = array('adddocument', 'editdocument', 'readdocument', 'movedocument', 'deldocument','recoverdocument', 'readworkgroup', 'addgroupuser', 'editgroupuser', 'delgroupuser', 'showgroupuser', 'uploadfile', 'downloadfile', 'updatefile', 'readsyslog', 'readdoclog', 'lookuphistory','powersetting', 'sharesetting', 'copydocumentstruct'); //    
            $user = array('readdocument', 'adddocument', 'editdocument','deldocument',  'movedocument', 'uploadfile', 'downloadfile', 'readsyslog', 'readdoclog', 'lookuphistory', 'showgroupuser', 'sharesetting', 'copydocumentstruct');
            if($grade == 99){
                if(in_array($op, $sysmanager)){
                    return true; 
                } else {
                    return false;
                }
            } elseif($grade == 98 || $grade == 4 ||$grade == 3 || $grade == 2) {
                if(in_array($op, $supervisor)){
                    return true;    
                }
                return false;
            } elseif($grade == 1){
                if(in_array($op, $groupmanager)){
                    #如果是恢复文件操作，返回true
                    if($op=='recoverdocument'){
                        return true;
                    }
                    ##文件操作判断
                    if($op=='adddocument' || $op=='editdocument' || $op=='deldocument' || $op=='uploadfile' || $op=='downloadfile' || $op=='adddocument' ||$op=='movedocument' || $op=='powersetting' || $op=='sharesetting' || $op=='copydocumentstruct'){
                        #如果是恢复文件操作，需要在日志表中查找记录
                        if(!empty($opobj_id)){
                            $sql = "select * from ".self::$document_table . " where fs_id='{$opobj_id}' and fs_group='{$login_user_info['u_targetgroup']}'";
                            return  (boolean)self::$db->get_row($sql);
                        } else {
                            return false;
                        }
                    }
                    #用户操作判断
                    if($op=='editgroupuser' ||  $op=='delgroupuser' || $op=='editworkgroup'){
                        if(!empty($opobj_id)){
                            $sql = "select * from ".self::$usergroup_table . " where u_parent='{$login_user_info['u_targetgroup']}' and u_id='$opobj_id'"; 
                            return  (boolean)self::$db->get_row($sql);
                        } else {
                            return false;
                        }
                    }

                    if($op=='addgroupuser' || $op=='showgroupuser'){  //和工作组有关， 判断用户是否能在这个工作组中操作
                        if(!empty($opobj_id)){
                            $sql = "select * from ".self::$usergroup_table . " where u_parent='{$login_user_info['u_targetgroup']}'";
                            return  (boolean)self::$db->get_row($sql);
                        } else {
                            return false;
                        }
                    }
                    if($op=='readdocument' || $op=='readworkgroup' || $op=='readsyslog' || $op=='readdoclog' || $op=='lookuphistory'){
                        return true;
                    }else{
                        return false;
                    }
                }
                return false;
            } elseif($grade == 0){
                if(in_array($op, $user)){
                    if($op=='editdocument' || $op=='uploadfile' || $op=='adddocument' || $op=='movedocument' || $op=='sharesetting' || $op=='deldocument'){
                        if(!empty($opobj_id)){
                            $sql = "select * from ".self::$document_table . " where fs_id='{$opobj_id}' and fs_user='{$login_user_info['u_id']}'";
                            return (boolean)self::$db->get_row($sql);
                        } else {
                            return false;
                        }
                    }
                    if($op=='readdocument' || $op=='readworkgroup' || $op=='readsyslog' || $op=='readdoclog' || $op=='lookuphistory' || $op=='showgroupuser' || $op=='downloadfile'){
                        return true;
                    }
                    return false; 
                } else {
                    return false;
                }
            }
            return false;
        }

        /**
        * 获取用户权限
        * 
        * @param mixed $grade
        */
        public static function getpower($grade){
            $rs = array();
            if($grade == 100){
                $rs = array('adddocument', 'editdocument', 'readdocument', 'movedocument', 'deldocument', 'recoverdocument', 'readworkgroup', 'addworkgroup', 'editworkgroup', 'addgroupuser', 'delgroupuser', 'showgroupuser', 'uploadfile', 'downloadfile', 'updatefile', 'powersetting', 'readsyslog', 'readdoclog','lookuphistory', 'addproject', 'sharesetting', 'copydocumentstruct');
            }elseif($grade == 99){
                $rs = array('adddocument', 'editdocument', 'readdocument', 'movedocument', 'deldocument', 'recoverdocument', 'readworkgroup', 'addworkgroup', 'editworkgroup', 'addgroupuser', 'delgroupuser', 'showgroupuser', 'uploadfile', 'downloadfile', 'updatefile', 'powersetting', 'readsyslog', 'readdoclog','lookuphistory', 'sharesetting', 'copydocumentstruct');
            } elseif($grade == 98 || $grade == 4 ||$grade == 3 || $grade == 2) {
                $rs = array('readdocument', 'readworkgroup', 'readsyslog', 'readdoclog', 'downloadfile', 'showgroupuser', 'lookuphistory');
            } elseif($grade == 1){
                $rs = array('adddocument', 'editdocument', 'readdocument', 'movedocument', 'deldocument', 'recoverdocument', 'readworkgroup', 'addworkgroup', 'editworkgroup', 'addgroupuser', 'delgroupuser', 'showgroupuser', 'uploadfile', 'downloadfile', 'updatefile', 'readsyslog', 'readdoclog','lookuphistory', 'powersetting', 'sharesetting', 'copydocumentstruct');    
            } elseif($grade == 0){
                $rs = array('readdocument', 'adddocument', 'editdocument','deldocument', 'movedocument', 'uploadfile', 'downloadfile', 'readsyslog', 'readdoclog', 'lookuphistory', 'sharesetting', 'copydocumentstruct');
            }
            return $rs;
        }


        public static function alterpwd($data, $login_user_info){
            self::init();
            $oldpwd = strip_tags(trim($data['oldpwd']));
            $newpwd = strip_tags(trim($data['newpwd']));
            $newpwdconfirm = strip_tags(trim($data['newpwdconfirm']));
            if(!$oldpwd){
                $rs['success'] = false;
                $rs['msg'] = '请输入原密码';
                return $rs; 
            }
            if(!$newpwd){
                $rs['success'] = false;
                $rs['msg'] = '请输入新密码';
                return $rs;
            }
            if(!$newpwdconfirm){
                $rs['success'] = false;
                $rs['msg'] = '请输入确认密码';
                return $rs;
            }            
            if($newpwdconfirm!=$newpwd){
                $rs['success'] = false;
                $rs['msg'] = '您两次输入的密码不一致，请重新输入！';
                return $rs;
            }
            $sql = "select * from " . self::$usergroup_table . " where u_id='{$login_user_info['u_id']}' and u_pwd='".md5($oldpwd)."'";
            $userinfo = self::$db->get_row($sql);
            if($userinfo){
                $sql = "update " . self::$usergroup_table . " set u_pwd='" . md5($newpwd) . "' where u_id='{$login_user_info['u_id']}'"; 
                if(self::$db->query($sql)){
                    $rs['success'] = true;
                    $rs['msg'] = '操作成功！';
                    ZF_Libs_Cookie::set('auth', '');
                    //ZF_Libs_Cookie::set('auth', ZF_Libs_String::authcode("{$login_user_info['u_id']}\t{$login_user_info['u_email']}\t{$login_user_info['u_name']}\t{$login_user_info['u_parent']}\t{$login_user_info['u_grade']}", 'ENCODE'));
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>' 修改密码 ，操作成功！'));
                    return $rs; 
                } else{
                    $rs['success'] = false;
                    $rs['msg'] = '操作失败！';
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>' 修改密码 ，操作失败！'.$sql));
                    return $rs; 
                }
            }else{
                $rs['success'] = false;
                $rs['msg'] = '您输入的旧密码有误，请重新输入！';
                return $rs; 
            }
        }  

        /**
        * 判断工作组是否存在
        * 
        * @param mixed $workgroup_name
        * @param mixed $workgroup_id
        */
        function checkSameGroupuser($workgroup_name, $workgroup_id){
            $sql = "SELECT * FROM ".self::$usergroup_table." WHERE u_name='{$workgroup_name}' and u_isgroup=1 and u_id!='{$workgroup_id}'";
            $hasgroup = self::$db->get_row($sql);
            if(false !== $hasgroup){
                $rs['success'] = false;
                $rs['msg'] = '工作组已存在！';
                return $rs;
            }else{
                $rs['success'] = true;
                $rs['msg'] = '';
                return $rs; 
            }
        }

        /**
        * 返回用户角色名称
        * 
        * @param mixed $role_id
        * @return mixed
        */
        function getuserrole($role_id){
            $role_arr = array('0'=>'普通组员', '1'=>'组文件管理员', '2'=>'工作组领导', '3'=>'部门负责人', '4'=>'项目部负责人', '98'=>'系统监察员', '99'=>'系统管理员', '100'=>'超级管理员');
            return $role_arr[$role_id];
        }

        /**
        * 列出当前用户可以控制的用户组
        * 
        * @param mixed $login_user_info
        * @return object
        */
        public function listUserGroup($login_user_info){
            $uid= $login_user_info['u_id']; 
            self::init();
            if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98|| $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){
                $sql = "SELECT u_id, u_parent, u_name from ".self::$usergroup_table." WHERE u_isgroup='1'";
            }else{//($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){ //组管理员
                $sql = "SELECT u_id, u_parent, u_name from ".self::$usergroup_table." WHERE u_id='{$login_user_info['u_parent']}' and u_isgroup='1'";
            }
            $res = self::$db->get_results($sql);

            return $res;
        }        

        /**
        * 列出当前用户可以访问的用户
        * 
        * @param mixed $login_user_info
        * @return object
        */
        public function listUser($login_user_info){
            self::init();
            if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98|| $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){
                $sql = "SELECT u_id, u_parent, u_name from ".self::$usergroup_table." WHERE u_isgroup='0'";
            }else if($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){ //组管理员
                    $sql = "SELECT u_id, u_parent, u_name from ".self::$usergroup_table." WHERE u_parent='{$login_user_info['u_parent']}' and u_isgroup='0'";
                } else{
                    $sql = "SELECT u_id, u_parent, u_name from ".self::$usergroup_table." WHERE u_id='{$login_user_info['u_id']}' and u_isgroup='0'";
            }
            $res = self::$db->get_results($sql);

            return $res;
        }


        /**
        * 修改用户可以管理
        * 
        */
        public function alterUsertargetDoc(){
            self::init();
            $sql = "select * from fs_user";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    $power_arr = explode(',', $val['u_grade']);
                    if(!$val['u_targetgroup']){
                        $sql = "update fs_user set u_targetgroup = '{$val['u_parent']}' where u_id='{$val['u_id']}'";
                        echo $sql . "<br/>";
                        self::$db->query($sql);
                    }else{

                    }
                }
            }
            return array('success'=>true, "msg"=>'ok');
        }


        /**
        * 根据工作组ID获取工作组信息
        * 
        */
        public function getworkgroupbyid($data, $login_user_info){
            self::init();
            $workgroup_id = intval($data['workgroup_id']);
            if($workgroup_id){
                $sql = "select * from ".self::$usergroup_table. " where u_id='{$workgroup_id}' and u_isgroup='1'";
                $row = self::$db->get_row($sql);
                if($row){
                    return $row;
                }else{
                    return array();
                }
            }else{
                return array();
            }
        }

    } 
?>
