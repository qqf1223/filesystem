<?php
//require_once APP_PATH . DS . "include" . DS . "db_mysql.class.php";
include(ROOT_PATH . DS . 'ZF' . DS . 'Db' . DS . 'db_mysql.class.php');
//echo ROOT_PATH . DS . 'ZF' . DS . 'Db' . DS . 'db_mysql.class.php';
/*
class DB_Local_Read extends DB_Sql {
    var $Host = "localhost";
    var $Database = "filemgr";
    var $User = "root";
    var $Password = "";
    var $LinkName = "conn_fs_read";
    var $CharSet = "UTF8";
}
*/
class DB_Local_Read extends DB_Sql {
    var $Host = "localhost";
    var $Database = "filesystem";
    var $User = "root";
    var $Password = "";
    var $LinkName = "conn_fs_read";
    var $CharSet = "UTF8";
}
    