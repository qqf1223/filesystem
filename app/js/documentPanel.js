Ext.override(Ext.data.TreeStore, {
    load : function(options) {
        options = options || {};
        options.params = options.params || {};

        var me = this, node = options.node || me.tree.getRootNode(), root;

        // If there is not a node it means the user hasnt defined a rootnode
        // yet. In this case lets just
        // create one for them.
        if (!node) {
            node = me.setRootNode( {
                expanded : true
            });
        }

        if (me.clearOnLoad) {
            node.removeAll(false);
        }

        Ext.applyIf(options, {
            node : node
        });
        options.params[me.nodeParam] = node ? node.getId() : 'root';

        if (node) {
            node.set('loading', true);
        }
        return me.callParent( [ options ]);
    }
});

Ext.override(Ext.data.TreeStore, {
    onUpdateRecords: function(records, operation, success){
        if (success) {
            var me = this,
            i = 0,
            length = records.length,
            data = me.data,
            original,
            parentNode,
            record;
            for (; i < length; ++i) {
                record = records[i];
                parentNode = me.tree.getNodeById(record.data.parentId);
                original = parentNode.lastChild;
                if (parentNode) {
                    // prevent being added to the removed cache
                    original.isReplace = true;
                    parentNode.replaceChild(record, original);
                    original.isReplace = false;
                }
            }
            if (record.dirty) {
                record.commit();
            }
        }
    }
});

Ext.override(Ext.grid.Scroller, {

    afterRender: function() {
        var me = this;
        me.callParent();
        me.mon(me.scrollEl, 'scroll', me.onElScroll, me);
        Ext.cache[me.el.id].skipGarbageCollection = true;
        // add another scroll event listener to check, if main listeners is active
        $(me.scrollEl.dom).scroll({scope: me}, me.onElScrollCheck);
    },

    // flag to check, if main listeners is active
    wasScrolled: false,

    // synchronize the scroller with the bound gridviews
    onElScroll: function(event, target) {
        this.wasScrolled = true; // change flag -> show that listener is alive
        this.fireEvent('bodyscroll', event, target);
    },

    // executes just after main scroll event listener and check flag state
    onElScrollCheck: function(event, target) {
        var me = event.data.scope;
        if (!me.wasScrolled)
            // Achtung! Event listener was disappeared, so we'll add it again
            me.mon(me.scrollEl, 'scroll', me.onElScroll, me);
        me.wasScrolled = false; // change flag to initial value
    }

}); 
var mystore = Ext.create('Ext.data.TreeStore', {
    //nodeParam: 'fs_id',
    proxy:{
        type: 'ajax',
        url: base_path + "index.php?c=document&a=listdocument",
        //data: <?=$login_user_tree?>,
        reader: {
            type: 'json',
            root: ''
        }
    },
    autoLoad: false,
    //mode: 'SIMPLE',
    fields:['id', 'fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'managerok', 'fs_code', 'fs_isproject', 'fs_id_path', 'fs_is_share', 'fs_group', 'fs_user', 'fs_parent', 'fs_type', 'fs_create', 'fs_lastmodify', 'fs_size', 'fs_encrypt', 'fs_haspaper', 'fs_hashname']
});
var projectTreePanel = Ext.create('Ext.tree.Panel', {
    rootVisible: false,
    singleExpand: false,
    width:'100%',
    autoScroll:false,
    autoHeight: true,
    multiSelect : true,
    root:{
        text: '中国机械设备工程股份有限公司',
        id:'root',
        //children: login_user_tree,
        expanded: true
    },
    border:0,
    store: mystore,
    //draggable: true,
    viewConfig:{
        plugins:{
            ptype: 'treeviewdragdrop',
            appendOnly: true
        }
    }

}); 
var task = new Ext.util.DelayedTask();
var oneClick = function(rcd){
    if(rcd.get('fs_isdir')==1){
        showdocumentgrid(rcd); 
        /**将当前文件夹放入cookie中， 建立导航标签*/
        setcookienav(rcd);     
}};
var  dbClick= function(view, rcd, item, idx, event, eOpts){
    if(rcd.get('fs_isdir')=='0'){
        openfile(view, rcd, item, idx, event, eOpts);
    }else{
        return false;
    }
};
function treebeforeitemexpand(rcd){
    projectTreePanel.getSelectionModel().select(rcd);
    showdocumentgrid(rcd);
    setcookienav(rcd);
}
/*导航标签*/
var navflag = {};
var cookiearr = [];
projectTreePanel.on({
    'beforeitemmove':function(node, oldParent, newParent, index, eOpts){
        if(Ext.isEmpty(newParent) || newParent.get('id')=='root' || newParent.raw.fs_isdir=='0'){
            Ext.Msg.alert('提示', '目标文件夹错误， 请重现选择');
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
                    dragtreepaneldata(node, oldParent, newParent);
                }
                return false;
            } 
        });
        return false;
    },
    //文件夹树单击事件
    'itemclick' : function(view, rcd, item, idx, event, eOpts) {
        event.stopEvent();
        task.delay(500, oneClick, this, [rcd]); //屏蔽双击问题
        return false;
    },     
    //文件夹树单击事件
    'beforeitemdbclick' : function(view, rcd, item, idx, event, eOpts) {
        event.stopEvent();
        return false;
    },    
    //文件夹树双击击事件
    'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
        event.stopEvent();
        //task.delay(300, dbClick, this, [view, rcd, item, idx, event, eOpts]); //屏蔽双击问题
        //return false;
        if(rcd.get('fs_isdir')=='0'){
            openfile(view, rcd, item, idx, event, eOpts);
        }else{
            return false;
        }
    },
    'beforeitemexpand': function(rcd, eOpts){
        projectTreePanel.getSelectionModel().select(rcd);
        if(!rcd.isLoaded() && !rcd.isExpanded()){
            mystore.setProxy({
                type:'ajax', 
                url:base_path + "index.php?c=document&a=listdocument&fs_id="+rcd.get('fs_id'),
                reader:'json'
            });
        }else{
            if(!rcd.isExpanded()){
                return true;
            }else{
                return false;
            }
        }
        //task.delay(500, treebeforeitemexpand, this, [rcd]); //屏蔽双击问题
    },
    'itemexpand':function(rcd, eOpts){
        //projectTreePanel.getSelectionModel().select(rcd);
        //setcookienav(rcd);
        //showdocumentgrid(rcd);
        task.delay(300, treebeforeitemexpand, this, [rcd]); //屏蔽双击问题
    },    
    beforeitemcollapse:function(rcd, eOpts){
        projectTreePanel.getSelectionModel().select(rcd);
    },
    //文件夹数右键事件
    'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
        event.preventDefault();
        event.stopEvent();
        var selectlength = projectTreePanel.getSelectionModel().selected.length;
        if(selectlength>1){
            showmenu(view, rcd, item, idx, event, eOpts);
            return false;
        }else{
            projectTreePanel.getSelectionModel().select(rcd); 
            showmenu(view, rcd, item, idx, event, eOpts);
        }
    },

    scope : this
});
/**/
function refreshtree(node,current){
    if(!Ext.isEmpty(node)){
        //var content = mystore.getNodeById(node.raw.fs_parent);//
        var content = node;//Ext.isEmpty(current)&&node.raw.fs_parent!='0' ? mystore.getNodeById(node.raw.fs_parent) : mystore.getNodeById(node.raw.fs_id);
        var fs_id =  Ext.isEmpty(current)&&node.raw.fs_parent!='0' ? node.raw.fs_parent : node.raw.fs_id;
        mystore.setProxy({
            type:'ajax', 
            url:base_path + 'index.php?c=document&a=refresh&fs_id='+fs_id,
            reader:'json'
        });
        mystore.load({node:content});
    }else{
        while (delNode = projectTreePanel.getRootNode().childNodes[0]) {
            projectTreePanel.getRootNode().removeChild(delNode);
        }
        mystore.setProxy({
            type:'ajax', 
            url:base_path + 'index.php?c=document&a=refresh&fs_id=0',
            reader:'json'
        });
        mystore.load();
    }

    projectTreePanel.doLayout();
    //Ext.getCmp('tab-002').doLayout();

}

