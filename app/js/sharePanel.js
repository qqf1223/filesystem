var sharedocstore = Ext.create('Ext.data.TreeStore', {
    autoLoad: false,
    proxy:{
        type:'ajax', 
        url: base_path + "index.php?c=document&a=listsharedocument",
        reader:'json'
    },
    fields:['id', 'fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'managerok', 'fs_code', 'fs_isproject', 'fs_id_path', 'fs_is_share', 'fs_group', 'fs_user', 'fs_parent', 'fs_type', 'fs_create', 'fs_lastmodify', 'fs_size', 'fs_encrypt', 'fs_haspaper', 'fs_hashname']
});
//function getsharefsTreePanel(){
var sharefsTreePanel = Ext.create('Ext.tree.Panel', {
    rootVisible: false,
    singleExpand: false,
    height : '100%',
    width:'100%',
    multiSelect : true,
    root:{
        name: '系统管理员',
        id:'root',
        expanded: true},
    border:1,
    store: sharedocstore,
    viewConfig:{
        plugins:{
            ptype: 'treeviewdragdrop',
            appendOnly: true
        }
    }
});
sharefsTreePanel.on({
    'beforeitemmove':function(node, oldParent, newParent, index, eOpts){
        if(Ext.isEmpty(newParent) || newParent.get('id')=='root' || newParent.get('fs_isdir')=='0'){
            Ext.Msg.alert('提示', '目标目录错误， 请重现选择');
            return false;
        }
        Ext.Msg.show({  
            title:'提示',
            closable: false, 
            msg:'确定移动'+node.get('text')+' 到 '+newParent.get('text')+'下吗？', 
            icon:Ext.MessageBox.QUESTION,
            buttons: Ext.Msg.YESNO,
            fn: function(btn){
                if(btn=='yes'){
                    dragsharedata(node, oldParent, newParent);
                }
                return false;
            } 
        });
        return false;
    },
    //目录树单击事件
    'itemclick' : function(view, rcd, item, idx, event, eOpts) {
        event.stopEvent();
        if(rcd.get('fs_isdir')==1){
            showsharedocumentgrid(rcd);
            setsharecookienav(rcd);
        }
    },
    //目录树双击击事件
    'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
        if(rcd.get('fs_isdir')=='0'){
            opensharefile(view, rcd, item, idx, event, eOpts);
        }
    },
    'beforeitemexpand': function(rcd, eOpts){
        sharedocstore.setProxy({
            type:'ajax', 
            url:base_path + "index.php?c=document&a=listsharedocument&fs_id="+rcd.get('fs_id'),
            reader:'json'
        });
    },
    //目录数右键事件
    'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
        event.preventDefault();
        event.stopEvent();
        var selectlength = sharefsTreePanel.getSelectionModel().selected.length;
        if(selectlength>1){
            showsharemenu(view, rcd, item, idx, event, eOpts);
            return false;
        }else{
            projectTreePanel.getSelectionModel().select(rcd); 
            showsharemenu(view, rcd, item, idx, event, eOpts);
        }
        /*
        event.preventDefault();
        event.stopEvent();
        sharefsTreePanel.getSelectionModel().select(rcd);
        showsharemenu(view, rcd, item, idx, event, eOpts, rcd.parentNode, 'tree');
        */
    },
    scope : this
});
//return sharefsTreePanel;
//} 
//function showsharefsPanel(){
function dragsharedata(node, oldparent, newparent){
    var nodeid = node.get('fs_id');
    //oldparent = node.parentNode;
    var oldparentid = oldparent.get('fs_id');
    var newparentid = newparent.get('fs_id');

    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在移动……'
    });
    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=movesharedocument",
        params : {nodeid:nodeid, nodehashname:node.get('fs_hashname'), oldparentid:oldparentid, newparentid:newparentid, document_name: node.get('fs_name'), fs_type:node.get('fs_type'), fs_size:node.get('fs_size'), fs_intro:node.get('fs_intro'), fs_isdir:node.get('fs_isdir')},
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                Ext.Msg.alert('提示', result.msg);
                node.remove();
                refreshsharetree(newparent, 1);
                showsharedocumentgrid(newparent);
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        },
        failure: function(form, action){
            Ext.Msg.alert('温馨提示', action.result.msg); 
            return false;
        } 
    });
}

var sharefsPanel = Ext.create('Ext.panel.Panel', {
    layout: 'border',
    width : '100%',
    height: '100%',
    items: [{ 
        region: 'west',
        //title: '共享文件列表',
        collapsible: false,
        width:250,
        split: true,
        layout: 'fit',
        items:sharefsTreePanel,
        autoScroll : false
    }, {
        region: 'center',
        id: 'sharedocarea',
        autoScroll: true
    }]
});


