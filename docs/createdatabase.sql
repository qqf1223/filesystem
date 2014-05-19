-- MySQL dump 10.13  Distrib 5.5.25a, for Win32 (x86)
--
-- Host: localhost    Database: filesystem
-- ------------------------------------------------------
-- Server version	5.5.25a

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `fs_log`
--

DROP TABLE IF EXISTS `fs_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fs_log` (
  `log_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '日志id',
  `fs_id` int(10) DEFAULT NULL COMMENT '对应的文件树id',
  `fs_name` varchar(512) DEFAULT NULL COMMENT '本次操作产生的名称',
  `fs_hashname` varchar(512) DEFAULT NULL COMMENT '每次对文件的更新操作都会产生的hash名称',
  `fs_intro` varchar(1024) DEFAULT NULL COMMENT '文件说明',
  `fs_size` int(11) DEFAULT NULL COMMENT '文件大小',
  `fs_type` char(10) DEFAULT NULL COMMENT '文件类型（扩展名）',
  `log_user` int(11) DEFAULT NULL COMMENT '操作的用户',
  `log_type` tinyint(4) DEFAULT NULL COMMENT '0:创建 1:更新 2:改名 3:移动 4:删除 5:上传 6:下载',
  `log_lastname` varchar(512) DEFAULT NULL COMMENT '操作之前的名称',
  `log_optdate` datetime DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='修改历史记录';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fs_log`
--

LOCK TABLES `fs_log` WRITE;
/*!40000 ALTER TABLE `fs_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `fs_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fs_sys_log`
--

DROP TABLE IF EXISTS `fs_sys_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fs_sys_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `log_date` datetime DEFAULT NULL COMMENT '日志发生时间',
  `log_user` varchar(50) DEFAULT NULL COMMENT '操作者姓名',
  `log_email` varchar(50) DEFAULT NULL COMMENT '操作者登录邮箱',
  `log_desc` text COMMENT '操作描述',
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='系统操作日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fs_sys_log`
--

LOCK TABLES `fs_sys_log` WRITE;
/*!40000 ALTER TABLE `fs_sys_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `fs_sys_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fs_tree`
--

DROP TABLE IF EXISTS `fs_tree`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fs_tree` (
  `fs_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `fs_parent` int(11) NOT NULL DEFAULT '0' COMMENT '所在目录',
  `fs_isdir` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:文件  1:目录',
  `fs_group` int(11) NOT NULL DEFAULT '0' COMMENT '所在组',
  `fs_user` int(11) NOT NULL DEFAULT '0' COMMENT '所属用户',
  `fs_create` datetime DEFAULT NULL COMMENT '创建时期和时间',
  `fs_lastmodify` datetime DEFAULT NULL COMMENT '最后修改日期时间',
  `fs_name` varchar(1024) DEFAULT NULL COMMENT '文件名称',
  `fs_intro` varchar(1024) DEFAULT NULL COMMENT '文件说明',
  `fs_size` int(11) DEFAULT NULL COMMENT '文件大小',
  `fs_type` varchar(50) DEFAULT NULL COMMENT '文件类型 扩展名',
  `fs_encrypt` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:普通  1:加密',
  `fs_haspaper` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:电子版(过程文件)  1:纸版',
  `fs_hashname` varchar(255) DEFAULT NULL COMMENT 'hash名称',
  PRIMARY KEY (`fs_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fs_tree`
--

LOCK TABLES `fs_tree` WRITE;
/*!40000 ALTER TABLE `fs_tree` DISABLE KEYS */;
/*!40000 ALTER TABLE `fs_tree` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fs_user`
--

DROP TABLE IF EXISTS `fs_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fs_user` (
  `u_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '成员ID',
  `u_parent` int(11) DEFAULT NULL COMMENT '所在组',
  `u_name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `u_email` varchar(255) NOT NULL DEFAULT '' COMMENT '登录邮箱',
  `u_isgroup` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:组员  1:组',
  `u_grade` varchar(255) DEFAULT NULL COMMENT '角色权限',
  `u_pwd` varchar(32) DEFAULT NULL COMMENT '用户密码',
  PRIMARY KEY (`u_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='各组及成员';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fs_user`
--

LOCK TABLES `fs_user` WRITE;
/*!40000 ALTER TABLE `fs_user` DISABLE KEYS */;
INSERT INTO `fs_user` VALUES (1,0,'超级管理员','admin@admin.com',0,'100','96e79218965eb72c92a549dd5a330112');
/*!40000 ALTER TABLE `fs_user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-06-12 21:39:41
