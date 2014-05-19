<?php
	/**
	* @name      M_Default.php
	* @describe  我的报题操作类
	* @author    qinqf
	* @version   1.0 
	* @todo       
	* @changelog  
	*/  
	class M_Default extends M_Model {

		static $db;
		static $table_name = 'fc_ecms_myreport';
		/*** 初始化操作 */
		public static function init(){
			global $db;
			self::$db = $db;
		}

		/**
		* 获取我的报题信息
		* 
		*/
		public static function getBaoTi($fields=array(), $where) {
			
		}

	}
?>