var itemsPerPage = 50;
var sharedocumentgridstore = Ext.create('Ext.data.Store', {
    fields: ['text', 'fs_name', 'fs_isdir', 'fs_intro', 'fs_encrypt', 'fs_haspaper', 'fs_create', 'fs_lastmodify', 'u_name', 'fs_id', 'icon', 'fs_size', 'managerok', 'fs_id_path'],
    pageSize: itemsPerPage,
    autoLoad: false,
    remoteSort: true
});
function showsharedocumentgrid(rcd, contentid){
    var _contentid = 'sharedocarea';
    var _store = sharedocumentgridstore;
    var _url = base_path + "index.php?c=document&a=listsharedocumentgrid&fs_id="+rcd.get('fs_id');

    var parentrcd = rcd;
    _store.setProxy({
        type: 'ajax',
        url: _url, 
        reader: {
            type: 'json',
            root: 'rows',
            totalProperty: 'total'
        }
    });
    _store.loadPage(1, { start: 0, limit: itemsPerPage });
    //var gridHeight = $("#"+_contentid).innerHeight();
    var gridHeight = Ext.getCmp(_contentid).getHeight();
    var icon = function(val){
        if(val){
            return '<img src="'+val+'">'; 
        }else{
            return '<img src="'+images_path+'folder.gif">';
        }
    };
    var ishaspaper = function(val){
        var rcd = arguments[2];
        var isdir = rcd.get('fs_isdir');
        if(val=='1' && isdir=='0'){
            return '有';
        }else if(val=='0' && isdir=='0'){
            return '无'; 
        }else{
            return '';
        }
    };    
    var isencrypt = function(val){
        if(val=='1'){
            return '<font color="red">已加密</font>';
        }else if(val=='0'){
            return '否'; 
        }else{
            return '';
        }
    };
    var formatFileSize=function(size){
        if(!size){return '';}
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
    var docgrid = Ext.create('Ext.grid.Panel', {
        //autoWidth: true,
        //title: 'Simpsons',
        height: gridHeight,
        frame: true,
        store: _store,
        multiSelect: false,
        columns: [
        { header: '类型', width: 40, dataIndex: 'icon', renderer: icon,sortable: false, menuDisabled : true},
        { header: '文件编号', width: 120, dataIndex: 'text', sortable: true,menuDisabled : true },
        { header: '文件名称', width: 150, dataIndex: 'fs_intro',sortable: false, menuDisabled : true},
        { header: '纸版', width: 50, dataIndex: 'fs_haspaper', renderer: ishaspaper, menuDisabled : true },
        { header: '大小', align:'right', width: 70, dataIndex: 'fs_size', renderer: formatFileSize, menuDisabled : true },
        { header: '创建时间', width: 150, dataIndex: 'fs_create', menuDisabled : true },
        { header: '更新时间', width: 150, dataIndex: 'fs_lastmodify', menuDisabled : true },
        { header: '所属用户', width: 120, dataIndex: 'u_name', sortable: false, menuDisabled : true },
        { header: '是否加密', width: 70, dataIndex: 'fs_encrypt', renderer: isencrypt, sortable: false, menuDisabled : true  }
        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: _store,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        }],
        listeners:{
            'itemdblclick': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();
                oepndocument(view, rcd, item, index, event, eOpts, _contentid);
            },
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();                
                showsharemenu(view, rcd, item, index, event, eOpts, parentrcd, _contentid);
            },
            'containercontextmenu':function(view, event, eOpts ){
                event.stopEvent();
                if(_contentid=='displayCenterPanel'){
                    var menu = Ext.create('Ext.menu.Menu', {
                        float: true,
                        items: [{
                            text: '新建目录',
                            iconCls:'icon-doc-new',
                            handler: function(){
                                this.up("menu").hide();
                                addsharedocumentform(rcd);
                            }
                        },{
                            text:'上传文件',
                            iconCls:'icon-doc-upload', 
                            handler: function(){
                                this.up('menu').hide();
                                var uppanel = Ext.create('Org.fileupload.Panel',{
                                    //var uppanel = Ext.create('Org.dragfileupload.Panel',{
                                    width : '100%',
                                    title : '上传文件---目录'+rcd.get('text'),
                                    items : [
                                    {
                                        border : false,
                                        fileSize : 1024*1000000,//限制文件大小单位是字节
                                        uploadUrl : base_path+'index.php?c=upload&a=uploadsharedoc',//提交的action路径
                                        flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                                        filePostName : 'uploads', //后台接收参数
                                        fileTypes : '*.*',//可上传文件类型
                                        parentNode : rcd,
                                        postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                                    }
                                    ]
                                });
                                Ext.getCmp('displayCenterPanel').remove(Ext.getCmp('displayCenterPanel').items.get(0));
                                Ext.getCmp('displayCenterPanel').add(uppanel).doLayout();
                            }
                        }]
                    }).showAt(event.getXY());
                }
            }
        }
    });
    Ext.getCmp(_contentid).remove(Ext.getCmp(_contentid).items.get(0)); 
    Ext.getCmp(_contentid).add(docgrid).doLayout();
}

function addsharedocumentform(rcd){
    var adddocumentformPanel = Ext.create('Ext.form.Panel', {
        autoHeight : true,
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            autoFitErrors: false,
            labelSeparator : '：',
            labelWidth: 80,
            width: 300,
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
            xtype:'hiddenfield',
            name: 'project_doc_parentid',
            value: rcd.get('fs_id')
        },{
            xtype:'textfield',
            name: 'project_doc_name',
            id: 'project_doc_name',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '目录编号'
        }, {
            xtype:'textfield',
            width: 300,
            name: 'project_doc_intro',
            id: 'project_doc_intro',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '目录名称'
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(adddocumentformPanel.form.isValid()){
                    adddocumentformPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=addsharedocument',
                        method: 'post',
                        timeout: 30,
                        params: adddocumentformPanel.getForm().getValues(),
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshsharetree(rcd);
                            showsharedocumentgrid(rcd, 'sharedocarea');
                            win.hide();
                        },
                        failure: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                        }
                    });
                }
            }
        }]
    });
    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:350,
        closeAction:'hide',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: adddocumentformPanel
    });
    adddocumentformPanel.form.reset();
    adddocumentformPanel.isAdd = true;
    win.setTitle('创建共享目录');
    win.show();
}

