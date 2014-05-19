<?php
    /**
    * @desc 文件IO
    * 
    * @copyright (c) 2011 
    * @author qinqf,2011-12-20
    * @package IOFile.class.php
    * @version IOFile.class.php,v 1.0 2011/11/16 8:57:57 qinqf Exp $
    */

    class ZF_Libs_IOFile
    {

        public static function write($file, $data, $append = false)
        {
            if (!file_exists($file)){
                if (!self::mkdir(dirname($file))) {
                    return false;
                }
            }
            $len  = false;
            $mode = $append ? 'ab' : 'wb';
            $fp = @fopen($file, $mode);
            if (!$fp) {
                exit("Can not open file $file !");
            }
            flock($fp, LOCK_EX);
            $len = @fwrite($fp, $data);
            flock($fp, LOCK_UN);
            @fclose($fp);
            return $len;
        }

        public static function read($file) {

            if (!file_exists($file)){
                return false;
            }
            if (!is_readable($file)) {
                return false;
            }
            $result = '';
            if (function_exists('file_get_contents')){
                $result = file_get_contents($file);
            }else{
                $result = (($contents = file($file))) ? implode('', $contents) : false; 
            }
            return $result;
        }

        /**
        * 建立文件夹
        * 
        * @param mixed $path
        * @return bool
        */
        public static function mkdir($path) 
        {
            $rst = true;
            if (!file_exists($path)){
                self::mkdir(dirname($path));
                $rst = @mkdir($path, 0777);
            }
            return $rst;
        }

        /**
        * 移除文件
        * 
        * @param string $path
        * @return bool
        */
        public static function rm($path)
        {    
            $path = rtrim($path,'/\\ ');
            if ( !is_dir($path) ){ return @unlink($path); }
            if ( !$handle= opendir($path) ){ 
                return false; 
            }
            while( false !==($file=readdir($handle)) ){
                if($file=="." || $file=="..") continue ;
                $file=$path . $file;
                if(is_dir($file)){ 
                    self::rm($file);
                } else {
                    if(!@unlink($file)){
                        return false;
                    }
                }
            }
            closedir($handle);
            if(!rmdir($path)){
                return false;
            }
            return true;
        }

        /**
        * copy 文件
        * 
        */
        public static function backup($srcfile, $targetfile){
            $date = date('Ymd'); //date('YmdHis');
            $dstfile = FILE_BACKUP_PATH . DS . $date .DS . $targetfile;
            if(self::moveFile($srcfile, $dstfile)){
                return true;
            } else {
                return false;
            }
        }

        /**
        * 移动文件
        *
        * @param string $fileUrl
        * @param string $aimUrl
        * @param boolean $overWrite 该参数控制是否覆盖原文件
        * @return boolean
        */
        public static function moveFile($fileUrl, $aimUrl, $overWrite = false) {
            if (!file_exists($fileUrl)) {
                return false;
            }
            if (file_exists($aimUrl) && $overWrite == false) {
                return false;
            } elseif (file_exists($aimUrl) && $overWrite == true) {
                self::rm($aimUrl);
            }
            $aimDir = dirname($aimUrl);
            self::mkdir($aimDir);
            rename($fileUrl, $aimUrl);
            return true;
        }

        /** 
        * 复制文件 
        * 
        * @param string $fileUrl 
        * @param string $aimUrl 
        * @param boolean $overWrite 该参数控制是否覆盖原文件 
        * @return boolean 
        */ 
        public static function copyFile($fileUrl, $aimUrl, $overWrite = false) { 
            if (!file_exists($fileUrl)) { 
                return false; 
            } 
            if (file_exists($aimUrl) && $overWrite == false) { 
                return false; 
            } elseif (file_exists($aimUrl) && $overWrite == true) { 
                self::rm($aimUrl); 
            } 
            $aimDir = dirname($aimUrl); 
            self::mkdir($aimDir); 
            copy($fileUrl, $aimUrl); 
            return true; 
        }

        /**
        * copy 目录
        * 
        * @param mixed $src
        * @param mixed $dst
        */
        function copy_dir($src,$dst) {  
            $dir = opendir($src);
            @mkdir($dst);
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . '/' . $file) ) {
                        copy_dir($src . '/' . $file,$dst . '/' . $file);
                        continue;
                    }
                    else {
                        copy($src . '/' . $file,$dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }

        /**
        * 判断文件是否为空
        * 
        * @param mixed $dir
        */
        public static function judge_empty_dir($dir){
            if(!is_dir($dir)) return false;
            if($handle = opendir($dir)){
                while($file = readdir($handle)){
                    if($file != '.' && $file != '..'){
                        closedir($handle);
                        return false;
                    }
                }
                closedir($handle);
                return true;
            }
        }

        /** 
        * 复制文件夹 
        * 
        * @param string $oldDir 
        * @param string $aimDir 
        * @param boolean $overWrite 该参数控制是否覆盖原文件 
        * @return boolean 
        */ 
        function copyDir($oldDir, $aimDir, $overWrite = false) { 
            $aimDir = str_replace('', '/', $aimDir); 
            $aimDir = substr($aimDir, -1) == '/' ? $aimDir : $aimDir . '/'; 
            $oldDir = str_replace('', '/', $oldDir); 
            $oldDir = substr($oldDir, -1) == '/' ? $oldDir : $oldDir . '/'; 
            if (!is_dir($oldDir)) { 
                return false; 
            } 
            if (!file_exists($aimDir)) { 
                self::mkdir($aimDir); 
            } 
            $dirHandle = opendir($oldDir); 
            while (false !== ($file = readdir($dirHandle))) { 
                if ($file == '.' || $file == '..') { 
                    continue; 
                } 
                if (!is_dir($oldDir . $file)) { 
                    self :: copyFile($oldDir . $file, $aimDir . $file, $overWrite); 
                } else { 
                    self :: copyDir($oldDir . $file, $aimDir . $file, $overWrite); 
                } 
            } 
            return closedir($dirHandle); 
        }
    }
?>
