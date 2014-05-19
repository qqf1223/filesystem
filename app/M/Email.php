<?php
/**
* @name      M_Email.php
* @describe  Email操作类
* @author    qinqf
* @todo       
* @changelog  
*/

class M_Email extends M_Model
{
    static $db;
    static $document_table = 'fs_tree';
    static $share_document_table = 'fs_share_tree';
    static $log_table = 'fs_log';
    /*** 初始化操作 */
    public static function init(){
        self::$db = parent::init();     
    }

    /**
    * Email附件操作
    * 
    */
    public static function moveemail($data, $login_user_info) {
        self::init();
        set_time_limit(0);
        $emailtreepathvalue = isset($data['emailtreepathvalue']) ? $data['emailtreepathvalue'] : '';
        $emailtreepathid = isset($data['emailtreepathid']) ? $data['emailtreepathid'] : '';  //父级目录ID
        $fs_is_share = isset($data['fs_is_share']) ? $data['fs_is_share'] : 0;
        $emailmsgid = isset($data['emailmsgid']) ? $data['emailmsgid'] : '';
        $emailuidl = isset($data['emailuidl']) ? $data['emailuidl'] : '';
        $emailsubject = !empty($data['emailsubject']) ? $data['emailsubject'] : '无主题';
        $useremail = $login_user_info['u_email'];
        $password = isset($data['password']) ? $data['password'] : '';
        if(!$emailtreepathvalue){
            $rs['msg'] = '操作失败！';
            $rs['success'] = false;
            return $rs;
        }
        #开始下载EMAIL文件
        global $base_path;
        $oldmailpath = APP_PATH . '/POP3/tmp/' . $useremail . DS . $emailmsgid;

        $url = $base_path . 'POP3/test.php?op=save&id='.$emailmsgid.'&user='.$useremail.'&pass='.$password;
        /*
        if(function_exists('curl_init')){
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt ( $ch, CURLOPT_TIMEOUT, $timeOut );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        $content = curl_exec ( $ch );
        curl_close($ch);
        }else
        */
        #判断附件已经下载过，如果已经下载则直接进行文件移动操作，否则，下载移动
        if(!is_dir($oldmailpath) || ZF_Libs_IOFile::judge_empty_dir($oldmailpath)){
            $opts = array( 
            'http' => array( 
            'method'=>"GET", 
            'header'=>"Content-Type: text/html; charset=utf-8" 
            ) 
            ); 
            $timeOut = 360;
            $context = stream_context_create($opts); 
            if(function_exists('curl_init')){
                $ch = curl_init ();
                curl_setopt ( $ch, CURLOPT_URL, $url );
                curl_setopt ( $ch, CURLOPT_HEADER, 0 );
                curl_setopt ( $ch, CURLOPT_TIMEOUT, $timeOut );
                curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
                $content = curl_exec ( $ch );
                curl_close($ch);
            }elseif(function_exists('file_get_contents')){
                $content = @file_get_contents($url);
            }else{
                $rs['msg'] = '请检查环境配置！';
                $rs['success'] = false;
                return $rs;
            }
        }else{
            $content = json_encode(array('status'=>'ok'));
        }

        if(false!==$content){
            $res = json_decode($content);
            if(!empty($res->status) && $res->status == 'ok'){
                #下载成功在父级目录（$emailtreepathid）中建立子目录 【目录编号为自动编号， 目录名为邮件标题】
                #1、获取自动编号目录ID
                //$maxid = intval(M_Document::getMaxfilecode(array('fs_parent'=>$emailtreepathid))) + 1;
                $sql = "select * from " . self::$document_table . " where fs_name='email{$emailmsgid}' and fs_parent='{$emailtreepathid}' limit 1";
                $record = self::$db->get_col($sql);
                if(!$record){
                    $newhashname = parent::hashname($emailmsgid);
                    $newmailpath = PROJECT_DOC_PATH.M_Document::splitdocpath(M_Document::getParentpath($emailtreepathid)) . DS . $newhashname;
                    if(ZF_Libs_IOFile::mkdir($newmailpath)){ #创建目录成功，开始拷贝文件
                        $fs_code = $emailtreepathvalue . '-' . "email{$emailmsgid}";
                        #在数据库中插入新创建的目录
                        $time = date('Y-m-d H:i:s');
                        $sql = "insert into " . self::$document_table . " set 
                        fs_name='email{$emailmsgid}',
                        fs_parent='{$emailtreepathid}',
                        fs_isdir='1',
                        fs_group='{$login_user_info['u_parent']}',
                        fs_user='{$login_user_info['u_id']}',
                        fs_create='{$time}',
                        fs_intro='{$emailsubject}',
                        fs_hashname='{$newhashname}',
                        fs_code='{$fs_code}',
                        fs_is_share='{$fs_is_share}'";

                        if(self::$db->query($sql)){
                            $parent_insertid = self::$db->last_insert_id();
                            /*对目录的fs_id_path进行设置， 方便在前端进行目录树的展开和收缩*/
                            if(!empty($record['fs_id_path'])){
                                $fs_id_path = $record['fs_id_path'] . '-' . $parent_insertid;
                            }else{
                                $fs_id_path = substr(M_Document::getFileIdpath($parent_insertid), 1);
                            }
                            $sql = "update ". self::$document_table . " set fs_id_path='{$fs_id_path}' where fs_id='{$parent_insertid}'";
                            self::$db->query($sql);

                            #记录文件操作日志
                            $doclog = array('fs_id'=>$parent_insertid, 'fs_name'=>'email'.$emailmsgid, 'fs_hashname'=>$newhashname, 'fs_intro'=>$emailsubject, 'fs_size'=>'', 'fs_type'=>'', 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>'email'.$emailmsgid);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建目录 '.substr(M_Document::getFilenamepath($parent_insertid), 1).'（'.$emailsubject.'）  操作成功！'));


                            #插入数据库中成功, 开始向创建的目录中移动文件 
                            $oldDir = $oldmailpath;
                            $aimDir = $newmailpath;

                            /*移动文件时对文件进行编号*/
                            $aimDir = str_replace('', '/', $aimDir); 
                            $aimDir = substr($aimDir, -1) == '/' ? $aimDir : $aimDir . '/'; 
                            $oldDir = str_replace('', '/', $oldDir); 
                            $oldDir = substr($oldDir, -1) == '/' ? $oldDir : $oldDir . '/'; 
                            if (!is_dir($oldDir)) { 
                                $rs['msg'] = '原文件目录不存在';
                                $rs['success'] = false;
                                return $rs;
                            } 
                            if (!file_exists($aimDir)) { 
                                $rs['msg'] = '目标文件目录不存在';
                                $rs['success'] = false;
                                return $rs; 
                            } 
                            $dirHandle = opendir($oldDir); 
                            while (false !== ($file = readdir($dirHandle))) {
                                if ($file == '.' || $file == '..') { 
                                    continue; 
                                }
                                if (!is_dir($oldDir . $file)) {
                                    $size = filesize($oldDir . $file);
                                    #开始对目标文件进行编号和hashname添加
                                    $filehashname = parent::hashname($file);
                                    $filetype = substr($file, strrpos($file, '.')+1);
                                    if($filetype == 'eml'){
                                        $fileintro = $emailsubject;
                                    }else{
                                        $fileinfo = trim(self::decode_mime_string($file));
                                        $info  =  pathinfo ($fileinfo); 
                                        $fileintro = $info['filename'];
                                        $filetype = $info['extension'];
                                    }

                                    $aimFile = $aimDir . $filehashname . '.' . $filetype;
                                    if(copy($oldDir . $file, $aimFile)){
                                        #操作成功， 进行插入数据库文件操作

                                        $last_arr = M_Document::getMaxfilecode(array('fs_parent'=>$parent_insertid));
                                        $maxfileid = $last_arr['data']+1;
                                        $time = date('Y-m-d H:i:s');
                                        $sql = "insert into " . self::$document_table . " set 
                                        fs_name='{$maxfileid}',
                                        fs_parent='{$parent_insertid}',
                                        fs_isdir='0',
                                        fs_group='{$login_user_info['u_parent']}',
                                        fs_user='{$login_user_info['u_id']}',
                                        fs_create='{$time}',
                                        fs_intro='{$fileintro}',
                                        fs_type='{$filetype}',
                                        fs_size='{$size}',
                                        fs_hashname='{$filehashname}',
                                        fs_is_share='{$fs_is_share}'";
                                        if(self::$db->query($sql)){
                                            $insertid = self::$db->last_insert_id();
                                            $rs['msg'] = '操作成功！';
                                            $rs['success'] = true;
                                            #记录文件操作日志
                                            $doclog = array('fs_id'=>$insertid, 'fs_name'=>$maxfileid, 'fs_hashname'=>$filehashname, 'fs_intro'=>$fileintro, 'fs_size'=>$size, 'fs_type'=>$filetype, 'log_user'=>$login_user_info['u_id'], 'log_type'=>0, 'log_lastname'=>$maxfileid);
                                            M_Log::doclog($doclog);
                                            #记录系统操作日志
                                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在目录 '.substr(M_Document::getFilenamepath($parent_insertid), 1).'（'.$emailsubject.'） 下创建文件'.substr(M_Document::getFilenamepath($insertid), 1).' 操作成功！'));
                                        }else{
                                            $rs['msg'] = '操作失败！';
                                            $rs['success'] = false;
                                        }
                                    } else {
                                        $rs['msg'] = '复制操作失败！';
                                        $rs['success'] = false;
                                    } 
                                }
                            } 
                            closedir($dirHandle);
                            return $rs;
                        }else{
                            $rs['msg'] = '插入数据库操作失败！';
                            $rs['success'] = false;
                            return $rs;
                        }

                    }else{
                        $rs['msg'] = '创建目录失败！';
                        $rs['success'] = false;
                        return $rs;
                    }
                }else{
                    $rs['msg'] = '邮件目录已存在！';
                    $rs['success'] = false;
                    return $rs;
                }

            }else{
                $rs['msg'] = '用户名密码错误！';
                $rs['success'] = false;
                return $rs;
            }

        }else{
            $rs['msg'] = '附件下载失败！';
            $rs['success'] = false;
            return $rs; 
        }
    }


