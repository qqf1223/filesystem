/*生成目录功能函数*/
function showgeneratedocPanel(){
    /*创建Panel*/
    var panelHeight = $("#displayCenterPanel").innerHeight();
    var generatedocPanel = Ext.create('Ext.panel.Panel', {
        autoWidth: true,
        height: panelHeight,
        html:'<iframe src="/index.php?c=document&a=listUserDocument" frameborder="0" marginheight="0" marginwidth="0" width="100%" height="100%"></iframe>'
    }); 
    
    return generatedocPanel;
}


/*生成共享文件目录功能函数*/
function showgeneratesharedocPanel(){
    /*创建Panel*/
    var panelHeight = $("#displayCenterPanel").innerHeight();
    var generatedocPanel = Ext.create('Ext.panel.Panel', {
        autoWidth: true,
        height: panelHeight,
        html:'<iframe src="/index.php?c=document&a=listUserShareDocument" frameborder="0" marginheight="0" marginwidth="0" width="100%" height="100%"></iframe>'
    }); 
    
    return generatedocPanel;
}

