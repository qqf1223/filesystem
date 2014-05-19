<?php
/**
* @name      M_Upload.php
* @describe  文件上传类
* @author    qinqf
* @todo       
* @changelog  
*/

class M_Upload extends M_Model
{
    static $db;
    static $document_table = 'fs_tree';
    static $log_table = 'fs_log';
    /*** 初始化操作 */
    public static function init(){
        self::$db = parent::init();     
    }

    /**
    * 文件上传操作
    * 
    */
    public static function upload($login_user_info) {
        set_time_limit(0);
        if (!empty($login_user_info) && isset($_FILES["uploads"]) || is_uploaded_file($_FILES["uploads"]["tmp_name"]) || $_FILES["uploads"]["error"] != 0) {
            $upload_file = $_FILES['uploads'];
            $file_info = pathinfo($upload_file['name']);
            $file_type = $file_info['extension'];
            $name = $_FILES['uploads']['tmp_name']; 

            self::init();
            $fs_name = !empty($_REQUEST['filecode']) ? addslashes(strip_tags(trim($_REQUEST['filecode']))) : ''; //文件编号
            $fs_intro = !empty($_REQUEST['filedesc']) ? addslashes(trim($_REQUEST['filedesc'])) : ''; //文件描述
            $fs_parent = !empty($_REQUEST['fs_id']) ? intval($_REQUEST['fs_id']) : 0;   //文件上级
            $login_user_id = $login_user_info['u_id']; //登录用户ID
            $groupid = $login_user_info['u_parent'];   //用户组ID
            $fs_haspaper = !empty($_REQUEST['haspaper']) ? intval($_REQUEST['haspaper']) : '';  //是否有纸版
            $fs_hasencrypt = !empty($_REQUEST['hasencrypt']) ? ($_REQUEST['hasencrypt']=='true'?1:0) : ''; //是否加密

            $savepath = !empty($_REQUEST['savePath']) ? PROJECT_DOC_PATH.M_Document::splitdocpath($_REQUEST['savePath']) : '';  //路径—+ 文件编号
            if(!$savepath){
                $savepath = PROJECT_DOC_PATH.M_Document::splitdocpath(M_Document::getParentpath($fs_parent));
            }
            $hashname = parent::hashname($savepath . DS . $fs_name);
            $savefilename = $hashname. '.' . strtolower($file_info['extension']); //要保存的文件名
            $save = $savepath . DS . $savefilename;

            $ext = strtolower($file_info['extension']);
            $fs_size = $upload_file['size'];
            $createTime = date('Y-m-d H:i:s');
            $fs_type = strtolower($ext);


            $verify = M_Usergroup::verify($login_user_info, 'uploadfile', $fs_parent);
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode($rs);exit;
            } 
            #2013-08-11  修改上传文件的权限继承文件夹的权限
            #查询文件夹权限
            $sql = "select * from " . self::$document_table . " where fs_id='{$fs_parent}' ";
            $parentDoc = self::$db->get_row($sql);
            if(!empty($parentDoc['fs_code'])){
                $log_save_path = $parentDoc['fs_code'];
                $fs_code = $log_save_path . '-' . $fs_name;
            }else{ #兼容以前程序
                $log_save_path = substr(M_Document::getFilenamepath($fs_parent),1);
                $fs_code = $log_save_path . '-' . $fs_name;
            }


            #判断文件是否已存在
            $checkresult = M_Document::checkSamedoc($fs_name, $fs_parent, 0);
            if($checkresult['flag']==1){
                if(isset($_REQUEST['coverfile'])&& $_REQUEST['coverfile']==1){ //判断用户是否对现有文件允许备份， 不允许的返回错误
                    #对现有文件进行更新操作, 对旧文件进行备份
                    $oldfilename = $checkresult['data']['fs_hashname'] . '.' . strtolower($checkresult['data']['fs_type']);
                    $oldfile = $savepath . DS . $oldfilename;
                    if(ZF_Libs_IOFile::backup($oldfile, $oldfilename)){
                        if (!move_uploaded_file($name, $save)) {
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'移动上传文件 '.$fs_intro.' 操作失败！'));
                            header('HTTP/1.1 500 Internal Server Error');
                            echo '操作失败';exit;
                        }
                        $sql = "UPDATE ".self::$document_table." SET
                        fs_parent='{$fs_parent}',
                        fs_group='{$parentDoc['fs_group']}',
                        fs_user='{$parentDoc['fs_user']}',
                        fs_create='{$createTime}',
                        fs_name='{$fs_name}',
                        fs_intro='{$fs_intro}',
                        fs_size='{$fs_size}',
                        fs_type='{$fs_type}',
                        fs_encrypt='{$fs_hasencrypt}',
                        fs_haspaper='{$fs_haspaper}',
                        fs_hashname='{$hashname}',
                        fs_code='{$fs_code}',
                        fs_is_share='{$parentDoc['fs_is_share']}' 
                        where fs_id='{$checkresult['data']['fs_id']}'";
                        $rs = self::$db->query($sql);
                        if($rs){
                            #记录文件操作日志
                            $doclog = array('fs_id'=>$checkresult['data']['fs_id'], 'fs_name'=>$fs_name, 'fs_hashname'=>$hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>1, 'log_lastname'=>$fs_name);
                            M_Log::doclog($doclog);
                            #记录系统操作日志
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'更新目录 '.$log_save_path.' 下的文件 '.substr(M_Document::getFilenamepath($checkresult['data']['fs_id']), 1).'（'.$checkresult['data']['fs_intro'].'） 为 '.$log_save_path.'-'.$fs_name.'（'.$fs_intro.'） 操作成功！'));
                            header("HTTP/1.1 200 OK");
                            $sql = "select * from " . self::$document_table . " where fs_id='{$checkresult['data']['fs_id']}' ";
                            $return = self::$db->get_row($sql);
                            if($return){
                                $return['fs_fullpath'] =  M_Document::getParentpath($checkresult['data']['fs_id']);
                                $return['exists'] = 1;
                                echo json_encode($return);
                            }else{
                                echo '';
                            }
                            //echo '操作成功';exit;
                            return true;
                        } else {
                            M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在目录 '.$log_save_path.'中上传了文件 '.$log_save_path.'-'.$fs_name.' 但是更新数据库数据失败！'));
                            header('HTTP/1.1 500 Internal Server Error');
                            echo '操作失败';exit;
                            return false;
                        }

                    } else{
                        #记录系统操作日志(备份失败)
                        M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'备份目录 '.$log_save_path.' 下的文件 '.$checkresult['data']['fs_name'].'（'.$checkresult['data']['fs_intro'].'） 操作失败！'));
                        header('HTTP/1.1 500 Internal Server Error');
                        echo '操作失败';exit;
                        return false;
                    }
                }else{
                    header('HTTP/1.1 500 Internal Server Error');
                    echo '{"result":"save img error!"}';exit;
                    return false;
                }
            }elseif($checkresult['flag']==2){
                #记录系统操作日志(备份失败)
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>$checkresult['msg']));
                header('HTTP/1.1 500 Internal Server Error');
                return false;
            }

            if (!move_uploaded_file($name, $save)) {
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在目录 '.$log_save_path.' 中上传文件 '.$log_save_path.'-'.$fs_name.'（'.$fs_intro.'） 操作失败！'));
                header('HTTP/1.1 500 Internal Server Error');
                return false;
            }

            $sql = "INSERT INTO ".self::$document_table." SET
            fs_parent='{$fs_parent}',
            fs_isdir=0,
            fs_group='{$parentDoc['fs_group']}',
            fs_user='{$parentDoc['fs_user']}',
            fs_create='{$createTime}',
            fs_name='{$fs_name}',
            fs_intro='{$fs_intro}',
            fs_size='{$fs_size}',
            fs_type='{$fs_type}',
            fs_encrypt='{$fs_hasencrypt}',
            fs_haspaper='{$fs_haspaper}',
            fs_hashname='{$hashname}',
            fs_code='{$fs_code}', 
            fs_is_share='{$parentDoc['fs_is_share']}'";

            $rs = self::$db->query($sql);
            if($rs){
                #记录日志
                $log_fs_id = self::$db->last_insert_id();
                #记录文件操作日志
                $doclog = array('fs_id'=>$log_fs_id, 'fs_name'=>$fs_name, 'fs_hashname'=>$hashname, 'fs_intro'=>$fs_intro, 'fs_size'=>$fs_size, 'fs_type'=>$fs_type, 'log_user'=>$login_user_info['u_id'], 'log_type'=>5, 'log_lastname'=>$fs_name);
                M_Log::doclog($doclog); 
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在目录 '.$log_save_path.' 中上传文件 '.$log_save_path.'-'.$fs_name.'（'.$fs_intro.'） 操作成功！'));
                header("HTTP/1.1 200 OK");
                $sql = "select * from " . self::$document_table . " where fs_id='{$log_fs_id}' ";
                $return = self::$db->get_row($sql);
                if($return){
                    $return['fs_fullpath'] =  M_Document::getParentpath($log_fs_id);
                    $return['exists'] = 0;
                    echo json_encode($return);
                }else{
                    echo '';
                }
                return true;
            } else {
                #记录系统操作日志
                M_Log::systemlog(array('login_user_name'=>$login_user_info['u_name'], 'login_user_email'=>$login_user_info['u_email'], 'desc'=>'在目录 '.$log_save_path.' 中上传文件 '.$log_save_path.'-'.$fs_name.'（'.$fs_intro.'） 数据库操作失败！'));
                header('HTTP/1.1 500 Internal Server Error');
                return false;
            }
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            return false;
        }
    }
    /****************************************************
    //将数组的输出存起来以供查看
    $fileName = 'test.txt';
    $upload_file['savePath'] = $save;
    $postData = var_export(array_merge($upload_file, $_REQUEST), true);
    $file = fopen($fileName, "a+");
    fwrite($file,$postData);
    fclose($file);
    /****************************************************/



}

