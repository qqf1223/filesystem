<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%
	String path = request.getContextPath();
	String basePath = request.getScheme()+"://"+request.getServerName()+":"+request.getServerPort()+path+"/";
%>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<base href="<%=basePath%>">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Insert title here</title>
<link rel="stylesheet" href="ext/resources/css/ext-all.css" >
<script type="text/javascript" src="ext/ext-all-dev.js"></script>
<script type="text/javascript" src="ext/local/ext-lang-zh_CN.js"></script>
<script type="text/javascript" src="app/view/fileupload/UploadPanel.js"></script>
<script type="text/javascript" src="swfupload.js"></script>
<script type="text/javascript" src="plugins/swfupload.speed.js"></script>
<script type="text/javascript" src="plugins/swfupload.queue.js"></script>
<script type="text/javascript">
Ext.onReady(function(){
	new Ext.window.Window({
		width : 650,
		title : 'swfUpload demo',
		height : 300,
		layout : 'fit',
		items : [
			{
				xtype:'fileuploadPanel',
				border : false,
				fileSize : 1024*4000,//限制文件大小单位是字节
				uploadUrl : 'upload-files.shtml',//提交的action路径
				flashUrl : 'js/swfupload/swfupload.swf',//swf文件路径
				filePostName : 'uploads', //后台接收参数
				fileTypes : '*.*',//可上传文件类型
				postParams : {savePath:'upload\\'} //http请求附带的参数
			}
		]
	}).show();
	
});
</script>
</head>
<body>
<div id="div1"></div>
</body>
</html>