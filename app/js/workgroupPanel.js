var editworkgroup=addworkgroup=addgroupuser=delgroupuser=false;
for(var p=0; p<power.length; p++){
    switch(power[p]){
        case 'editworkgroup' : editworkgroup=true;break;
        case 'addworkgroup': addworkgroup=true;break;
        case 'addgroupuser': addgroupuser=true;break;
        case 'delgroupuser': delgroupuser=true;break;
    }
}
var workgroupTreestore = Ext.create('Ext.data.TreeStore', {
    proxy:{
        type: 'ajax',
        url: base_path + "index.php?c=usergroup&a=listworkgroup",
        reader: 'json'
    },
    fields:['u_id','u_name', 'u_parent', 'text', 'u_email', 'u_isgroup', 'u_grade', 'u_targetgroup'],
    autoLoad: false
}); 
//function getworkgroupTreePanel(){
var workgroupTreePanel = Ext.create('Ext.tree.Panel', {
    rootVisible: false,
    singleExpand: false,
    height : '100%',
    width:'100%',
    //id: 'workgrouptreepanel',
    root:{
        name: '系统管理员',
        id:1,
        expanded: false},
    border:1,
    store: workgroupTreestore
});
workgroupTreePanel.on({
    //目录树单击事件
    'itemclick' : function(view, rcd, item, idx, event, eOpts) {
        event.stopEvent();
        if(rcd.raw.u_isgroup==1){
            showWorkGroupgrid(rcd);
        }else{
            //showdocumentgrid(rcd, 'workgroupoparea');  
            showUserTree(rcd);
        }
    },
    'beforeitemexpand': function(rcd, eOpts){
        if(!Ext.isEmpty(rcd.raw)){
            workgroupTreestore.setProxy({
                type:'ajax', 
                url:base_path + "index.php?c=usergroup&a=listgroupuser&groupid="+rcd.get('u_id'),
                reader:'json'
            });
        }
    },  
    //目录数右键事件
    'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
        event.preventDefault();
        event.stopEvent();
        var editworkgroup_obj=addworkgroup_obj=addgroupuser_obj=delgroupuser_obj=null;
        if(editworkgroup){
            editworkgroup_obj = {
                text: '编辑',
                iconCls: 'icon-edit',
                handler: function(){
                    this.up("menu").hide();
                    if(rcd.raw.u_isgroup==1){
                        editworkgroupform(rcd);
                    }else{
                        editgroupuserform(rcd);
                    }
                }
            };
        }
        if(addworkgroup && (login_user.u_grade=='99' || login_user.u_grade=='100')){
            addworkgroup_obj={
                text: '添加工作组',
                iconCls: 'icon-user-add',
                handler: function(){
                    this.up("menu").hide();
                    addworkgroupform();
                }
            };
        }
        if(addgroupuser && rcd.get('u_isgroup')==1){
            addgroupuser_obj={
                text:'添加组员',
                iconCls: 'icon-user-add',
                handler: function(){
                    this.up('menu').hide();
                    addworkgroupuserform(rcd);
                }
            };
        }
        if(delgroupuser && rcd.get('u_isgroup')==0){
            delgroupuser_obj={
                text: '删除组员',
                iconCls: 'icon-user-del',
                handler: function(){
                    this.up("menu").hide();
                    Ext.Msg.show({  
                        title:'提示',
                        closable: false, 
                        msg:'确定要删除 '+rcd.parentNode.get('text')+' '+rcd.get('u_name')+' 组员信息吗？', 
                        icon:Ext.MessageBox.QUESTION,
                        buttons:Ext.Msg.OKCANCEL,
                        fn: function(btn){
                            if(btn=='ok'){
                                deletegroupuser(rcd.get('u_id'));
                            }
                            return false;
                        } 
                    }); 
                } 
            }
        }
        var refresh = {
            text: '刷新',
            iconCls: 'refresh',
            handler: function(){
                this.up("menu").hide();
                refreshworkgroup();
            } 
        }
        var menu = new Ext.menu.Menu({
            float: true
        });
        rcd.raw.u_isgroup=='1' && menu.add(refresh);
        Ext.isEmpty(editworkgroup_obj) || menu.add(editworkgroup_obj);
        Ext.isEmpty(addworkgroup_obj) || menu.add(addworkgroup_obj);
        Ext.isEmpty(addgroupuser_obj) || menu.add(addgroupuser_obj);
        Ext.isEmpty(delgroupuser_obj) || menu.add(delgroupuser_obj);
        menu.showAt(event.getXY());
    },
    scope : this
});
//return workgroupTreePanel;
//} 

