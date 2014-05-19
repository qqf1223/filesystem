<?php
    /**
    * @name      M_Sharedocument.php
    * @describe   共享文件及目录管理Model类
    * @author    qinqf
    * @version   1.0 
    * @todo       
    * @changelog  
    */  
    class M_Sharedocument extends M_Model{

        static $db;
        static $usergroup_table='fs_user';
        static $document_table = 'fs_share_tree';  #共享文件目录表
        static $doclog_table_name = 'fs_log'; 
        /*** 初始化操作 */
        public static function init(){
            self::$db = parent::init();    
        }


        public static function addsharedocroot($data, $login_user_info){
            self::init();
            $project_name = isset($data['projectname']) ? addslashes(strip_tags(trim($data['projectname']))) : '';   //暂时取消
            $project_intro = isset($data['projectintro']) ? addslashes(strip_tags(trim($data['projectintro']))) : '';
            $type = isset($data['rb']) ? intval($data['rb']) : '';
            $workgroupid = isset($data['workgroupid']) ? intval($data['workgroupid']) : '';

            $login_user_id = intval($login_user_info['u_id']);
            $login_user_group = intval($login_user_info['u_parent']); 
            if($type==2){
                if($workgroupid){
                    $groupid = $workgroupid;
                    $userid = '0';
                }else{
                    $rs['msg'] = '非法操作';
                    $rs['success'] = false;
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'非法操作'));
                    return $rs;
                }
            }else{
                $groupid = $login_user_group;
                $userid = $login_user_id;
            }

            $rs = array();
            if(!$login_user_id){
                $rs['msg'] = '请登录后进行操作';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'登录失效'));
                return $rs;
            }
            //var_dump($groupid);
            $ishaveshare = self::ishaveshare($groupid);
            if(!$ishaveshare){
                $rs['msg'] = '该组共享目录已存在！';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'共享目录已存在！'));
                return $rs;
            }

            if(empty($rs)){  //验证成功
                $createtime = date('Y-m-d H:i:s');
                $hashname = parent::hashname($project_name);
                /**开始创建项目目录**/
                $rsfile = ZF_Libs_IOFile::mkdir(PROJECT_DOC_PATH . DS . $hashname);
                if($rsfile){
                    $sql = "INSERT INTO ".self::$document_table." 
                    SET fs_parent=0, 
                    fs_user='{$userid}',
                    fs_isdir=1, 
                    fs_group='{$groupid}',
                    fs_create='{$createtime}',
                    fs_name='{$project_intro}',
                    fs_intro='{$project_intro}',
                    fs_hashname='{$hashname}'
                    ";
                    $res = self::$db->query($sql);
                    if($res){
                        $rs['msg'] = '添加共享目录 '.$project_name. ' 成功';
                        $rs['success'] = true;
                        #记录目录操作日志
                        $log_fs_id = self::$db->last_insert_id();
                        $sql = "update " . self::$document_table . " set fs_id_path='{$log_fs_id}' where fs_id='{$log_fs_id}' ";
                        self::$db->query($sql);
                        #记录文件操作日志
                        //$doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$project_name, 'fs_intro'=>$project_intro, 'fs_size'=>0, 'fs_type'=>0, 'fs_hashname'=>$hashname,'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$project_name);
                        //M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加共享目录 '.$project_name. ' 成功'));
                    } else {
                        $rs['msg'] = '操作失败';
                        $rs['success'] = false;
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加共享目录 '.$project_name. '失败'));
                    }
                }else{
                    $rs['msg'] = '创建共享目录失败';
                    $rs['success'] = false;

                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建项目目录失败'));
                }
            }
            return $rs;
        }

        /**
        * 根据条件获取文件树
        * 
        * @param int $project_id
        * @return string
        */
        public static function docmenttree($data, $login_user_info, $refresh=0){
            self::init();
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : 0;
            $uid = $login_user_info['u_id'];
            $where = $fs_id  ? " and fs_parent='{$fs_id}'" : '';//" and (fs_id='{$fs_id}' or fs_parent='{$fs_id}') ";
            $order = $fs_id ? " order by fs_isdir asc, LENGTH(o), o asc" : " order by fs_isdir asc, fs_id asc,  LENGTH(o), o asc";

            $tree_rs = array();
            if(!empty($login_user_info)){
                if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){  //超级管理员|系统监察员|项目部负责人|部门负责人
                    $where .= $fs_id ? '' : ' and fs_parent=0 ';
                    #方案三： 字母和数字区分
                    $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where 1 " . $where . $order;
                }else{
                    $where .= $fs_id ? "" : " and fs_id=1 or fs_group='{$login_user_info['u_parent']}' and fs_user='0' "; 
                    $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where 1 " . $where . $order; 
                }
                //echo $sql;
                $res_doc = self::$db->get_results($sql);
                if($res_doc){
                    foreach($res_doc as $key=>&$value){
                        #fs_textname 修改为读取fs_code字段
                        if(!empty($value['fs_code'])){
                            $fs_textname = $value['fs_code']; 
                        }else{
                            if($value['fs_parent']!=0){
                                $fs_textname = substr(self::getFilenamepath($value['fs_id']), 1);
                            }else{
                                $fs_textname = $value['fs_name'];
                            } 
                        }

                        if(!empty($value['fs_intro'])){
                            $value['text'] = $value['fs_parent']=='0' ? $value['fs_intro'] : $fs_textname . '（'.$value['fs_intro'].'）';   
                        }else{
                            $value['text'] = $fs_textname;
                        }

                        if(!($value['fs_isdir']==1)){
                            $type = strtolower($value['fs_type']);
                            $value['icon'] = self::getIconByType($type);
                        }
                        $value['id'] = $value['fs_id'];

                        $fs_fullpath = self::getParentpath($value['fs_id']);
                        $value['fs_fullpath'] = $fs_fullpath;
                        $value['leaf'] = $value['fs_isdir']==1?false:true; 
                        if($fs_id){
                            $value['managerok'] = true; 
                        }else{
                            $value['managerok'] = false;
                        }
                        $res_doc_arr[$value['fs_id']] = $value;
                        $res_fs_id[] = $value['fs_id'];
                        $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                    }

                    $tree_rs = $res_doc;
                }
            }
            return  $tree_rs;
        }


        /**
        * 创建共享文件夹
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function addsharedocument($data, $login_user_info) {
            self::init();   
            $document_name = isset($data['project_doc_name']) ? addslashes(strip_tags(trim($data['project_doc_name']))) : '';
            $document_parentid = isset($data['project_doc_parentid']) ? intval($data['project_doc_parentid']) : '';
            $document_intro = isset($data['project_doc_intro']) ? addslashes(strip_tags(trim($data['project_doc_intro']))) : '';
            #判断文件是否存在
            $checkresult = self::checkSamedoc($document_name, $document_parentid, 0, 1);  
            if($checkresult['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $document_name.'目录已经存在！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'adddocument', $document_parentid, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $parent_path = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($document_parentid)); //父级目录
            $hashname = parent::hashname(array($login_user_info['u_parent'], $login_user_info['u_id'], $parent_path.DS.$document_name));  //当前目录名称

            $rs = array();
            if(!$document_name){
                $rs['success'] = false;
                $rs['msg'] = '请添写目录编号';
                return $rs;
            }
            if(!$document_intro){
                $rs['success'] = false;
                $rs['msg'] = '请添写目录描述';
                return $rs;
            }
            if(!$document_parentid){
                $rs['success'] = false;
                $rs['msg'] = '请选择父级目录';
                return $rs;
            }
            if(empty($login_user_info)){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;  
            }

            #获取父级目录的权限
            #2013-08-11  修改添加的目录的权限继承文件夹的权限
            #查询文件夹权限
            $sql = "select * from " . self::$document_table . " where fs_id='{$document_parentid}' ";
            $parentDoc = self::$db->get_row($sql);

            /**开始创建项目目录**/
            $current_doc_dir = $parent_path.DS. $hashname;  
            $rsfile = ZF_Libs_IOFile::mkdir($current_doc_dir);

            $createtime = date('Y-m-d H:i:s');
            #生成文件编号， 查询使用
            if($login_user_info['u_parent']==$parentDoc['fs_group'] && $login_user_info['u_id']==$parentDoc['fs_user']) {
                $groupid = $parentDoc['fs_group'];
                $userid = $parentDoc['fs_user'];
                if($parentDoc['fs_parent']==0){
                    $fs_code = $document_name;
                }else{
                    if($parentDoc['fs_code']){
                        $fs_code = $parentDoc['fs_code'] . '-' . $document_name;
                    }else{
                        $fs_code = substr(self::getFilenamepath($document_parentid), 1) . '-' . $document_name;  
                    }
                }
            }else{
                $groupid = $parentDoc['fs_group'];
                #查找本组下的第一个找到的管理员， 给新建的目录的user自动分配到组管理员上（目前一个组只有一个管理员）
                $sql = "select * from " . self::$usergroup_table ." where u_parent='{$groupid}' and u_grade like '%,1%' limit 1 ";
                $row = self::$db->get_row($sql);
                $userid = $row['u_id'];
                if($parentDoc['fs_parent']==0){
                    $fs_code = $document_name;
                }else{
                    if($parentDoc['fs_code']){
                        $fs_code = $parentDoc['fs_code'] . '-' . $document_name;
                    }else{
                        $fs_code = substr(self::getFilenamepath($document_parentid), 1) . '-' . $document_name;  
                    }
                }
            }

            if($rsfile){
                $sql = "INSERT INTO ".self::$document_table." 
                SET fs_parent='{$document_parentid}',
                fs_group='{$groupid}',
                fs_user='{$userid}', 
                fs_isdir='1', 
                fs_create='$createtime', 
                fs_name='{$document_name}', 
                fs_intro='{$document_intro}',
                fs_hashname='{$hashname}', 
                fs_code='{$fs_code}'";
                $res = self::$db->query($sql);
                if($res){
                    $log_fs_id = self::$db->last_insert_id();

                    #生成文件ID路径编号， 展开目录树时使用 , 此处更新结果未做判断
                    if(!empty($parentDoc['fs_id_path'])){
                        $fs_id_path = $parentDoc['fs_id_path'] . '-' . $log_fs_id;
                    }else{
                        $idpath = substr(self::getFileIdpath($parentDoc['fs_id']), 1);
                        if($idpath){
                            $fs_id_path = $idpath . '-' . $log_fs_id;
                        }else{
                            $fs_id_path = $log_fs_id; 
                        }
                    }
                    $sql = "update " . self::$document_table . " set fs_id_path='{$fs_id_path}' where fs_id='{$log_fs_id}' ";
                    self::$db->query($sql);

                    $rs['msg'] = '添加共享目录【'.$fs_code. '】成功';
                    $rs['success'] = true;
                    #记录文件操作日志
                    $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$document_name, 'fs_hashname'=>$hashname, 'fs_intro'=>$document_intro, 'fs_size'=>0, 'fs_type'=>'', 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$document_name, 'fs_code'=>$fs_code, 'fs_parent'=>$document_parentid);
                    //M_Log::doclog($doclog);
                    #记录系统日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加共享目录 '.$fs_code. ' 成功'));
                } else {
                    $rs['msg'] = '操作失败';
                    $rs['success'] = false;
                    #记录日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加共享目录 '.$fs_code. ' 失败'));
                }
                return $rs;
            }else{
                $rs['msg'] = '创建目录失败';
                $rs['success'] = false;
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$rs['msg']));
                return $rs;
            }
        }

        /**
        * 对目录进行编辑
        * 
        * @param mixed $data
        * @return string
        */
        public static function editdocument($data, $login_user_info) {
            self::init(); 
            $document_name = !empty($data['project_doc_name']) ? addslashes(strip_tags(trim($data['project_doc_name']))) : '';
            $document_oldname = !empty($data['project_doc_oldname']) ? addslashes(strip_tags(trim($data['project_doc_oldname']))) : '';
            $document_parentid = !empty($data['document_parentid']) ? intval($data['document_parentid']) : '';
            $document_id = intval($data['project_doc_id']);
            $document_oldintro = addslashes(strip_tags(trim($data['project_doc_oldintro'])));
            $document_intro = addslashes(strip_tags(trim($data['project_doc_intro'])));
            if($document_name==''){
                $document_name = $document_intro;
            }

            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editdocument', $document_id, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(empty($document_name)){
                $rs['success'] = false;
                $rs['msg'] = '请输入目录编号';
                return $rs;
            }
            if(empty($document_intro)){
                $rs['success'] = false;
                $rs['msg'] = '请输入目录说明';
                return $rs;
            }
            if(!$document_id){
                $rs['success'] = false;
                $rs['msg'] = '请选择需要编辑的目录';
                return $rs;
            }
            if(!$login_user_info['u_id']){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;
            }

            #判断当前项目下此文件已存在
            $ret = self::checkSamedoc($document_name, $document_parentid, $document_id, 1);
            if($ret['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $document_name.'已经存在！';
                return $rs;
            }

            //$old_path_name = substr(self::getFilenamepath($document_id), 1);
            if(!empty($ret['data']['fs_code'])){
                $old_fs_code = $ret['data']['fs_code'];
            }else{
                $old_fs_code = substr(self::getFilenamepath($document_id), 1);
            }

            if(!empty($ret['data']['fs_id_path'])){
                $fs_id_path = $ret['data']['fs_id_path']; 
            }else{
                $fs_id_path = substr(self::getFileIdpath($document_id), 1);
            }

            #获取上级目录的fs_code
            if($document_parentid=='0' || $document_parentid==''){
                $new_fs_code = '';
            }else{
                $sql = "select * from " . self::$document_table . " where fs_id='{$document_parentid}'";
                $row_parent = self::$db->get_row($sql);
                if($row_parent){
                    if($row_parent['fs_code']){
                        $new_fs_code = $row_parent['fs_code'] . '-' . $document_name;
                    }else{
                        if($row_parent['fs_parent']=='0'){
                            $new_fs_code = $document_name;
                        }else{
                            $r = substr(self::getFilenamepath($document_parentid), 1); 
                            $new_fs_code = $r ? $r . '-' . $document_name : $document_name;
                        }
                    }
                }else{
                    $new_fs_code = $document_name; 
                }
            }

            #编辑日期
            $edittime = date('Y-m-d H:i:s');

            /**开始数据库操作**/
            $sql = "UPDATE ".self::$document_table." SET 
            fs_lastmodify='{$edittime}', 
            fs_name='{$document_name}',
            fs_intro='{$document_intro}',
            fs_code='{$new_fs_code}' 
            WHERE fs_id='$document_id'";
            $res = self::$db->query($sql);
            if($res){
                #当前移动目录更新成功后需要对目录下的所有子目录的fs_code进行处理
                self::dealwithmovefscode($document_id, $new_fs_code, $fs_id_path);

                $rs['msg'] = '操作成功';
                if($document_parentid=='0' || $document_parentid==''){
                    $rs['data'] = array('document_name'=>$document_name,'document_pathname'=>$document_intro, 'document_intro'=>$document_intro);
                }else{
                    $rs['data'] = array('document_name'=>$document_name,'document_pathname'=>$new_fs_code, 'document_intro'=>$document_intro);
                }
                $rs['success'] = true;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'修改共享目录编号 '.$old_fs_code.' 为 '.$new_fs_code.' 目录名称由 '.$document_oldintro.' 修改为 '.$document_intro.' 操作成功'));
                return $rs;
            } else {
                $rs['msg'] = '操作失败';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'修改共享目录编号 '.$old_fs_code.' 为 '.$new_fs_code.' 目录名称由 '.$document_oldintro.' 修改为 '.$document_intro.' 操作失败'));
                return $rs;
            }
        }        

        /**
        * 对文件进行编辑
        * 
        * @param mixed $data
        * @return string
        */
        public static function editfile($data, $login_user_info) {
            self::init(); 
            $file_name = isset($data['file_name']) ? addslashes(strip_tags(trim($data['file_name']))) : '';
            $file_oldname = isset($data['file_oldname']) ? addslashes(strip_tags(trim($data['file_oldname']))) : '';
            $file_parentid = intval(strip_tags(trim($data['file_parentid'])));
            $file_id = isset($data['file_id']) ? intval($data['file_id']) : '';
            $file_intro = isset($data['file_intro']) ? addslashes(strip_tags(trim($data['file_intro']))) : '';
            $file_encrypt = isset($data['encrypt']) ? intval($data['encrypt']) : 0;
            $file_haspaper = intval($data['haspaper']);
            $file_size = intval($data['size']);
            $file_type = intval($data['type']);

            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editdocument', $file_id, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }

            $rs = array();
            if(empty($file_name)){
                $rs['success'] = false;
                $rs['msg'] = '请输入文件编号';
                return $rs;
            }
            if(empty($file_intro)){
                $rs['success'] = false;
                $rs['msg'] = '请输入文件说明';
                return $rs;
            }
            if(!$file_id){
                $rs['success'] = false;
                $rs['msg'] = '请选择需要编辑的文件';
                return $rs;
            }
            if(!$login_user_info['u_id']){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;
            }

            #判断当前项目下此文件已存在
            $ret = self::checkSamedoc($file_name, $file_parentid, $file_id, 0);
            if($ret['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $file_name.'已经存在！';
                return $rs;
            }
            $old_fs_code = $ret['data']['fs_code'];
            $edittime = date('Y-m-d H:i:s');

            #获取上级目录的fs_code
            $sql = "select * from " . self::$document_table . " where fs_id='{$file_parentid}'";
            $row_parent = self::$db->get_row($sql);
            $new_fs_code = '';
            if($row_parent){
                if($row_parent){
                    if($row_parent['fs_parent']=='0'){
                        $new_fs_code = $file_name;
                    }else{
                        $new_fs_code = $row_parent['fs_code'] . '-' . $file_name;
                    }
                }else{
                    $r = substr(self::getFilenamepath($file_id), 1);
                    $new_fs_code = $r ? $r . '-' . $file_name : $file_name; 
                }
            }else{
                $new_fs_code = $file_name;
            }
            
            /**开始数据库操作**/
            $sql = "UPDATE ".self::$document_table." SET 
            fs_lastmodify='{$edittime}', 
            fs_name='{$file_name}',
            fs_intro='{$file_intro}',
            fs_encrypt='{$file_encrypt}',
            fs_haspaper='{$file_haspaper}',
            fs_code='{$new_fs_code}'  WHERE fs_id='$file_id'";
            $res = self::$db->query($sql);
            if($res){
                $rs['msg'] = '操作成功';
                $rs['success'] = true;
                $rs['data'] = array('document_name'=>$file_name,'document_pathname'=>$new_fs_code, 'document_intro'=>$file_intro);
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'编辑文件 '.$old_fs_code.' 操作成功'));
                return $rs;
            } else {
                $rs['msg'] = '操作失败';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'编辑文件 '.$old_fs_code.' 操作成功'));
                return $rs;
            }

        }


        /**
        * 递归获取物理目录路径
        * 
        * @param mixed $node_id
        */
        public static function getParentpath($node_id){
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                self::init();
                $sql = "select * from ".self::$document_table." where fs_id='$node_id'";
                $rs = self::$db->get_row($sql);
                if(false!==$rs){
                    /*
                    if(PHP_OS == 'WINNT'){
                    $filename = mb_convert_encoding($rs['fs_name'], 'GBK', 'UTF8');
                    }else{
                    $filename = $rs['fs_name'];
                    }
                    return self::getParentpath($rs['fs_parent']) . DS . $filename;
                    */
                    return self::getParentpath($rs['fs_parent']) . $rs['fs_hashname'];
                }
            }
        }

        /**
        * 递归获取编号路径
        * 
        * @param mixed $node_id
        */
        public static function getFilenamepath($node_id){
            self::init();
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                $sql = "select * from ".self::$document_table." where fs_id='$node_id'";
                $rs = self::$db->get_row($sql);
                if($rs){
                    if(PHP_OS == 'WINNT'){
                        $filename = mb_convert_encoding($rs['fs_name'], 'GBK', 'UTF-8');
                    }else{
                        $filename = $rs['fs_name'];
                    }
                    if($rs['fs_parent']!=0){
                        return self::getFilenamepath($rs['fs_parent']) . '-' . $filename; 
                    }
                }
            }
        }


        /**
        * 递归获取历史文件编号路径
        * 
        * @param mixed $node_id
        */
        public static function getHistoryFilenamepath($node_id){
            self::init();
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                $sql = "select * from ".self::$document_table." where fs_id='$node_id'";
                $rs = self::$db->get_row($sql);
                if($rs){
                    if(PHP_OS == 'WINNT'){
                        $filename = mb_convert_encoding($rs['fs_name'], 'GBK', 'UTF8');
                    }else{
                        $filename = $rs['fs_name'];
                    }
                    if($rs['fs_parent']!=0){
                        return self::getFilenamepath($rs['fs_parent']) . '-' . $filename; 
                    }
                }
            }
        }

        /**
        * 分配目录权限
        * 
        * @param mixed $data
        */
        public static function adddocpower($data, $login_user_info){
            self::init();
            $workgroup_id = intval($data['workgroup_id']);
            $user_id = intval($data['user_id']);
            $project_doc_id = intval($data['project_doc_id']);
            #记录日志使用
            $project_doc_name = $data['project_doc_name']; 
            $login_user_id = $login_user_info['u_id'];
            $login_user_group = $login_user_info['u_parent'];
            $login_user_name = $login_user_info['u_name'];
            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'powersetting', $project_doc_id, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }

            if($workgroup_id){
                if($user_id){
                    if($project_doc_id){
                        $respower = self::grantpower($project_doc_id, $workgroup_id, $user_id); 
                        if($respower){
                            $rs['success'] = true;
                            $rs['msg'] = '权限分配成功！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$project_doc_name.' 目录权限分配成功'));
                        } else{
                            $rs['success'] = false;
                            $rs['msg'] = '权限分配失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$project_doc_name.' 目录权限分配失败'));
                        }
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '请选择目录';
                    }
                }else{
                    $rs['success'] = false;
                    $rs['msg'] = '请选择组员';
                }
            } else {
                $rs['success'] = false;
                $rs['msg'] = '请选择工作组';
            }
            return $rs;
        }

        /**
        * 递归授权
        * 
        * @param mixed $document_id
        * @param mixed $workgroup_id
        * @param mixed $user_id
        */
        public static function grantpower($document_id, $workgroup_id, $user_id){
            self::init();
            $time = date('Y-m-d H:i:s'); 
            $sql = "UPDATE  ". self::$document_table. " SET
            fs_group='{$workgroup_id}',
            fs_user='{$user_id}',
            fs_lastmodify='{$time}' WHERE fs_id='{$document_id}' or fs_parent='{$document_id}'";
            $res = self::$db->query($sql);
            if($res){
                $sql = "select * from ".self::$document_table." where fs_parent='{$document_id}'";
                $rs = self::$db->get_results($sql);
                if($rs){
                    foreach($rs as $val){
                        if($val['fs_isdir']=='1'){
                            self::grantpower($val['fs_id'], $workgroup_id, $user_id);
                        }
                    } 
                }

                return true;
            } else {
                return false;
            }
        }

        /**
        * 递归处理移动文件夹过程对fs_code的更新操作
        * 
        * @param mixed $nodeid
        */
        public static function dealwithmovefscode($nodeid, $nodefilecode, $nodefileidpath=''){
            self::init();
            $sql = "select * from " . self::$document_table . " where fs_parent='{$nodeid}'";
            $res = self::$db->get_results($sql);
            if(!empty($res)){
                foreach($res as $val){

                    $fs_code = $nodefilecode ? $nodefilecode . '-' . $val['fs_name'] : $val['fs_name'];
                    #更新fs_id_path
                    $fs_id_path = $nodefileidpath ? $nodefileidpath . '-' . $val['fs_id'] : $val['fs_id'];

                    $sql = "update " . self::$document_table . " set fs_code='{$fs_code}', fs_id_path='{$fs_id_path}' where fs_id='{$val['fs_id']}'";
                    self::$db->query($sql);

                    if($val['fs_isdir']=='1'){ #如果是文件夹的话
                        self::dealwithmovefscode($val['fs_id'], $fs_code, $fs_id_path);
                    }    
                }
            } 
        }

        /**
        * 递归获取ID拼接的路径， 用于目录树的展开（用名称fs_name会出现文件和文件夹同名的时候会报错）
        * 
        * @param mixed $node_id
        */
        public static function getFileIdpath($node_id){
            self::init();
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                $sql = "select * from ".self::$document_table." where fs_id='$node_id'";
                $rs = self::$db->get_row($sql);
                if($rs){
                    $fs_id = $rs['fs_parent'];
                    //if($rs['fs_parent']!=0){
                    return self::getFileIdpath($rs['fs_parent']) . '-' . $rs['fs_id']; 
                    //}
                }
            }
        }

        /**
        * 删除
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function delsharedocument($data, $login_user_info){
            self::init(); 
            $fs_id = intval($data['fs_id']);
            $fs_isdir = isset($data['fs_isdir']) ? intval($data['fs_isdir']) : '';
            $fs_fullpath = $data['fs_fullpath'];
            $fs_intro = $data['fs_intro'];
            $fs_type = $data['fs_type'];
            $fs_size = intval($data['fs_size']);
            $fs_name = $data['fs_name'];
            $fs_hashname = $data['fs_hashname'];

            $fs_parentid = !empty($data['fs_parent']) ? $data['fs_parent'] : '';
            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'deldocument', $fs_id, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }

            //如果是文件夹
            if($fs_isdir==1){
                //判断是否为空文件夹, 空文件夹可以删除
                $sql = "select * from ".self::$document_table." where fs_parent='{$fs_id}' ";
                $hasdoc = self::$db->get_row($sql); 
                if($hasdoc){
                    //开始对非空文件夹进行删除操作
                    self::circledeldocument($data, $login_user_info);
                    $rs['msg'] = '操作成功！';
                    $rs['success'] = true;
                    return $rs;
                } else {
                    //进行删除目录操作
                    $fs_fullpath = PROJECT_DOC_PATH . self::splitdocpath($fs_fullpath);
                    if(ZF_Libs_IOFile::rm($fs_fullpath)){
                        #删除记录之前获取filecode
                        $filecode = substr(self::getFilenamepath($fs_id), 1);
                        //删除数据库记录
                        $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}' ";
                        if(self::$db->query($sql)){
                            $rs['msg'] = '操作成功！';
                            $rs['success'] = true;
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                            //M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $fs_intro . ' 操作成功'));
                            return $rs;
                        } else{
                            $rs['msg'] = '操作失败！';
                            $rs['success'] = false;
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $fs_intro . ' 操作失败'));
                            return $rs;  
                        }
                    }else {
                        $rs['msg'] = '操作失败！';
                        $rs['success'] = false;
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $fs_intro . ' 操作失败'));
                        return $rs;  
                    }

                }
                /* } */
            }else{ //文件操作
                $fs_file = PROJECT_DOC_PATH . self::splitdocpath($fs_fullpath) . '.' . $fs_type;
                $fs_parentpath = substr(self::getFilenamepath($fs_parentid), 1);
                $file_textname = substr(self::getFilenamepath($fs_id), 1);
                
                if(ZF_Libs_IOFile::backup($fs_file, $fs_hashname.'.'.$fs_type)){ 
                    #删除记录之前获取filecode
                    $filecode = substr(self::getFilenamepath($fs_id), 1); 
                    $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}'";
                    if(self::$db->query($sql)){
                        $rs['success'] = true;
                        $rs['msg'] = '操作成功！';
                        #记录文件操作日志
                        $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                        //M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 操作成功'));    
                        return $rs;
                    } else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 数据库操作失败'));    
                        return $rs;
                    }
                }else{
                    $rs['success'] = false;
                    $rs['msg'] = '操作失败！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname  . '（'.$fs_intro.'） 物理操作失败'));    
                    return $rs;
                }
            }
        }

        
        /**
        * 删除
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function circledeldocument($data, $login_user_info){
            self::init(); 
            $fs_id = intval($data['fs_id']);
            $fs_isdir = isset($data['fs_isdir']) ? intval($data['fs_isdir']) : '';
            $fs_fullpath = self::getParentpath($fs_id);
            $fs_intro = $data['fs_intro'];
            $fs_type = $data['fs_type'];
            $fs_size = intval($data['fs_size']);
            $fs_name = $data['fs_name'];
            $fs_hashname = $data['fs_hashname'];

            $fs_parentid = !empty($data['fs_parent']) ? $data['fs_parent'] : '';

            //如果是文件夹
            if($fs_isdir==1){
                //判断是否为空文件夹, 空文件夹可以删除
                $sql = "select * from ".self::$document_table." where fs_parent='{$fs_id}' ";
                $hasdoc = self::$db->get_results($sql); 
                if($hasdoc){
                    //开始对非空文件夹进行删除操作
                    foreach($hasdoc as $val){
                        self::circledeldocument($val, $login_user_info);
                    }
                    self::circledeldocument($data, $login_user_info);
                } else {
                    //进行删除目录操作
                    $fs_fullpath = PROJECT_DOC_PATH . self::splitdocpath($fs_fullpath);
                    #删除记录之前获取filecode
                    $sql = "select * from " . self::$document_table . " where fs_id='{$fs_id}' ";
                    $row = self::$db->get_row($sql);
                    if(!empty($row['fs_code'])){
                        $filecode = $row['fs_code'];  
                    }else{
                        $filecode = substr(self::getFilenamepath($fs_id), 1);
                    }
                    if(ZF_Libs_IOFile::rm($fs_fullpath)){
                        //删除数据库记录
                        $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}' ";
                        if(self::$db->query($sql)){
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $filecode . ' 操作成功'));
                        } else{
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $filecode . ' 操作失败'));
                        }
                    }else {
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除共享目录 '. $filecode . ' 操作失败'));
                    }

                }
                /* } */
            }else{ //文件操作
                $fs_file = PROJECT_DOC_PATH . self::splitdocpath($fs_fullpath) . '.' . $fs_type;
                $fs_parentpath = substr(self::getFilenamepath($fs_parentid), 1);
                $file_textname = substr(self::getFilenamepath($fs_id), 1);
                if(ZF_Libs_IOFile::backup($fs_file, $fs_hashname.'.'.$fs_type)){ 
                    #删除记录之前获取filecode
                    $filecode = substr(self::getFilenamepath($fs_id), 1); 
                    $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}'";
                    if(self::$db->query($sql)){
                        $rs['success'] = true;
                        $rs['msg'] = '操作成功！';
                        #记录文件操作日志
                        $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                        M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 操作成功'));    
                    } else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 数据库操作失败'));    
                    }
                }else{
                    $rs['success'] = false;
                    $rs['msg'] = '操作失败！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.$fs_parentpath.' 中删除文件 '. $file_textname  . '（'.$fs_intro.'） 物理操作失败'));    
                }
            }
        }

        
        /**
        * 文件恢复
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function recoverdocument($data, $login_user_info){
            self::init();
            $fs_parent = !empty($data['fs_parent']) ? intval($data['fs_parent']) : '';
            #获取删除的源文件目录
            $fs_hashname = $data['fs_hashname']; #原文件的HASH码
            $fs_date = date('Ymd', strtotime($data['log_optdate']));
            $fs_type = $data['fs_type'];
            $fs_name = !empty($data['fs_name']) ? $data['fs_name'].'['.date('YmdHis').']' : '';
            $fs_intro = $data['fs_intro'];
            $fs_size = $data['fs_size'];
            $fs_type = $data['fs_type'];
            $log_user = $data['log_user'];

            #需要恢复的文件的物理路径
            $file = FILE_BACKUP_PATH.DS.$fs_date.DS.$fs_hashname.'.'.$fs_type;
            $ppath = substr(self::getFilenamepath($fs_parent), 1);
            $filecode = $ppath . '-' . $fs_name;
            #项目的父级目录为0， 项目是不可能删除的，所以此处不考虑父级为0的情况
            if($fs_parent){
                #判断原来的父级目录是否还存在
                $sql = "select * from ".self::$document_table." where fs_id='{$fs_parent}'";
                $parentrs = self::$db->get_row($sql);
                if($parentrs){
                    #如果原来父级目录还存在， 获取父级目录的物理路径, 将文件恢复到此目录下
                    $parent_fullpath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($fs_parent));
                    $fs_new_hashname = parent::hashname($parent_fullpath . DS.$fs_hashname.'.'.$fs_type);
                    $recover_file = $parent_fullpath . DS.$fs_new_hashname.'.'.$fs_type;
                    $oprs = ZF_Libs_IOFile::copyFile($file, $recover_file);
                    if($oprs){
                        $time = date('Y-m-d H:i:s');
                        #文件移动成功，开始对数据库进行操作(1、原始内容无法完全恢复，纸版和加密状态，组和用户有恢复到某个目录的属性决定)
                        $sql = "insert into " . self::$document_table . " set 
                        fs_parent='{$fs_parent}', 
                        fs_isdir='0', 
                        fs_group='{$parentrs['fs_group']}', 
                        fs_user='{$parentrs['fs_user']}', 
                        fs_create='{$time}',
                        fs_name='{$fs_name}',
                        fs_intro='{$fs_intro}',
                        fs_size='{$fs_size}',
                        fs_type='{$fs_type}',
                        fs_hashname='{$fs_new_hashname}'
                        ";
                        if(self::$db->query($sql)){
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录文件操作日志
                            $log_fs_id = self::$db->last_insert_id();
                            $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_new_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parent);
                            //M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$filecode.'（'.$fs_intro.'）'.' 到目标目录 '.$ppath.' 操作成功'));    
                            return $rs;     
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$filecode.'（'.$fs_intro.'）'.' 到目标目录 '.$ppath.' 操作失败'));    
                            return $rs;  
                        }
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标目录 '.$ppath.' 操作失败'));    
                        return $rs;  
                    }
                }else{
                    $rs['success'] = false;
                    $rs['msg'] = '原目录不存在！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标目录 '.$ppath.' 操作失败'));    
                    return $rs;
                    //return self::docmenttree($data, $login_user_info);  
                }
            }else{
                $rs['success'] = false;
                $rs['msg'] = '原目录不存在！';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标目录 '.$ppath.' 操作失败'));    
                return $rs;
                //return self::docmenttree($data, $login_user_info);
            }
        }           
        /**
        * 文件下载
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function downloadfile($data, $login_user_info){
            $fs_id = intval($data['fs_id']);
            $fs_type = !empty($data['t']) ? $data['t'] : '';
            if(isset($data['file'])){

                $file = self::splitdocpath($data['file']);
                $file = PROJECT_DOC_PATH . $file . '.' . $fs_type;

                //$filename = substr($file, strrpos($file, '/')+1);
                $filename = substr(self::getFilenamepath($fs_id), 1) . '_' . $data['fs_intro'] . '.' . $fs_type;
                
                header('Content-Description: File Transfer');
                header("Content-type: application/octet-stream");
                header("Accept-Ranges: bytes");
                header("Content-Disposition: attachment; filename=" . $filename);
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                ob_clean();
                flush();
                readfile($file);

                exit();
            } else {
                $fs_fullpath = !empty($data['fs_fullpath']) ? $data['fs_fullpath'] : '';
                $fs_intro = !empty($data['fs_intro']) ? $data['fs_intro'] : '';
                $fs_type = !empty($data['fs_type']) ? $data['fs_type'] : '';
                $fs_name = !empty($data['fs_name']) ? $data['fs_name'] : '';
                $fs_hashname = !empty($data['fs_hashname']) ? $data['fs_hashname'] :'';
                $fs_size = !empty($data['fs_size']) ? intval($data['fs_size']) : 0;

                if(!$fs_fullpath){
                    $file_path = self::splitdocpath(self::getParentpath($fs_id));  
                }else{
                    $file_path = self::splitdocpath($fs_fullpath);
                }
                $fs_file = PROJECT_DOC_PATH . $file_path.'.'.$fs_type;
                //var_dump($fs_file);
                if(!file_exists($fs_file)){
                    $rs['msg'] = '文件不存在或已删除！';
                    $rs['success'] = false;
                    return $rs;    
                }else{
                    $rs['msg'] = $fs_fullpath.'&fs_id='.$fs_id.'&t='.$fs_type.'&fs_intro='.$fs_intro;
                    $rs['success'] = true;
                    #记录文件操作日志
                    $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>6, 'log_lastname'=>$fs_name);
                    //M_Log::doclog($doclog);
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'下载文件 '. $fs_name . ' 操作成功'));     
                    return $rs;
                }
            }
        } 

        /**
        * 历史文件下载
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function downloadhistory($data, $login_user_info){
            $fs_id = intval($data['fs_id']);
            $fs_type = !empty($data['t']) ? $data['t'] : '';
            $fs_date = !empty($data['d']) ? $data['d'] : '';
            if(isset($data['file'])){
                #权限验证
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id, 'share');
                if(!$verify){
                    $rs['msg'] = '对不起，您没有此操作权限';
                    $rs['success'] = false;
                    echo json_encode($rs);exit;
                } 
                if($fs_date){ //历史版本
                    $file = FILE_BACKUP_PATH.DS.$fs_date.DS.$data['file'].'.'.$fs_type;
                    $filename = $data['file'].'.'.$fs_type  ;
                } else{
                    $file = self::splitdocpath($data['file']);
                    $file = PROJECT_DOC_PATH . $file . '.' . $fs_type;
                    //$filename = substr($file, strrpos($file, '/')+1);
                    $filename = substr(self::getHistoryFilenamepath($fs_id), 1) . '_' . $data['fs_intro'] . '.' . $fs_type;
                }

                header('Content-Description: File Transfer');
                header("Content-type: application/octet-stream");
                header("Accept-Ranges: bytes");
                header("Content-Disposition: attachment; filename=" . $filename);
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                ob_clean();
                flush();
                readfile($file);
            } else {
                $fs_intro = !empty($data['fs_intro']) ? $data['fs_intro'] : '';
                $fs_type = !empty($data['fs_type']) ? $data['fs_type'] : '';
                $fs_name = !empty($data['fs_name']) ? $data['fs_name'] : '';
                $fs_hashname = !empty($data['fs_hashname']) ? $data['fs_hashname'] :'';
                $fs_size = !empty($data['fs_size']) ? intval($data['fs_size']) : 0;
                $fs_date = !empty($data['log_optdate'])? $data['log_optdate'] : '';
                #权限验证
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id, 'share');
                if(!$verify){
                    $rs['msg'] = '对不起，您没有此操作权限';
                    $rs['success'] = false;
                    return $rs;
                }
                #先查找备份文件是否存在

                $file_date = date('Ymd', strtotime($fs_date));
                $fs_file = FILE_BACKUP_PATH.DS.$file_date.DS.$fs_hashname.'.'.$fs_type;
                if(!file_exists($fs_file)){
                    $fs_fullpath = self::getParentpath($fs_id);
                    $fs_path_name = substr(self::getFilenamepath($fs_id), 1);
                    echo $fs_path_name;die;
                    $file_path = self::splitdocpath($fs_fullpath);  

                    $fs_file = PROJECT_DOC_PATH.$file_path.'.'.$fs_type;
                    if(!file_exists($fs_file)){
                        $rs['msg'] = '文件不存在或已删除！';
                        $rs['success'] = false;
                        return $rs;    
                    }else{
                        $rs['msg'] = $fs_fullpath.'&fs_id='.$fs_id.'&t='.$fs_type . '&fs_intro=' . $fs_intro;
                        $rs['success'] = true;
                        #记录文件操作日志
                        //$doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>6, 'log_lastname'=>$fs_name);
                        ////M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'下载历史文件 '. $fs_path_name . ' 操作成功'));     
                        return $rs;

                    }
                } else {
                    $fs_path_name = substr(self::getFilenamepath($fs_id), 1);
                    $rs['msg'] = $fs_hashname.'&fs_id='.$fs_id.'&t='.$fs_type.'&d='.$file_date;//str_replace(PROJECT_DOC_PATH, '', $fs_file).'&fs_id='.$fs_id
                    $rs['success'] = true;
                    #记录文件操作日志
                    //$doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>6, 'log_lastname'=>$fs_name);
                    ////M_Log::doclog($doclog);
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'下载历史文件 '. $fs_path_name . ' 操作成功'));     
                    return $rs; 
                }
            }
        } 

        /**
        * 判断文件是否存在
        * 
        * @param mixed $data
        * @return object
        */
        public static function checkSamedoc($fs_name, $fs_parent, $fs_id, $isdir=0){
            self::init();  
            if(!$fs_name){
                $rs['success'] = false;
                $rs['msg'] = '文件编号不能为空';
                $rs['flag'] = 2;
                $rs['data'] = '';
                return $rs;
            }            
            if($fs_parent==''){
                $rs['success'] = false;
                $rs['msg'] = '父级目录不存在';
                $rs['flag'] = 2;
                $rs['data'] = '';
                return $rs;
            }

            $fs_name = mysql_real_escape_string($fs_name);
            $fs_parent = $fs_parent;
            $fs_name_regex = '/[a-zA-Z0-9\-].*?/';
            if(!$fs_name || !preg_match($fs_name_regex, $fs_name)){
                $rs['msg'] = '文件编号错误';
                $rs['success'] = false;
                $rs['flag'] = 2;
                $rs['data'] = '';
                return $rs;
            }
            if(!$fs_parent){
                $rs['msg'] = '请选择目录';
                $rs['success'] = false;
                $rs['flag'] = 2;
                $rs['data'] = '';
                return $rs;
            }
            if(!$fs_id){
                $where = '';
            }else{
                $where = " and fs_id!='{$fs_id}'";
            }
            if(!$isdir){
                $where .= " and fs_isdir='0' ";
            } else {
                $where .= " and fs_isdir='{$isdir}' ";
            }
            $sql = "select * from ".self::$document_table." where fs_parent='{$fs_parent}' and fs_name='{$fs_name}' ".$where;
            $res = self::$db->get_row($sql);
            if(!empty($res)){
                $rs['msg'] = '文件已存在！';
                $rs['success'] = false;
                $rs['flag'] = 1;
                $rs['data'] = $res;
            }else{
                $rs['msg'] = '没有文件';
                $rs['success'] = true;
                $rs['flag'] = 0;
                if($fs_id){
                    $sql = "select * from ".self::$document_table." where fs_id='{$fs_id}'";
                    $docinfo = self::$db->get_row($sql);
                    $rs['data'] = $docinfo;
                }else{
                    $rs['data'] = '';
                }
            }
            return $rs;
        }

        /**
        * 文件列表 GRID
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        * @return object
        */
        public static function listdocumentgrid($data, $login_user_info){
            self::init();
            $pagesize = intval($data['limit']);
            $start = intval($data['start']);
            $page = !empty($data['page'])?intval($data['page']):1;
            $sortobj = isset($data['sort'])? json_decode($data['sort']) : array((object)array('property'=>'o', 'direction'=>'ASC'));
            $sort = $sortobj[0]->direction;
            $sortfield = $sortobj[0]->property=='text' ? ' LENGTH(o), o' : ' LENGTH(o), ' . $sortobj[0]->property;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            $uid = $login_user_info['u_id'];
            $limit = " limit " . $start . ",".$pagesize;
            $where = $fs_id ? " and fs_parent='{$fs_id}'" :'';

            //查询用户权限
            $tree_rs = array();
            if(!empty($login_user_info)){
                $where .= $fs_id ? '' :  " and fs_group='{$login_user_info['u_parent']}' or fs_id=1 "; 
                #获取统计总数、分页使用
                $sql =  "select count(*) from ".self::$document_table." where 1 ".$where;
                $count_arr = self::$db->get_col($sql);
                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." as d left join (select u_id, u_name from ".self::$usergroup_table.")  as u on d.fs_user=u.u_id where 1 " . $where . " order by  fs_isdir " . $sort .',' . $sortfield .' '. $sort . $limit;

                //echo $sql;
                $res_doc = self::$db->get_results($sql);
                #设置前端要用的数据格式
                if($res_doc){
                    foreach($res_doc as $key=>&$value){
                        if($value['fs_parent']!=0){
                            $fs_textname = substr(self::getFilenamepath($value['fs_id']), 1);
                        }else{
                            $fs_textname = $value['fs_name'];
                        }
                        $value['text'] = $fs_textname; 
                        $value['id'] = $value['fs_id'];
                        if(!($value['fs_isdir']==1 || $value['fs_isdir']==2)){
                            $type = strtolower($value['fs_type']);
                            $value['icon'] = self::getIconByType($type);
                        }
                        $fs_fullpath = self::getParentpath($value['fs_id']);
                        $value['fs_fullpath'] = $fs_fullpath;
                        $value['leaf'] = $value['fs_isdir']==1 || $value['fs_isdir']==2?false:true; 

                        $res_doc_arr[$value['fs_id']] = $value;
                        $res_fs_id[] = $value['fs_id'];
                        $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                    }
                    if($login_user_info['u_grade']<90){ 
                        foreach($res_fs_parent_id as $fs_key_id=>$parent_id){
                            if(in_array($parent_id, $res_fs_id)){
                                continue;
                            }else{
                                $tree_rs[] = $res_doc_arr[$fs_key_id];
                            }
                        }
                    } else{
                        $tree_rs = $res_doc;
                    }
                }
            }
            /*
            #添加返回上级目录
            #获取当前节点信息
            $sql = "select * from ".self::$document_table." where fs_id='{$fs_id}' ";
            $rs = self::$db->get_row($sql);
            if($rs['fs_parent']!=0 && $login_user_info['u_grade']>2){
            array_unshift($tree_rs, array('text'=>'..返回上级目录', 'fs_isdir'=>1, 'fs_id'=>$rs['fs_parent'], 'storable'=>false));
            }
            */
            $return_rs['rows'] = $tree_rs; 
            $return_rs['total'] = $count_arr[0];
            unset($tree_rs);
            return  $return_rs;
        }        


        /**
        * 文件列表 GRID
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        * @return object
        */
        public static function showhistory($data, $login_user_info){
            $verify = M_Usergroup::verify($login_user_info, 'lookuphistory');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $pagesize = intval($data['limit']);
            $start = intval($data['start']);
            $page = !empty($data['page'])?intval($data['page']):1;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            $limit = " limit " . $start . ",".$pagesize;
            $where = $fs_id ? " and fs_id='{$fs_id}' and log_type in('0', '1', '2', '3', '5')" :'';

            #获取统计总数、分页使用
            if($where){
                $sql =  "select count(*) from ".self::$doclog_table_name." where 1 ".$where;
                $count_arr = self::$db->get_col($sql);
                $sql = "select * from ".self::$doclog_table_name." as dl left join ".self::$usergroup_table. " as u on dl.log_user=u.u_id   where 1 ".$where." order by log_id desc ".$limit;
                $res = self::$db->get_results($sql);
                $rs = array();
                if(!empty($res)){
                    foreach($res as $k=>$v){
                        $rs[$k]['log_type'] = self::getOperatename($v['log_type']);
                        $rs[$k]['fs_id'] = $v['fs_id'];
                        $rs[$k]['fs_name'] = $v['fs_name'];
                        $rs[$k]['fs_hashname'] = $v['fs_hashname'];
                        $rs[$k]['fs_intro'] = $v['fs_intro'];
                        $rs[$k]['log_user'] = $v['u_name'];
                        $rs[$k]['fs_size'] = $v['fs_size'];
                        $rs[$k]['log_optdate'] = $v['log_optdate'];
                        $rs[$k]['fs_type'] = $v['fs_type'];
                    }
                }
                $return_rs['rows'] = $rs; 
                $return_rs['total'] = $count_arr[0];
                return  $return_rs;
            }else{
                return array();
            }
        }

        /**
        * 获取操作名称
        * 
        * @param mixed $opid
        * @return mixed
        */
        public static function getOperatename($opid){
            $op_arr = array('0'=>'创建', '1'=>'更新', '2'=>'改名', '3'=>'移动', '4'=>'删除', '5'=>'上传', '6'=>'下载', '7'=>'还原');
            return $op_arr[$opid];
        }

        /**
        * 搜索
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function search($data, $login_user_info){
            self::init();
            $fs_name = !empty($data['fs_name']) ? $data['fs_name'] : '';
            $fs_name_type = isset($data['fs_name_type']) ? intval($data['fs_name_type']) : '';
            $fs_intro = !empty($data['fs_intro']) ? $data['fs_intro'] : '';
            $fs_intro_type = isset($data['fs_intro_type']) ? intval($data['fs_intro_type']) : '';
            $workgroup_id = !empty($data['workgroup_id']) ? intval($data['workgroup_id']) : '';
            $user_id = !empty($data['user_id']) ? intval($data['user_id']) : '';
            $fs_type = !empty($data['fs_type']) ?  $data['fs_type'] : '';
            $from_date = !empty($data['from_date']) ? date('Y-m-d H:i:s', strtotime($data['from_date'])) : '';
            $to_date = !empty($data['to_date']) ? date('Y-m-d H:i:s', strtotime($data['to_date'])) : date('Y-m-d H:i:s');
            #分页参数
            $pagesize = isset($data['limit']) ? intval($data['limit']) : 50;
            $start = isset($data['start']) ? (intval($data['start'])<0?0:intval($data['start'])) : 0;
            $page = isset($data['page'])?intval($data['page']):1;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : ''; //父级目录ID或文件ID， 文件打开方式已修改为在线打开
            $limit = " limit " . $start . ",".$pagesize;

            $imgext_arr = array();
            $docext_arr = array();
            $where = '';
            $rs = array();
            if($fs_name){
                $where .= " and fs_name like '%{$fs_name}%' ";
                if($fs_name_type!==''){
                    $where .= " and fs_isdir='{$fs_name_type}' ";
                }
            }
            if($fs_intro){
                $where .= " and fs_intro like '%{$fs_intro}%' ";
                if($fs_intro_type!==''){
                    $where .= " and fs_isdir='{$fs_intro_type}' ";
                } 
            }
            if($workgroup_id){
                $where .= " and fs_group='{$workgroup_id}' ";  
            }
            if($user_id){
                $where .= " and fs_user='{$user_id}' ";
            }

            if(!empty($fs_type)){
                $fs_type_where = "'" . implode("','", $fs_type) . "'";
                $where .= " and fs_type in(".$fs_type_where.") ";
            }

            if($from_date){
                $where .= " and fs_create>'{$from_date}' and fs_create<'{$to_date}' ";
            }

            $where .= " and fs_parent!=0 ";//排除项目文件
            $where .= $fs_id ? " and fs_parent='{$fs_id}' " : '';

            $sql = "select count(*) from ". self::$document_table." where 1 " . $where;
            $count_arr = self::$db->get_col($sql);

            //$sql = "select * from ". self::$document_table." where 1 " . $where . $limit;
            if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3)
            {  
                //超级管理员|系统监察员|项目部负责人|部门负责人
                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where 1 " . $where . " order by fs_isdir asc, LENGTH(o), o asc";
            }
            elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2)
            {  
                //组管理员，组领导
                #todo 获取项目共享目录, 此处写死 ， 只有一个项目， 如有多个项目此处需要修改
                //$groupwhere .= ' and fs_parent IN (SELECT fs_id FROM fs_tree WHERE fs_isdir=2 AND fs_parent=1) OR fs_isdir=2 AND fs_parent=1 ';
                $groupwhere .= " and fs_group='{$login_user_info['u_parent']}' ";
                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o  from (select * from ".self::$document_table." where 1 ".$groupwhere.") as t2 where 1 ".$where." order by fs_isdir asc, LENGTH(o),o asc";
            }
            else
            {
                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name), fs_name) as o  from ".self::$document_table." where fs_user='{$login_user_info['u_id']}' ".$where." order by fs_isdir asc, LENGTH(o), o asc";
            }
            $sql .= $limit;
            //echo $sql;
            $res = self::$db->get_results($sql);
            if($res){
                foreach($res as $key=>&$value){
                    if($value['fs_parent']!=0){
                        $fs_textname = substr(self::getFilenamepath($value['fs_id']), 1);
                    }else{
                        $fs_textname = $value['fs_name'];
                    }
                    $value['text'] = $fs_textname; 
                    if(!($value['fs_isdir']==1 || $value['fs_isdir']==2)){
                        $type = strtolower($value['fs_type']);
                        $value['icon'] = self::getIconByType($type);
                    }
                    $value['id'] = $value['fs_id'];

                    $fs_fullpath = self::getParentpath($value['fs_id']);
                    $value['fs_fullpath'] = $fs_fullpath;
                    $value['leaf'] = $value['fs_isdir']==1 || $value['fs_isdir']==2?false:true; 
                }
                /*
                if($fs_id){
                #添加返回上级目录
                #获取当前节点信息
                $sql = "select * from ".self::$document_table." where fs_id='{$fs_id}' ";
                $rs = self::$db->get_row($sql);
                if($rs['fs_parent']!=0 && $login_user_info['u_grade']>2){
                array_unshift($res, array('text'=>'..返回上级目录', 'fs_isdir'=>1, 'fs_id'=>$rs['fs_parent']));
                }
                }
                */
                $rs['success'] = true;
                $rs['msg'] = 'OK';
                $rs['rows'] = $res;
                $rs['total'] = $count_arr[0];
                return $rs;
            } else{
                $rs['success'] = false;
                $rs['msg'] = '没有找到您要的数据！';
                $rs['rows'] = '';
                $rs['total'] = 0;
                return $rs;
            }
        }


        /**
        * 直接打开文件
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function openfile($data, $login_user_info){
            $fs_id = intval($data['fs_id']);
            //$file_hash = !empty($data['fs_fullpath']) ? addslashes($data['fs_fullpath']) : (!empty($data['file']) ? addslashes($data['file']) : '');
            $file_type = !empty($data['fs_type']) ? addslashes($data['fs_type']) : (!empty($data['t'])?addslashes($data['t']):'');
            /***/
            $file_hash = self::splitdocpath(self::getParentpath($fs_id));
            /*
            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
            if(!$verify){
            $rs['msg'] = '对不起，您没有此操作权限';
            $rs['success'] = false;
            return $rs;
            }
            */
            $file = PROJECT_DOC_URL . $file_hash . '.' . $file_type;;
            header("Location: $file");
            exit;
        }


        /**
        * 文件路径切分
        * 
        * @param string $path
        * @return mixed
        */
        public static function splitdocpath($path){
            if(empty($path)){
                return '';
            }
            $path = str_split($path, 16);
            $path = implode('/', $path);
            return DS.$path;
        }

        /**
        * 根据类型获取图片地址
        * 
        * @param mixed $type
        */
        public static function getIconByType($type){
            $pic_type_arr = array('jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp');    
            $video_type_arr = array('h264', '3gp', 'asx', 'avi', 'flv', 'mpg', 'mp4', 'mpeg', 'mkv', 'ogv', 'rm', 'rmvb', 'wmv', 'mov', 'divx', 'xvid');    
            $audio_type_arr = array('mp3', 'wma', 'wav', 'ogg', 'ra'); 
            if($type=='pdf'){
                $rs = 'image/ico/16x16/pdf-icon.png';
            } elseif(in_array($type, $pic_type_arr)){
                $rs = 'image/ico/16x16/picture-icon.png';
            } elseif(in_array($type,$video_type_arr)){
                $rs = 'image/ico/16x16/video-icon.png';
            } elseif(in_array($type,$audio_type_arr)){
                $rs = 'image/ico/16x16/audio-icon.png';
            } elseif($type=='ai'){
                $rs = 'image/ico/16x16/ai-icon.png';
            } elseif($type=='doc'){
                $rs = 'image/ico/16x16/doc-icon.png';
            } elseif($type=='docx'){
                $rs = 'image/ico/16x16/docx-icon.png';
            } elseif($type=='dwg'){
                $rs = 'image/ico/16x16/dwg-icon.png';
            } elseif($type=='ppt'){
                $rs = 'image/ico/16x16/ppt-icon.png';
            } elseif($type=='pptx'){
                $rs = 'image/ico/16x16/pptx-icon.png';
            } elseif($type=='psd'){
                $rs = 'image/ico/16x16/psd-icon.png';
            } elseif($type=='rar'){
                $rs = 'image/ico/16x16/rar-icon.png';
            } elseif($type=='swf'){
                $rs = 'image/ico/16x16/swf-icon.png';
            } elseif($type=='xls'){
                $rs = 'image/ico/16x16/xls-icon.png';
            } elseif($type=='xlsx'){
                $rs = 'image/ico/16x16/xlsx-icon.png';
            } elseif($type=='zip'){
                $rs = 'image/ico/16x16/zip-icon.png';
            } elseif($type=='7z'){
                $rs = 'image/ico/16x16/7z-icon.png';
            }elseif($type=='eml'){
                $rs = 'image/ico/16x16/eml-icon.png';
            } else {
                $rs = 'image/templates.png';
            }
            return $rs;
        }

        /**
        * 文件类型CHECKBOX列表
        * 
        */
        public static function listfiletype(){
            self::init();
            $sql = "select fs_type from " . self::$document_table . " where fs_type is not null group by fs_type";
            $rs = self::$db->get_results($sql);  //var_dump($rs);
            if(!empty($rs)){
                foreach($rs as $k=>$val){
                    $return[$k]['boxLabel'] = $val['fs_type'];
                    $return[$k]['name'] = 'fs_type[]';
                    $return[$k]['inputValue'] = $val['fs_type'];
                }
            }
            return $return;
        }


        /**
        * 查找目录下最大的编号
        * 
        * @param mixed $data
        */
        public static function getMaxfilecode($data){
            self::init();
            $parentid = intval($data['fs_parent']);
            $sql = "select * from " . self::$document_table . " where fs_parent='{$parentid}'";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                $regex = '/^[0-9]*?$/';
                $result = array();
                foreach($rs as $val){
                    if(preg_match($regex, $val['fs_name'])){
                        $result[] = $val['fs_name'];
                    }
                }
                sort($result);
                $resnum = (int)array_pop($result);
                return array('success'=>true, 'data'=>$resnum, 'msg'=>'ok');
            }else{
                return array('success'=>true, 'data'=>0, 'msg'=>'ok');
            }
        }

        /**
        * 根据UID获取文件列表
        * 
        * @param mixed $data
        * @param mixed $login_user_info 登录用户信息
        */
        public static function listdocbyuid($data, $login_user_info){
            self::init();
            $u_id = intval($data['u_id']);

            $pagesize = intval($data['limit']);
            $start = intval($data['start']);
            $page = !empty($data['page'])?intval($data['page']):1;
            $sortobj = isset($data['sort'])? json_decode($data['sort']) : array((object)array('property'=>'o', 'direction'=>'ASC'));
            $sort = $sortobj[0]->direction;
            $sortfield = $sortobj[0]->property=='text' ? 'o' : $sortobj[0]->property;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            $limit = " limit " . $start . ",".$pagesize;
            $where = $u_id ? " and fs_user='{$u_id}'" :'';

            #统计用户管理文件个数
            $sql =  "select count(*) from ".self::$document_table." where 1 " . $where;
            $count_arr = self::$db->get_col($sql);
            #获取用户管理文件列表
            $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o  from " . self::$document_table . " where 1 and fs_isdir=1 " . $where . " order by  fs_isdir " . $sort .',' . $sortfield .' '. $sort . $limit;;
            $res_doc = self::$db->get_results($sql);

            $tree_rs = array(); 
            if(!empty($res_doc)){
                foreach($res_doc as $key=>&$value){
                    if($value['fs_parent']!=0){
                        $fs_textname = substr(self::getFilenamepath($value['fs_id']), 1);
                    }else{
                        $fs_textname = $value['fs_name'];
                    }
                    $value['text'] = $fs_textname . '（'.$value['fs_intro'].'）'; 
                    if(!($value['fs_isdir']==1 || $value['fs_isdir']==2)){
                        $type = strtolower($value['fs_type']);
                        $value['icon'] = self::getIconByType($type);
                    }
                    $value['id'] = $value['fs_id'];

                    $fs_fullpath = self::getParentpath($value['fs_id']);
                    $value['fs_fullpath'] = $fs_fullpath;
                    $value['leaf'] = $value['fs_isdir']==1 || $value['fs_isdir']==2?false:true; 

                    $res_doc_arr[$value['fs_id']] = $value;
                    $res_fs_id[] = $value['fs_id'];
                    $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                }
                //var_dump($res_fs_parent_id);
                //$res_fs_parent_id = array_unique($res_fs_parent_id);
                foreach($res_fs_parent_id as $fs_id=>$parent_id){
                    if(in_array($parent_id, $res_fs_id)){
                        continue;
                    }else{
                        $tree_rs[] = $res_doc_arr[$fs_id];
                    }
                }
                unset($res_doc_arr);

            }
            $return_rs['rows'] = $tree_rs; 
            $return_rs['total'] = $count_arr[0];
            unset($tree_rs);
            return  $return_rs; 
        }


        /**
        * 获取所有目录列表， 用在生成目录文件时
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        * @return object
        */
        public static function listUserDocument($data, $login_user_info){
            self::init();

            $rs = array();
            if(!empty($login_user_info)){
                $where = ''; //' and fs_parent!=0 ';
                $sql = "select fs_id, fs_parent, fs_isdir, fs_group, fs_name, fs_intro, fs_size, fs_type, fs_haspaper, fs_user from ".self::$document_table." where 1 " . $where ;
                $rs = self::$db->get_results($sql); 
            }
            return $rs;
        }




        /**
        * 复制目录结构
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function copydocstruct($data, $login_user_info){
            self::init();
            $document_name = strip_tags(trim($data['document_newname']));
            $document_intro = strip_tags(trim($data['document_newintro']));

            $document_parentid = intval($data['document_parentid']);
            $current_doc_id = intval($data['current_doc_id']);

            #判断文件是否存在
            $checkresult = self::checkSamedoc($document_name, $document_parentid, 0, 1);  
            if($checkresult['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $document_name.'目录已经存在！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'adddocument', $document_parentid, 'share');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $parent_path = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($document_parentid)); //父级目录物理路径
            $hashname = parent::hashname(array($login_user_info['u_id'], $parent_path.DS.$document_name));  //当前目录HASH名称

            $rs = array();
            if(!$document_name){
                $rs['success'] = false;
                $rs['msg'] = '请添写目录编号';
                return $rs;
            }
            if(!$document_intro){
                $rs['success'] = false;
                $rs['msg'] = '请添写目录描述';
                return $rs;
            }
            if(!$document_parentid){
                $rs['success'] = false;
                $rs['msg'] = '请选择父级目录';
                return $rs;
            }
            if(empty($login_user_info)){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;  
            }

            /**开始创建目录 此目录需要循环创建和复制目录相同的目录结构**/
            #确定要COPY的目录结构

            $aimDir = $parent_path.DS. $hashname; 
            $rsfile = ZF_Libs_IOFile::mkdir($aimDir);   //建立目标目录
            //$oldDir = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($current_doc_id)); //COPY目录物理原始路径 
            $xx = self::getdocstruct($current_doc_id); var_dump($xx);die;

            $document_name = addslashes($document_name);
            $document_intro = addslashes($document_intro);
            $createtime = date('Y-m-d H:i:s');

            if($rsfile){
                $sql = "INSERT INTO ".self::$document_table." 
                SET fs_parent='{$document_parentid}',
                fs_group='{$login_user_info['u_parent']}',
                fs_user='{$login_user_info['u_id']}', 
                fs_isdir='1', 
                fs_create='$createtime', 
                fs_name='{$document_name}', 
                fs_intro='{$document_intro}',
                fs_hashname='{$hashname}'";
                $res = self::$db->query($sql);
                if($res){
                    $log_fs_id = self::$db->last_insert_id();
                    $fs_code = substr(self::getFilenamepath($log_fs_id), 1);
                    $rs['msg'] = '添加目录【'.$fs_code. '】成功';
                    $rs['success'] = true;
                    #记录文件操作日志
                    $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$document_name, 'fs_hashname'=>$hashname, 'fs_intro'=>$document_intro, 'fs_size'=>0, 'fs_type'=>'', 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$document_name, 'fs_code'=>$fs_code, 'fs_parent'=>$document_parentid);
                    //M_Log::doclog($doclog);
                    #记录系统日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加目录 '.$fs_code. ' 成功'));
                } else {
                    $rs['msg'] = '操作失败';
                    $rs['success'] = false;
                    #记录日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加目录失败'));
                }
                return $rs;
            }else{
                $rs['msg'] = '创建目录失败';
                $rs['success'] = false;
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建目录失败'));
                return $rs;
            } 
        }


        public static function getdocstruct($fs_id) { 
            self::init();
            $fs_id = intval($fs_id);
            $sql = "SELECT * FROM " . self::$document_table . " WHERE fs_parent='{$fs_id}' AND fs_isdir='1' ";
            $rs = self::$db->get_results($sql);die;
            if(!empty($rs)){
                foreach($rs as $key=>$val){
                    return $rs[$key]['children'] = self::getdocstruct($val['fs_id']);
                }
                return $rs;    
            }
        }

        /**
        * 验证用户是否已经创建过顶级共享文件夹
        * 
        * @param mixed $login_user_info
        */
        public static function ishaveshare($groupid){
            self::init();
            $sql = " select * from " . self::$document_table . " where fs_group='{$groupid}' and fs_parent='0' limit 1 ";
            $rs = self::$db->get_row($sql);
            if($rs){
                return false;    
            }else{
                return true;
            }
        }


        /**
        * 根据ID获取当前目录的面包屑
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function getnavdata($data, $login_user_info, $res=array()){
            self::init();
            $node_id = intval($data['fs_id']);
            $sql = "select * from " .self::$document_table. " where fs_id='$node_id'";
            $rs = self::$db->get_row($sql);
            if($rs['fs_parent']==0 || !$rs){
                return $res;
            }
            $res[] = $rs;
            if($rs['fs_parent']!=0){
                return self::getnavdata(array('fs_id'=>$rs['fs_parent']), $login_user_info, $res);
            }
            //return $res;
        } 


        /**
        * 判断此共享目录是否是用户可以操作的
        * 
        * @param mixed $targetfsid
        * @param mixed $login_user_info
        */
        public static function checksharedocpower($targetfsid, $login_user_info){
            self::init();
            if($targetfsid){
                $sql = "select * from " . self::$document_table . " where fs_id='{$targetfsid}'";
                $rs = self::$db->get_row($sql);
                if(!empty($rs)){
                    if($login_user_info['u_grade']=='0'){
                        if($rs['fs_user']==$login_user_info['u_id'] && $rs['fs_group']==$login_user_info['u_parent']){
                            return true;
                        }else{
                            return false;
                        }
                    }elseif($login_user_info['u_grade']=='1'){
                        if($rs['fs_group']==$login_user_info['u_parent']){
                            return true;
                        }else{
                            return false;
                        }
                    }else{
                        return true;
                    }
                }else{
                    return false;
                }
            }else{
                return false;
            }
        } 




        /*******************************************以下程序测试使用*********************************************/
        /**
        * 递归添加编号路径  手工添加使用
        * 
        * @param mixed $node_id
        */
        public static function setFilenamepath(){
            self::init();
            $sql = "update " .self::$document_table. " set fs_code='' where 1";
            self::$db->query($sql);
            $sql = "update ".self::$document_table ." set fs_code='test' where fs_id=1";
            self::$db->query($sql);
            $sql = "select * from ". self::$document_table." where 1";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    if(!$val['fs_code']){
                        $filecode = substr(self::getFilenamepath($val['fs_id']), 1); echo $filecode;
                        $sql = "update ".self::$document_table." set fs_code='{$filecode}' where fs_id='{$val['fs_id']}'";
                        self::$db->query($sql);
                    } 
                }
            }
        }

        /**
        * 递归添加文件ID路径  手工添加使用
        * 
        * @param mixed $node_id
        */
        public static function setFileIdpath(){
            self::init();
            $sql = "update " .self::$document_table. " set fs_id_path='' where 1";
            self::$db->query($sql);
            $sql = "update " .self::$document_table. " set fs_id_path='1' where fs_id=1";
            self::$db->query($sql);
            $sql = "select * from " .self::$document_table. " where 1";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    if(!$val['fs_id_path']){
                        $fs_id_path = substr(self::getFileIdpath($val['fs_id']), 1); echo $fs_id_path;
                        $sql = "update " .self::$document_table. " set fs_id_path='{$fs_id_path}' where fs_id='{$val['fs_id']}'";
                        self::$db->query($sql);
                    } 
                }
            }
        }
        
                /**
        * 移动文件
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function movedocument($data, $login_user_info){
            self::init();
            $nodeid = !empty($data['nodeid']) ? intval($data['nodeid']) : '';
            $oldparentid = !empty($data['oldparentid']) ? intval($data['oldparentid']) : '';
            $newparentid = !empty($data['newparentid']) ? intval($data['newparentid']) : '';

            #文件或文件夹物理路径
            $nodepath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($nodeid));
            $oldparentpath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($oldparentid));
            $newparentpath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($newparentid));

            $document_name = !empty($data['document_name']) ? $data['document_name'] : '';
            $nodehashname = !empty($data['nodehashname']) ? $data['nodehashname'] : '';
            $fs_type = !empty($data['fs_type']) ? $data['fs_type'] : '';
            $fs_size = !empty($data['fs_size']) ? intval($data['fs_size']) : 0;
            $fs_intro = !empty($data['fs_intro']) ? addslashes($data['fs_intro']) : '';
            $fs_isdir = !empty($data['fs_isdir']) ? intval($data['fs_isdir']) : '';

            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'movedocument', $nodeid);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if($fs_isdir==0){
                #判断文件是否存在
                $checkresult = self::checkSamedoc($document_name, $newparentid, $nodeid);
                if($checkresult['flag']==1){
                    $rs['success'] = false;
                    $rs['msg'] = '文件已经存在！';
                    return $rs;
                }else{ #开始移动文件
                    #记录移动操作日志
                    #旧路径 fs_code
                    //$log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    $sql = "select * from " . self::$document_table . " where fs_id='{$nodeid}'";
                    $oldfile_res = self::$db->get_row($sql);
                    if(!empty($oldfile_res['fs_code'])){
                        $log_oldfilepath = $oldfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    }
                    #新路径 fs_code
                    #$log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    $sql = "select * from " . self::$document_table . " where fs_id='{$newparentid}'";
                    $newfile_res = self::$db->get_row($sql);
                    if(!empty($newfile_res['fs_code'])){
                        $log_newfilepath = $newfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    }


                    $newhashname = parent::hashname($newparentpath.DS.$document_name);
                    $newfile = $newparentpath.DS.$newhashname.'.'.$fs_type;
                    if(!ZF_Libs_IOFile::moveFile($oldparentpath.DS.$nodehashname.'.'.$fs_type, $newfile)){
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录文件操作日志
                        //$doclog = array();
                        //M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作失败'));    
                        return $rs;
                    } else {
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        $fs_id_path = $newfile_res['fs_id_path'] . '-' . $nodeid;
                        $sql = "UPDATE " . self::$document_table . " SET 
                        fs_parent = '{$newparentid}',
                        fs_hashname= '{$newhashname}',
                        fs_code='{$fs_code}',
                        fs_id_path='{$fs_id_path}'
                        WHERE fs_id='{$nodeid}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$nodeid, 'fs_name'=>$document_name, 'fs_hashname'=>$newhashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>3, 'log_lastname'=>$document_name, 'fs_code'=>$log_oldfilepath, 'fs_parent'=>$newparentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作失败'));    
                            return $rs;
                        }

                    }
                }
            } elseif($fs_isdir==1) { //拖动目录
                #判断目录是否存在
                $checkresult = self::checkSamedoc($document_name, $newparentid, $nodeid, 1);  
                if($checkresult['flag']==1){
                    $rs['success'] = false;
                    $rs['msg'] = '目录已经存在！';
                    return $rs;
                }else{ #开始移动目录下的文件, （文件的HASH值暂不做处理，只改变当前目录的HASH值)
                    #记录移动操作日志
                    #旧路径 fs_code
                    //$log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    $sql = "select * from " . self::$document_table . " where fs_id='{$nodeid}'";
                    $oldfile_res = self::$db->get_row($sql);
                    if(!empty($oldfile_res['fs_code'])){
                        $log_oldfilepath = $oldfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    }
                    #新路径 fs_code
                    #$log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    $sql = "select * from " . self::$document_table . " where fs_id='{$newparentid}'";
                    $newfile_res = self::$db->get_row($sql);
                    if(!empty($newfile_res['fs_code'])){
                        $log_newfilepath = $newfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    }

                    #获取物理存储HASH码
                    $newhashname = parent::hashname($newparentpath.DS.$document_name);
                    if(!ZF_Libs_IOFile::moveFile($nodepath, $newparentpath.DS.$newhashname)){
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败的！';
                        #记录文件操作日志
                        //$doclog = array();
                        //M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'目录 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作失败'));    
                        return $rs;
                    } else {
                        #移动目录的fs_code
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        $fs_id_path = $newfile_res['fs_id_path'] . '-' . $nodeid; //展开目录树需要的字段数据
                        $sql = "UPDATE " . self::$document_table . " SET 
                        fs_parent = '{$newparentid}',
                        fs_hashname= '{$newhashname}', 
                        fs_code='{$fs_code}',
                        fs_id_path='{$fs_id_path}' 
                        WHERE fs_id='{$nodeid}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            #当前移动目录更新成功后需要对目录下的所有子目录的fs_code进行处理
                            self::dealwithmovefscode($nodeid, $fs_code, $fs_id_path, $newfile_res['fs_is_share'], $newfile_res['fs_encrypt']);
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$nodeid, 'fs_name'=>$document_name, 'fs_hashname'=>$newhashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>3, 'log_lastname'=>$document_name, 'fs_code'=>$log_oldfilepath, 'fs_parent'=>$newparentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'目录 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'目录 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 目录下 操作失败'));    
                            return $rs;
                        }

                    }
                }
            } else{
                $rs['success'] = false;
                $rs['msg'] = '操作失败！';
                return $rs;
            }
        }

        
        
    }
?>