    /**
    * put your comment there...
    * 
    * @param string $string
    * @return string
    */
    public static function decode_mime_string($string){
        if(($pos = strpos($string,"=?")) === false) return $string;
        while(!($pos === false)) {
            $newresult .= substr($string,0,$pos);
            $string = substr($string,$pos+2,strlen($string));
            $intpos = strpos($string,"?");
            $charset = substr($string,0,$intpos);
            $enctype = strtolower(substr($string,$intpos+1,1));
            $string = substr($string,$intpos+3,strlen($string));
            $endpos = strpos($string,"?=");
            $mystring = substr($string,0,$endpos);
            $string = substr($string,$endpos+2,strlen($string));
            if($enctype == "q") {
                $mystring = str_replace("_"," ",$mystring);
                $mystring = self::decode_qp($mystring);
            } else if ($enctype == "b")
                    $mystring = base64_decode($mystring);
                $newresult .= $mystring;
            $pos = strpos($string,"=?");
        }
        return mb_convert_encoding($newresult.$string, 'utf-8', $charset);
        //return $newresult.$string;
    }


    /**
    * put your comment there...
    * 
    * @param string $text
    * @return string
    */
    public static function decode_qp($text) {
        $text = quoted_printable_decode($text);
        /*
        $text = str_replace("\r","",$text);
        $text = ereg_replace("=\n", "", $text);
        $text = str_replace("\n","\r\n",$text);
        */
        $text = ereg_replace("=\r", "\r", $text);
        return $text;
    }


