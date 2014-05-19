function showAddprojectPanel(){
    var addprojectPanel = Ext.create('Ext.form.Panel', {
        width: 380,
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
            xtype:'textfield',
            name: 'projectname',
            id: 'projectname',
            fieldLabel: '项目编号'
        }, {
            xtype:'textfield',
            width: 300,
            name: 'projectintro',
            id: 'projectintro',
            fieldLabel: '项目名称'
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(addprojectPanel.form.isValid()){
                    addprojectPanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=addproject',
                        method: 'post',
                        timeout: 30,
                        params: addprojectPanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshtree();
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
        items: addprojectPanel
    });
    addprojectPanel.form.reset();
    win.setTitle('添加项目');
    win.show();
    return  addprojectPanel;
}


function showaddsharedocPanel(){
    var addsharePanel = Ext.create('Ext.form.Panel', {
        width: 380,
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
        items: [
        /*{
            xtype:'textfield',
            name: 'projectname',
            id: 'projectname',
            allowBlank: false,
            blankText: '不允许为空',
            fieldLabel: '目录编号'
        }, */{
            xtype:'textfield',
            width: 300,
            name: 'projectintro',
            id: 'projectintro',
            fieldLabel: '目录名称',
            allowBlank: false,
            blankText: '不允许为空'
        }, {
            xtype:'radiogroup',
            width: 300,
            columns: 2,
            vertical: true,
            items:[
            { 
                boxLabel: '系统公共信息栏', 
                name: 'rb', 
                inputValue: '1', 
                listeners:{
                    change : function(field, newValue, oldValue, eOpts){
                        if(newValue){
                            Ext.getCmp('share_doc_workgroup_list').disable();
                        }else{
                            Ext.getCmp('share_doc_workgroup_list').enable();
                        }
                    }
                } 
            },{ 
                boxLabel: '分组公共信息栏', 
                name: 'rb', 
                inputValue: '2',
                checked: true,
                listeners:{
                    change : function(field, newValue, oldValue, eOpts){
                        if(newValue){
                            Ext.getCmp('share_doc_workgroup_list').enable();
                        }else{
                            Ext.getCmp('share_doc_workgroup_list').disable();
                        }
                    }
                }  
            }]
        }, {
            xtype:'combo',
            width: 300,
            id: 'share_doc_workgroup_list',
            name: 'workgroupid',
            fieldLabel : '组',
            emptyText : '请选择工作组',
            triggerAction: 'all', 
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
        }],
        buttons:[{
            text: '添加',
            handler: function(){
                if(addsharePanel.form.isValid()){
                    addsharePanel.getForm().submit({
                        url: base_path+'index.php?c=document&a=addsharedocroot',
                        method: 'post',
                        timeout: 30,
                        params: addsharePanel.getForm().getValues,
                        success: function(form, action){
                            Ext.Msg.alert('温馨提示', action.result.msg);
                            refreshtree();
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
        items: addsharePanel
    });
    addsharePanel.form.reset();
    win.setTitle('添加公共信息栏目录');
    win.show();
    return  addsharePanel;
}