function dragtreepaneldata(node, oldparent, newparent){
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
        url: base_path + "index.php?c=document&a=movedocument",
        params : {nodeid:nodeid, nodehashname:node.get('fs_hashname'), oldparentid:oldparentid, newparentid:newparentid, document_name: node.get('fs_name'), fs_type:node.get('fs_type'), fs_size:node.get('fs_size'), fs_intro:node.get('fs_intro'), fs_isdir:node.get('fs_isdir')},
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                Ext.Msg.alert('提示', result.msg);
                node.remove();
                refreshtree(newparent, 1);
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

function deldocumentfs(fs){
    var fs_parent=mystore.getNodeById(fs.get('fs_parent')); //!Ext.isEmpty(fs.parentNode) ? fs.parentNode : arguments[1];
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在删除……'
    });
    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=deldocument",
        params : fs.data,
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                Ext.Msg.alert('提示', result.msg);
                refreshtree(fs_parent, 1);
                showdocumentgrid(fs_parent); 
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        }
    });     
}
/*批量删除*/
function batch_deldocumentfs(selectrcd){
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
            url: base_path + "index.php?c=document&a=deldocument",
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
    showdocumentgrid(fs_parent);     
}
function downloadfilefs(fs){
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在获取下载资源……'
    });

    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=downloadfile",
        params : fs.raw,
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                location.href=base_path + "index.php?c=document&a=downloadfile&file="+result.msg;
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        }
    });    
}

