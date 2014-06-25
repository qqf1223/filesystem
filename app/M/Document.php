<?php
    /**
    * @name      M_Document.php
    * @describe   文件及文件夹管理Model类
    * @author    qinqf
    * @version   1.0 
    * @todo       
    * @changelog  
    */  
    class M_Document extends M_Model{

        static $db;
        static $usergroup_table='fs_user';
        static $document_table = 'fs_tree';
        static $share_document_table = 'fs_share_tree';
        static $user_share_document = 'fs_user_sharedoc';
        static $doclog_table_name = 'fs_log'; 
        /*** 初始化操作 */
        public static function init(){
            self::$db = parent::init();    
        }

        /**
        * 根据条件获取文件树
        * 
        * @param int $project_id
        * @return string
        */
        public static function docmenttree($data, $login_user_info, $refresh=0){
            $verify = M_Usergroup::verify($login_user_info, 'readdocument');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : 0;
            $uid = $login_user_info['u_id'];
            $showshare = isset($data['showshare']) ? $data['showshare'] : ''; //共享文件夹是否显示
            $where = $fs_id  ? " fs_parent='{$fs_id}' and" : ' fs_isdir=1 and';//" and (fs_id='{$fs_id}' or fs_parent='{$fs_id}') ";

            //查询用户权限
            $tree_rs = array();
            if(!empty($login_user_info)){
                if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){  //超级管理员|项目部负责人|部门负责人
                    $where .= $fs_id ? '' : ' fs_parent=0 and';
                     #20140305 添加项目部负责人|部门负责人不可以查看加密文件
                    if($login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){ 
                        $where .= " (fs_encrypt!='1' or (fs_encrypt='1' and fs_user='{$login_user_info['u_id']}' )) and";
                    }
                }elseif($login_user_info['u_grade']==98 || $login_user_info['u_grade']==99 ){ #系统监察员| 系统管理员 单独处理， 对加密的文件此用户不可以看到
                    $where .= $fs_id ? '' : ' fs_parent=0 and';
                    $where .= " fs_encrypt!='1' and";
                }elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){  //组管理员，组领导
                    #2013、10、11 修改 组对应为管理的那个组
                    $where .= $fs_id ? "" : " fs_group='{$login_user_info['u_targetgroup']}' and";
                    if($login_user_info['u_grade']==1){  #20130916 添加组管理员不可以查看加密文件
                        $where .= " (fs_encrypt!='1' or (fs_encrypt='1' and fs_user='{$login_user_info['u_id']}' )) and";
                    }
                }else{
                    $where .= " fs_user='{$login_user_info['u_id']}' and";
                }
                $where = substr($where, 0, -3);
                #方案四： 全部取出，然后进行排序
                $sql = "select * from " . self::$document_table . " where " . $where;
                //echo $sql;

                $res_doc = self::$db->get_results($sql);
                $res_doc = false === $res_doc ? array() : $res_doc;

                //列出共享文件夹
                if($login_user_info['u_grade']==0 && $showshare!='1'){
                    $user_share_folder = array();
                    #获取用户所有共享的文件夹
                    $sql = "select  fs_parent from ".self::$user_share_document . " where u_id='{$login_user_info['u_id']}'";
                    $allshare_tmp = self::$db->get_col($sql);
                    $all_share = $allshare_tmp ? $allshare_tmp : array();

                    #判断当前传入的fs_id对应的文件夹是否为共享文件夹、
                    if($fs_id){
                        $sql = "select * from fs_tree where fs_id='{$fs_id}'";
                        $current_node = self::$db->get_row($sql);
                        if($current_node){
                            if($current_node['fs_is_share']){
                                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where fs_parent='{$fs_id}' order by fs_isdir asc, LENGTH(o), o asc";
                                $user_share_folder = self::$db->get_results($sql);
                            }elseif(in_array($fs_id, $all_share)){
                                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where u_id='{$login_user_info['u_id']}' order by fs_isdir asc, LENGTH(o), o asc";
                                $user_share_folder = self::$db->get_results($sql);
                            }
                        }  
                    }else{
                        $sql = "select t.* from fs_user_sharedoc as s left join fs_tree as t on s.fs_id=t.fs_id where u_id='{$login_user_info['u_id']}' ";
                        $user_share_folder = self::$db->get_results($sql);
                    }

                    if($user_share_folder){
                        foreach($user_share_folder as $f){
                            $res_doc[] = $f;
                        }
                    }    
                }

                if($res_doc){
                    foreach($res_doc as $key=>$value){
                        $res_doc_arr[$value['fs_id']] = $value;
                        $res_fs_id[] = $value['fs_id'];
                        $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                    }
                    if($login_user_info['u_grade']<90){ 
                        //$res_fs_parent_id = array_unique($res_fs_parent_id);
                        foreach($res_fs_parent_id as $fsid=>$parent_id){
                            if(in_array($parent_id, $res_fs_id)){
                                continue;
                            }else{
                                if(!$fs_id){
                                    //第一次打开没有展开文件夹树的时候
                                    //$xx = explode('-', $res_doc_arr[$fsid]['text']);
                                    $tree_rs[$fsid][] = $res_doc_arr[$fsid];
                                }else{
                                    $tree_rs[] = $res_doc_arr[$fsid]; 
                                }

                            }
                        }

                        if(!$fs_id){
                            //ksort($tree_rs);
                            foreach($tree_rs as $val){
                                if(!empty($val) && is_array($val)){
                                    foreach($val as $v){
                                        $tree_rs_tmp[] = $v;
                                    }  
                                }
                            }
                            unset($tree_rs);
                            $tree_rs = $tree_rs_tmp;
                            unset($tree_rs_tmp);
                        }
                        unset($res_doc_arr);
                    }else{
                        $tree_rs = $res_doc;
                    }
                }

                if(!empty($tree_rs)){
                    foreach($tree_rs as $s=>&$value){
                        #fs_textname 修改为读取fs_code字段
                        if(!empty($value['fs_code'])){
                            $fs_textname = $value['fs_code']; 
                        }else{
                            if($value['fs_parent']!=0){
                                $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                            }else{
                                $fs_textname = $value['fs_name'];
                            } 
                        }

                        $value['text'] = $fs_textname . '（'.$value['fs_intro'].'）'; 
                        if(!($value['fs_isdir']==1)){
                            $type = strtolower($value['fs_type']);
                            $value['icon'] = self::getIconByType($type);
                        }
                        $value['id'] = $value['fs_id'];
                        $value['fs_code'] = $fs_textname;
                        //if(!empty($value['fs_fullpath'])){
                        if(0){
                            $fs_fullpath = $value['fs_fullpath'];
                        }else{
                            $fs_fullpath = M_Document::getParentpath($value['fs_id']);
                            $update_fullpath = "update " . self::$document_table . " set fs_fullpath='{$fs_fullpath}' where fs_id='{$value['fs_id']}'";
                            self::$db->query($update_fullpath);
                        }
                        //$fs_fullpath = M_Document::getParentpath($value['fs_id']);
                        $value['fs_fullpath'] = $fs_fullpath;
                        $value['leaf'] = $value['fs_isdir']==1?false:true; 
                        #添加对文件夹的编辑权限， 没有fs_id的时候所能看到的文件夹应该都是上一级管理人员分配过来的文件夹
                        if($fs_id){
                            $value['managerok'] = true; 
                        }else{
                            $value['managerok'] = false;
                        }
                        if($value['fs_is_share'] && $value['fs_user']!=$login_user_info['u_id'] && $login_user_info['u_grade']=='0')                                  {
                            $value['iconCls'] = 'icon-share-doc-setting';
                        }
                        if($login_user_info['u_grade']=='10'){
                            $sql = "select * from " . self::$document_table . " where fs_parent='{$value['fs_id']}' and fs_user!='{$value['fs_user']}' limit 1";
                            if(self::$db->get_row($sql)){
                                unset($tree_rs[$s]);
                            }
                        }
                    }

                    $tree_rs = self::multiSort($tree_rs, 'fs_code');
                    //
                }
            }

            return  $tree_rs;
        }

        //排序
        public static function multiSort() { 
            //get args of the function 
            $args = func_get_args(); 
            $c = count($args); 
            if ($c < 2) { 
                return false; 
            } 
            $sortarr = array('ASC', 'DESC');
            $sort = in_array($args[$c-1], $sortarr) ? $args[$c-1] : 'ASC';
            //get the array to sort 
            $array = array_splice($args, 0, 1);
            $array = $array[0]; 
            //sort with an anoymous function using args
            if($sort=='ASC'){ 
                usort($array, function($a, $b) use($args) {
                    $i = 0; 
                    $sortarr = array('ASC', 'DESC');
                    $c = count($args);
                    if(in_array($args[$c-1], $sortarr)){$c=count($args)-1;} 
                    $cmp = 0; 
                    while($cmp == 0 && $i < $c) 
                    {
                        $cmp = strnatcmp($a[$args[$i]], $b[ $args[$i]]); 
                        $i++; 
                    }
                    return $cmp; 
                }); 
            }else{
                usort($array, function($a, $b) use($args) {
                    $i = 0; 
                    $sortarr = array('ASC', 'DESC');
                    $c = count($args); 
                    if(in_array($args[$c-1], $sortarr)){$c=count($args)-1;} 
                    $cmp = 0; 
                    while($cmp == 0 && $i < $c) 
                    {
                        $cmp = strnatcmp($a[$args[$i]], $b[ $args[$i]]); 
                        $i++; 
                    }
                    return -$cmp; 
                });   
            }

            return $array; 

        } 
        public static function sort_by($field, &$arr, $sorting=SORT_ASC, $case_insensitive=true){
            if(is_array($arr) && (count($arr)>0) && ( ( is_array($arr[0]) && isset($arr[0][$field]) ) || ( is_object($arr[0]) && isset($arr[0]->$field) ) ) ){
                if($case_insensitive==true) $strcmp_fn = "strnatcasecmp";
                else $strcmp_fn = "strnatcmp";

                if($sorting==SORT_ASC){
                    $fn = create_function('$a,$b', '
                    if(is_object($a) && is_object($b)){
                    return '.$strcmp_fn.'($a->'.$field.', $b->'.$field.');
                    }else if(is_array($a) && is_array($b)){
                    return '.$strcmp_fn.'($a["'.$field.'"], $b["'.$field.'"]);
                    }else return 0;
                    ');
                }else{
                    $fn = create_function('$a,$b', '
                    if(is_object($a) && is_object($b)){
                    return '.$strcmp_fn.'($b->'.$field.', $a->'.$field.');
                    }else if(is_array($a) && is_array($b)){
                    return '.$strcmp_fn.'($b["'.$field.'"], $a["'.$field.'"]);
                    }else return 0;
                    ');
                }
                usort($arr, $fn);
                return true;
            }else{
                return false;
            }
        }

        /**
        * 根据用户ID获取用户文件树
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        * @return object
        */
        public static function listusertree($data, $login_user_info){
            $verify = M_Usergroup::verify($login_user_info, 'readdocument');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : 0;
            $u_id = intval($data['u_id']); 
            $where = $fs_id  ? " fs_parent={$fs_id} and " : '';
            #根据用户ID获取用户信息
            $user_info = M_Usergroup::getuserinfo($u_id);
            if(!empty($user_info['u_grade'])){
                $u_grade = explode(',', $user_info['u_grade']);
                sort($u_grade);
                $user_info['u_grade'] = array_pop($u_grade);
            }

            //查询用户权限
            $tree_rs = array();
            if(!empty($user_info)){
                if($user_info['u_grade']==100 || $user_info['u_grade']==99 || $user_info['u_grade']==98 || $user_info['u_grade']==4 || $user_info['u_grade']==3){  //超级管理员|系统监察员|项目部负责人|部门负责人
                    $where .= $fs_id ? '' : ' fs_parent=0 and';
                }elseif($user_info['u_grade']==1 || $user_info['u_grade']==2){  //组管理员，组领导
                    $where .= $fs_id ? "" : " fs_group='{$user_info['u_targetgroup']}' and";
                }else{
                    $where .= " fs_user='{$user_info['u_id']}' and";
                }
                
                #不可以查看加密文件
                if($login_user_info['u_grade']==4 || $login_user_info['u_grade']==3 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==1){ 
                    $where .= " fs_encrypt!='1' and";
                }
                $where = substr($where, 0, -3);               
                $sql = "select * from " . self::$document_table . " where " . $where;
                //echo $sql;
                $res_doc = self::$db->get_results($sql);
                if($res_doc){
                    foreach($res_doc as $key=>$value){
                        $res_doc_arr[$value['fs_id']] = $value;
                        $res_fs_id[] = $value['fs_id'];
                        $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                    }
                    if($user_info['u_grade']<90){ 
                        foreach($res_fs_parent_id as $fsid=>$parent_id){
                            if(in_array($parent_id, $res_fs_id)){
                                continue;
                            }else{
                                if(!$fs_id){
                                    //第一次打开没有展开文件夹树的时候
                                    //$xx = explode('-', $res_doc_arr[$fsid]['text']);
                                    $tree_rs[$fsid][] = $res_doc_arr[$fsid];
                                }else{
                                    $tree_rs[] = $res_doc_arr[$fsid]; 
                                }
                            }
                        }

                        if(!$fs_id){
                            //ksort($tree_rs);
                            foreach($tree_rs as $val){
                                if(!empty($val) && is_array($val)){
                                    foreach($val as $v){
                                        $tree_rs_tmp[] = $v;
                                    }  
                                }
                            }
                            unset($tree_rs);
                            $tree_rs = $tree_rs_tmp;
                            //unset($tree_rs_tmp);
                        }
                        unset($res_doc_arr);
                    }else{
                        $tree_rs = $res_doc;
                    }
                }

                if(!empty($tree_rs)){
                    foreach($tree_rs as &$value){
                        #fs_textname 修改为读取fs_code字段
                        if(!empty($value['fs_code'])){
                            $fs_textname = $value['fs_code']; 
                        }else{
                            if($value['fs_parent']!=0){
                                $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                            }else{
                                $fs_textname = $value['fs_name'];
                            } 
                        }
                        $value['text'] = $fs_textname . '（'.$value['fs_intro'].'）'; 
                        if($value['fs_isdir']==0){
                            $type = strtolower($value['fs_type']);
                            $value['icon'] = self::getIconByType($type);
                        }
                        $value['id'] = $value['fs_id'];

                        $fs_fullpath = M_Document::getParentpath($value['fs_id']);
                        $value['fs_fullpath'] = $fs_fullpath;
                        $value['leaf'] = $value['fs_isdir']==1 ?false:true; 
                        #添加对文件夹的编辑权限， 没有fs_id的时候所能看到的文件夹应该都是上一级管理人员分配过来的文件夹
                        if($fs_id){
                            $value['managerok'] = true; 
                        }else{
                            $value['managerok'] = false;
                        }
                    }

                    $tree_rs = self::multiSort($tree_rs, 'fs_code');
                }
            }

            return  $tree_rs;
        }

        /**
        * 添加文件夹方法 
        * 
        *
        */
        public static function adddocument($data, $login_user_info) {
            self::init();
            $document_name = isset($data['project_doc_name']) ? addslashes(strip_tags(trim($data['project_doc_name']))) : '';
            $document_parentid = isset($data['project_doc_parentid']) ? intval($data['project_doc_parentid']) : '';
            $document_intro = isset($data['project_doc_intro']) ? addslashes(strip_tags(trim($data['project_doc_intro']))) : '';

            #是否加密
            $encrypt = isset($data['encrypt']) ? intval($data['encrypt']) : 0;

            #判断文件是否存在
            $checkresult = self::checkSamedoc($document_name, $document_parentid, 0, 1);  
            if($checkresult['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $document_name.'文件夹已经存在！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'adddocument', $document_parentid);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $parent_path = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($document_parentid)); //父级文件夹
            $hashname = parent::hashname(array($login_user_info['u_parent'], $login_user_info['u_id'], $parent_path.DS.$document_name));  //当前文件夹名称

            $rs = array();
            if(!$document_name){
                $rs['success'] = false;
                $rs['msg'] = '请添写文件夹编号';
                return $rs;
            }
            if(!$document_intro){
                $rs['success'] = false;
                $rs['msg'] = '请添写文件夹描述';
                return $rs;
            }
            if(!$document_parentid){
                $rs['success'] = false;
                $rs['msg'] = '请选择父级文件夹';
                return $rs;
            }
            if(empty($login_user_info)){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;  
            }

            #获取父级文件夹的权限
            #2013-08-11  修改添加的文件夹的权限继承文件夹的权限
            #查询文件夹权限
            $sql = "select * from " . self::$document_table . " where fs_id='{$document_parentid}' ";
            $parentDoc = self::$db->get_row($sql);

            /**开始创建项目文件夹**/
            $current_doc_dir = $parent_path.DS. $hashname;  
            $rsfile = ZF_Libs_IOFile::mkdir($current_doc_dir);

            $createtime = date('Y-m-d H:i:s');
            #生成文件编号， 查询使用
            if($parentDoc['fs_parent']==0){ //如果父级节点是项目
                $fs_code = $document_name;    
            } else { //如果父级节点不是项目
                if($parentDoc['fs_code']){
                    $fs_code = $parentDoc['fs_code'] . '-' . $document_name;
                }else{
                    $fs_code = substr(M_Document::getFilenamepath($document_parentid), 1) . '-' . $document_name;  
                }
            }

            if($rsfile){
                if($parentDoc['fs_parent']!=0){
                    $sql = "INSERT INTO ".self::$document_table." 
                    SET fs_parent='{$document_parentid}',
                    fs_group='{$parentDoc['fs_group']}',
                    fs_user='{$parentDoc['fs_user']}', 
                    fs_isdir='1', 
                    fs_create='{$createtime}', 
                    fs_name='{$document_name}', 
                    fs_intro='{$document_intro}',
                    fs_encrypt='{$encrypt}',
                    fs_hashname='{$hashname}', 
                    fs_code='{$fs_code}',
                    fs_is_share='{$parentDoc['fs_is_share']}'"; 
                } else{
                    $sql = "INSERT INTO ".self::$document_table." 
                    SET fs_parent='{$document_parentid}',
                    fs_group='{$login_user_info['u_parent']}',
                    fs_user='{$login_user_info['u_id']}', 
                    fs_isdir='1', 
                    fs_create='{$createtime}', 
                    fs_name='{$document_name}', 
                    fs_intro='{$document_intro}',
                    fs_encrypt='{$encrypt}',
                    fs_hashname='{$hashname}', 
                    fs_code='{$fs_code}'";
                }

                $res = self::$db->query($sql);
                if($res){
                    $log_fs_id = self::$db->last_insert_id();

                    #生成文件ID路径编号， 展开文件夹树时使用 , 此处更新结果未做判断
                    if($parentDoc['fs_parent']==0){ //如果父级节点是项目
                        $fs_id_path = $log_fs_id;
                    }else{
                        $fs_id_path = $parentDoc['fs_id_path'] . '-' . $log_fs_id;
                    }
                    $sql = "update " . self::$document_table . " set fs_id_path='{$fs_id_path}' where fs_id='{$log_fs_id}' ";
                    self::$db->query($sql);

                    $rs['msg'] = '添加文件夹【'.$fs_code. '】成功';
                    $rs['success'] = true;
                    #记录文件操作日志
                    $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$document_name, 'fs_hashname'=>$hashname, 'fs_intro'=>$document_intro, 'fs_size'=>0, 'fs_type'=>'', 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$document_name, 'fs_code'=>$fs_code, 'fs_parent'=>$document_parentid);
                    M_Log::doclog($doclog);
                    #记录系统日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加文件夹 '.$fs_code. ' 成功'));
                } else {
                    $rs['msg'] = '操作失败';
                    $rs['success'] = false;
                    #记录日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加文件夹失败'));
                }
                return $rs;
            }else{
                $rs['msg'] = '创建文件件夹失败';
                $rs['success'] = false;
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建文件件夹失败'));
                return $rs;
            }
        }


        /**
        * 对文件件夹进行编辑
        * 
        * @param mixed $data
        * @return string
        */
        public static function editdocument($data, $login_user_info) {
            self::init(); 
            $document_name = !empty($data['project_doc_name']) ? addslashes(strip_tags(trim($data['project_doc_name']))) : '';
            $document_oldname = !empty($data['project_doc_oldname']) ? addslashes(strip_tags(trim($data['project_doc_oldname']))) : '';
            $document_parentid = !empty($data['document_parentid']) ? intval($data['document_parentid']) : '';
            $document_id = isset($data['project_doc_id']) ? intval($data['project_doc_id']) : '';
            $document_oldintro = addslashes(strip_tags(trim($data['project_doc_oldintro'])));
            $document_intro = addslashes(strip_tags(trim($data['project_doc_intro'])));
            #是否加密
            $encrypt = isset($data['encrypt']) ? intval($data['encrypt']) : 0;
            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editdocument', $document_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(empty($document_name)){
                $rs['success'] = false;
                $rs['msg'] = '请输入文件件夹编号';
                return $rs;
            }
            if(empty($document_intro)){
                $rs['success'] = false;
                $rs['msg'] = '请输入文件件夹说明';
                return $rs;
            }
            if(!$document_id){
                $rs['success'] = false;
                $rs['msg'] = '请选择需要编辑的文件件夹';
                return $rs;
            }
            if(!$login_user_info['u_id']){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;
            }

            #判断当前项目下此文件已存在
            $ret = M_Document::checkSamedoc($document_name, $document_parentid, $document_id, 1);
            if($ret['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $document_name.'已经存在！';
                return $rs;
            }

            $hashname = $ret['data']['fs_hashname']; #当前文件件夹hashname, 记录日志使用

            $old_fs_code = $ret['data']['fs_code'];
            #获取上级文件件夹的fs_code
            $sql = "select * from " . self::$document_table . " where fs_id='{$document_parentid}' and fs_isproject='0'";
            $row_parent = self::$db->get_row($sql);
            if(!empty($row_parent)){
                $new_fs_code = $row_parent['fs_code'] . '-' . $document_name;
            }else{
                $new_fs_code = $document_name;
            }
            #编辑日期
            $edittime = date('Y-m-d H:i:s');

            /**开始数据库操作**/
            $sql = "UPDATE ".self::$document_table." SET 
            fs_lastmodify='{$edittime}', 
            fs_name='{$document_name}',
            fs_intro='{$document_intro}',
            fs_encrypt='{$encrypt}',
            fs_code='{$new_fs_code}' 
            WHERE fs_id='$document_id'";
            $res = self::$db->query($sql);
            if($res){
                #当前移动文件件夹更新成功后需要对文件件夹下的所有子文件件夹的fs_code, fs_encrypt进行处理
                self::dealwithmovefscode($document_id, $new_fs_code, $ret['data']['fs_id_path'], $ret['data']['fs_is_share'], $encrypt);
                $rs['msg'] = '操作成功';
                $rs['data'] = array('document_name'=>$document_name,'document_pathname'=>$new_fs_code, 'document_intro'=>$document_intro);
                $rs['success'] = true;
                #记录文件操作日志
                $doclog = array('fs_id'=>$document_id, 'fs_name'=>$document_name, 'fs_intro'=>$document_intro, 'fs_size'=>0, 'fs_type'=>'', 'fs_hashname'=>$hashname,'log_user'=>$login_user_info['u_id'], 'log_type'=>2, 'log_lastname'=>$document_name);
                M_Log::doclog($doclog);
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'修改文件件夹编号 '.$old_fs_code.' 为 '.$new_fs_code.' 文件件夹名称由 '.$document_oldintro.' 修改为 '.$document_intro.' 操作成功'));
                return $rs;
            } else {
                $rs['msg'] = '操作失败';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'修改文件件夹编号 '.$old_fs_code.' 为 '.$new_fs_code.' 文件件夹名称由 '.$document_oldintro.' 修改为 '.$document_intro.' 操作失败'));
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
            $file_name = addslashes(strip_tags(trim($data['file_name'])));
            $file_oldname = addslashes(strip_tags(trim($data['file_oldname'])));
            $file_parentid = intval(strip_tags(trim($data['file_parentid'])));
            $file_id = intval($data['file_id']);
            $file_intro = addslashes(strip_tags(trim($data['file_intro'])));
            $file_encrypt = intval($data['encrypt']);
            $file_haspaper = intval($data['haspaper']);
            $file_size = intval($data['size']);
            $file_type = intval($data['type']);

            #用户权限验证
            $verify = M_Usergroup::verify($login_user_info, 'editdocument', $file_id);
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
            $ret = M_Document::checkSamedoc($file_name, $file_parentid, $file_id);
            if($ret['flag']==1){
                $rs['success'] = false;
                $rs['msg'] = $file_name.'已经存在！';
                return $rs;
            }
            $edittime = date('Y-m-d H:i:s');
            $old_fs_code = $ret['data']['fs_code'];
            #获取上级文件件夹的fs_code
            $sql = "select fs_code from " . self::$document_table . " where fs_id='{$file_parentid}'";
            $row_parent = self::$db->get_col($sql);
            $new_fs_code = '';
            if(!empty($row_parent)){
                $new_fs_code = $row_parent[0] . '-' . $file_name;
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
            //$file_textname = substr(self::getFilenamepath($file_id), 1);
            if($res){
                $rs['msg'] = '操作成功';
                $rs['success'] = true;
                $rs['data'] = array('document_name'=>$file_name,'document_pathname'=>$new_fs_code, 'document_intro'=>$file_intro);
                #记录文件操作日志
                $doclog = array('fs_id'=>$file_id, 'fs_name'=>$file_name, 'fs_intro'=>$file_intro, 'fs_size'=>$file_size, 'fs_type'=>$ret['data']['fs_type'], 'fs_hashname'=>$ret['data']['fs_hashname'],'log_user'=>$login_user_info['u_id'], 'log_type'=>2, 'log_lastname'=>$file_name);
                M_Log::doclog($doclog);
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
        /***************************************************************************************************/
        /**
        * 添加项目文件件夹
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function addproject($data, $login_user_info){
            $verify = M_Usergroup::verify($login_user_info, 'addproject');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            $project_name = strip_tags(trim($data['projectname']));
            $project_intro = strip_tags(trim($data['projectintro']));
            $login_user_id = intval($login_user_info['u_id']);
            $login_user_group = intval($login_user_info['u_parent']);
            $rs = array();
            if(!$login_user_id){
                $rs['msg'] = '请登录后进行操作';
                $rs['success'] = false;
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'登录失效'));
                return $rs;
            }
            self::init();
            #判断项目是否已经添加
            $sql = "SELECT * FROM ".self::$document_table." WHERE fs_name='{$project_name}' and fs_parent=0";
            $haspro = self::$db->get_row($sql);
            if(false !== $haspro){
                $rs['success'] = false;
                $rs['msg'] = '项目已存在！';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建同名项目'.$project_name.'文件件夹失败'));
                return $rs;
            }

            if(empty($rs)){  //验证成功
                self::init();
                $project_name = addslashes($project_name);
                $project_intro = addslashes($project_intro);
                $createtime = date('Y-m-d H:i:s');
                $hashname = parent::hashname($project_name);
                /**开始创建项目文件件夹**/
                $rsfile = ZF_Libs_IOFile::mkdir(PROJECT_DOC_PATH . DS . $hashname);
                if($rsfile){
                    $sql = "INSERT INTO ".self::$document_table." 
                    SET fs_parent=0, 
                    fs_user='{$login_user_id}',
                    fs_isdir=1, 
                    fs_group='{$login_user_group}',
                    fs_create='{$createtime}',
                    fs_name='{$project_name}',
                    fs_intro='{$project_intro}',
                    fs_hashname='{$hashname}',
                    fs_code='{$project_name}', 
                    fs_isproject='1'
                    ";
                    $res = self::$db->query($sql);
                    if($res){
                        $rs['msg'] = '添加项目'.$project_name. '成功';
                        $rs['success'] = true;
                        #记录文件件夹操作日志
                        $log_fs_id = self::$db->last_insert_id();
                        #记录文件操作日志
                        $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$project_name, 'fs_intro'=>$project_intro, 'fs_size'=>0, 'fs_type'=>0, 'fs_hashname'=>$hashname,'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$project_name);
                        M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加项目'.$project_name. '成功'));
                    } else {
                        $rs['msg'] = '操作失败';
                        $rs['success'] = false;
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'添加项目'.$project_name. '失败'));
                    }
                }else{
                    $rs['msg'] = '创建文件件夹失败';
                    $rs['success'] = false;

                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建项目文件件夹失败'));
                }
            }
            return $rs;
        }


        /**
        * 递归获取物理文件件夹路径
        * 
        * @param mixed $node_id
        */
        public static function getParentpath($node_id){
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                self::init();
                $sql = "select * from fs_tree where fs_id='$node_id'";
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
                $sql = "select * from fs_tree where fs_id='$node_id'";
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
        * 递归获取ID拼接的路径， 用于文件件夹树的展开（用名称fs_name会出现文件和文件夹同名的时候会报错）
        * 
        * @param mixed $node_id
        */
        public static function getFileIdpath($node_id){
            self::init();
            if($node_id==0){
                return ''; //PROJECT_DOC_PATH;
            } else {
                $sql = "select * from fs_tree where fs_id='$node_id'";
                $rs = self::$db->get_row($sql);
                if($rs){
                    $fs_id = $rs['fs_parent'];
                    if($rs['fs_parent']!=0){
                        return self::getFileIdpath($rs['fs_parent']) . '-' . $rs['fs_id']; 
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
                $sql = "select * from fs_tree where fs_id='$node_id'";
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
        * 分配文件件夹权限
        * 
        * @param mixed $data
        */
        public static function adddocpower($data, $login_user_info){
            self::init();
            $workgroup_id = intval($data['workgroup_id']);
            $user_id = intval($data['powersetting_user_id']);
            $project_doc_id = intval($data['project_doc_id']);
            #记录日志使用
            $project_doc_name = $data['project_doc_name']; 
            $login_user_id = $login_user_info['u_id'];
            $login_user_group = $login_user_info['u_parent'];
            $login_user_name = $login_user_info['u_name'];
            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'powersetting', $project_doc_id);
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
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$project_doc_name.' 文件件夹权限分配成功'));
                        } else{
                            $rs['success'] = false;
                            $rs['msg'] = '权限分配失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$project_doc_name.' 文件件夹权限分配失败'));
                        }
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '请选择文件件夹';
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
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作失败'));    
                        return $rs;
                    } else {
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        $fs_id_path = $newfile_res['fs_id_path'] . '-' . $nodeid;
                        $sql = "UPDATE " . self::$document_table . " SET 
                        fs_parent = '{$newparentid}',
                        fs_hashname= '{$newhashname}',
                        fs_code='{$fs_code}',
                        fs_id_path='{$fs_id_path}',
                        fs_is_share='{$newfile_res['fs_is_share']}'  
                        WHERE fs_id='{$nodeid}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$nodeid, 'fs_name'=>$document_name, 'fs_hashname'=>$newhashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>3, 'log_lastname'=>$document_name, 'fs_code'=>$log_oldfilepath, 'fs_parent'=>$newparentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作失败'));    
                            return $rs;
                        }

                    }
                }
            } elseif($fs_isdir==1) { //拖动文件件夹
                #判断文件件夹是否存在
                $checkresult = self::checkSamedoc($document_name, $newparentid, $nodeid, 1);  
                if($checkresult['flag']==1){
                    $rs['success'] = false;
                    $rs['msg'] = '文件件夹已经存在！';
                    return $rs;
                }else{ #开始移动文件件夹下的文件, （文件的HASH值暂不做处理，只改变当前文件件夹的HASH值)
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
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作失败'));    
                        return $rs;
                    } else {
                        #移动文件件夹的fs_code
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        $fs_id_path = $newfile_res['fs_id_path'] . '-' . $nodeid; //展开文件件夹树需要的字段数据
                        $sql = "UPDATE " . self::$document_table . " SET 
                        fs_parent = '{$newparentid}',
                        fs_hashname= '{$newhashname}', 
                        fs_code='{$fs_code}',
                        fs_id_path='{$fs_id_path}', 
                        fs_is_share='{$newfile_res['fs_is_share']}'  
                        WHERE fs_id='{$nodeid}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            #当前移动文件件夹更新成功后需要对文件件夹下的所有子文件件夹的fs_code进行处理
                            self::dealwithmovefscode($nodeid, $fs_code, $fs_id_path, $newfile_res['fs_is_share'], $newfile_res['fs_encrypt']);
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$nodeid, 'fs_name'=>$document_name, 'fs_hashname'=>$newhashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>3, 'log_lastname'=>$document_name, 'fs_code'=>$log_oldfilepath, 'fs_parent'=>$newparentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 文件件夹下 操作失败'));    
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

        /**
        * 递归处理移动文件夹过程对fs_code的更新操作
        * 
        * @param mixed $nodeid
        */
        public static function dealwithmovefscode($nodeid, $nodefilecode, $nodefileidpath='', $fs_is_share=0, $fs_encrypt=0){
            self::init();
            $sql = "select * from " . self::$document_table . " where fs_parent='{$nodeid}'";
            $res = self::$db->get_results($sql);
            if(!empty($res)){
                foreach($res as $val){
                    $fs_code = $nodefilecode . '-' . $val['fs_name'];
                    #更新fs_id_path
                    $fs_id_path = $nodefileidpath . '-' . $val['fs_id'];
                    $sql = "update " . self::$document_table . " set fs_code='{$fs_code}', fs_id_path='{$fs_id_path}', fs_is_share='{$fs_is_share}', fs_encrypt='{$fs_encrypt}' where fs_id='{$val['fs_id']}'";
                    self::$db->query($sql);

                    if($val['fs_isdir']=='1'){ #如果是文件夹的话
                        self::dealwithmovefscode($val['fs_id'], $fs_code, $fs_id_path, $fs_is_share, $fs_encrypt);
                    }    
                }
            } 
        }

        /**
        * 删除
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function deldocument($data, $login_user_info){
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
            $verify = M_Usergroup::verify($login_user_info, 'deldocument', $fs_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }

            //如果是文件夹
            if($fs_isdir==1){
                //判断是否为空文件夹, 空文件夹可以删除
                $sql = "select * from ".self::$document_table." where fs_parent='{$fs_id}' ";
                $hasdoc = self::$db->get_results($sql); 
                if($hasdoc){
                    //开始对非空文件夹进行删除操作
                    self::circledeldocument($data, $login_user_info);
                    $rs['msg'] = '操作成功！';
                    $rs['success'] = true;
                    return $rs;
                } else {
                    //进行删除文件件夹操作
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
                            $rs['msg'] = '操作成功！';
                            $rs['success'] = true;
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作成功'));
                            return $rs;
                        } else{
                            $rs['msg'] = '操作失败！';
                            $rs['success'] = false;
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作失败'));
                            return $rs;  
                        }
                    }else {
                        $rs['msg'] = '操作失败！';
                        $rs['success'] = false;
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作失败'));
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
                        M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 操作成功'));    
                        return $rs;
                    } else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 数据库操作失败'));    
                        return $rs;
                    }
                }else{
                    /*
                    $rs['success'] = false;
                    $rs['msg'] = '操作失败！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname  . '（'.$fs_intro.'） 物理操作失败'));    
                    return $rs;
                    */
                    $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}'";
                    if(self::$db->query($sql)){
                        $rs['success'] = true;
                        $rs['msg'] = '操作成功！';
                        return $rs;
                    }
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
            $fs_fullpath = M_Document::getParentpath($fs_id);
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
                    //进行删除文件件夹操作
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
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作成功'));
                        } else{
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作失败'));
                        }
                    }else {
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'删除文件件夹 '. $filecode . ' 操作失败'));
                    }

                }
                /* } */
            }else{ //文件操作
                $fs_file = PROJECT_DOC_PATH . self::splitdocpath($fs_fullpath) . '.' . $fs_type;
                $fs_parentpath = substr(self::getFilenamepath($fs_parentid), 1);
                $file_textname = substr(self::getFilenamepath($fs_id), 1);
                if(is_file($fs_file) && ZF_Libs_IOFile::backup($fs_file, $fs_hashname.'.'.$fs_type)){ 
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
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 操作成功'));    
                    } else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 数据库操作失败'));    
                    }
                }else{
                    /*
                    $rs['success'] = false;
                    $rs['msg'] = '操作失败！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname  . '（'.$fs_intro.'） 物理操作失败')); 
                    */
                    $sql = "delete from ".self::$document_table." where fs_id='{$fs_id}'";
                    if(self::$db->query($sql)){
                        $rs['success'] = true;
                        $rs['msg'] = '操作成功！';
                        #记录文件操作日志
                        $doclog = array('fs_id'=>$fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$fs_hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>4, 'log_lastname'=>$fs_name, 'fs_code'=>$filecode, 'fs_parent'=>$fs_parentid);
                        M_Log::doclog($doclog);
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 操作成功'));    
                    } else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在文件件夹 '.$fs_parentpath.' 中删除文件 '. $file_textname . '（'.$fs_intro.'） 数据库操作失败'));    
                    }   
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
            #获取删除的源文件文件件夹
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
            #项目的父级文件件夹为0， 项目是不可能删除的，所以此处不考虑父级为0的情况
            if($fs_parent){
                #判断原来的父级文件件夹是否还存在
                $sql = "select * from ".self::$document_table." where fs_id='{$fs_parent}'";
                $parentrs = self::$db->get_row($sql);
                if($parentrs){
                    #如果原来父级文件件夹还存在， 获取父级文件件夹的物理路径, 将文件恢复到此文件件夹下
                    $parent_fullpath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($fs_parent));
                    $fs_new_hashname = parent::hashname($parent_fullpath . DS.$fs_hashname.'.'.$fs_type);
                    $recover_file = $parent_fullpath . DS.$fs_new_hashname.'.'.$fs_type;
                    $oprs = ZF_Libs_IOFile::copyFile($file, $recover_file);
                    if($oprs){
                        $time = date('Y-m-d H:i:s');
                        #文件移动成功，开始对数据库进行操作(1、原始内容无法完全恢复，纸版和加密状态，组和用户有恢复到某个文件件夹的属性决定)
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
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$filecode.'（'.$fs_intro.'）'.' 到目标文件件夹 '.$ppath.' 操作成功'));    
                            return $rs;     
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$filecode.'（'.$fs_intro.'）'.' 到目标文件件夹 '.$ppath.' 操作失败'));    
                            return $rs;  
                        }
                    }else{
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标文件件夹 '.$ppath.' 操作失败'));    
                        return $rs;  
                    }
                }else{
                    $rs['success'] = false;
                    $rs['msg'] = '原文件件夹不存在！';
                    #记录系统操作日志
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标文件件夹 '.$ppath.' 操作失败'));    
                    return $rs;
                    //return self::docmenttree($data, $login_user_info);  
                }
            }else{
                $rs['success'] = false;
                $rs['msg'] = '原文件件夹不存在！';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'恢复文件 '.$fs_name.'（'.$fs_intro.'）'.' 到目标文件件夹 '.$ppath.' 操作失败'));    
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
            self::init();
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            if(isset($data['file'])){
                $fs_type = isset($data['t']) ? $data['t'] : '';
                #权限验证
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
                if(!$verify){
                    $rs['msg'] = '对不起，您没有此操作权限';
                    $rs['success'] = false;
                    echo json_encode($rs);exit;
                } 

                $file = self::splitdocpath($data['file']);
                $file = PROJECT_DOC_PATH . $file . '.' . $fs_type;

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
                #权限验证
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
                if(!$verify){
                    $rs['msg'] = '对不起，您没有此操作权限';
                    $rs['success'] = false;
                    return $rs;
                }
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
                    M_Log::doclog($doclog);
                    #记录系统操作日志
                    $sql = "select * from " . self::$document_table . " where fs_id='{$fs_id}' limit 1";
                    $ret = self::$db->get_row($sql);
                    if(!$ret['fs_code']){
                        $fs_code = self::getFilenamepath($fs_id);
                    }else{
                        $fs_code = $ret['fs_code'];
                    }
                    M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'下载文件 '. $fs_code . ' 操作成功'));     
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
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
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
                $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
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
                        //M_Log::doclog($doclog);
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
                    //M_Log::doclog($doclog);
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
            if(!$fs_parent){
                $rs['success'] = false;
                $rs['msag'] = '父级文件件夹不存在';
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
                $rs['msg'] = '请选择文件件夹';
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
            $verify = M_Usergroup::verify($login_user_info, 'readdocument');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $pagesize = intval($data['limit']);
            $start = intval($data['start']);
            $page = !empty($data['page'])?intval($data['page']):1;
            $sortobj = isset($data['sort'])? json_decode($data['sort']) : array((object)array('property'=>'text', 'direction'=>'ASC'));
            $sort = $sortobj[0]->direction;
            $sortfield = $sortobj[0]->property=='text' ? 'fs_code' : $sortobj[0]->property;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            $uid = $login_user_info['u_id'];
            $limit = " limit " . $start . ",".$pagesize;
            $where = $fs_id ? " and fs_parent='{$fs_id}'" :'';

            //查询用户权限
            $tree_rs = array();
            if(!empty($login_user_info)){
                if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){  //超级管理员|系统监察员|项目部负责人|部门负责人
                    $where .= $fs_id ? '' : ' and fs_parent=0 ';
                    #20140305 添加项目部负责人|部门负责人不可以查看加密文件
                    if($login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){ 
                        $where .= " (fs_encrypt!='1' or (fs_encrypt='1' and fs_user='{$login_user_info['u_id']}' )) and";
                    }
                }elseif($login_user_info['u_grade']==98 || $login_user_info['u_grade']==99){ #系统监察员|系统管理员， 对加密的文件此用户不可以看到
                    $where .= $fs_id ? '' : ' and fs_parent=0 ';
                    $where .= " and fs_encrypt!='1' ";
                }elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){  //组管理员，组领导
                    if($login_user_info['u_grade']==1){  #20130916 添加组管理员不可以查看加密文件
                        $where .= " and (fs_encrypt!='1' or (fs_encrypt='1' and fs_user='{$login_user_info['u_id']}' )) ";
                    }

                    $where .= $fs_id ? '' : " or fs_group='{$login_user_info['u_targetgroup']}' ";
                }else{
                    $where .= " and fs_user='{$login_user_info['u_id']}' ";
                }
                #获取统计总数、分页使用
                $sql =  "select count(*) from ".self::$document_table." where 1 " . $where;
                $count_arr = self::$db->get_col($sql);
                $sql = "select * from ".self::$document_table." as d left join (select u_id, u_name from ".self::$usergroup_table.")  as u on d.fs_user=u.u_id  where 1 " . $where ;//. " order by  fs_isdir " . $sort .',' . $sortfield .' '. $sort . $limit;

                //echo $sql;
                $res_doc = self::$db->get_results($sql);
                $res_doc = false === $res_doc ? array() : $res_doc;

                //列出共享文件件夹
                if($login_user_info['u_grade']==0){
                    $user_share_folder = array();
                    #获取用户所有共享的文件夹
                    $sql = "select  fs_parent from ".self::$user_share_document . " where u_id='{$login_user_info['u_id']}'";
                    $allshare_tmp = self::$db->get_col($sql);
                    $all_share = $allshare_tmp ? $allshare_tmp : array();

                    #判断当前传入的fs_id对应的文件件夹是否为共享文件件夹、
                    if($fs_id){
                        $sql = "select * from fs_tree where fs_id='{$fs_id}'";
                        $current_node = self::$db->get_row($sql);

                        if($current_node){
                            if($current_node['fs_is_share']){
                                $sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." as d left join (select u_id, u_name from ".self::$usergroup_table.")  as u on d.fs_user=u.u_id where fs_parent='{$fs_id}' order by  fs_isdir " . $sort .',' . $sortfield .' '. $sort . $limit;
                                $user_share_folder = self::$db->get_results($sql);
                            }elseif(in_array($fs_id, $all_share)){
                                $sql = "select t.* from fs_user_sharedoc as s left join fs_tree as t on s.fs_id=t.fs_id where u_id='{$login_user_info['u_id']}' ";
                                $user_share_folder = self::$db->get_results($sql);
                            }
                        }  
                    }else{
                        $sql = "select t.* from fs_user_sharedoc as s left join fs_tree as t on s.fs_id=t.fs_id where u_id='{$login_user_info['u_id']}' ";
                        $user_share_folder = self::$db->get_results($sql);
                    }

                    if($user_share_folder){
                        foreach($user_share_folder as $f){
                            $res_doc[] = $f;
                        }
                    }    
                }


                #设置前端要用的数据格式
                if($res_doc){
                    foreach($res_doc as $key=>&$value){
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

                if(!empty($tree_rs)){
                    foreach($tree_rs as &$value){
                        #fs_textname 修改为读取fs_code字段  2013/08/26
                        if(!empty($value['fs_code'])){
                            $fs_textname = $value['fs_code']; 
                        }else{
                            if($value['fs_parent']!=0){
                                $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                            }else{
                                $fs_textname = $value['fs_name'];
                            } 
                        }

                        $value['text'] = $fs_textname; 
                        $value['id'] = $value['fs_id'];
                        if($value['fs_isdir']!=1){
                            $type = strtolower($value['fs_type']);
                            $value['icon'] = self::getIconByType($type);
                        }
                        //设置共享文件件夹图标
                        if($value['fs_is_share'] && $value['fs_isdir']=='1' && $value['fs_user']!=$login_user_info['u_id'] && $login_user_info['u_grade']=='0'){
                            $value['icon'] = 'image/new_share_folder.png';
                        }
                        $fs_fullpath = M_Document::getParentpath($value['fs_id']);
                        $value['fs_fullpath'] = $fs_fullpath;
                        $value['leaf'] = $value['fs_isdir']==1?false:true;
                        #修复BUG
                        /*系统管理员分给组文件管理员的文件件夹只有系统管理员可以修改，组文件管理员分给组员的文件件夹只有组管理员和系统管理员可以修改，下面自己建的文件件夹就自己可以随便修改了，修改只能修改自己建的，不是自己建的就没有权限修改*/ 
                        $value['managerok'] = true;
                    }

                    if(!isset($data['sort'])){
                        $tree_rs = self::multiSort($tree_rs,  'fs_code');
                    }else{
                        $tree_rs = self::multiSort($tree_rs, $sortfield, $sort);
                    }
                }
            }

            $return_rs['rows'] = array_slice($tree_rs, $start, $pagesize); 
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
            $fs_mode = isset($data['fs_mode']) ? intval($data['fs_mode']) : '-1';
            $fs_name = !empty($data['fs_name']) ? $data['fs_name'] : '';
            //$fs_name_type = isset($data['fs_name_type']) ? intval($data['fs_name_type']) : '';
            $fs_intro = !empty($data['fs_intro']) ? $data['fs_intro'] : '';
            //$fs_intro_type = isset($data['fs_intro_type']) ? intval($data['fs_intro_type']) : '';
            $workgroup_id = !empty($data['workgroup_id']) ? intval($data['workgroup_id']) : '';
            $user_id = !empty($data['user_id']) ? intval($data['user_id']) : '';
            $fs_type = !empty($data['fs_type']) ?  $data['fs_type'] : '';
            $from_date = !empty($data['from_date']) ? date('Y-m-d H:i:s', strtotime($data['from_date'])) : '';
            $to_date = !empty($data['to_date']) ? date('Y-m-d H:i:s', strtotime($data['to_date'])) : date('Y-m-d H:i:s');
            #分页参数
            $pagesize = isset($data['limit']) ? intval($data['limit']) : 50;
            $start = isset($data['start']) ? (intval($data['start'])<0?0:intval($data['start'])) : 0;
            $page = isset($data['page'])?intval($data['page']):1;
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : ''; //父级文件件夹ID或文件ID， 文件打开方式已修改为在线打开
            $limit = " limit " . $start . ",".$pagesize;

            $sortobj = isset($data['sort'])? json_decode($data['sort']) : array((object)array('property'=>'text', 'direction'=>'ASC'));
            $sort = $sortobj[0]->direction;
            $sortfield = $sortobj[0]->property=='text' ? 'fs_code' : $sortobj[0]->property;

            $imgext_arr = array();
            $docext_arr = array();
            $where = '';
            $rs = array();

            $condition_str = $fs_mode=='-1'?'全部':($fs_mode=='1'?'文件件夹':'文件');
            if($fs_mode!='-1'){
                $where .= " fs_isdir='{$fs_mode}' and";
            }
            if($fs_name){
                $where .= " fs_code like '%{$fs_name}%' and";
                $condition_str .= ' 编号为'.$fs_name;
            }
            if($fs_intro){
                $where .= " fs_intro like '%{$fs_intro}%' and";
                $condition_str .= ' 名称为'.$fs_intro;
            }
            if($workgroup_id){
                $where .= " fs_group='{$workgroup_id}' and";
                #根据工作组ID获取工作组信息(名称)
                $workgroup_info = M_Usergroup::getworkgroupbyid(array('workgroup_id'=>$workgroup_id), $login_user_info);
                $condition_str .= ' 工作组为'.$workgroup_info['u_name'];  
            }
            if($user_id){
                $where .= " and fs_user='{$user_id}' ";
                #根据用户ID获取用户信息
                $user_info = M_Usergroup::getuserinfo($user_id);
                $condition_str .= ' 用户为'.$user_info['u_name'];
            }

            if(!empty($fs_type)){
                $fs_type_where = "'" . implode("','", $fs_type) . "'";
                $where .= " fs_type in(".$fs_type_where.") and";
                $condition_str .= " 类型为 ".$fs_type_where;
            }

            if($from_date){
                $where .= " fs_create>'{$from_date}' and fs_create<'{$to_date}' and";
                $condition_str .= ' 日期 '.$from_date.'--'.$to_date;
            }

            $where .= " fs_parent!=0 and";//排除项目文件
            $where .= $fs_id ? " fs_parent='{$fs_id}' and" : '';


            if($login_user_info['u_grade']==100 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3)
            {  
                //超级管理员|项目部负责人|部门负责人

            }elseif($login_user_info['u_grade']==98 || $login_user_info['u_grade']==99 ){ #系统监察员| 系统管理员 单独处理，
                $where .= " fs_encrypt!='1' and";                
            }
            elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2)
            {  
                //组管理员，组领导
                $where .= " fs_group='{$login_user_info['u_targetgroup']}' and";
                if($login_user_info['u_grade']==1){  #20130916 添加组管理员不可以查看加密文件
                    $where .= " (fs_encrypt!='1' or (fs_encrypt='1' and fs_user='{$login_user_info['u_id']}' )) and";
                }
                //$sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o  from (select * from ".self::$document_table." where 1 ".$groupwhere.") as t2 where 1 ".$where." order by fs_isdir asc, LENGTH(o),o asc";
            }
            else
            {
                $where .= " fs_user='{$login_user_info['u_id']}' and fs_group='{$login_user_info['u_targetgroup']}' and";
                //$sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where fs_user='{$login_user_info['u_id']}' ".$where." order by fs_isdir asc, LENGTH(o), o asc";
            }
            #记录搜索查询日志
            $conditions_desc = '查询 '.$condition_str.'';
            #记录系统日志
            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$conditions_desc));

            $where = substr($where, 0, -3);
            $sql = "select count(*) from ". self::$document_table." where " . $where;
            $count_arr = self::$db->get_col($sql);

            $sql = "select * from ".self::$document_table." as d left join (select u_id, u_name from ".self::$usergroup_table.")  as u on d.fs_user=u.u_id  where " . $where ;
            //echo $sql;
            //$sql = "select *, if(fs_name REGEXP '^[0-9]+$', LPAD(fs_name, 20, '0'), fs_name) as o from ".self::$document_table." where 1 " . $where . " order by fs_isdir asc, LENGTH(o), o asc";
            $sql .= $limit;
            //echo $sql;
            $res = self::$db->get_results($sql);
            if($res){
                foreach($res as $key=>&$value){
                    #fs_textname 修改为读取fs_code字段
                    if(!empty($value['fs_code'])){
                        $fs_textname = $value['fs_code']; 
                    }else{
                        if($value['fs_parent']!=0){
                            $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                        }else{
                            $fs_textname = $value['fs_name'];
                        } 
                    }


                    $value['text'] = $fs_textname; 
                    if(!($value['fs_isdir']==1 || $value['fs_isdir']==2)){
                        $type = strtolower($value['fs_type']);
                        $value['icon'] = self::getIconByType($type);
                    }
                    $value['id'] = $value['fs_id'];

                    $fs_fullpath = M_Document::getParentpath($value['fs_id']);
                    $value['fs_fullpath'] = $fs_fullpath;
                    $value['leaf'] = $value['fs_isdir']==1 || $value['fs_isdir']==2?false:true; 
                }
                if(!isset($data['sort'])){
                    $res = self::multiSort($res, 'fs_code');
                }else{
                    //var_dump($sort, $sortfield,$tree_rs);
                    $res = self::multiSort($res,  $sortfield, $sort);
                }
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

            #权限验证
            $verify = M_Usergroup::verify($login_user_info, 'downloadfile', $fs_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
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
        * 查找文件件夹下最大的编号
        * 
        * @param mixed $data
        */
        public static function getMaxfilecode($data){
            self::init();
            $parentid = intval($data['fs_parent']);
            $sql = "select * from " . self::$document_table . " where fs_parent='{$parentid}' and fs_isdir=0";
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
                        $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                    }else{
                        $fs_textname = $value['fs_name'];
                    }
                    $value['text'] = $fs_textname . '（'.$value['fs_intro'].'）'; 
                    if(!($value['fs_isdir']==1 || $value['fs_isdir']==2)){
                        $type = strtolower($value['fs_type']);
                        $value['icon'] = self::getIconByType($type);
                    }
                    $value['id'] = $value['fs_id'];

                    $fs_fullpath = M_Document::getParentpath($value['fs_id']);
                    $value['fs_fullpath'] = $fs_fullpath;
                    $value['leaf'] = $value['fs_isdir']==1 || $value['fs_isdir']==2?false:true; 

                    $res_doc_arr[$value['fs_id']] = $value;
                    $res_fs_id[] = $value['fs_id'];
                    $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                }
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


        public static function listUserDocument($data, $login_user_info){
            self::init();

            $rs = array();
            if(!empty($login_user_info)){
                $where = ' and fs_parent!=0 ';
                $sql = "select fs_id, fs_parent, fs_isdir, fs_group, fs_name, fs_intro, fs_size, fs_type, fs_haspaper, fs_user from ".self::$document_table." where 1 " . $where ;

                $rs = self::$db->get_results($sql); 
            }
            return $rs;
        }




        /**
        * 复制文件件夹结构
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
                $rs['msg'] = $document_name.'文件件夹已经存在！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'copydocumentstruct', $document_parentid);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }            
            $rs = array('success'=>false, 'msg'=>'系统错误');
            if(!$document_name){
                $rs['success'] = false;
                $rs['msg'] = '请添写文件件夹编号';
                return $rs;
            }
            if(!$document_intro){
                $rs['success'] = false;
                $rs['msg'] = '请添写文件件夹描述';
                return $rs;
            }
            if(!$document_parentid){
                $rs['success'] = false;
                $rs['msg'] = '请选择父级文件件夹';
                return $rs;
            }
            if(empty($login_user_info)){
                $rs['success'] = false;
                $rs['msg'] = '您的登录状态已过期， 请重新登录';
                return $rs;  
            }

            /**开始创建文件件夹 此文件件夹需要循环创建和复制文件件夹相同的文件件夹结构**/
            #确定要COPY的文件件夹结构
            $current_doc_struct = self::getdocstruct($current_doc_id);
            if($current_doc_struct){
                self::adddocument(array('project_doc_name'=>$document_name, 'project_doc_parentid'=>$document_parentid, 'project_doc_intro'=>$document_intro), $login_user_info);
                #查找新创建的文件夹的信息
                $sql = "select * from ".self::$document_table ." where fs_parent='{$document_parentid}' and fs_name='{$document_name}' ";
                $row = self::$db->get_row($sql);
                #循环COPY的文件件夹结构进行文件件夹创建
                self::createCopystruct($current_doc_struct, $row['fs_id'], $login_user_info);
                $rs = array('success'=>true, 'msg'=>'操作成功！');
                return $rs;
            }else{ //文件夹为空时建立一个新的文件夹即可
                return self::adddocument(array('project_doc_name'=>$document_name, 'project_doc_parentid'=>$document_parentid, 'project_doc_intro'=>$document_intro), $login_user_info);
            }

        }

        /**
        *  根据文件件夹ID获取文件件夹结构
        * 
        * @param int $fs_id
        * @return object
        */
        public static function getdocstruct($fs_id) { 
            self::init();
            $fs_id = intval($fs_id);
            $sql = "SELECT fs_id, fs_parent, fs_user, fs_group, fs_name, fs_intro, fs_code FROM " . self::$document_table . " WHERE fs_parent='{$fs_id}' AND fs_isdir='1' ";
            $rs = self::$db->get_results($sql);
            if(!$rs){
                return false;
            }
            foreach($rs as $key=>&$val){
                $arr = self::getdocstruct($val['fs_id']);
                $val['children'] = $arr;
            }
            return $rs;    

        }

        /**
        * 创建copy的文件件夹
        * 
        * @param mixed $data
        * @param mixed $parent_id
        * @param mixed $login_user_info
        */
        public static function createCopystruct($data, $parent_id, $login_user_info){
            self::init();
            if(!empty($data)){
                foreach($data  as $val){
                    self::adddocument(array('project_doc_name'=>$val['fs_name'], 'project_doc_parentid'=>$parent_id, 'project_doc_intro'=>$val['fs_intro']), $login_user_info);

                    if($val['children']){
                        #查找父级ID
                        $sql  = "select * from " . self::$document_table . " where fs_parent='{$parent_id}' and fs_name='{$val['fs_name']}' and fs_isdir='1'";
                        $row = self::$db->get_row($sql);
                        if($row){
                            self::createCopystruct($val['children'], $row['fs_id'], $login_user_info);
                        }
                    }
                }
            }
        }


        /**
        * 根据ID获取当前文件件夹的面包屑
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function getnavdata($data, $login_user_info, $res=array()){
            self::init();
            $node_id = intval($data['fs_id']);

            $sql = "select * from fs_tree where fs_id='$node_id'";

            $rs = self::$db->get_row($sql);

            if(($login_user_info['u_grade']==100 || $login_user_info['u_grade']==99 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3) && ($rs['fs_parent']==0 || !$rs)){
                return $res;
            }elseif(($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2) && ($rs['fs_group']!=$login_user_info['u_targetgroup'] )){
                return $res;
            }elseif($login_user_info['u_grade']==0 && $rs['fs_user']!=$login_user_info['u_id']){
                return $res;
            }
            $res[] = $rs;
            if($rs['fs_parent']!=0){
                return self::getnavdata(array('fs_id'=>$rs['fs_parent']), $login_user_info, $res);
            }
            //return $res;
        }

        /**
        * 根据fs_id获取单条文档信息
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        * @return record
        */
        public static function getdatabyid($data, $login_user_info){
            self::init();
            $node_id = intval($data['fs_id']);
            $sql = "select * from fs_tree where fs_id='$node_id'";
            $rs = self::$db->get_row($sql);
            return $rs;
        }




        /*******************************************以下程序测试使用*********************************************/
        /**
        * 递归添加编号路径  手工添加使用
        * 
        * @param mixed $node_id
        */
        public static function setFilenamepath(){
            self::init();
            $sql = "update fs_tree set fs_code='' where 1";
            self::$db->query($sql);
            $sql = "update fs_tree set fs_code='test' where fs_id=1";
            self::$db->query($sql);
            $sql = "select * from fs_tree where 1";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    if(!$val['fs_code']){
                        $filecode = substr(self::getFilenamepath($val['fs_id']), 1); echo $filecode;
                        $sql = "update fs_tree set fs_code='{$filecode}' where fs_id='{$val['fs_id']}'";
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
            $sql = "update fs_tree set fs_id_path='' where 1";
            self::$db->query($sql);
            $sql = "update fs_tree set fs_id_path='1' where fs_id=1";
            self::$db->query($sql);
            $sql = "select * from fs_tree where 1";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    if(!$val['fs_id_path']){
                        $fs_id_path = substr(self::getFileIdpath($val['fs_id']), 1); echo $fs_id_path;
                        $sql = "update fs_tree set fs_id_path='{$fs_id_path}' where fs_id='{$val['fs_id']}'";
                        self::$db->query($sql);
                    } 
                }
            }
        }

        /**
        * 添加文件夹的级别
        * 
        * @param mixed $node_id
        */
        public static function setFileLevel(){
            self::init();
            $sql = "select * from fs_tree";
            $rs = self::$db->get_results($sql);
            if(!empty($rs)){
                foreach($rs as $val){
                    if($val['fs_parent']=='0'){
                        $sql = "update fs_tree set fs_level='0' where fs_parent='0'";
                        self::$db->query($sql);
                    }else{
                        #查询父级节点的fs_level
                        $sql = "select * from fs_tree where fs_id='{$val['fs_parent']}' ";
                        $parent = self::$db->get_row($sql);
                        $level = $parent['fs_level'];
                        $thislevel = intval($level) + 1;
                        echo $thislevel  . "<br>";
                        $sql = "update fs_tree set fs_level='{$thislevel}' where fs_id='{$val['fs_id']}'";
                        self::$db->query($sql);
                        
                    }
                }
            }
        }


        /**
        * 项目文件移动至共享文件件夹
        * 
        */
        public static function movetoshare($data, $login_user_info) {
            self::init();
            set_time_limit(0);
            $treepathvalue = isset($data['targetpathvalue']) ? $data['targetpathvalue'] : ''; //目标父级文件件夹fs_code
            $newparentid = $treepathid = isset($data['targetpathid']) ? $data['targetpathid'] : '';  //目标父级文件件夹ID
            #判断目标文件件夹是否是登陆用户可以操作的
            $haspower = M_Sharedocument::checksharedocpower($newparentid, $login_user_info);
            if(false===$haspower){
                $rs['msg'] = '无权限操作此文件件夹！';
                $rs['success'] = false;
                return $rs;
            }

            //当前操作节点信息
            $nodeid = isset($data['fs_id']) ? $data['fs_id'] : '';
            $oldparentid = isset($data['fs_parent']) ? intval($data['fs_parent']) : 0;
            $fs_name = !empty($data['fs_name']) ? $data['fs_name'] : '';
            $nodehashname = !empty($data['fs_hashname']) ? $data['fs_hashname'] : '';
            $fs_type = !empty($data['fs_type']) ? $data['fs_type'] : '';
            $fs_size = !empty($data['fs_size']) ? intval($data['fs_size']) : 0;
            $fs_intro = !empty($data['fs_intro']) ? addslashes($data['fs_intro']) : '';
            $fs_isdir = !empty($data['fs_isdir']) ? intval($data['fs_isdir']) : '';
            $fs_haspaper = !empty($data['fs_haspaper']) ? intval($data['fs_haspaper']) : 0;

            if(!$treepathvalue){
                $rs['msg'] = '请选择目标文件件夹！';
                $rs['success'] = false;
                return $rs;
            }
            #文件或文件夹物理路径
            $nodepath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($nodeid));
            $oldparentpath = PROJECT_DOC_PATH . self::splitdocpath(self::getParentpath($oldparentid));
            $newparentpath = PROJECT_DOC_PATH . self::splitdocpath(M_Sharedocument::getParentpath($newparentid));


            if($fs_isdir==0){
                #判断文件是否存在
                $checkresult = M_Sharedocument::checkSamedoc($fs_name, $newparentid, $nodeid);  
                if($checkresult['flag']==1){
                    $rs['success'] = false;
                    $rs['msg'] = '文件已经存在！';
                    return $rs;
                }else{ #开始移动文件
                    #旧路径 fs_code
                    $sql = "select * from " . self::$document_table . " where fs_id='{$nodeid}'";
                    $oldfile_res = self::$db->get_row($sql);
                    if(!empty($oldfile_res['fs_code'])){
                        $log_oldfilepath = $oldfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    }
                    #新路径 fs_code
                    $sql = "select * from " . self::$share_document_table . " where fs_id='{$newparentid}'";
                    $newfile_res = self::$db->get_row($sql);
                    if(!empty($newfile_res['fs_code'])){
                        $log_newfilepath = $newfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    }


                    $newhashname = parent::hashname($newparentpath.DS.$fs_name);
                    $newfile = $newparentpath.DS.$newhashname.'.'.$fs_type;
                    if(!ZF_Libs_IOFile::copyFile($oldparentpath.DS.$nodehashname.'.'.$fs_type, $newfile)){
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败！';

                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作失败'));    
                        return $rs;
                    } else {
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        $createtime = date('Y-m-d H:i:s');
                        #在共享表中插入移入的数据， 在原项目表中将数据暂时不动
                        $sql = "insert into " . self::$share_document_table . " 
                        set fs_name='{$fs_name}',
                        fs_parent='{$newparentid}',
                        fs_isdir='0',
                        fs_group='{$newfile_res['fs_group']}',
                        fs_user='{$newfile_res['fs_user']}',
                        fs_create='{$createtime}',
                        fs_intro='{$fs_intro}',
                        fs_size='{$fs_size}',
                        fs_type='{$fs_type}',
                        fs_haspaper='{$fs_haspaper}',
                        fs_hashname='{$newhashname}',
                        fs_code='{$fs_code}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作失败'));    
                            return $rs;
                        }

                    }
                }
            } elseif($fs_isdir==1) { //拖动文件件夹
                #判断文件件夹是否存在
                $checkresult = M_Sharedocument::checkSamedoc($fs_name, $newparentid, $nodeid, 1);  
                if($checkresult['flag']==1){
                    $rs['success'] = false;
                    $rs['msg'] = '文件件夹已经存在！';
                    return $rs;
                }else{ #开始移动文件件夹下的文件, （文件的HASH值暂不做处理，只改变当前文件件夹的HASH值)
                    #旧路径 fs_code
                    $sql = "select * from " . self::$document_table . " where fs_id='{$nodeid}'";
                    $oldfile_res = self::$db->get_row($sql);
                    if(!empty($oldfile_res['fs_code'])){
                        $log_oldfilepath = $oldfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_oldfilepath = substr(self::getFilenamepath($nodeid), 1);
                    }
                    #新路径 fs_code
                    $sql = "select * from " . self::$share_document_table . " where fs_id='{$newparentid}'";
                    $newfile_res = self::$db->get_row($sql);
                    if(!empty($newfile_res['fs_code'])){
                        $log_newfilepath = $newfile_res['fs_code'];
                    }else{ #兼容以前的版本
                        $log_newfilepath = substr(self::getFilenamepath($newparentid), 1);
                    }

                    #获取物理存储HASH码
                    $newhashname = parent::hashname($newparentpath.DS.$fs_name);
                    //if(!ZF_Libs_IOFile::copyFile($nodepath, $newparentpath.DS.$newhashname)){
                    if(!ZF_Libs_IOFile::mkdir($newparentpath.DS.$newhashname)){
                        $rs['success'] = false;
                        $rs['msg'] = '操作失败的！';
                        #记录系统操作日志
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作失败'));    
                        return $rs;
                    } else {
                        #移动文件件夹的fs_code
                        $fs_code = $log_newfilepath . '-' . $oldfile_res['fs_name'];
                        #入库操作
                        $createtime = date('Y-m-d H:i:s');
                        $sql = "insert into " . self::$share_document_table . " 
                        set fs_name='{$fs_name}',
                        fs_parent='{$newparentid}',
                        fs_isdir='1',
                        fs_group='{$newfile_res['fs_group']}',
                        fs_user='{$newfile_res['fs_user']}',
                        fs_create='{$createtime}',
                        fs_intro='{$fs_intro}',
                        fs_size='{$fs_size}',
                        fs_type='{$fs_type}',
                        fs_haspaper='{$fs_haspaper}',
                        fs_hashname='{$newhashname}',
                        fs_code='{$fs_code}'";
                        $updateres = self::$db->query($sql);
                        if($updateres){
                            $curparentid = self::$db->last_insert_id();
                            #更新新文件件夹的fs_id_path 
                            if(!empty($newfile_res['fs_id_path'])){
                                $fs_id_path = $newfile_res['fs_id_path'] . '-' . $curparentid; 
                            }else{
                                $fs_id_path = substr(M_Sharedocument::getFileIdpath($curparentid), 1);
                            }
                            $sql = "update " . self::$share_document_table . " set fs_id_path='{$fs_id_path}' where fs_id='{$curparentid}'";
                            self::$db->query($sql);   

                            #当前移动文件件夹更新成功后需要对文件件夹下的所有子文件件夹进行处理
                            $dir = opendir($nodepath);
                            while(false !== ($file = readdir($dir))) {
                                if (($file != '.') && ($file != '..')) {
                                    if ( is_dir($nodepath . DS . $file) ) {
                                        $sql = "select * from " . self::$document_table . " where fs_hashname='{$file}' limit 1";
                                        $fs_rs = self::$db->get_row($sql);
                                        $fs_rs['targetpathid'] = $curparentid;
                                        $fs_rs['targetpathvalue'] = 1;
                                    } else {
                                        $file_name = substr($file, 0, strrpos($file, '.'));
                                        $sql = "select * from " . self::$document_table . " where fs_hashname='{$file_name}' ";
                                        $fs_rs = self::$db->get_row($sql);
                                        $fs_rs['targetpathid'] = $curparentid;
                                        $fs_rs['targetpathvalue'] = 1;
                                    }
                                    self::movetoshare($fs_rs, $login_user_info);
                                }else{
                                    continue;
                                }
                            }
                            closedir($dir);
                            //}

                            $rs['success'] = true;
                            $rs['msg'] = '操作成功！';

                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作成功'));
                            return $rs;
                        }else{
                            $rs['success'] = false;
                            $rs['msg'] = '操作失败！';
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'文件件夹 ' . $log_oldfilepath . '(' . $fs_intro . ') 移动至 '.$log_newfilepath.' 公共文件件夹下 操作失败'));    
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


        /**
        * 在项目文件中对文件夹进行共享操作（2013/09/22 添加）
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function sharedocument($data, $login_user_info){
            self::init();
            $u_id_str = isset($data['uids']) ? $data['uids'] : '';
            $fs_id = isset($data['fs_id']) ? $data['fs_id'] : '';
            $fs_code = isset($data['fs_code']) ? $data['fs_code']: '';
            $fs_parent = isset($data['fs_parent']) ? $data['fs_parent'] : '';
            if(!$u_id_str or !$fs_id){
                $rs['success'] = false;
                $rs['msg'] = '操作失败！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'sharesetting', $fs_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            if(!$fs_code){
                $fs_code = substr(M_Document::getFilenamepath($fs_id), 1); 
            }
            $u_id_arr = explode(',', $u_id_str);
            if(!empty($u_id_arr)){
                #删除此文件夹之前的共享数据， 重新进行设置
                $sql = "delete from " . self::$user_share_document . " where fs_id='{$fs_id}'";
                $row = self::$db->query($sql);
                foreach($u_id_arr as $u_id){
                    //$sql = "select * from " . self::$user_share_document . " where u_id='{$u_id}' and fs_id='{$fs_id}' limit 1";
                    $sql = "insert into " . self::$user_share_document . " set u_id='{$u_id}', fs_id='{$fs_id}', fs_code='{$fs_code}', fs_parent='{$fs_parent}' ";
                    self::$db->query($sql);
                }   
            }
            #递归设置当前文件夹下的内容为共享内容
            self::treesharesetting($fs_id, $login_user_info['u_id']);
            $rs['success'] = true;
            $rs['msg'] = '操作成功！';
            #记录系统操作日志
            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'设置共享文件件夹 ' . $fs_code . '  操作成功'));
            return $rs;
        }

        /**
        * 添加文件件夹树的共享标志位
        * 
        * @param mixed $fs_id
        * @param mixed $u_id
        */
        private static function treesharesetting($fs_id, $u_id){
            self::init();
            $sql = "update " . self::$document_table . " set fs_is_share='1' where fs_id='{$fs_id}' and fs_user='{$u_id}'";
            self::$db->query($sql);
            $sql = "select * from ". self::$document_table ." where fs_parent='{$fs_id}' ";
            $rs = self::$db->get_results($sql);
            if($rs){
                foreach($rs as $v){
                    if($v['fs_isdir']=='1'){
                        self::treesharesetting($v['fs_id'], $u_id);
                    }else{
                        $sql = "update ".self::$document_table . " set fs_is_share='1' where fs_id='{$v['fs_id']}' and fs_user='{$u_id}'";
                        self::$db->query($sql);
                    }
                }
            }
        }
        /**
        * 删除文件件夹树的共享标志位
        * 
        * @param mixed $fs_id
        * @param mixed $u_id
        */
        private static function treeshareremove($fs_id, $u_id){
            self::init();
            $sql = "update ".self::$document_table . " set fs_is_share='0' where fs_id='{$fs_id}' and fs_user='{$u_id}'";
            self::$db->query($sql);
            $sql = "select * from ". self::$document_table ." where fs_parent='{$fs_id}' ";
            $rs = self::$db->get_results($sql);
            if($rs){
                foreach($rs as $v){
                    if($v['fs_isdir']=='1'){
                        self::treeshareremove($v['fs_id'], $u_id);
                    }else{
                        $sql = "update ".self::$document_table . " set fs_is_share='0' where fs_id='{$v['fs_id']}' and fs_user='{$u_id}'";
                        self::$db->query($sql);
                    }
                }
            }
        }


        public static function removesharesetting($data, $login_user_info){
            self::init();
            $fs_id = isset($data['fs_id']) ? intval($data['fs_id']) : '';
            $fs_code = isset($data['fs_code']) ? addslashes($data['fs_code']) :''; 
            if(!$fs_id){
                $rs['success'] = false;
                $rs['msg'] = '请选择文件件夹！';
                return $rs;
            }
            $verify = M_Usergroup::verify($login_user_info, 'sharesetting', $fs_id);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            #删除此文件夹之前的共享数据， 重新进行设置
            $sql = "delete from " . self::$user_share_document . " where fs_id='{$fs_id}'";
            $row = self::$db->query($sql);
            if($row){
                $rs['success'] = true;
                $rs['msg'] = '操作成功！';
                #对文档表中文件的共享标志位进行设置
                self::treeshareremove($fs_id, $login_user_info['u_id']);
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'取消文件件夹 ' . $fs_code . '共享设置  操作成功'));
            }else{
                $rs['success'] = false;
                $rs['msg'] = '操作失败！';
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'取消文件件夹 ' . $fs_code . '共享设置  操作失败'));
            }
            return $rs;
        }

        /**
        * 生成文件件夹使用
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function getAlldocbySearch($data, $login_user_info){
            self::init();
            $fs_name = isset($data['fs_name']) ? trim($data['fs_name']) : '';
            $fs_group = isset($data['fs_group']) ? $data['fs_group'] : '-1';
            $fs_user = isset($data['fs_user']) ? $data['fs_user'] : '-1';
            if(!$fs_name && $fs_group=='-1' && $fs_user=='-1' && $login_user_info['u_grade']<98){
                $rs['success'] = false;
                $rs['msg'] = '请选择生成文件件夹的条件';
                return $rs;
            }

            $fields = 'fs_id, fs_name, fs_intro, fs_haspaper, fs_encrypt, fs_isdir, fs_parent, fs_code, fs_group, fs_user, fs_is_share';
            //$fields = 'fs_id, fs_parent,fs_code, fs_isdir';
            if($fs_name!=''){
                #判断当前登录用户权限
                if($login_user_info['u_grade']=='0'){ //普通用户登录
                    if($fs_user!=$login_user_info['u_id']){
                        $rs['success'] = false;
                        $rs['msg'] = '对不起，您没有权限访问该文件';
                        return $rs;
                    }
                    $sql = "select {$fields} from " . self::$document_table . " where fs_user='{$fs_user}' and fs_group='{$fs_group}' and fs_code like '%{$fs_name}%' ";
                } elseif($login_user_info['u_grade']=='1' || $login_user_info['u_grade']=='2'){
                    $sql = "select {$fields} from " . self::$document_table . " where fs_group='{$fs_group}' and fs_code like '%{$fs_name}%'";
                }else{
                    $sql = "select {$fields} from " . self::$document_table . " where fs_code like '%{$fs_name}%'";
                }
            }elseif($fs_user != '-1'){ //选择了用户
                if($login_user_info['u_grade']=='0'){ //普通用户登录
                    if($fs_user!=$login_user_info['u_id']){
                        $rs['success'] = false;
                        $rs['msg'] = '对不起，您没有权限访问该文件';
                        return $rs;
                    }

                }
                if($login_user_info['u_grade']=='1' || $login_user_info['u_grade']=='2'){ //组管理员， 组领导
                    //if($fs_group!=$login_user_info['u_targetgroup']){
                    //$rs['success'] = false;
                    //$rs['msg'] = '对不起，您没有权限访问该文件';
                    //return $rs;
                    //}
                }
                //h获取用户信息， 
                $user_info = M_Usergroup::getuserinfo($fs_user);
                $sql = "select {$fields} from " . self::$document_table . " where  fs_user='{$fs_user}' and fs_group='{$user_info['u_targetgroup']}' and fs_isdir=1";
            }elseif($fs_group!='-1'){ //选择了组
                if($login_user_info['u_grade']=='0' && $fs_user=='-1'){ //普通用户登录
                    $rs['success'] = false;
                    $rs['msg'] = '对不起，您没有权限访问该文件';
                    return $rs;
                }
                $sql = "select {$fields} from " . self::$document_table . " where fs_group='{$fs_group}' and fs_isdir=1";
            }else{
                $sql = "select {$fields} from " . self::$document_table . " where fs_parent!='0' and fs_isdir=1";
            }
            
            $res_doc = self::$db->get_results($sql);
            $tree_rs = array();
            $depth = 1;
            if($res_doc){
                foreach($res_doc as $key=>$value){
                    $res_doc_arr[$value['fs_id']] = $value;
                    $res_fs_id[] = $value['fs_id'];
                    $res_fs_parent_id[$value['fs_id']] = $value['fs_parent'];
                }
                //if($login_user_info['u_grade']<90){ 
                foreach($res_fs_parent_id as $fsid=>$parent_id){
                    if(in_array($parent_id, $res_fs_id)){
                        continue;
                    }else{
                        $tree_rs[$fsid][] = $res_doc_arr[$fsid];
                    }
                }
                unset($res_doc_arr);

                foreach($tree_rs as $val){
                    if(!empty($val) && is_array($val)){
                        foreach($val as $v){
                            $tree_rs_tmp[] = $v;
                        }  
                    }
                }
                unset($tree_rs);
                $tree_rs = $tree_rs_tmp;
                unset($tree_rs_tmp);                    
                //}else{
                //$tree_rs = $res_doc;
                //}


                if(!empty($tree_rs)){
                    foreach($tree_rs as &$value){
                        #fs_textname 修改为读取fs_code字段
                        if(!empty($value['fs_code'])){
                            $fs_textname = $value['fs_code']; 
                        }else{
                            if($value['fs_parent']!=0){
                                $fs_textname = substr(M_Document::getFilenamepath($value['fs_id']), 1);
                            }else{
                                $fs_textname = $value['fs_name'];
                            } 
                        }
                        $value['fs_code'] = $fs_textname;

                        #获取所有子文件件夹及文件
                        if($value['fs_isdir']==1){
                            $value['children'] = self::getAlldocstruct($value['fs_id'], $data);
                        }
                    }
                    //排序
                    $tree_rs = self::multiSort($tree_rs, 'fs_code');
                }

                $depth = self::array_depth($tree_rs);
            }
            return array('success'=>true, 'msg'=>$tree_rs, 'level'=>$depth);
        }

        /**
        * 创建copy的文件件夹
        * 
        * @param mixed $data
        * @param mixed $parent_id
        * @param mixed $login_user_info
        */
        public static function getAlldocstruct($fs_id, $data){
            self::init();
            $fs_id = intval($fs_id);
            //$sql = "SELECT * FROM " . self::$document_table . " WHERE fs_parent='{$fs_id}' ";
            $fields = 'fs_id, fs_name, fs_intro, fs_haspaper, fs_encrypt, fs_isdir, fs_parent, fs_code, fs_group, fs_user, fs_is_share';
            if($data['fs_user'] != '-1'){ //选择了用户
                //h获取用户信息， 
                $user_info = M_Usergroup::getuserinfo($data['fs_user']);
                $sql = "select {$fields} from " . self::$document_table . " where fs_parent='{$fs_id}' and fs_user='{$data['fs_user']}' and fs_group='{$user_info['u_targetgroup']}'";
            }elseif($data['fs_group']!='-1'){ //选择了组
                $sql = "select {$fields} from " . self::$document_table . " where fs_parent='{$fs_id}' and fs_group='{$data['fs_group']}'";
            }else{
                $sql = "select {$fields} from " . self::$document_table . " where fs_parent='{$fs_id}' and fs_parent!='0'";
            }
            //$sql .= " and fs_parent='{$fs_id}' ";

            $rs = self::$db->get_results($sql);
            if(!$rs){
                return false;
            }else{
                $rs = self::multiSort($rs, 'fs_code');
            }

            foreach($rs as $key=>&$val){
                #fs_textname 修改为读取fs_code字段
                if(!empty($val['fs_code'])){
                    $fs_textname = $val['fs_code']; 
                }else{
                    if($val['fs_parent']!=0){
                        $fs_textname = substr(self::getFilenamepath($val['fs_id']), 1);
                        #如果没有的话，默认修改数据库中的这个字段
                        //$sql = "update " . self::$document_table . " set fs_code='{$fs_textname}' where fs_id='{$val['fs_id']}'";
                        //self::$db->query($sql);
                    }else{
                        $fs_textname = $val['fs_name'];
                    } 
                }
                $val['fs_code'] = $fs_textname;
                if($val['fs_isdir']=='1'){
                    $val['children'] = self::getAlldocstruct($val['fs_id'], $data);
                }
            }
            return $rs;  
        }

        /**
        * 获取数组深度
        * 
        * @param mixed $array
        */
        public static function array_depth($array){
            $max_depth = 1;
            if(!empty($array)){
                foreach ($array as $value) {
                    if ($value['fs_isdir']) {
                        if(is_array($value['children'])){
                            $depth = self::array_depth($value['children']) + 1;

                            if ($depth > $max_depth) {
                                $max_depth = $depth;
                            }
                        }
                    }
                }
            }
            return $max_depth;
        }


    }
?>
