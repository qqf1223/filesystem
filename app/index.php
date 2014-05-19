<?php
/**
* @name      index.php
* @describe  入口
* @author    qinqf
* @version   1.0 
* @copyright qinqf
* @todo       
* @changelog  
*/

require_once "include/config.setting.php";

/* zf */
define('LIB_PATH', ROOT_PATH.DS.'ZF');
require_once(LIB_PATH.DS.'ZF.php');//定义app和模版地址
define ("APP_TEMPLATE",APP_PATH.DS.'V'); 


//加载对象类
ZF::setNameSpace(APP_PATH.DS.'C');
ZF::setNameSpace(APP_PATH.DS.'M');

Base::run();