var workgroupPanel = Ext.create('Ext.panel.Panel', {
    layout: 'border',
    width : '100%',
    height: '100%',
    //id: 'workgroupPanel',
    items: [{ 
        region: 'west',
        title: '工作组列表',
        collapsible: true,
        //id:'workgrouplist',
        width:200,
        split: true,
        layout: 'fit',
        items:workgroupTreePanel,
        autoScroll : false
    }, {
        region: 'center',
        id: 'workgroupoparea',
        autoScroll: true
    }]
});



function refreshworkgroup(){
    while (delNode = workgroupTreePanel.getRootNode().childNodes[0]) {
        workgroupTreePanel.getRootNode().removeChild(delNode);
    }
    workgroupTreestore.setProxy({
        type:'ajax', 
        url:base_path + "index.php?c=usergroup&a=listworkgroup",
        reader:'json'
    });
    workgroupTreestore.load();
    workgroupTreePanel.doLayout();
}

function deletegroupuser(id){
    var msgTip = Ext.MessageBox.show({
        title:'提示',
        width: 250,
        msg: '正在删除员工信息……'
    });
    Ext.Ajax.request({
        url: base_path + "index.php?c=usergroup&a=delgroupuser",
        params : {uid: id},
        method : 'POST',
        success: function(response, options){
            msgTip.hide();
            var result = Ext.JSON.decode(response.responseText);
            if(result.success){
                Ext.Msg.alert('提示', result.msg);
                refreshworkgroup();
            }else{
                Ext.Msg.alert('提示', result.msg); 
            }
        }
    });
}

function addworkgroupform(){
    var addworkgroupPanel = Ext.create('Ext.form.Panel', {
        frame: true,
        bodyStyle: 'padding: 5 5 5 5',
        defaultType: 'textfield',
        buttonAlign: 'center',
        defaults: {
            labelSeparator : '：',
            labelWidth: 80,
            width: 220,
            allowBlank: false,
            blankText: '不允许为空',
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
            xtype:'textfield',
            name: 'workgroupname',
            fieldLabel: '工作组名称'
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(addworkgroupPanel.form.isValid()){
                    addworkgroupPanel.getForm().submit({
                        url: base_path+'index.php?c=usergroup&a=addworkgroup',
                        method: 'post',
                        timeout: 30,
                        params: addworkgroupPanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshworkgroup();
                            win.close();
                        },
                        failure: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg); 
                        }
                    });
                }
            }
        },{
            text: '重置',
            handler: function(){
                addworkgroupPanel.form.reset();
            }
        }]
    });
    var win = Ext.create('Ext.window.Window',{
        layout:'fit',
        width:300,
        closeAction:'destory',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items: addworkgroupPanel
    });
    addworkgroupPanel.form.reset();
    addworkgroupPanel.isAdd = true;
    win.setTitle('添加工作组');
    win.show();
}

