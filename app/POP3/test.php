<?php
error_reporting(E_ERROR);
set_time_limit(0);
define('ROOT' , dirname(__FILE__));
//必须导入的文件
require_once('Socket.php');
require_once('POP3.php');
require_once('PEAR.php');
require_once('parse.php');

//-----------------------config------------------------------
//logpath 为保存附件的目录
$logpath = ROOT.'/tmp/';
$mgs_id=3; //测试用3号邮件带附件
$user=$_GET['user'];
$pass=$_GET['pass'];
//$host='mail.cmec.com';
$host='pop.staff.cntv.cn'; #测试用
$port="110";
//-----------------------config------------------------------

//测试区
//1.初始化
$pop3 = new Net_POP3($user,$pass,$host,$port);
#$parse = new mime_decode();

//2.获得list
if(strtolower($_GET['op']) == 'list'){
	$top = intval($_GET['top']);
	if(empty($top)) exit('not top values');
	$list = $pop3->newlist($top);
	echo(json_encode($list));
	exit;
}

/*
 * 保存附件到服务器 
 * @param  string $mgs_id 邮件列表中的ID
 * @param  string $mgs_id 邮件列表中的ID
 * @param  string $logpath 附件保存路径
 * @param  bool   $eml    是否保存eml副本
 * @param  bool   $content   是否保存内容
 * @return array   附件路径
 * */
if(strtolower($_GET['op']) == 'save'){
	$mgs_id = intval($_GET['id']);
	if(empty($mgs_id)) exit('not id values');
	$eml = TRUE;
	$savepath = $pop3->saveAttachment($mgs_id,$logpath,$eml, $user);
    $rs['status'] = 'ok';
    $rs['savepath'] = $savepath;
	echo(json_encode($rs));
    exit;
}
?>