function adddocumentform(rcd){
    var parentDocument={
        xtype:'textfield',
        fieldLabel: '上级文件夹',
        readOnly:true,
        value:rcd.get('text')
    };  

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
        },parentDocument, {
            xtype:'textfield',
            name: 'project_doc_name',
            id: 'project_doc_name',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '文件夹编号'
        }, {
            xtype:'textfield',
            width: 300,
            name: 'project_doc_intro',
            id: 'project_doc_intro',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '文件夹名称'
        },{
            xtype:'radiogroup',
            fieldLabel: '是否加密',
            width:250,
            items: [
            { boxLabel: '是', name: 'encrypt', inputValue: '1', checked:encrypt(1)},
            { boxLabel: '否', name: 'encrypt', inputValue: '0', checked:encrypt(0)}
            ]
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(adddocumentformPanel.form.isValid()){
                    adddocumentformPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=adddocument',
                        method: 'post',
                        timeout: 30,
                        params: adddocumentformPanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshtree(rcd,1);
                            showdocumentgrid(rcd);
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
    /*是否加密*/
    function encrypt(val){
        if(val==rcd.get('fs_encrypt')){
            return true;
        } 
        return false;
    }
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
    win.setTitle('新建文件夹');
    win.show();
}

function editdocumentformPanel(rcd){
    var editprojectform = Ext.create('Ext.form.Panel', {
        //autoHeight : true,
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            autoFitErrors: false,
            labelSeparator : '：',
            labelWidth: 80,
            allowBlank: false,
            blankText: '不允许为空',
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
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
            width: 250,
            value:rcd.get('fs_name')
        }, {
            xtype:'textfield',
            width: 250,
            name: 'project_doc_intro',
            fieldLabel: '名称',
            value:rcd.get('fs_intro')
        },{
            xtype:'radiogroup',
            fieldLabel: '是否加密',
            width:250,
            items: [
            { boxLabel: '是', name: 'encrypt', inputValue: '1', checked:encrypt(1)},
            { boxLabel: '否', name: 'encrypt', inputValue: '0', checked:encrypt(0)}
            ]
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(editprojectform.form.isValid()){
                    editprojectform.getForm().submit({
                        url: base_path+'index.php?c=document&a=editdocument',
                        method: 'post',
                        timeout: 30,
                        params: editprojectform.getForm().getValues(),
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            if(rcd.index==undefined){ //tree
                                rcd.set('text', action.result.data.document_pathname + '（'+action.result.data.document_intro+'）');
                                showdocumentgrid(rcd.parentNode);
                            }else{
                                rcd.set('text', action.result.data.document_pathname);
                            }
                            rcd.set('fs_name', action.result.data.document_name);
                            rcd.set('fs_intro', action.result.data.document_intro);
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
    /*是否加密*/
    function encrypt(val){
        if(val==rcd.get('fs_encrypt')){
            return true;
        } 
        return false;
    }
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


function editfileformPanel(rcd){
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
        }, {
            xtype:'radiogroup',
            fieldLabel: '是否加密',
            width:200,
            items: [
            { boxLabel: '是', name: 'encrypt', inputValue: '1', checked:encrypt(1)},
            { boxLabel: '否', name: 'encrypt', inputValue: '0', checked:encrypt(0)}
            ]
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(editprojectform.form.isValid()){
                    editprojectform.getForm().submit({
                        url: base_path+'index.php?c=document&a=editfile',
                        method: 'post',
                        timeout: 30,
                        params: editprojectform.getForm().getValues(),
                        success: function(form, action){
                            win.hide();/*
                            if(Ext.isEmpty(rcd_parentnode)){
                            refreshtree(rcd);
                            } else {
                            refreshtree(rcd);
                            showdocumentgrid(rcd_parentnode);
                            } 
                            */
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            if(rcd.index==undefined){ //tree
                                rcd.set('text', action.result.data.document_pathname + '（'+action.result.data.document_intro+'）');
                                showdocumentgrid(rcd_parentnode);
                            }else{
                                rcd.set('text', action.result.data.document_pathname);
                            }
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


function powersettingformPanel(rcd){
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
            fieldLabel: '文件夹',
            width:280,
            readOnly:true,
            //value:rcd.raw.fs_name
            value:rcd.get('text')
        }, {
            xtype:'combo',
            name: 'workgroup_id',
            id: 'powersetting_workgroup_id',
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
                    Ext.getCmp('powersetting_user_id').clearValue();
                    Ext.getCmp('powersetting_user_id').store.load({
                        params:{
                            groupid: combo.getValue()
                        }
                    });
                },
                'afterRender' : function(combo) {
                    //Ext.getCmp('powersetting_workgroup_id').setValue(rcd.raw.fs_group);
                    Ext.getCmp('powersetting_workgroup_id').setValue(rcd.get('fs_group'));
                    Ext.getCmp('powersetting_user_id').clearValue();
                    Ext.getCmp('powersetting_user_id').store.load({
                        params:{
                            groupid: rcd.get('fs_group')
                        }
                    });
                }
            }
        }, {
            xtype:'combo',
            name: 'powersetting_user_id',
            id: 'powersetting_user_id',
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
                    //Ext.getCmp('powersetting_user_id').setValue(rcd.raw.fs_user);
                    Ext.getCmp('powersetting_user_id').setValue(rcd.get('fs_user'));
                }            
            }
        }],
        buttons:[{
            text: '修改',
            handler: function(){
                if(powersettingPanel.form.isValid()){
                    powersettingPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=adddocpower',
                        method: 'post',
                        timeout: 30,
                        params: powersettingPanel.getForm().getValues(),
                        success: function(form, action){
                            //refreshtree(rcd,1);
                            //修改本地树中的store
                            alterrcdvalue(rcd, 'fs_group', Ext.getCmp('powersetting_workgroup_id').getValue())
                            alterrcdvalue(rcd, 'fs_user', Ext.getCmp('powersetting_user_id').getValue())
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
    win.setTitle('文件夹权限设置');
    win.show();
}

/*COPY 文件夹结构*/
function copydocstruct(rcd){
    parentrcd = rcd.parentNode;
    var parentDocument={
        xtype:'textfield',
        fieldLabel: '上级文件夹',
        readOnly:true,
        value:parentrcd.get('text')
    }; 
    /*用正则匹配出新的文件夹编号*/
    var fs_name=rcd.get('fs_name');
    var new_fs_name='';
    regex_a=/^-?\d+$/;
    if(regex_a.test(fs_name)){
        int_fs_name=parseInt(fs_name);
        new_fs_name=(int_fs_name + 1);
    }
    regex_b=/^0+\d+/;
    if(regex_b.test(fs_name)){
        int_fs_name=parseInt(fs_name);
        var pos=fs_name.indexOf(int_fs_name);
        var zerostr=fs_name.substring(0, pos);
        if((int_fs_name + 1).toString().length>int_fs_name.toString().length){
            zerostr=zerostr.substr(0, zerostr.length-1);
        }
        new_fs_name=zerostr + '' + (parseInt(fs_name) + 1);
    }
    regex_c=/^([A-Za-z]+)(\d+)$/;
    if(regex_c.test(fs_name)){
        regex_c_a=/\d+$/g;
        var regexstr=fs_name.match(regex_c);
        int_fs_name=parseInt(regexstr[2]);
        var pos=fs_name.indexOf(regexstr[1])+regexstr.length;
        var intpos=fs_name.indexOf(int_fs_name);
        var zerostr=fs_name.substring(pos, intpos);
        if((int_fs_name + 1).toString().length>int_fs_name.toString().length){
            zerostr=zerostr.substr(0, zerostr.length-1);
        }
        new_fs_name=regexstr[1] + '' + zerostr + (int_fs_name + 1);
    }


    /*
    var copyrcd = rcd.copy(true);
    parentrcd.appendChild(copyrcd);
    */
    var copydocstructPanel = Ext.create('Ext.form.Panel', {
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
            name: 'document_parentid',
            value: parentrcd.get('fs_id')
        },{
            xtype:'hiddenfield',
            name: 'current_doc_id',
            value: rcd.get('fs_id')
        },parentDocument, {
            xtype:'textfield',
            name: 'document_newname',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '文件夹编号',
            value:new_fs_name
        }, {
            xtype:'textfield',
            width: 300,
            name: 'document_newintro',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '文件夹名称',
            value: rcd.get('fs_intro')
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(copydocstructPanel.form.isValid()){
                    copydocstructPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=copydocstruct',
                        method: 'post',
                        timeout: 30,
                        params: copydocstructPanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            //refreshtree(rcd,1);
                            showdocumentgrid(rcd);
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
        closeAction:'hide',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: copydocstructPanel
    });
    copydocstructPanel.form.reset();
    win.setTitle('复制文件夹结构');
    win.show();
}

var itemsPerPage = 50;
var documentgridstore = Ext.create('Ext.data.Store', {
    fields: ['text', 'fs_name', 'fs_isdir', 'fs_intro', 'fs_encrypt', 'fs_haspaper', 'fs_create', 'fs_lastmodify', 'u_name', 'fs_id', 'icon', 'fs_size', 'managerok', 'fs_id_path', 'fs_group', 'fs_user', 'fs_parent', 'fs_is_share', 'fs_type',  'fs_hashname', 'fs_fullpath'],
    pageSize: itemsPerPage,
    autoLoad: false,
    remoteSort: true
});
var workdocgridstore = Ext.create('Ext.data.Store', {
    fields: ['text', 'fs_name', 'fs_isdir', 'fs_intro', 'fs_encrypt', 'fs_haspaper', 'fs_create', 'fs_lastmodify', 'u_name', 'fs_id', 'icon', 'fs_size', 'managerok','fs_type',  'fs_hashname'],
    pageSize: itemsPerPage,
    autoLoad: false,
    remoteSort: true
});

function showdocumentgrid(rcd, contentid){
    var _contentid = Ext.isEmpty(contentid) ? 'displayCenterPanel' : contentid;
    var _store = _contentid=='displayCenterPanel' ? documentgridstore : workdocgridstore;
    switch(_contentid){
        case 'displayCenterPanel': 
            _url = base_path + "index.php?c=document&a=listdocumentgrid&fs_id="+rcd.get('fs_id');break;
        case 'workgroupoparea':
        if(!Ext.isEmpty(rcd.get('fs_id'))){
            _url = base_path + "index.php?c=document&a=listdocumentgrid&fs_id="+rcd.get('fs_id');break;
        }else{
            _url = base_path + "index.php?c=document&a=listdocbyuid&u_id="+rcd.get('u_id');break;
        }
        default:
            _url = base_path + "index.php?c=document&a=listdocumentgrid&fs_id="+rcd.get('fs_id');break;
    }
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
    /*获取导航文件夹 */   
    var scrollMenu = Ext.create('Ext.menu.Menu');
    var treepathcookie = !Ext.isEmpty(eval(Ext.util.Cookies.get('treepath'))) ? eval(Ext.util.Cookies.get('treepath')) : [];
    for (var i = 0; i < treepathcookie.length; ++i){
        scrollMenu.add({
            text: treepathcookie[i].fs_code,
            fs_id: treepathcookie[i].fs_id,
            iconCls: 'icon-doc-open', 
            listeners: {
                click: function(val){
                    opennavdocument(val.fs_id);
                }
            }
        });
    }

    var docgrid = Ext.create('Ext.grid.Panel', {
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
        },{
            xtype: 'toolbar',
            dock: 'top',
            id: 'navtoolbar',
            items: [{
                text: '',
                xtype: 'button',
                iconCls: 'go_history',
                disabled: true,
                id: 'go_history',
                //menu: [{text: 'Menu Button 1'}],
                handler:function(){
                    var selectdocid=Ext.util.Cookies.get("selectdocid"); 
                    /*
                    for(var i=0;i<treepathcookie.length; i++){
                    //边界判断
                    if(treepathcookie[i].fs_id==selectdocid && i!=0){
                    opennavdocument(treepathcookie[i-1].fs_id, 'back');
                    }
                    }
                    */
                    var fs_parent = mystore.getNodeById(selectdocid).parentNode.data.fs_id;
                    opennavdocument(fs_parent, 'history');
                }
            },'-',{
                text: '',
                //xtype: 'splitbutton',
                iconCls: 'go_forward',
                disabled: true,
                id:'go_forward',
                //menu: [{text: 'Menu Button 1'}],
                handler: function(){
                    var selectdocid=Ext.util.Cookies.get("selectdocid");
                    for(var i=0;i<treepathcookie.length; i++){
                        //边界判断
                        if(treepathcookie[i].fs_id==selectdocid && i!=treepathcookie.length-1){
                            opennavdocument(treepathcookie[i+1].fs_id, 'forward');
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
                /*打开文档文件夹*/
                oepndocument(view, rcd, item, index, event, eOpts, _contentid);
            },
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent(); 
                docgrid.getSelectionModel().select(rcd);               
                showmenu(view, rcd, item, index, event, eOpts, parentrcd, _contentid);
            },
            'containercontextmenu':function(view, event, eOpts ){
                event.stopEvent();
                if(_contentid=='displayCenterPanel'){
                    var menu = Ext.create('Ext.menu.Menu', {
                        float: true,
                        items: [{
                            text: '新建文件夹',
                            iconCls:'icon-doc-new',
                            handler: function(){
                                this.up("menu").hide();
                                adddocumentform(rcd);
                            }
                        },{
                            text:'上传文件',
                            iconCls:'icon-doc-upload', 
                            handler: function(){
                                this.up('menu').hide();
                                var uppanel = Ext.create('Org.fileupload.Panel',{
                                    //var uppanel = Ext.create('Org.dragfileupload.Panel',{
                                    width : '100%',
                                    title : '上传文件---文件夹'+rcd.get('text'),
                                    items : [
                                    {
                                        border : false,
                                        fileSize : 1024*1000000,//限制文件大小单位是字节
                                        uploadUrl : base_path+'index.php?c=upload',//提交的action路径
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
function oepndocument(view, rcd, item, index, event, eOpts, contentid){
    if(rcd.get('fs_isdir')=='1'){
        /*cookie 存储文件夹导航*/
        //setcookienav(rcd);
        /*展开文件夹树*/
        if(login_user.u_grade=='3' || login_user.u_grade=='4' || login_user.u_grade=='98' || login_user.u_grade=='99' || login_user.u_grade=='100'){
            var path = '-root-1-' + rcd.get('fs_id_path');
        }else{
            //获取节点的fs_id_path
            var nodeid = getfsidpath(rcd.get('fs_parent'));
            var str='-'+nodeid+'-';
            var path = '-root-' + rcd.get('fs_id_path');
            var offset = path.indexOf(str);
            var stridpath = path.substring(offset+str.length-1, path.length);
            if(offset!=-1){
                if(stridpath.substr(0,1)=='-'){
                    path = '-root' + stridpath;
                }else{
                    path = '-root-' + stridpath;
                }
            }else{
                path = '-'+stridpath;
            }

        }

        if(mystore.getNodeById(rcd.get('fs_id'))){ //判断当前ID节点是否存在（是否已加载到treestore中）
            if(mystore.getNodeById(rcd.get('fs_id')).isExpanded()){  //已加载状态判断是否已展开
                //projectTreePanel.getSelectionModel().select(mystore.getNodeById(rcd.get('fs_id'))); 
                showdocumentgrid(rcd, contentid);
                setcookienav(rcd);
            }else{
                projectTreePanel.expandPath(path, 'id', '-');
            }   
        }else{
            projectTreePanel.expandPath(path, 'id', '-');
        }     
    }else{
        openfile(view, rcd, item, index, event, eOpts);
    }
}
function getfsidpath(fs_id){
    var rcd =mystore.getNodeById(fs_id);
    if(rcd==undefined){
        return fs_id;
    }else{
        //if(rcd.get('fs_parent')!='0'){
        return getfsidpath(rcd.get('fs_parent'));  
        //}else{
        //return fs_id;
        //}
    }
}
function openfile(view, rcd, item, index, event, eOpts){
    window.open(base_path + "index.php?c=document&a=openfile&fs_id="+rcd.get('fs_id')+'&t='+rcd.get('fs_type'));
}
/*查看历史版本*/
function showhistorygrid(rcd){
    var itemsPerPage = 12;
    var filegridstore = Ext.create('Ext.data.Store', {
        autoLoad: { start: 0, limit: itemsPerPage },
        fields: ['fs_id', 'fs_name', 'fs_intro', 'log_type', 'log_user', 'fs_size', 'log_optdate'],
        pageSize: itemsPerPage,
        proxy: {
            type: 'ajax',
            url: base_path + "index.php?c=document&a=showhistory&fs_id="+rcd.get('fs_id'), 
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
//*下载历史版本*/
function downloadhistoryfile(rcd){
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在获取下载资源……'
    });

    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=downloadhistory",
        params : rcd.raw,
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                location.href=base_path + "index.php?c=document&a=downloadhistory&file="+result.msg;
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        }
    });    
}

function showmenu(view, rcd, item, idx, event, eOpts){
    parentrcd = arguments[6];
    var _contentid = arguments[7]==undefined ? 'displayCenterPanel' : arguments[7];
    var editdocument=adddocument=uploadfile=adddocpower=deldocument=downloadfile=powersetting=updatefile=lookuphistory=sharesetting=copydocumentstruct=false;  
    for(var p=0; p<power.length; p++){
        switch(power[p]){
            case 'editdocument' : editdocument=true;break;
            case 'adddocument': adddocument=true;break;
            case 'uploadfile': uploadfile=true;break;
            case 'deldocument': deldocument=true;break;
            case 'powersetting': powersetting=true;break;
            case 'downloadfile': downloadfile=true;break;
            case 'updatefile': updatefile=true;break;
            case 'lookuphistory': lookuphistory=true;break;
            case 'sharesetting': sharesetting=true;break;
            case 'copydocumentstruct': copydocumentstruct=true;break;
        }
    }
    var editdocument_obj=adddocument_obj=uploadfile_obj=uploadfile_obj=deldocument_obj=downloadfile_obj=powersetting_obj=updatefile_obj=addsharemulu_obj=history_obj=movetoshare_obj=sharedoc_setting_obj=sharedoc_delsetting_obj=null;
    var editdocument_obj = {
        text: '修改编号或名称',iconCls:'icon-edit',
        handler: function(){
            this.up("menu").hide();
            if(rcd.get('fs_isdir')==1){
                editdocumentformPanel(rcd);
            }else{
                editfileformPanel(rcd, parentrcd); 
            }
        }
    };
    var adddocument_obj = {
        text: '新建文件夹',
        iconCls:'icon-doc-new',
        handler: function(){
            this.up("menu").hide();
            adddocumentform(rcd);
        }
    };

    var movetoshare_obj = {
        text: '移动至共享',
        iconCls:'icon-share-doc-setting',
        handler: function(){
            this.up("menu").hide();
            movetoshare(rcd);
        }
    };

    var sharedoc_setting_obj = {
        text: '设置共享',
        iconCls:'icon-share-doc-setting',
        handler: function(){
            this.up("menu").hide();
            sharedoc_setting(rcd);
        }
    };   

    var sharedoc_delsetting_obj = {
        text: '取消共享',
        iconCls:'icon-share-doc-setting',
        handler: function(){
            this.up("menu").hide();
            sharedoc_delsetting(rcd);
        }
    };

    var uploadfile_obj = {
        text:'上传文件',
        iconCls:'icon-doc-upload', 
        handler: function(){
            this.up('menu').hide();
            var uppanel = Ext.create('Org.fileupload.Panel',{
                width : '100%',
                title : '上传文件---文件夹'+rcd.get('text'),
                items : [
                {
                    border : false,
                    fileSize : 1024*1000000,//限制文件大小单位是字节
                    uploadUrl : base_path+'index.php?c=upload',//提交的action路径
                    flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                    filePostName : 'uploads', //后台接收参数
                    fileTypes : '*.*',//可上传文件类型
                    parentNode : rcd,
                    postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                }
                ]
            });
            Ext.getCmp(_contentid).remove(Ext.getCmp(_contentid).items.get(0));
            Ext.getCmp(_contentid).add(uppanel).doLayout();
        }
    };
    var draguploadfile_obj={
        text:'上传文件',
        iconCls:'icon-doc-upload', 
        handler: function(){
            this.up('menu').hide();
            var uppanel = Ext.create('Org.dragfileupload.Panel',{
                width : '100%',
                title : '上传文件---文件夹'+rcd.get('text'),
                //draggable:true,
                items : [
                {
                    border : false,
                    fileSize : 1024*1000000,//限制文件大小单位是字节
                    uploadUrl : base_path+'index.php?c=upload',//提交的action路径
                    flashUrl : js_path+'swfupload/swfupload.swf',//swf文件路径
                    filePostName : 'uploads', //后台接收参数
                    fileTypes : '*.*',//可上传文件类型
                    parentNode : rcd,
                    postParams : {savePath:rcd.get('fs_fullpath'), fs_id:rcd.get('fs_id')} //http请求附带的参数
                }
                ]
            });
            Ext.getCmp(_contentid).remove(Ext.getCmp(_contentid).items.get(0));
            Ext.getCmp(_contentid).add(uppanel).doLayout();
        }
    };    
    var deldocument_obj = {
        text:'删除',
        iconCls:'icon-doc-remove', 
        handler: function(){
            this.up('menu').hide();
            var selectlength = projectTreePanel.getSelectionModel().getSelection().length;
            if(selectlength>1){
                var selectrcd = projectTreePanel.getSelectionModel().getSelection();
                var array=[];
                for(var i in selectrcd){
                    if(Ext.isEmpty(array)){
                        array.push(selectrcd[i].parentNode);
                    }else
                        if(!Ext.Array.contains(array, selectrcd[i].parentNode)){
                        Ext.Msg.alert('提示', '请选择同一个文件夹下的文件进行操作！');
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
                            batch_deldocumentfs(selectrcd);
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
                            deldocumentfs(rcd, parentrcd);
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
            showhistorygrid(rcd);
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
                        downloadfilefs(rcd);
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
            powersettingformPanel(rcd);
        }
    };
    var opendocument_obj = {
        text: '打开',
        iconCls: 'icon-doc-open',
        handler: function(){
            this.up("menu").hide();
            oepndocument(view, rcd, item, idx, event, eOpts, _contentid);
        }
    };
    var copydocstruct_obj = {
        text: '复制文件夹结构',
        iconCls: 'icon-doc-open',
        handler: function(){
            this.up("menu").hide();
            copydocstruct(rcd);
        }
    };
    var refresh_obj={
        text: '刷新',
        iconCls: 'refresh',
        handler: function(){
            this.up("menu").hide();
            refreshtree(rcd, 1);
        }
    };
    var menu = Ext.create('Ext.menu.Menu', {
        float: true
    });
    if(rcd.get('fs_parent')=='0'){
        menu.add(refresh_obj);
        !adddocument || !Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(adddocument_obj);
    }else{
        if(rcd.get('fs_is_share')=='1' && rcd.get('fs_user')!=login_user.u_id && !(login_user.u_grade=='1' || login_user.u_grade=='99')){
            adddocument_obj.disabled=true;
            editdocument_obj.disabled=true;
            uploadfile_obj.disabled=true;
            sharedoc_setting_obj.disabled=true;
            sharedoc_delsetting_obj.disabled=true;
        }
        var managerok = rcd.get('managerok');
        var parentNode=!Ext.isEmpty(rcd.parentNode) ? rcd.parentNode : '';
        Ext.isEmpty(rcd.isNode) || menu.add(refresh_obj);
        !editdocument || menu.add(opendocument_obj);
        if(managerok){
            !editdocument || menu.add(editdocument_obj);
        }
        !adddocument || !Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(adddocument_obj);

        !uploadfile || !Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(uploadfile_obj);
        //!uploadfile || !Boolean(parseInt(rcd.raw.fs_isdir)) || menu.add(draguploadfile_obj);
        if(managerok && deldocument){//if(){
            menu.add(deldocument_obj);
        }
        !downloadfile || Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(downloadfile_obj);
        if(login_user.u_grade=='1' || login_user.u_grade=='99'){
            !powersetting || !Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(powersetting_obj);
        }

        //!updatefile || menu.add(updatefile_obj);
        !lookuphistory || Boolean(parseInt(rcd.get('fs_isdir'))) || menu.add(history_obj);
        if(login_user.u_grade=='0' || login_user.u_grade=='1' || login_user.u_grade=='99' || login_user.u_grade=='100'){
            //menu.add(movetoshare_obj); /*移动至共享文件夹*/
        }
        //if(copystruct && Boolean(parseInt(rcd.get('fs_isdir')))){
        /*用户有copydocumentstruct 权限 且文件可控 且 是文件夹 且 是在tree中 显示copt操作*/
        if(copydocumentstruct && managerok && Boolean(parseInt(rcd.get('fs_isdir'))) && rcd.index==undefined){
            menu.add(copydocstruct_obj); 
        }
        if(sharesetting && Boolean(parseInt(rcd.get('fs_isdir')))){
            menu.add(sharedoc_setting_obj);
        }
        if(sharesetting && Boolean(parseInt(rcd.get('fs_isdir'))) &&　rcd.get('fs_is_share')=='1'){
            menu.add(sharedoc_delsetting_obj);
        }

    }
    menu.showAt(event.getXY());  

}


/* obj to json convert */
function Serialize(obj){ 
    switch(obj.constructor){     
        case Object:     
        var str = "{";     
        for(var o in obj){     
            str += o + ":" + Serialize(obj[o]) +",";     
        }     
        if(str.substr(str.length-1) == ",")     
            str = str.substr(0,str.length -1);     
        return str + "}";     
        break;     
        case Array:                 
        var str = "[";     
        for(var o in obj){     
            str += Serialize(obj[o]) +",";     
        }     
        if(str.substr(str.length-1) == ",")     
            str = str.substr(0,str.length -1);     
        return str + "]";     
        break;     
        case Boolean:     
            return "\"" + obj.toString() + "\"";     
            break;     
        case Date:     
            return "\"" + obj.toString() + "\"";     
            break;     
        case Function:     
            break;     
        case Number:     
            return "\"" + obj.toString() + "\"";     
            break;      
        case String:     
            return "\"" + obj.toString() + "\"";     
            break;         
    }   
} 

/*设置导航COOKIE*/
function setcookienav(rcd){
    /**将当前文件夹放入cookie中， 建立导航标签*/
    if(rcd){
        /*调用接口返回面包屑*/
        Ext.Ajax.request({
            url: base_path+'index.php?c=document&a=getnavdata',
            params: {
                fs_id: rcd.get('fs_id')
            },
            success: function(response){
                var text = response.responseText;
                text=eval(text);
                var navtoolbar=Ext.getCmp('navtoolbar');  
                for(var i=text.length-1; i>=0; i--){
                    text[i].text=text[i].fs_code;
                    text[i].listeners={click: function(val){
                            opennavdocument(val.fs_id); /*打开导航文件夹*/
                    }};
                    if(i==0){
                        text[i].text=text[i].fs_code + '('+text[i].fs_intro+')';
                    }
                    navtoolbar.add(text[i]);
                    if(i!=0){ 
                        navtoolbar.add({iconCls: 'x-toolbar-more-icon'}); 
                    }
                }
            }
        });

        if(cookiearr.length>0){
            var ishere = 0;
            if(arguments[1]==undefined){  //判断是否点击的是前进后退按钮 
                for(var i=0; i<cookiearr.length; i++){
                    if(cookiearr[i].fs_id==rcd.get('fs_id')){
                        ishere=1;
                        var tmp=cookiearr[i];
                        cookiearr.splice(i,1);                         
                    }
                }
                if(ishere==0){ 
                    cookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code'), "text":rcd.get('fs_code')});
                }else{
                    cookiearr.push(tmp); 
                }
            }else{
                for(var i=0; i<cookiearr.length; i++){
                    if(cookiearr[i].fs_id==rcd.get('fs_id')){
                        ishere=1;                      
                    }
                }
                if(ishere==0){ 
                    cookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code'), "text":rcd.get('fs_code'), 'fs_parent':rcd.get('fs_parent')});
                }
            }
        }else{
            cookiearr.push({"fs_id":rcd.get('fs_id'), "fs_code":rcd.get('fs_code'), "text":rcd.get('fs_code'), 'fs_parent':rcd.get('fs_parent')});
        }
        var json_rcd = Serialize(cookiearr);  
        Ext.util.Cookies.set("treepath", json_rcd);
        //设置选中文件夹的cookie
        Ext.util.Cookies.set("selectdocid", rcd.get('fs_id'));

        /*对左右箭头进项可用性设置*/
        if(cookiearr.length>1){
            Ext.getCmp('go_history').setDisabled(false);
            if(arguments[1]!=undefined && arguments[1]=='history'){
                Ext.getCmp('go_forward').setDisabled(false);
            }
            else if(cookiearr[cookiearr.length-1].fs_id == rcd.get('fs_id')){
                Ext.getCmp('go_forward').setDisabled(true); 
            }
            else if(arguments[1]!=undefined && arguments[1]=='forward'){
                Ext.getCmp('go_forward').setDisabled(false);
            }
        }else{
            Ext.getCmp('go_history').setDisabled(true);
            Ext.getCmp('go_forward').setDisabled(true);
        }
        //console.log(Ext.getCmp('navtoolbar').initialConfig.items[0]);
    }
}

/*根据fs_id 获取 rcd*/
function opennavdocument(fs_id){
    /*根据fs_id获取grid中的 rcd 和 tree 中的 rcd*/    
    var rcd = mystore.getNodeById(fs_id);
    if(rcd==undefined && arguments[1]!=undefined && arguments[1]=='history'){
        Ext.getCmp('go_history').setDisabled(true);
    }
    if(rcd){
        projectTreePanel.getSelectionModel().select(rcd);

        //收缩文件夹树
        if(rcd.isExpanded()){
            rcd.collapseChildren(true);
        }else{
            /*展开文件夹树*/
            var path = '-root-' + rcd.get('fs_id_path');
            projectTreePanel.expandPath(path, 'fs_id', '-');  
        }

        //显示grid数据
        showdocumentgrid(rcd);
        setcookienav(rcd, arguments[1]);  
    }
}

/*移动文件夹或文件至共享系统*/
function movetoshare(rcd){
    var win = Ext.create('Ext.window.Window', {
        layout:'fit',
        width:600,
        height: 500,
        autoScroll: true,
        closeAction:'destory',
        resizable: true,
        shadow: true,
        modal: true,
        closable: true,
        items: [{
            xtype: 'treepanel',
            rootVisible: false,
            singleExpand: false,
            width:'100%',
            autoScroll:true,
            autoHeight: true,
            border:0,
            store:  new Ext.data.TreeStore({
                proxy:{
                    type: 'ajax',
                    url: base_path + "index.php?c=document&a=listsharedocument",   //用户可以进行上传操作的共享文件夹
                    reader: 'json'
                },
                autoLoad: true, 
                fields:['fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'fs_code']
            }),
            listeners : {
                //文件夹数右键事件
                'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
                    event.preventDefault();
                    event.stopEvent();
                },   
                //文件夹树单击事件
                'itemclick' : function(view, rcd, item, idx, event, eOpts) {
                    event.stopEvent();
                    if(rcd.get('fs_isdir')!=0){
                        Ext.getCmp('treepathvalue').setValue(rcd.get('fs_code'));
                        Ext.getCmp('treepathid').setValue(rcd.get('fs_id'));
                    }
                },
                //文件夹树双击击事件
                'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
                    if(rcd.get('fs_isdir')=='0'){
                        opensharedocument(view, rcd, item, idx, event, eOpts);
                    }
                },
                'beforeitemexpand': function(rcd, eOpts){
                    this.store.setProxy({
                        type:'ajax', 
                        url:base_path + "index.php?c=document&a=listsharedocument&fs_id="+rcd.get('fs_id'),
                        reader:'json'
                    });
                }
            }
        }],
        buttons: [{
            text: '确 定',
            scale   : 'medium',
            handler: function(){
                var treepathvalue = Ext.getCmp('treepathvalue').getValue();
                var treepathid = Ext.getCmp('treepathid').getValue();  
                //var emailmsgid = Ext.getCmp('emailmsgid').getValue();
                //var emailsubject = Ext.getCmp('emailsubject').getValue();
                //var emailuidl = Ext.getCmp('emailuidl').getValue();
                if(!treepathvalue){
                    Ext.Msg.alert('提示', '请选择要存储的文件夹！');
                    return false;
                }else{
                    win.hide();
                    /*开始移动EMAIL文件及附件*/
                    var msgTip = new Ext.LoadMask(Ext.getBody(),{  
                        msg:'正在处理，请稍候...',  
                        removeMask : true                     
                    });  
                    msgTip.show();
                    rcd.raw.targetpathvalue=treepathvalue;
                    rcd.raw.targetpathid=treepathid;  
                    Ext.Ajax.request({
                        url: base_path + "index.php?c=document&a=movetoshare",
                        params : rcd.raw,
                        method : 'POST',
                        timeout: 600000,
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
                        },
                        failure: function(resp,opts) {
                            msgTip.hide();
                            Ext.Msg.alert('提示', '操作失败！  ');   
                        }      
                    });
                }
            }
        },{
            xtype: 'textfield',
            readOnly: true,
            id: 'treepathvalue'
        },{
            xtype: 'hiddenfield',
            id: 'treepathid'
        }]
    });

    win.setTitle('请选择邮件要存储的文件夹！');
    win.show();
}


function sharedoc_setting(rcd){
    var fs_id=rcd.get('fs_id');
    var workgroupTreestore = Ext.create('Ext.data.TreeStore', {
        proxy:{
            type: 'ajax',
            url: base_path + "index.php?c=usergroup&a=listworkgroup&type=checkbox",
            reader: 'json'
        },
        fields:['u_id', 'u_name', 'u_parent', 'u_email', 'u_isgroup','text', 'id'],
        autoLoad: false
    });
    var win = Ext.create('Ext.window.Window', {
        layout:'fit',
        width:400,
        height: 500,
        autoScroll: true,
        closeAction:'hide',
        resizable: true,
        shadow: true,
        modal: true,
        closable: true,
        items: [{
            xtype: 'treepanel',
            rootVisible: false,
            singleExpand: false,
            width:'100%',
            autoScroll:true,
            autoHeight: true,
            border:0,
            store:  workgroupTreestore,
            listeners : {
                //文件夹树双击击事件
                'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
                    event.preventDefault();
                    event.stopEvent();
                },
                'beforeitemexpand': function(rcd, eOpts){
                    this.store.setProxy({
                        type:'ajax', 
                        url: base_path + "index.php?c=usergroup&a=listgroupuser&type=checkbox&fs_id="+fs_id+"&groupid="+rcd.get('u_id'),
                        reader:'json'
                    });
                },
                'checkchange' : function(node ,checked){
                    if(node.raw.u_isgroup=='1'){
                        node.expand();
                    }
                    node.checked = checked;
                    if(node.hasChildNodes()){
                        node.eachChild(function(child) {
                            child.set('checked', checked);
                            child.fireEvent('checkchange', child, checked);
                        });
                    }
                    if(node.parentNode.get('id')!='root'){
                        if(node.parentNode.checked==true && checked==false){
                            node.parentNode.set('checked', false);   
                        }
                    }
                }
            }
        }],
        buttons:[{
            text: '确认',
            handler: function(){
                var treepanel = this.ownerCt.ownerCt.items.items[0];
                var checkedrcd = treepanel.getChecked();
                var user_arr=[];
                var ischeck =false;
                for(var i=0; i<checkedrcd.length; i++){
                    if(checkedrcd[i].get('u_isgroup')=='0'){
                        ischeck = true;
                        if(checkedrcd[i].get('u_id')==login_user.u_id){
                            Ext.Msg.alert('提示', '请选择非当前用户进行共享！');
                            return false;
                        }
                        user_arr.push(checkedrcd[i].get('u_id'));
                    }
                }
                user_str = user_arr.join(',');
                if(ischeck){
                    win.hide();
                    Ext.Ajax.request({
                        url: base_path + "index.php?c=document&a=sharedocument",
                        params : {uids: user_str, fs_id: rcd.get('fs_id'), fs_code: rcd.get('fs_code'), fs_parent:rcd.get('fs_parent')},
                        method : 'POST',
                        timeout: 600000,
                        success: function(response, options){
                            var result = Ext.JSON.decode(response.responseText); 
                            if(result.success){
                                //修改rcd中的内容
                                alterrcdvalue(rcd, 'fs_is_share', '1');
                                Ext.Msg.alert('提示', result.msg);
                                return true;
                            }else{
                                Ext.Msg.alert('提示', result.msg); 
                                return false;
                            }
                        },
                        failure: function(resp,opts) {
                            Ext.Msg.alert('提示', '操作失败！  ');   
                        }      
                    });
                }else{
                    //Ext.Msg.alert('请选择要共享的用户！');
                    sharedoc_delsetting(rcd, win);
                }
            }
        }]
    });

    win.setTitle('请选择共享给的人员');
    win.show();

}

function sharedoc_delsetting(rcd,win){
    var msgTip = new Ext.LoadMask(Ext.getBody(),{  
        msg:'正在处理，请稍候...',  
        removeMask : true                     
    });  
    msgTip.show();
    Ext.Ajax.request({
        url: base_path + "index.php?c=document&a=removesharesetting",
        params : rcd.raw,
        method : 'POST',
        timeout: 600000,
        success: function(response, options){
            msgTip.hide();
            if(win!=undefined){
                win.hide();
            }
            var result = Ext.JSON.decode(response.responseText); 
            if(result.success){
                //修改rcd中的内容
                alterrcdvalue(rcd, 'fs_is_share', '0');
                Ext.Msg.alert('提示', result.msg);
                return true;
            }else{
                Ext.Msg.alert('提示', result.msg); 
                return false;
            }
        },
        failure: function(resp,opts) {
            msgTip.hide();
            Ext.Msg.alert('提示', '操作失败！  ');   
        }      
    });
}

function alterrcdvalue(rcd, field, value){
    var treercd = mystore.getNodeById(rcd.get('fs_id'));
    if(!Ext.isEmpty(treercd)){
        treercd.set(field, value);
    }
    var gridrcd = documentgridstore.findRecord('fs_id', rcd.get('fs_id'));
    if(!Ext.isEmpty(gridrcd)){
        gridrcd.set(field, value);
    }
}