function addworkgroupuserform(rcd){
    var addgroupuserPanel = Ext.create('Ext.form.Panel', {
        /*height: 150,*/
        //autoHeight : true,
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
            name: 'workgroup_id',
            allowBlank: false,
            blankText: '不允许为空',
            value: rcd.raw.u_id
        },{
            xtype:'textfield',
            name: 'workgroup_name',
            fieldLabel: '工作组',
            allowBlank: false,
            blankText: '不允许为空',
            readOnly: true,
            value: rcd.raw.u_name
        },{
            xtype:'textfield',
            name: 'username',
            allowBlank: false,
            blankText: '不允许为空',
            id: 'username',
            fieldLabel: '姓 名'
        }, {
            xtype:'textfield',
            vtype: 'email',
            id: 'email',
            allowBlank: false,
            blankText: '不允许为空',
            name: 'email',
            fieldLabel: '邮 箱'
        }, {
            xtype:'checkboxgroup',
            id: 'grade',
            fieldLabel: '权 限',
            columns: 2,
            vertical: true,
            items: addpowersettingshow()
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(addgroupuserPanel.form.isValid()){
                    addgroupuserPanel.getForm().submit({
                        url: base_path+'index.php?c=usergroup&a=addgroupuser',
                        method: 'post',
                        timeout: 30,
                        params: addgroupuserPanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshworkgroup();
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
        width:380,
        closeAction:'destroy',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items:addgroupuserPanel
    });
    addgroupuserPanel.form.reset();
    win.setTitle('添加组员');
    win.show();

    function addpowersettingshow(){
        var ret=[];
        if(login_user.u_grade>90){
            ret = [{ boxLabel: '普通组员', name: 'grade[]', inputValue: '0'},{ boxLabel: '组文件管理员', name: 'grade[]', inputValue: '1'},{ boxLabel: '工作组领导', name: 'grade[]', inputValue: '2'},{ boxLabel: '部门负责人', name: 'grade[]', inputValue: '3'},{ boxLabel: '项目部负责人', name: 'grade[]', inputValue: '4' },{ boxLabel: '系统管理员', name: 'grade[]', inputValue: '99' },{ boxLabel: '系统监察员', name: 'grade[]', inputValue: '98' }];
        } else {
            ret=[{ boxLabel: '普通组员', name: 'grade[]', inputValue: '0'},{ boxLabel: '组文件管理员', name: 'grade[]', inputValue: '1'},{ boxLabel: '工作组领导', name: 'grade[]', inputValue: '2' }];
        }
        return ret;
    }
}


function editworkgroupform(rcd){
    editworkgrouppanel = Ext.create('Ext.form.Panel', {
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
            allowBlank: false,
            blankText: '不允许为空',
            labelAlign: 'left',
            msgTarget: 'under'  
        },
        items: [{
            xtype:'hiddenfield',
            name: 'workgroup_id',
            value: rcd.get('u_id')
        },{
            xtype:'hiddenfield',
            name: 'u_oldname',
            id: 'u_oldname',
            value: rcd.get('u_name')
        },{
            xtype:'textfield',
            name: 'workgroup_name',
            fieldLabel: '工作组名称',
            value:rcd.get('u_name')
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(editworkgrouppanel.form.isValid()){
                    var formparams = editworkgrouppanel.getForm().getValues();
                    editworkgrouppanel.getForm().submit({
                        url: base_path + 'index.php?c=usergroup&a=editgroup',
                        method: 'post',
                        timeout: 30,
                        params: formparams,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshworkgroup();
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
        width:380,
        closeAction:'destory',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items:editworkgrouppanel
    });
    editworkgrouppanel.form.reset();
    editworkgrouppanel.isAdd = true;
    win.setTitle('编辑【'+rcd.get('u_name')+'】');
    win.show();
}


function editgroupuserform(rcd){
    editworkgrouppanel = Ext.create('Ext.form.Panel', {
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
            xtype:'combo',
            name: 'workgroup_id',
            id:'combo-workgroup-id',
            emptyText : '请选择工作组',
            listConfig:{
                emptyText: '请选择工作组',
                loadingText : '正在加载工作组信息',
                maxHeight: 100
            },
            triggerAction: 'all',
            queryMode: 'remote',
            editable: false,
            store: new Ext.data.Store({
                stortId: 'workgroupstore1',
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
            fieldLabel: '所属工作组',
            listeners:{
                'afterRender' : function(combo) {
                    Ext.getCmp('combo-workgroup-id').setValue(rcd.get('u_parent'));
                }
            }
        },{
            xtype:'hiddenfield',
            name: 'user_id',
            value: rcd.get('u_id')
        },{
            xtype:'textfield',
            name: 'username',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '姓 名',
            value: rcd.get('u_name')
        }, {
            xtype:'textfield',
            vtype: 'email',
            name: 'email',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '邮 箱',
            value: rcd.get('u_email')
        },{
            xtype:'combo',
            name: 'targetgroup_id',
            id:'combo-workgroup-manage-id',
            emptyText : '请选择工作组',
            disabled: true,
            listConfig:{
                emptyText: '请选择工作组',
                loadingText : '正在加载工作组信息',
                maxHeight: 100
            },
            triggerAction: 'all',
            queryMode: 'remote',
            editable: false,
            store: new Ext.data.Store({
                proxy : {
                    type : 'ajax',
                    url : base_path+'index.php?c=usergroup&a=listworkgroup',
                    actionMethods : 'post',
                    reader : 'json'
                },
                fields : ['u_id', 'u_name', 'u_targetgroup', 'u_grade'],
                autoLoad:true
            }),

            valueField: 'u_id',
            displayField: 'u_name',
            fieldLabel: '管理工作组',
            listeners:{
                'afterRender' : function(combo) {
                    if(!Ext.isEmpty(rcd.get('u_targetgroup'))){
                        Ext.getCmp('combo-workgroup-manage-id').setValue(rcd.get('u_targetgroup'));
                    }else{
                        Ext.getCmp('combo-workgroup-manage-id').setValue(rcd.get('u_parent'));
                    }
                    var ugrade = rcd.get('u_grade');
                    var ugradearr = ugrade.split(',');
                    if(Ext.Array.contains(ugradearr, '1') || Ext.Array.contains(ugradearr, '2')){
                        Ext.getCmp('combo-workgroup-manage-id').setDisabled(false);
                    }
                }
            }
        }, {
            xtype:'checkboxgroup',
            fieldLabel: '权 限',
            columns: 2,
            vertical: true,
            items: powersettingshow(),
            listeners:{
                'change' : function(field, newvalue, oldvalue, eOpts){console.log();
                    var arr = newvalue['grade[]'];
                    flag = 0;
                    for(i in arr){
                        if(arr[i]=='1' ||arr[i]=='2'){
                            flag = 1;
                            Ext.getCmp('combo-workgroup-manage-id').setDisabled(false);
                        }
                    }
                    if(flag==0){
                        Ext.getCmp('combo-workgroup-manage-id').setDisabled(true);
                    }
                }
            }
        }],
        buttons:[{
            text: '确定',
            handler: function(){
                if(editworkgrouppanel.form.isValid()){
                    var formparams = editworkgrouppanel.getForm().getValues();
                    editworkgrouppanel.getForm().submit({
                        url: base_path + 'index.php?c=usergroup&a=editgroupuser',
                        method: 'post',
                        timeout: 30,
                        params: formparams,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshworkgroup(rcd.parentNode);
                            showWorkGroupgrid(rcd.parentNode);
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
        width:380,
        closeAction:'destory',
        resizable: false,
        shadow: true,
        modal: true,
        closable : true,
        items:editworkgrouppanel
    });
    //editworkgrouppanel.form.reset();
    //editworkgrouppanel.isAdd = true;
    win.setTitle('编辑【'+rcd.raw.u_name+'】');
    win.show();

    function powerfun(val){
        u_grade_arr=rcd.get('u_grade').split(',');
        for(var i=0;i<u_grade_arr.length;i++){
            if(u_grade_arr[i]==val){
                return true;
            }
        } 
        return false;
    }

    function powersettingshow(){
        var ret=[];
        if(login_user.u_grade>90){
            ret = [{ boxLabel: '普通组员', name: 'grade[]', inputValue: '0', checked:powerfun('0')},{ boxLabel: '组文件管理员', name: 'grade[]', inputValue: '1', checked:powerfun('1')},{ boxLabel: '工作组领导', name: 'grade[]', inputValue: '2',checked:powerfun('2') },{ boxLabel: '部门负责人', name: 'grade[]', inputValue: '3',checked:powerfun('3') },{ boxLabel: '项目部负责人', name: 'grade[]', inputValue: '4',checked:powerfun('4') },{ boxLabel: '系统管理员', name: 'grade[]', inputValue: '99',checked:powerfun('99') },{ boxLabel: '系统监察员', name: 'grade[]', inputValue: '98',checked:powerfun('98') }];
        } else {
            ret=[{ boxLabel: '普通组员', name: 'grade[]', inputValue: '0', checked:powerfun('0')},{ boxLabel: '工作组领导', name: 'grade[]', inputValue: '2',checked:powerfun('2') }];
        }
        return ret;
    }
}



function showWorkGroupgrid(rcd){
    var workgroup_id='';
    if(typeof rcd=='object'){
        workgroup_id= "&workgroup_id="+rcd.get('u_id');
    }else{
        console.log(rcd);
        if(!Ext.isEmpty(rcd)){
            workgroup_id= "&workgroup_id="+rcd; 
        }  
    }
    var itemsPerPage = 17;
    var usergridstore = Ext.create('Ext.data.Store', {
        autoLoad: { start: 0, limit: itemsPerPage },
        fields: ['u_id', 'u_name', 'u_email', 'u_isgroup', 'u_grade'],
        pageSize: itemsPerPage,
        proxy: {
            type: 'ajax',
            url: base_path + "index.php?c=usergroup&a=listgroupusergrid"+workgroup_id, 
            reader: {
                type: 'json',
                root: 'rows',
                totalProperty: 'total'
            }
        }
    });
    var gridHeight = $("#workgroupoparea").innerHeight();
    var usergrid = Ext.create('Ext.grid.Panel', {
        //autoWidth: true,
        height: gridHeight,
        frame: true,
        store: usergridstore,
        multiSelect: false,
        columns: [
        /*{ header: 'ID', width: 80, dataIndex: 'u_id', sortable: true, menuDisabled : true},*/
        { header: '用户', width: 150, dataIndex: 'u_name', sortable: false, menuDisabled : true},
        { header: '邮箱', width: 200, dataIndex: 'u_email', sortable: true,menuDisabled : true },
        //{ header: '是否为组', width: 100, dataIndex: 'u_isgroup',sortable: false, menuDisabled : true},
        { header: '权限', width: 300, dataIndex: 'u_grade', renderer : getpowerfun, sortable: false, menuDisabled : true }
        ],
        dockedItems: [{
            xtype: 'pagingtoolbar',
            store: usergridstore,   // same store GridPanel is using
            dock: 'bottom',
            displayInfo: true
        }],
        listeners:{
            'itemdblclick': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();
                showUserTree(rcd); 
                /*showgridmenu(view, rcd, item, index, event, eOpts);*/
            }
        }
    });
    Ext.getCmp('workgroupoparea').remove(Ext.getCmp('workgroupoparea').items.get(0)); 
    Ext.getCmp('workgroupoparea').add(usergrid).doLayout();
    function getpowerfun(val){
        pstr='';
        if(!Ext.isEmpty(val)){
            var p=val.split(',');
            for(var j=0;j<p.length;j++){
                if(p[j]==0){
                    pstr+='普通组员,' 
                }else if(p[j]==1){
                    pstr+='组文件管理员,' 
                }else if(p[j]==2){
                    pstr+='工作组领导,'
                }else if(p[j]==3){
                    pstr+='部门负责人,'
                }else if(p[j]==4){
                    pstr+='项目部负责人,'
                }else if(p[j]==99){
                    pstr+='系统管理员,'
                } else if(p[j]==98){
                    pstr+='系统监察员,'
                } 
            }
        }
        return pstr;
    }
}


function showUserTree(userrcd){
    var win = Ext.create('Ext.window.Window', {
        layout:'fit',
        width:600,
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
            store:  new Ext.data.TreeStore({
                proxy:{
                    type: 'ajax',
                    url: base_path + "index.php?c=usergroup&a=listusertree&u_id="+userrcd.raw.u_id,
                    reader: 'json'
                },
                autoLoad: true, 
                fields:['fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'fs_type']
            }),
            listeners : {   
                //目录树双击击事件
                'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
                    if(rcd.raw.fs_isdir=='0'){
                        openfile(view, rcd, item, idx, event, eOpts);
                    }
                },
                'beforeitemexpand': function(rcd, eOpts){
                    this.store.setProxy({
                        type:'ajax', 
                        url: base_path + "index.php?c=usergroup&a=listusertree&u_id="+userrcd.get('u_id')+"&fs_id="+rcd.get('fs_id'),
                        reader:'json'
                    });
                }
            }
        }]
    });

    win.setTitle(userrcd.get('u_name') + ' 目录结构');
    win.show();

}