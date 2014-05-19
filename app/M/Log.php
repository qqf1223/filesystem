<?php
    /**
    * @name      M_Log.php
    * @describe  日志Model类
    * @author    qinqf
    * @version   1.0 
    * @todo 归类日志      
    * @changelog  
    */  
    class M_Log extends M_Model{

        static $db;
        static $syslog_table_name = 'fs_sys_log';
        static $doclog_table_name = 'fs_log';
        static $user_table_name = 'fs_user';
        /*** 初始化操作 */
        public static function init(){
            self::$db = parent::init();     
        }

        /**
        * #记录系统操作日志
        * 
        */
        public static function systemlog($data) {
            $desc = addslashes($data['desc']);
            self::init();
            $now = date('Y-m-d H:i:s');
            $sql = "insert into ".self::$syslog_table_name." set 
            log_date='{$now}',
            log_user='{$data['login_user_name']}',
            log_email='{$data['login_user_email']}',
            log_desc='{$desc}'";
            $rs = self::$db->query($sql);
            return true;
        }

        /**
        * 记录文件操作记录
        * 
        * @param mixed $data
        */
        public static function doclog($data){
            self::init();

            $fs_id = intval($data['fs_id']);
            $fs_name = mysql_real_escape_string($data['fs_name']);
            $fs_hashname = mysql_real_escape_string($data['fs_hashname']);
            $fs_intro = mysql_real_escape_string($data['fs_intro']);
            $fs_size = intval($data['fs_size']);
            $fs_type = $data['fs_type'];
            $log_type = $data['log_type'];
            $log_lastname = $data['log_lastname'];
            $log_user = intval($data['log_user']);
            $createtime = date('Y-m-d H:i:s');
            $fs_parent = !empty($data['fs_parent']) ? addslashes($data['fs_parent']) : '';
            $fs_code = !empty($data['fs_code']) ? addslashes($data['fs_code']) : '';

            $sql = "insert into ".self::$doclog_table_name." set 
            fs_id='{$fs_id}',
            fs_name='{$fs_name}',
            fs_hashname='{$fs_hashname}',
            fs_intro='{$fs_intro}',
            fs_size='{$fs_size}',
            fs_type='{$fs_type}',
            log_user='{$log_user}',
            log_type='{$log_type}',
            log_lastname='{$log_lastname}',
            log_optdate='{$createtime}'";
            if($fs_code){
                $sql .= " ,fs_code='{$fs_code}' ";
            }
            if($fs_parent){
                $sql .= " ,fs_parent='{$fs_parent}'";
            }
            self::$db->query($sql);
            return true;  
        }

        /**
        * 显示系统操作日志
        * 
        * @param mixed $login_user_info
        */
        public static function showsyslog($data, $login_user_info){
            $verify = M_Usergroup::verify($login_user_info, 'readsyslog');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $page = !empty($data['page']) ? intval($data['page']) : 1;
            //$pagenum = 20;
            $offset = !empty($data['start']) ? intval($data['start']) : 0;//($page - 1) * $pagenum;
            $pagenum = !empty($data['limit']) ? intval($data['limit']) : 50;
            $limit = ' limit ' . $offset . ', ' . $pagenum;
            if($login_user_info['u_grade']==100){
                $where = '';
            }elseif($login_user_info['u_grade']==99 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){  //超级管理员|系统监察员|项目部负责人|部门负责人
                $where = '';
            }elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){  //组管理员，组领导
                #获取组成员信息
                $sql = "select * from ".self::$user_table_name." where u_parent='{$login_user_info['u_parent']}'";
                $groupuser = self::$db->get_results($sql);
                $user_email_arr = array();
                if($groupuser){
                    foreach($groupuser as $user){
                        $user_email_arr[] = $user['u_email']; 
                    }
                }
                $user_email_str = "'".implode("','", $user_email_arr)."'";
                $where = " and log_email in(".$user_email_str.")";
            }else{
                $where = " and log_email in('".$login_user_info['u_email']."')";
            }

            $sql = "select count(*) from " . self::$syslog_table_name . " where 1 ".$where;
            $count_rs = self::$db->get_col($sql);
            $count=$count_rs[0];

            //$pages = ceil($count/$pagenum);

            $sql = "select * from ". self::$syslog_table_name . " where 1 ".$where." order by log_id desc ".$limit;
            $log_rs = self::$db->get_results($sql);

            $rs["rows"] = $log_rs;
            $rs["total"] = $count;  
            return $rs;
        }

        /**
        * 显示文件操作日志
        * 
        * @param mixed $data
        * @param mixed $login_user_info
        */
        public static function showdoclog($data, $login_user_info){
            $verify = M_Usergroup::verify($login_user_info, 'readdoclog');
            if(!$verify){
                $rs['msg'] = '对不起，您没有此操作权限';
                $rs['success'] = false;
                return $rs;
            }
            self::init();
            $page = !empty($data['page']) ? intval($data['page']) : 1;

            $offset = !empty($data['start']) ? intval($data['start']) : 0;;
            $pagenum = !empty($data['limit']) ? intval($data['limit']) : 50;
            $limit = ' limit ' . $offset . ', ' . $pagenum;
            if($login_user_info['u_grade']==100){
                $where = '';
            }elseif($login_user_info['u_grade']==99 || $login_user_info['u_grade']==98 || $login_user_info['u_grade']==4 || $login_user_info['u_grade']==3){  //超级管理员|系统监察员|项目部负责人|部门负责人
                $where = '';
            }elseif($login_user_info['u_grade']==1 || $login_user_info['u_grade']==2){  //组管理员，组领导
                #获取组成员信息
                $sql = "select * from ".self::$user_table_name." where u_parent='{$login_user_info['u_parent']}'";
                $groupuser = self::$db->get_results($sql);
                $user_id_arr = array();
                if($groupuser){
                    foreach($groupuser as $user){
                        $user_id_arr[] = $user['u_id']; 
                    }
                }
                $user_id_str = "'".implode("','", $user_id_arr)."'";
                $where = " and log_user in(".$user_id_str.")";
            }else{
                $where = " and log_user in('".$login_user_info['u_id']."')";
            }

            $sql = "select count(*) from " . self::$doclog_table_name . " where 1 ".$where;
            $count_rs = self::$db->get_col($sql);
            $count=$count_rs[0];

            $sql = "select * from ". self::$doclog_table_name . " left join ".self::$user_table_name . ' on log_user=u_id  where 1 ' .$where." order by log_id desc ".$limit;

            $log_rs = self::$db->get_results($sql);
            if(!empty($log_rs)){
                foreach($log_rs as &$val){
                    $val['log_type'] = self::getOperateType($val['log_type']);
                    if($val['fs_code']){
                        $val['fs_textname'] = $val['fs_code'];
                    } else {
                        $fs_textname = substr(M_Document::getFilenamepath($val['fs_id']), 1);
                        $val['fs_textname'] = $fs_textname ? $fs_textname : $val['fs_name'];
                    }
                }
            }
            $rs["rows"] = $log_rs;
            $rs["total"] = $count;  
            return $rs;
        }



        /**
        * 显示操作日志
        * 
        * @param mixed $type
        */
        public static function showfilelog($type='all'){
            $doc_log_file = LOG_PATH . 'document.log';
            $doc_log_content = ZF_Libs_IOFile::read($doc_log_file);
            return $doc_log_content;
        }

        /**
        * 记录日志到文件中
        * 
        */
        public static function logtofile($msg, $file='document.log'){
            $opmsg = "\r\n".date('Y-m-d H:i:s')."\t". $msg . "\r\n";
            ZF_Libs_IOFile::mkdir(LOG_PATH);
            error_log($opmsg, 3, LOG_PATH.DS.$file); 
        }

        /**
        * 根据类型ID获取操作名称
        * 
        * @param mixed $typeid
        */
        public static function getOperateType($typeid){
            #0:创建 1:更新 2:改名 3:移动 4:删除 5:上传 6:下载
            switch($typeid){
                case 0: $rs='创建';break;
                case 1: $rs='更新';break;
                case 2: $rs='改名'; break;
                case 3: $rs='移动';break;
                case 4: $rs='删除';break;
                case 5: $rs='上传';break;
                case 6: $rs='下载';break;
                case 7: $rs='还原';break;
                default: $rs='';
            }
            return $rs;
        }

    }
?>