/*编辑目录*/
function editsharedocumentPanel(rcd, parentNode, from){
    if(rcd.get('fs_parent')=='0'){
        var dd = [{
            xtype:'hiddenfield',
            name: 'project_doc_id',
            value: rcd.get('fs_id')
        },{
            xtype:'hiddenfield',
            name: 'document_parentid',
            value: rcd.get('fs_parent')
        },{
            xtype:'hiddenfield',
            name: 'project_doc_oldintro',
            value: rcd.get('fs_intro')
        },{
            xtype:'textfield',
            width: 300,
            name: 'project_doc_intro',
            fieldLabel: '名称',
            value:rcd.get('fs_intro')
        }];
    }else{
        var dd = [{
            xtype:'hiddenfield',
            name: 'project_doc_id',
            value: rcd.get('fs_id')
        },{
            xtype:'hiddenfield',
            name: 'document_parentid',
            value: rcd.get('fs_parent')
        },{
            xtype:'hiddenfield',
            name: 'project_doc_oldintro',
            value: rcd.get('fs_intro')
        },{
            xtype:'textfield',
            name: 'project_doc_name',
            fieldLabel: '编号',
            width: 300,
            value:rcd.get('fs_name')
        }, {
            xtype:'textfield',
            width: 300,
            name: 'project_doc_intro',
            fieldLabel: '名称',
            value:rcd.get('fs_intro')
        }];
    }
    var editprojectform = Ext.create('Ext.form.Panel', {
        //autoHeight : true,
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            autoFitErrors: false,
            labelSeparator : '：',
            labelWidth: 50,
            //allowBlank: false,
            //blankText: '不允许为空',
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: dd,
        buttons:[{
            text: '确定',
            handler: function(){
                if(editprojectform.form.isValid()){
                    editprojectform.getForm().submit({
                        url: base_path+'index.php?c=document&a=editsharedocument',
                        method: 'post',
                        timeout: 30,
                        params: editprojectform.getForm().getValues(),
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            if(from=='tree' && rcd.get('fs_parent')!='0'){
                                var text=action.result.data.document_pathname+'（'+action.result.data.document_intro+'）';
                            }else{
                                var text=action.result.data.document_pathname;
                            }
                            rcd.set('text', text);
                            rcd.set('fs_name', action.result.data.document_name);
                            rcd.set('fs_intro', action.result.data.document_intro);
                            //refreshsharetree(rcd, 1);
                            //showsharedocumentgrid(rcd);
                            win.hide(); 
                        },
                        failure: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                        }
                    });
                }
            }
        }]
    });

    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:350,
        closeAction:'hide',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: editprojectform
    });
    editprojectform.isAdd = true;
    win.setTitle('编辑-'+rcd.get('fs_name'));
    win.show();
}

/*权限设置*/
function powersettingsharePanel(rcd){
    var powersettingPanel = Ext.create('Ext.form.Panel', {
        autoHeight : true,
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            autoFitErrors: false,
            labelSeparator : '：',
            labelWidth: 100,
            width: 280,
            allowBlank: true,
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
            xtype:'hiddenfield',
            name: 'project_doc_id',
            value: rcd.get('fs_id')
        },{
            xtype:'hiddenfield',
            name: 'project_doc_name',
            value: rcd.get('fs_name')
        },{
            xtype:'textfield',
            fieldLabel: '目录',
            width:280,
            readOnly:true,
            //value:rcd.raw.fs_name
            value:rcd.get('text')
        }, {
            xtype:'combo',
            name: 'workgroup_id',
            id: 'powersetting_share_workgroup_id',
            emptyText : '请选择工作组',
            listConfig:{
                emptyText: '请选择工作组',
                loadingText : '加载中……',
                maxHeight: 100,
                width:250
            },
            triggerAction: 'all',
            queryMode: 'local',
            editable: false,
            store: new Ext.data.Store({
                stortId: 'workgroupstore',
                proxy : {
                    type : 'ajax',
                    url : base_path+'index.php?c=usergroup&a=listworkgroup',
                    actionMethods : 'post',
                    reader : 'json'
                },
                fields : ['u_id', 'u_name'],
                autoLoad:true
            }),
            valueField: 'u_id',
            displayField: 'u_name',
            fieldLabel: '请选择工作组',
            listeners:{
                'select':function(combo, records, options){
                    Ext.getCmp('user_id').clearValue();
                    Ext.getCmp('user_id').store.load({
                        params:{
                            groupid: combo.getValue()
                        }
                    });
                },
                'afterRender' : function(combo) {
                    Ext.getCmp('powersetting_share_workgroup_id').setValue(rcd.get('fs_group'));
                    Ext.getCmp('user_id').clearValue();
                    Ext.getCmp('user_id').store.load({
                        params:{
                            groupid: rcd.get('fs_group')
                        }
                    });
                }
            }
        }, {
            xtype:'combo',
            name: 'user_id',
            id: 'user_id',
            emptyText : '请选择组员', 
            listConfig:{
                loadMask:false
                //loadingText : '正在加载组员信息',
            },
            triggerAction: 'all',
            queryMode: 'local',
            editable: false,
            fieldLabel: '请选择组员',
            store: new Ext.data.Store({   
                storeId:'personStore',   
                proxy: {   
                    type: 'ajax',   
                    url : base_path+'index.php?c=usergroup&a=listgroupuser',
                    reader: 'json' 
                },   
                fields: ['u_id', 'u_name'],  
                autoLoad:false
            }),
            valueField: 'u_id',
            displayField: 'u_name',
            listeners:{
                'afterRender' : function(combo) {
                    Ext.getCmp('user_id').setValue(rcd.get('fs_user'));
                }            
            }
        }],
        buttons:[{
            text: '修改',
            handler: function(){
                if(powersettingPanel.form.isValid()){
                    powersettingPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=addsharedocpower',
                        method: 'post',
                        timeout: 30,
                        params: powersettingPanel.getForm().getValues(),
                        success: function(form, action){
                            refreshsharetree(sharedocstore.getNodeById(rcd.get('fs_parent')), 1);
                            showdocumentgrid(rcd);
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            win.close();
                        },
                        failure: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                        }
                    });
                }
            }
        }]
    });

    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:350,
        closeAction:'destory',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: powersettingPanel
    });
    powersettingPanel.form.reset();
    powersettingPanel.isAdd = true;
    win.setTitle('目录权限设置');
    win.show();
}


