<?php
include_once(ROOT_PATH . DS . 'ZF' . DS . 'Db' . DS . 'db_mysql.class.php');
//echo ROOT_PATH . 'ZF' . DS . 'DB' . DS . 'db_mysql.class.php';die;

class DB_Read extends DB_Sql {
    var $Host = "10.64.4.167";
    var $Database = "ich_cms";
    var $User = "root";
    var $Password = "cntv2010cn";
    var $LinkName = "conn_document_read";
    var $CharSet = "UTF8";
}

class DB_Local_Read extends DB_Sql {
    var $Host = "localhost";
    var $Database = "fs";
    var $User = "root";
    var $Password = "111111";
    var $LinkName = "conn_fs_read";
    var $CharSet = "UTF8";
}
    
    