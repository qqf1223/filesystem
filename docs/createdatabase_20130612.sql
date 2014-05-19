CREATE DATABASE `filesystem` /*!40100 COLLATE 'utf8_general_ci' */;
USE `filesystem`;
CREATE TABLE `fs_tree` (
 `fs_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
 `fs_parent` INT(11) NOT NULL DEFAULT '0' COMMENT '所在目录',
 `fs_isdir` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0:文件  1:目录',
 `fs_group` INT(11) NOT NULL DEFAULT '0' COMMENT '所在组',
 `fs_user` INT(11) NOT NULL DEFAULT '0' COMMENT '所属用户',
 `fs_create` DATETIME DEFAULT NULL COMMENT '创建时期和时间',
 `fs_lastmodify` DATETIME DEFAULT NULL COMMENT '最后修改日期时间',
 `fs_name` VARCHAR(1024) DEFAULT NULL COMMENT '文件名称',
 `fs_intro` VARCHAR(1024) DEFAULT NULL COMMENT '文件说明',
 `fs_size` INT(11) DEFAULT NULL COMMENT '文件大小',
 `fs_type` VARCHAR(50) DEFAULT NULL COMMENT '文件类型 扩展名',
 `fs_encrypt` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0:普通  1:加密',
 `fs_haspaper` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0:电子版(过程文件)  1:纸版',
 `fs_hashname` VARCHAR(255) DEFAULT NULL COMMENT 'hash名称', PRIMARY KEY (`fs_id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;
CREATE TABLE `fs_user` (
 `u_id` INT(10) NOT NULL AUTO_INCREMENT COMMENT '成员ID',
 `u_parent` INT(11) DEFAULT NULL COMMENT '所在组',
 `u_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '名称',
 `u_email` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '登录邮箱',
 `u_isgroup` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0:组员  1:组',
 `u_grade` VARCHAR(255) DEFAULT NULL COMMENT '角色权限',
 `u_pwd` VARCHAR(32) DEFAULT NULL COMMENT '用户密码', PRIMARY KEY (`u_id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8 COMMENT='各组及成员';
CREATE TABLE `fs_sys_log` (
 `log_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
 `log_date` DATETIME DEFAULT NULL COMMENT '日志发生时间',
 `log_user` VARCHAR(50) DEFAULT NULL COMMENT '操作者姓名',
 `log_email` VARCHAR(50) DEFAULT NULL COMMENT '操作者登录邮箱',
 `log_desc` TEXT COMMENT '操作描述', PRIMARY KEY (`log_id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8 COMMENT='系统操作日志';
CREATE TABLE `fs_log` (
 `log_id` INT(10) NOT NULL AUTO_INCREMENT COMMENT '日志id',
 `fs_id` INT(10) DEFAULT NULL COMMENT '对应的文件树id',
 `fs_name` VARCHAR(512) DEFAULT NULL COMMENT '本次操作产生的名称',
 `fs_hashname` VARCHAR(512) DEFAULT NULL COMMENT '每次对文件的更新操作都会产生的hash名称',
 `fs_intro` VARCHAR(1024) DEFAULT NULL COMMENT '文件说明',
 `fs_size` INT(11) DEFAULT NULL COMMENT '文件大小',
 `fs_type` CHAR(10) DEFAULT NULL COMMENT '文件类型（扩展名）',
 `log_user` INT(11) DEFAULT NULL COMMENT '操作的用户',
 `log_type` TINYINT(4) DEFAULT NULL COMMENT '0:创建 1:更新 2:改名 3:移动 4:删除 5:上传 6:下载',
 `log_lastname` VARCHAR(512) DEFAULT NULL COMMENT '操作之前的名称',
 `log_optdate` DATETIME DEFAULT NULL COMMENT '操作时间', PRIMARY KEY (`log_id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8 COMMENT='修改历史记录';
INSERT INTO `fs_user`(`u_parent`,`u_name`,`u_email`,`u_isgroup`,`u_grade`,`u_pwd`) VALUES (0,'超级管理员','admin@admin.com',0,'100','96e79218965eb72c92a549dd5a330112');