function showsharemenu(view, rcd, item, idx, event, eOpts, parentrcd, from){
    var _contentid = arguments[7]==undefined ? 'displayCenterPanel' : arguments[7];
    var editdocument=adddocument=uploadfile=adddocpower=deldocument=downloadfile=powersetting=lookuphistory=false;  
    for(var p=0; p<power.length; p++){
        switch(power[p]){
            case 'editdocument' : editdocument=true;break;
            case 'adddocument': adddocument=true;break;
            case 'uploadfile': uploadfile=true;break;
            case 'deldocument': deldocument=true;break;
            case 'powersetting': powersetting=true;break;
            case 'downloadfile': downloadfile=true;break;
            case 'lookuphistory': lookuphistory=true;break;
        }
    }
    var editdocument_obj=adddocument_obj=uploadfile_obj=deldocument_obj=downloadfile_obj=updatefile_obj=history_obj=null;
    var editdocument_obj = {
        text: '修改编号或名称',iconCls:'icon-edit',
        handler: function(){
            this.up("menu").hide();
            if(rcd.get('fs_isdir')==1){
                editsharedocumentPanel(rcd, '', from);
            }else{
                editsharefilePanel(rcd, parentrcd, from); 
            }
        }
    };
    var adddocument_obj = {
        text: '新建目录',
        iconCls:'icon-doc-new',
        handler: function(){
            this.up("menu").hide();
            addsharedocumentform(rcd);
        }
    };

    var uploadfile_obj = {
        text:'上传文件',
        iconCls:'icon-doc-upload', 
        handler: function(){
            this.up('menu').hide();
            var uppanel = Ext.create('Share.fileupload.Panel',{
                width : '100%',
                title : '上传文件---目录'+rcd.get('text'),
                items : [
                {
                    border : false,
                    fileSize : 1024*1000000,//限制文件大小单位是字节
                    uploadUrl : base_path+'index.php?c=upload&a=uploadsharedoc',//提交的action路径
                    flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                    filePostName : 'uploads', //后台接收参数
                    fileTypes : '*.*',//可上传文件类型
                    parentNode : rcd,
                    postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                }
                ]
            });
            Ext.getCmp('sharedocarea').remove(Ext.getCmp('sharedocarea').items.get(0));
            Ext.getCmp('sharedocarea').add(uppanel).doLayout();
        }
    };
    var draguploadfile_obj={
        text:'上传文件',
        iconCls:'icon-doc-upload', 
        handler: function(){
            this.up('menu').hide();
            var uppanel = Ext.create('Share.dragfileupload.Panel',{
                width : '100%',
                title : '上传文件---目录'+rcd.get('text'),
                //draggable:true,
                items : [
                {
                    border : false,
                    fileSize : 1024*1000000,//限制文件大小单位是字节
                    uploadUrl : base_path+'index.php?c=upload&a=uploadsharedoc',//提交的action路径
                    flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                    filePostName : 'uploads', //后台接收参数
                    fileTypes : '*.*',//可上传文件类型
                    parentNode : rcd,
                    postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                }
                ]
            });
            Ext.getCmp('sharedocarea').remove(Ext.getCmp('sharedocarea').items.get(0));
            Ext.getCmp('sharedocarea').add(uppanel).doLayout();
        }
    };    
    var deldocument_obj = {
        text:'删除',
        iconCls:'icon-doc-remove', 
        handler: function(){
            this.up('menu').hide();
            var selectlength = sharefsTreePanel.getSelectionModel().getSelection().length;
            if(selectlength>1){
                var selectrcd = sharefsTreePanel.getSelectionModel().getSelection();
                var array=[];
                for(var i in selectrcd){
                    if(Ext.isEmpty(array)){
                        array.push(selectrcd[i].parentNode);
                    }else
                        if(!Ext.Array.contains(array, selectrcd[i].parentNode)){
                        Ext.Msg.alert('提示', '请选择同一个目录下的文件进行操作！');
                        return false;
                    }
                }
                Ext.Msg.show({  
                    title:'提示',
                    closable: false, 
                    msg:'确定进行批量删除这'+selectlength+'个文件么？', 
                    icon:Ext.MessageBox.QUESTION,
                    buttons: Ext.Msg.OKCANCEL,
                    fn: function(btn){
                        if(btn=='ok'){
                            batch_delsharefs(selectrcd);
                        }
                        return false;
                    } 
                });
            }else{
                Ext.Msg.show({  
                    title:'提示',
                    closable: false, 
                    msg:'确定删除 '+rcd.get('text'), 
                    icon:Ext.MessageBox.QUESTION,
                    buttons: Ext.Msg.OKCANCEL,
                    fn: function(btn){
                        if(btn=='ok'){
                            delsharedoc(rcd, parentrcd);
                        }
                        return false;
                    } 
                });
            }
        }
    };
    var history_obj = {
        text:'历史版本',
        iconCls:'icon-doc-history', 
        handler: function(){
            this.up('menu').hide();
            showsharehistorygrid(rcd);
        }
    };
    var downloadfile_obj = {
        text:'下载',
        iconCls:'icon-doc-download', 
        handler: function(){
            this.up('menu').hide();
            Ext.Msg.show({  
                title:'提示',
                closable: false, 
                msg:'确定下载 '+rcd.get('fs_intro')+' 吗？', 
                icon:Ext.MessageBox.QUESTION,
                buttons: Ext.Msg.OKCANCEL,
                fn: function(btn){
                    if(btn=='ok'){
                        downloadsharefile(rcd);
                    }
                    return false;
                } 
            });
        }
    };
    var powersetting_obj = {
        text:'权限设置',
        iconCls:'icon-doc-setting',
        handler: function(){
            this.up('menu').hide();
            powersettingsharePanel(rcd);
        }
    };
    var refresh_obj={
        text: '刷新',
        iconCls: 'refresh',
        handler: function(){
            this.up("menu").hide();
            refreshsharetree(rcd, 1);
        }
    };
    var menu = Ext.create('Ext.menu.Menu', {
        float: true
    });
    var powers=['1', '99', '100'];
    var superpowers=['99', '100'];
    if(rcd.get('fs_parent')=='0'){
        menu.add(refresh_obj);
        if(adddocument && Boolean(parseInt(rcd.get('fs_isdir'))) && Ext.Array.contains(superpowers, login_user.u_grade)){
            menu.add(adddocument_obj);
            menu.add(uploadfile_obj);
        }
        if(editdocument && Ext.Array.contains(superpowers, login_user.u_grade) || editdocument && login_user.u_grade=='1' && rcd.get('fs_parent')!='0' && rcd.get('fs_group')==login_user.u_group){
            menu.add(editdocument_obj);
            rcd.get('fs_id')==1 || menu.add(deldocument_obj);
        }
    }else{
        Ext.isEmpty(rcd.isNode) || menu.add(refresh_obj);
        if(editdocument && Ext.Array.contains(superpowers, login_user.u_grade) || editdocument && login_user.u_grade=='1' && rcd.get('fs_parent')!='0' && rcd.get('fs_group')==login_user.u_group || editdocument && rcd.get('fs_group')==login_user.u_group && rcd.get('fs_user')==login_user.u_id && !Boolean(parseInt(rcd.get('fs_isdir')))){
            menu.add(editdocument_obj);
        }
        if(adddocument && Boolean(parseInt(rcd.get('fs_isdir'))) && Ext.Array.contains(superpowers, login_user.u_grade) || adddocument && Boolean(parseInt(rcd.get('fs_isdir'))) && login_user.grade=='1' && rcd.get('fs_group')==login_user.u_parent){
            menu.add(adddocument_obj);
        }
        if(uploadfile && Boolean(parseInt(rcd.get('fs_isdir'))) && Ext.Array.contains(superpowers, login_user.u_grade) || Boolean(parseInt(rcd.get('fs_isdir'))) && rcd.get('fs_group')==login_user.u_group && rcd.get('fs_user')==login_user.u_id){
            menu.add(uploadfile_obj);
        }
        if(Ext.Array.contains(superpowers, login_user.u_grade) || rcd.get('fs_group')==login_user.u_group && rcd.get('fs_user')==login_user.u_id){
            !deldocument || menu.add(deldocument_obj);
        }
        if(downloadfile && !Boolean(parseInt(rcd.get('fs_isdir')))){
            menu.add(downloadfile_obj);
        }
        var managerok = rcd.get('managerok');
        if(managerok && (Ext.Array.contains(superpowers, login_user.u_grade) || rcd.raw.fs_group==login_user.u_group && rcd.get('fs_user')==login_user.u_id)){
            !powersetting || !Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(powersetting_obj);
        }
    }
    menu.showAt(event.getXY());  

}