    /**
    * Email附件操作
    * 
    */
    public static function moveemailtoshare($data, $login_user_info) {
        self::init();
        set_time_limit(0);
        $emailtreepathvalue = isset($data['emailtreepathvalue']) ? $data['emailtreepathvalue'] : '';
        $emailtreepathid = isset($data['emailtreepathid']) ? $data['emailtreepathid'] : '';  //父级目录ID
        $emailmsgid = isset($data['emailmsgid']) ? $data['emailmsgid'] : '';
        $emailuidl = isset($data['emailuidl']) ? $data['emailuidl'] : '';
        $emailsubject = !empty($data['emailsubject']) ? $data['emailsubject'] : '无主题';
        $useremail = $login_user_info['u_email'];
        $password = isset($data['password']) ? $data['password'] : '';
        if(!$emailtreepathvalue){
            $rs['msg'] = '操作失败！';
            $rs['success'] = false;
            return $rs;
        }

        #判断目标目录是否是登陆用户可以操作的
        $haspower = M_Sharedocument::checksharedocpower($emailtreepathid, $login_user_info);
        if(false===$haspower){
            $rs['msg'] = '无权限操作此目录！';
            $rs['success'] = false;
            return $rs;
        }
        
        
        #开始下载EMAIL文件
        global $base_path;
        $oldmailpath = APP_PATH . '/POP3/tmp/' . $useremail . DS . $emailmsgid;

        $url = $base_path . 'POP3/test.php?op=save&id='.$emailmsgid.'&user='.$useremail.'&pass='.$password;
        #判断附件已经下载过，如果已经下载则直接进行文件移动操作，否则，下载移动
        if(!is_dir($oldmailpath) || ZF_Libs_IOFile::judge_empty_dir($oldmailpath)){
            $opts = array( 
            'http' => array( 
            'method'=>"GET", 
            'header'=>"Content-Type: text/html; charset=utf-8" 
            ) 
            ); 
            $timeOut = 360;
            $context = stream_context_create($opts); 
            if(function_exists('curl_init')){
                $ch = curl_init ();
                curl_setopt ( $ch, CURLOPT_URL, $url );
                curl_setopt ( $ch, CURLOPT_HEADER, 0 );
                curl_setopt ( $ch, CURLOPT_TIMEOUT, $timeOut );
                curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
                $content = curl_exec ( $ch );
                curl_close($ch);
            }elseif(function_exists('file_get_contents')){
                $content = @file_get_contents($url);
            }else{
                $rs['msg'] = '请检查环境配置！';
                $rs['success'] = false;
                return $rs;
            }
        }else{
            $content = json_encode(array('status'=>'ok'));
        }

        if(false!==$content){
            $res = json_decode($content);
            if(!empty($res->status) && $res->status == 'ok'){
                #下载成功在父级目录（$emailtreepathid）中建立子目录 【目录编号为自动编号， 目录名为邮件标题】
                #1、获取自动编号目录ID
                //$maxid = intval(M_Document::getMaxfilecode(array('fs_parent'=>$emailtreepathid))) + 1;
                $sql = "select * from " . self::$share_document_table . " where fs_name='email{$emailmsgid}' and fs_parent='{$emailtreepathid}' limit 1";
                $record = self::$db->get_col($sql);
                if(!$record){
                    $newhashname = parent::hashname($emailmsgid);
                    $newmailpath = PROJECT_DOC_PATH.M_Sharedocument::splitdocpath(M_Sharedocument::getParentpath($emailtreepathid)) . DS . $newhashname;
                    if(ZF_Libs_IOFile::mkdir($newmailpath)){ #创建目录成功，开始拷贝文件
                        $fs_code = $emailtreepathvalue . '-' . "email{$emailmsgid}";
                        #在数据库中插入新创建的目录
                        $time = date('Y-m-d H:i:s');
                        $sql = "insert into " . self::$share_document_table . " set 
                        fs_name='email{$emailmsgid}',
                        fs_parent='{$emailtreepathid}',
                        fs_isdir='1',
                        fs_group='{$login_user_info['u_parent']}',
                        fs_user='{$login_user_info['u_id']}',
                        fs_create='{$time}',
                        fs_intro='{$emailsubject}',
                        fs_hashname='{$newhashname}',
                        fs_code='{$fs_code}'";

                        if(self::$db->query($sql)){
                            $parent_insertid = self::$db->last_insert_id();

                            /*对目录的fs_id_path进行设置， 方便在前端进行目录树的展开和收缩*/
                            if(!empty($record['fs_id_path'])){
                                $fs_id_path = $record['fs_id_path'] . '-' . $parent_insertid;
                            }else{
                                $fs_id_path = substr(M_Sharedocument::getFileIdpath($parent_insertid), 1);
                            }
                            $sql = "update ". self::$share_document_table . " set fs_id_path='{$fs_id_path}' where fs_id='{$parent_insertid}'";
                            self::$db->query($sql);

                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'创建共享目录 '.substr(M_Sharedocument::getFilenamepath($parent_insertid), 1).'（'.$emailsubject.'）  操作成功！'));


                            #插入数据库中成功, 开始向创建的目录中移动文件 
                            $oldDir = $oldmailpath;
                            $aimDir = $newmailpath;

                            /*移动文件时对文件进行编号*/
                            $aimDir = str_replace('', '/', $aimDir); 
                            $aimDir = substr($aimDir, -1) == '/' ? $aimDir : $aimDir . '/'; 
                            $oldDir = str_replace('', '/', $oldDir); 
                            $oldDir = substr($oldDir, -1) == '/' ? $oldDir : $oldDir . '/'; 
                            if (!is_dir($oldDir)) { 
                                $rs['msg'] = '原文件目录不存在';
                                $rs['success'] = false;
                                return $rs;
                            } 
                            if (!file_exists($aimDir)) { 
                                $rs['msg'] = '目标文件目录不存在';
                                $rs['success'] = false;
                                return $rs; 
                            } 
                            $dirHandle = opendir($oldDir); 
                            while (false !== ($file = readdir($dirHandle))) {
                                if ($file == '.' || $file == '..') { 
                                    continue; 
                                }
                                if (!is_dir($oldDir . $file)) {
                                    $size = filesize($oldDir . $file);
                                    #开始对目标文件进行编号和hashname添加
                                    $filehashname = parent::hashname($file);
                                    $filetype = substr($file, strrpos($file, '.')+1);
                                    if($filetype == 'eml'){
                                        $fileintro = $emailsubject;
                                    }else{
                                        $fileinfo = trim(self::decode_mime_string($file));
                                        $info  =  pathinfo ($fileinfo); 
                                        $fileintro = $info['filename'];
                                        $filetype = $info['extension'];
                                    }

                                    $aimFile = $aimDir . $filehashname . '.' . $filetype;
                                    if(copy($oldDir . $file, $aimFile)){
                                        #操作成功， 进行插入数据库文件操作

                                        $last_arr = M_Sharedocument::getMaxfilecode(array('fs_parent'=>$parent_insertid));
                                        $maxfileid = $last_arr['data']+1;
                                        $time = date('Y-m-d H:i:s');
                                        $sql = "insert into " . self::$share_document_table . " set 
                                        fs_name='{$maxfileid}',
                                        fs_parent='{$parent_insertid}',
                                        fs_isdir='0',
                                        fs_group='{$login_user_info['u_parent']}',
                                        fs_user='{$login_user_info['u_id']}',
                                        fs_create='{$time}',
                                        fs_intro='{$fileintro}',
                                        fs_type='{$filetype}',
                                        fs_size='{$size}',
                                        fs_hashname='{$filehashname}'";
                                        if(self::$db->query($sql)){
                                            $insertid = self::$db->last_insert_id();
                                            $rs['msg'] = '操作成功！';
                                            $rs['success'] = true;
                                            #记录系统操作日志
                                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在共享目录 '.substr(M_Sharedocument::getFilenamepath($parent_insertid), 1).'（'.$emailsubject.'） 下共享文件'.substr(M_Sharedocument::getFilenamepath($insertid), 1).' 操作成功！'));
                                        }else{
                                            $rs['msg'] = '操作失败！';
                                            $rs['success'] = false;
                                        }
                                    } else {
                                        $rs['msg'] = '复制操作失败！';
                                        $rs['success'] = false;
                                    } 
                                }
                            } 
                            closedir($dirHandle);
                            return $rs;
                        }else{
                            $rs['msg'] = '插入数据库操作失败！';
                            $rs['success'] = false;
                            return $rs;
                        }

                    }else{
                        $rs['msg'] = '创建目录失败！';
                        $rs['success'] = false;
                        return $rs;
                    }
                }else{
                    $rs['msg'] = '邮件目录已存在！';
                    $rs['success'] = false;
                    return $rs;
                }

            }else{
                $rs['msg'] = '用户名密码错误！';
                $rs['success'] = false;
                return $rs;
            }

        }else{
            $rs['msg'] = '附件下载失败！';
            $rs['success'] = false;
            return $rs; 
        }
    }
}

