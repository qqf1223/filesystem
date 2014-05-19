function showsysloggrid(){
    var itemsPerPage = 50;
    var sysloggridstore = Ext.create('Ext.data.Store', {
        autoLoad: { start: 0, limit: itemsPerPage },
        fields: ['log_id', 'log_date', 'log_user', 'log_email', 'log_desc'],
        pageSize: itemsPerPage,
        proxy: {
            type: 'ajax',
            url: base_path+"index.php?c=log&a=syslogdata",
            reader: {
                type: 'json',
                root: 'rows',
                totalProperty: 'total'
            }
        }
        //autoLoad:false

    });
    //var gridHeight = document.body.clientHeight * 19;
    var gridHeight = $("#displayCenterPanel").innerHeight();
    var sysloggridPanel = Ext.create('Ext.grid.Panel', {
        autoWidth: true,
        height: gridHeight,
        frame: true,
        store: sysloggridstore,
        multiSelect: false,
        columns: [
        //{xtype: 'rownumberer'}, 
        /*{ header: '日志ID', width: 80, dataIndex: 'log_id', sortable: true, menuDisabled : true},*/
        { header: '操作时间', width: 150, dataIndex: 'log_date', sortable: true, menuDisabled : true  },
        { header: '操作用户', width: 100, dataIndex: 'log_user', sortable: true,menuDisabled : true },
        { header: '用户邮箱', width: 200, dataIndex: 'log_email',sortable: false, menuDisabled : true},
        { header: '操作描述', width: 600, dataIndex: 'log_desc', sortable: false, menuDisabled : true }
        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: sysloggridstore,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        }],
        listeners:{
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent(); 
            }
        }
    });       
    return sysloggridPanel;
}




function showdocloggrid(){
    var itemsPerPage = 50;
    var docloggridstore = Ext.create('Ext.data.Store', {
        autoLoad: { start: 0, limit: itemsPerPage },
        fields: ['log_id', 'fs_id', 'fs_name', 'fs_textname', 'fs_hashname', 'fs_intro', 'fs_size', 'fs_type', 'log_user', 'log_type', 'log_lastname', 'log_optdate', 'u_name', 'fs_parent'],
        pageSize: itemsPerPage,
        proxy: {
            type: 'ajax',
            url: base_path+"index.php?c=log&a=doclogdata",
            reader: {
                type: 'json',
                root: 'rows',
                totalProperty: 'total'
            }
        }
    });
    function formatFilesize(size){
        if(size==0)return '';
        if (size>=1024*1024*1204){
            size = parseFloat(size/(1024*1024*1204)).toFixed(1)+'GB';
        }
        else if(size >= 1024*1024){
            size = parseFloat(size / (1024*1024)).toFixed(1) + 'MB';
        }
        else if(size >= 1024){
            size = parseFloat(size / 1024).toFixed(1) + 'KB';
        }
        else{
            size = parseFloat(size).toFixed(1) + 'B';
        }
        return size;
    }
    var gridHeight = $("#displayCenterPanel").innerHeight();
    var docLoggridPanel = Ext.create('Ext.grid.Panel', {
        autoWidth: true,
        height: gridHeight,
        frame: true,
        store: docloggridstore,
        multiSelect: false,
        columns: [
        //{xtype: 'rownumberer'}, 
        /*{ header: '日志ID', width: 80, dataIndex: 'log_id', sortable: true, menuDisabled : true},
        { header: '文件ID', width: 80, dataIndex: 'fs_id', sortable: true,menuDisabled : true }, */
        { header: '操作时间', width: 150, dataIndex: 'log_optdate', sortable: false, menuDisabled : true },
        { header: '操作用户', width: 120, dataIndex: 'u_name', sortable: true, menuDisabled : true  },
        { header: '文件编号', width: 150, dataIndex: 'fs_textname', sortable: true,menuDisabled : true },
        { header: '文件名称', width: 200, dataIndex: 'fs_intro', sortable: true,menuDisabled : true },
        { header: '大小', width: 75, align:'right', dataIndex: 'fs_size', renderer:formatFilesize, sortable: true,menuDisabled : true },
        { header: '类型', width: 80, align:'center', dataIndex: 'fs_type',sortable: false, menuDisabled : true},
        { header: '操作', width: 100, dataIndex: 'log_type', sortable: true, menuDisabled : true  }
        /*{ header: '最新编号', width: 100, dataIndex: 'log_lastname', sortable: false, menuDisabled : true },*/
        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: docloggridstore,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        }],
        viewConfig: {
            getRowClass: function (record, rowIndex, rowParams, store) {
                if (record.get('log_type') == "删除") {
                    return "del_row_style"; 
                }
            }
        },
        listeners:{
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();
                var recoverdocument_flag=false; 
                for(var p=0; p<power.length; p++){
                    switch(power[p]){
                        case 'recoverdocument' : recoverdocument_flag=true;break;
                    }
                }
                if(recoverdocument_flag && rcd.raw.log_type=='删除' && parseInt(rcd.raw.fs_size)>0){
                    var menu = Ext.create('Ext.menu.Menu', {
                        float: true,
                        items:[{
                            text: '恢复',
                            iconCls: 'icon-doc-rollback',
                            handler: function(){
                                this.up("menu").hide();
                                var msgTip = Ext.MessageBox.show({
                                    title:'提示',
                                    width: 250,
                                    msg: '正在恢复中……'
                                }); 
                                //AJAX请求恢复
                                Ext.Ajax.request({
                                    url: base_path + "index.php?c=document&a=recoverdocument",
                                    params : rcd.data,
                                    method : 'POST',
                                    success: function(response, options){
                                        msgTip.hide();
                                        var result = Ext.JSON.decode(response.responseText);
                                        if(result.success){
                                            Ext.Msg.alert('提示', result.msg);
                                            return true;
                                        }else{
                                            Ext.Msg.alert('提示', result.msg); 
                                            return false;
                                        }
                                    }
                                });
                            }
                        }]
                    });
                    menu.showAt(event.getXY());
                }  
            }
        }
    });

    return docLoggridPanel;
}