/*刷新目录*/
function refreshsharetree(node,current){
    if(!Ext.isEmpty(node)){
        var content = Ext.isEmpty(current)&&node.get('fs_parent')!='0' ? sharedocstore.getNodeById(node.get('fs_parent')) : sharedocstore.getNodeById(node.get('fs_id'));
        var fs_id =  Ext.isEmpty(current)&&node.get('fs_parent')!='0' ? node.get('fs_parent') : node.get('fs_id');
        sharedocstore.setProxy({
            type:'ajax', 
            url:base_path + 'index.php?c=document&a=shareRefresh&fs_id='+fs_id,
            reader:'json'
        });
        sharedocstore.load({node:content});
    }else{
        var sharefsTreePanel = sharefsTreePanel;
        while (delNode = sharefsTreePanel.getRootNode().childNodes[0]) {
            sharefsTreePanel.getRootNode().removeChild(delNode);
        }
        sharedocstore.setProxy({
            type:'ajax', 
            url:base_path + 'index.php?c=document&a=shareRefresh&fs_id=0',
            reader:'json'
        });
        sharedocstore.load();
    }
    //Ext.getCmp('tab-002').doLayout();
}

/*删除共享目录*/
function delsharedoc(fs){
    var fs_parent= !Ext.isEmpty(fs.parentNode) ? fs.parentNode : arguments[1];
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在删除……'
    });
    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=delsharedocument",
        params : fs.data,
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                Ext.Msg.alert('提示', result.msg);
                if(Ext.isEmpty(fs_parent)){
                    refreshsharetree(fs);
                } else {
                    refreshsharetree(fs);
                    showsharedocumentgrid(fs_parent);
                }
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        }
    });     
}

/*批量删除*/
function batch_delsharefs(selectrcd){
    var fs_parent=selectrcd[0].parentNode; //!Ext.isEmpty(fs.parentNode) ? fs.parentNode : arguments[1];
    for(var i in selectrcd){
        var fs = selectrcd[i];

        fs.remove();
        var msgTip = Ext.MessageBox.show({
            title:'提示',
            width: 250,
            msg: '正在删除……'
        });
        Ext.Ajax.request({
            url: base_path + "index.php?c=document&a=delsharedocument",
            params : fs.data,
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
    showsharedocumentgrid(fs_parent);     
}

function editsharefilePanel(rcd, rcd_parentnode, from){
    var rcd_parentnode = !Ext.isEmpty(rcd.parentNode) ? rcd.parentNode : arguments[1];
    function haspaper(val){
        if(val==rcd.get('fs_haspaper')){
            return true;
        } 
        return false;
    }
    function encrypt(val){
        if(val==rcd.get('fs_encrypt')){
            return true;
        } 
        return false;
    }
    var editprojectform = Ext.create('Ext.form.Panel', {
        //autoHeight : true,
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            autoFitErrors: false,
            labelSeparator : '：',
            labelWidth: 100,
            allowBlank: false,
            blankText: '不允许为空',
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
            xtype:'hiddenfield',
            name: 'file_id',
            value: rcd.get('fs_id')
        },{
            xtype:'hiddenfield',
            name: 'size',
            value: rcd.get('fs_size')
        },{
            xtype:'hiddenfield',
            name: 'type',
            value: rcd.get('fs_type')
        },{
            xtype:'hiddenfield',
            name: 'file_oldname',
            value: rcd.get('fs_name')
        },{
            xtype:'hiddenfield',
            name: 'file_parentid',
            value: rcd.get('fs_parent')
        },{
            xtype:'textfield',
            name: 'file_name',
            fieldLabel: '编号',
            width: 300,
            value:rcd.get('fs_name')
        }, {
            xtype:'textfield',
            width: 300,
            name: 'file_intro',
            fieldLabel: '名称',
            value:rcd.get('fs_intro')
        }, {
            xtype:'radiogroup',
            fieldLabel: '是否有纸版',
            width:200,
            items: [
            { boxLabel: '是', name: 'haspaper', inputValue: '1',checked:haspaper(1)},
            { boxLabel: '否', name: 'haspaper', inputValue: '0',checked:haspaper(0)}
            ]
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(editprojectform.form.isValid()){
                    editprojectform.getForm().submit({
                        url: base_path+'index.php?c=document&a=editsharefile',
                        method: 'post',
                        timeout: 30,
                        params: editprojectform.getForm().getValues(),
                        success: function(form, action){
                            win.hide();
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            if(from=='tree'){
                                fs_textname=action.result.data.document_pathname + '（'+action.result.data.document_intro+'）';
                            }else{
                                fs_textname=action.result.data.document_pathname; 
                            }
                            rcd.set('text', fs_textname);
                            rcd.set('fs_name', action.result.data.document_name);
                            rcd.set('fs_intro', action.result.data.document_intro);
                        },
                        failure: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                        }
                    });
                }
            }
        }]
    });

    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:350,
        closeAction:'hide',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: editprojectform
    });
    editprojectform.form.reset();
    editprojectform.isAdd = true;
    win.setTitle('编辑-'+rcd.get('fs_name'));
    win.show();
}


/*查看历史版本*/
function showsharehistorygrid(rcd){
    var itemsPerPage = 12;
    var filegridstore = Ext.create('Ext.data.Store', {
        autoLoad: { start: 0, limit: itemsPerPage },
        fields: ['fs_id', 'fs_name', 'fs_intro', 'log_type', 'log_user', 'fs_size', 'log_optdate'],
        pageSize: itemsPerPage,
        proxy: {
            type: 'ajax',
            url: base_path + "index.php?c=document&a=showsharehistory&fs_id="+rcd.get('fs_id'), 
            reader: {
                type: 'json',
                root: 'rows',
                totalProperty: 'total'
            }
        }
    });
    function formatFileSize(size){
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
    var docgrid = Ext.create('Ext.grid.Panel', {
        //autoWidth: true,
        frame: true,
        store: filegridstore,
        multiSelect: false,
        columns: [
        { header: '文件编号', width: 80, dataIndex: 'fs_name', sortable: true,menuDisabled : true },
        { header: '文件名称', width: 150, dataIndex: 'fs_intro',sortable: false, menuDisabled : true},
        { header: '操作', width: 50, dataIndex: 'log_type', menuDisabled : true },
        { header: '操作用户', width: 100, dataIndex: 'log_user',  menuDisabled : true },
        { header: '大小', width: 80, dataIndex: 'fs_size', renderer: formatFileSize, menuDisabled : true },
        { header: '操作时间', width: 150, dataIndex: 'log_optdate', menuDisabled : true }

        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: filegridstore,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        }],
        listeners:{
            'beforeitemdblclick': function(view, rcd, item, index, event, eOpts){
                event.stopEvent(); 
            },
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent(); 
                showgridmenu(view, rcd, item, index, event, eOpts);
            }
        }
    });
    function showgridmenu(view, rcd, item, index, event, eOpts){
        var gridmenu =Ext.create('Ext.menu.Menu', {});
        gridmenu.add({
            text: '下载',
            iconCls: 'icon-doc-download',
            handler: function(){
                this.up("menu").hide();
                downloadhistoryfile(rcd);
            }
        });
        gridmenu.showAt(event.getXY());
    }
    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:700,
        height: 400,
        closeAction:'hide',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: docgrid
    });
    win.setTitle('历史版本');
    win.show();
}

/*下载文件*/
function downloadsharefile(fs){
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在获取下载资源……'
    });

    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=downloadsharefile",
        params : fs.data,
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                location.href=base_path + "index.php?c=document&a=downloadsharefile&file="+result.msg;
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        }
    });    
}



function opensharefile(view, rcd, item, index, event, eOpts){
    window.open(base_path + "index.php?c=document&a=opensharefile&fs_id="+rcd.get('fs_id')+'&t='+rcd.get('fs_type'));
}
function opensharedocument(view, rcd, item, index, event, eOpts, contentid){
    if(rcd.get('fs_isdir')=='1'){
        showsharedocumentgrid(rcd, contentid);
        /*cookie 存储目录导航*/
        setsharecookienav(rcd);
        /*展开目录树*/
        var path = '-root-' + rcd.get('fs_id_path');
        sharefsTreePanel.expandPath(path, 'id', '-');
    }else{
        opensharefile(view, rcd, item, index, event, eOpts);
    }
}

var itemsPerPage = 50;
var sharedocumentgridstore = Ext.create('Ext.data.Store', {
    fields: ['text', 'fs_name', 'fs_isdir', 'fs_intro', 'fs_encrypt', 'fs_haspaper', 'fs_create', 'fs_lastmodify', 'u_name', 'fs_id', 'icon', 'fs_size', 'managerok', 'fs_id_path', 'fs_group', 'fs_user', 'fs_parent', 'fs_is_share', 'fs_type',  'fs_hashname', 'fs_fullpath'],
    pageSize: itemsPerPage,
    autoLoad: false,
    remoteSort: true
});





/**grid**/
function showsharedocumentgrid(rcd, contentid){
    var _contentid = Ext.isEmpty(contentid) ? 'sharedocarea' : contentid;
    var _store = sharedocumentgridstore;

    var _url = base_path + "index.php?c=document&a=listsharedocumentgrid&fs_id="+rcd.get('fs_id');

    var parentrcd = rcd;
    _store.setProxy({
        type: 'ajax',
        url: _url, 
        reader: {
            type: 'json',
            root: 'rows',
            totalProperty: 'total'
        }
    });
    _store.loadPage(1, { start: 0, limit: itemsPerPage });
    var gridHeight = Ext.getCmp(_contentid).getHeight();
    var icon = function(val){
        if(val){
            return '<img src="'+val+'">'; 
        }else{
            return '<img src="'+images_path+'folder.gif">';
        }
    };
    var ishaspaper = function(val){
        var rcd = arguments[2];
        var isdir = rcd.get('fs_isdir');
        if(val=='1' && isdir=='0'){
            return '有';
        }else if(val=='0' && isdir=='0'){
            return '无'; 
        }else{
            return '';
        }
    };    
    var isencrypt = function(val){
        if(val=='1'){
            return '<font color="red">已加密</font>';
        }else if(val=='0'){
            return '否'; 
        }else{
            return '';
        }
    };
    var formatFileSize=function(size){
        if(!size){return '';}
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
    /*获取导航目录 */   
    var scrollMenu = Ext.create('Ext.menu.Menu');
    var treepathcookie = !Ext.isEmpty(eval(Ext.util.Cookies.get('sharetreepath'))) ? eval(Ext.util.Cookies.get('sharetreepath')) : [];
    for (var i = 0; i < treepathcookie.length; ++i){
        scrollMenu.add({
            text: treepathcookie[i].text,
            fs_id: treepathcookie[i].fs_id,
            iconCls: 'icon-doc-open',
            listeners: {
                click: function(val){
                    openShareNavdocument(val.fs_id);
                }
            }
        });
    }

    var docgrid = Ext.create('Ext.grid.Panel', {
        //autoWidth: true,
        //title: 'Simpsons',
        height: gridHeight,
        frame: true,
        store: _store,
        multiSelect: false,
        columns: [
        { header: '类型', width: 40, dataIndex: 'icon', renderer: icon,sortable: false, menuDisabled : true},
        { header: '文件编号', width: 120, dataIndex: 'text', sortable: true,menuDisabled : true },
        { header: '文件名称', width: 150, dataIndex: 'fs_intro',sortable: false, menuDisabled : true},
        { header: '纸版', width: 50, dataIndex: 'fs_haspaper', renderer: ishaspaper, menuDisabled : true },
        { header: '大小', align:'right', width: 70, dataIndex: 'fs_size', renderer: formatFileSize, menuDisabled : true },
        { header: '创建时间', width: 150, dataIndex: 'fs_create', menuDisabled : true },
        { header: '更新时间', width: 150, dataIndex: 'fs_lastmodify', menuDisabled : true },
        { header: '所属用户', width: 120, dataIndex: 'u_name', sortable: false, menuDisabled : true }
        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: _store,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        },{
            xtype: 'toolbar',
            dock: 'top',
            id: 'sharenavtoolbar',
            items: [{
                text: '',
                //xtype: 'splitbutton',
                iconCls: 'go_history',
                id:'go_share_history',
                disabled: false,
                handler:function(){
                    var selectdocid=Ext.util.Cookies.get("shareselectdocid"); 
                    for(var i=0;i<treepathcookie.length; i++){
                        //边界判断
                        if(treepathcookie[i].fs_id==selectdocid && i!=0){
                            openShareNavdocument(treepathcookie[i-1].fs_id, 'back');
                        }
                    }
                }
            },'-',{
                text: '',
                //xtype: 'splitbutton',
                iconCls: 'go_forward',
                id:'go_share_forward',
                disabled: false,
                handler: function(){
                    var selectdocid=Ext.util.Cookies.get("shareselectdocid");;
                    for(var i=0;i<treepathcookie.length; i++){
                        //边界判断
                        if(treepathcookie[i].fs_id==selectdocid && i!=treepathcookie.length-1){
                            openShareNavdocument(treepathcookie[i+1].fs_id, 'forward');
                        }
                    }
                }
            },'-',{
                text: '更多',
                //iconCls: 'x-toolbar-more-icon',
                menu: scrollMenu 
            }]
        }],
        listeners:{
            'itemdblclick': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();
                opensharedocument(view, rcd, item, index, event, eOpts, _contentid);
            },
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();                
                showsharemenu(view, rcd, item, index, event, eOpts, parentrcd, _contentid);
            },
            'containercontextmenu':function(view, event, eOpts ){
                event.stopEvent();
                if(rcd.get('fs_parent')=='0' && Ext.Array.contains(['99', '100'], login_user.u_grade) || rcd.get('fs_parent')!='0'){ 
                    var menu = Ext.create('Ext.menu.Menu', {
                        float: true,
                        items: [{
                            text: '新建目录',
                            iconCls:'icon-doc-new',
                            handler: function(){
                                this.up("menu").hide();
                                addsharedocumentform(rcd);
                            }
                        },{
                            text:'上传文件',
                            iconCls:'icon-doc-upload', 
                            handler: function(){
                                this.up('menu').hide();
                                var uppanel = Ext.create('Share.fileupload.Panel',{
                                    //var uppanel = Ext.create('Org.dragfileupload.Panel',{
                                    width : '100%',
                                    title : '上传文件---目录'+rcd.get('text'),
                                    items : [
                                    {
                                        border : false,
                                        fileSize : 1024*1000000,//限制文件大小单位是字节
                                        uploadUrl : base_path+'index.php?c=upload&a=uploadsharedoc',//提交的action路径
                                        flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                                        filePostName : 'uploads', //后台接收参数
                                        fileTypes : '*.*',//可上传文件类型
                                        parentNode : rcd,
                                        postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                                    }
                                    ]
                                });
                                Ext.getCmp('sharedocarea').remove(Ext.getCmp('sharedocarea').items.get(0));
                                Ext.getCmp('sharedocarea').add(uppanel).doLayout();
                            }
                        }]
                    }).showAt(event.getXY());
                }
            }
        }
    });
    Ext.getCmp(_contentid).remove(Ext.getCmp(_contentid).items.get(0)); 
    Ext.getCmp(_contentid).add(docgrid).doLayout();
}




/*设置导航COOKIE*/
var sharecookiearr=[]; 
function setsharecookienav(rcd){
    /**将当前目录放入cookie中， 建立导航标签*/
    /*调用接口返回面包屑*/
    Ext.Ajax.request({
        url: base_path+'index.php?c=document&a=getsharenavdata',
        params: {
            fs_id: rcd.get('fs_id')
        },
        success: function(response){
            var text = response.responseText;
            text=eval(text);
            var navtoolbar=Ext.getCmp('sharenavtoolbar');  
            for(var i=text.length-1; i>=0; i--){
                text[i].text=text[i].fs_code;
                text[i].listeners={click: function(val){
                        openShareNavdocument(val.fs_id); /*打开导航目录*/
                }};
                navtoolbar.add(text[i]);
                if(i!=0){ 
                    navtoolbar.add({iconCls: 'x-toolbar-more-icon'}); 
                }
            }
        }
    });
    var text = rcd.get('fs_code')?rcd.get('fs_code'):rcd.get('fs_intro');
    if(sharecookiearr.length>0){
        var ishere = 0;
        if(arguments[1]==undefined){  //判断是否点击的是前进后退按钮 
            for(var i=0; i<sharecookiearr.length; i++){
                if(sharecookiearr[i].fs_id==rcd.get('fs_id')){
                    ishere=1;
                    var tmp=sharecookiearr[i];
                    sharecookiearr.splice(i,1);                         
                }
            }
            if(ishere==0){ 
                sharecookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code')?rcd.get('fs_code'):"", "text":text});
            }else{
                sharecookiearr.push(tmp); 
            }
        }else{
            for(var i=0; i<sharecookiearr.length; i++){
                if(sharecookiearr[i].fs_id==rcd.get('fs_id')){
                    ishere=1;                      
                }
            }
            if(ishere==0){ 
                sharecookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code')?rcd.get('fs_code'):"", "text":text});
            }
        }
    }else{
        sharecookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code')?rcd.get('fs_code'):"", "text":text});
    }

    var json_rcd = Serialize(sharecookiearr); 

    Ext.util.Cookies.set("sharetreepath", json_rcd);
    //设置选中目录的cookie
    Ext.util.Cookies.set("shareselectdocid", rcd.get('fs_id'));

    /*
    //对左右箭头进项可用性设置
    if(cookiearr.length>1){
        Ext.getCmp('go_share_history').setDisabled(false);
        if(arguments[1]!=undefined && arguments[1]=='back'){
            Ext.getCmp('go_share_forward').setDisabled(false);
        }
        else if(cookiearr[cookiearr.length-1].fs_id == rcd.get('fs_id')){
            Ext.getCmp('go_share_forward').setDisabled(true); 
        }
        else if(arguments[1]!=undefined && arguments[1]=='forward'){
            Ext.getCmp('go_share_forward').setDisabled(false);
        }
    }else{
        Ext.getCmp('go_share_history').setDisabled(true);
        Ext.getCmp('go_share_forward').setDisabled(true);
    }
    */
}

/*根据fs_id 获取 rcd*/
function openShareNavdocument(fs_id){

    /*根据fs_id获取grid中的 rcd 和 tree 中的 rcd*/    
    var rcd = sharedocstore.getNodeById(fs_id); 

    //收缩目录树
    if(rcd.isExpanded()){
        rcd.collapseChildren(true);
    }else{
        /*展开目录树*/
        var path = '-root-' + rcd.get('fs_id_path');
        //sharefsTreePanel.expandPath(rcd.getPath(), 'fs_id', '-'); 
        sharefsTreePanel.expandPath(path, 'id', '-');  
    }

    //显示grid数据
    showsharedocumentgrid(rcd);
    setsharecookienav(rcd, arguments[1]);  
}