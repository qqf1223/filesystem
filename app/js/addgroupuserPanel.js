/*自定义分组前端验证*/
Ext.apply(Ext.form.field.VTypes, {
    groupregex : function(v){
        return  /^\+?[1-9][0-9]*$/.test(v);
    },
    groupregexText: '请选择工作组'
});

var workgroupStore = Ext.create('Ext.data.Store', {
    //data: <?=$group_rs?>,
    proxy:{
        type: 'ajax',
        url: base_path + "index.php?c=usergroup&a=listworkgroup",
        reader: 'json'
    },
    fields:[{name: 'u_name', type: 'string'},{name: 'u_id', type: 'string'}],
    autoLoad: false
});
var addgroupuserPanel = Ext.create('Ext.form.Panel',{
    title: '添加工作组',
    //id:'addgroupuserPanel',
    width: 400,
    //height: 150,
    autoHeight : true,
    frame: true,
    bodyStyle: 'padding: 5 5 5 5',
    defaultType: 'textfield',
    buttonAlign: 'center',
    defaults: {
        autoFitErrors: false,
        labelSeparator : '：',
        labelWidth: 100,
        width: 300,
        allowBlank: false,
        blankText: '不允许为空',
        labelAlign: 'left',
        msgTarget: 'under'  
    },
    items: [{
        xtype: 'combo',
        //vtype: 'groupregex',
        fieldLabel: '工作组',
        name: 'workgroup_id',
        emptyText: '请选择工作组',
        listConfig:{
            emptyText: '请选择工作组',
            maxHeight: 100
        },
        triggerAction: 'all',
        store: workgroupStore,
        displayField: 'u_name',
        valueField: 'u_id',
        queryMode: 'remote',
        forceSelection: true,
        editable: false,
    },{
        xtype:'hiddenfield',
        name: 'login_user_id',
        value: login_user.u_id
    },{
        xtype:'hiddenfield',
        name: 'login_user_name',
        value: login_user.u_name
    },{
        xtype:'hiddenfield',
        name: 'login_user_group',
        value: login_user.u_parent
    }, {
        xtype:'textfield',
        name: 'username',
        id: 'username',
        fieldLabel: '姓 名'
    }, {
        xtype:'textfield',
        vtype: 'email',
        name: 'email',
        id: 'email',
        fieldLabel: '邮 箱'
    }, {
        xtype:'radiogroup',
        id: 'grade',
        fieldLabel: '权限',
        columns: 2,
        vertical: true,
        items: [
        { boxLabel: '普通组员', name: 'grade', inputValue: '0', checked: true},
        { boxLabel: '组文件管理员', name: 'grade', inputValue: '1'},
        { boxLabel: '工作组领导', name: 'grade', inputValue: '2' },
        { boxLabel: '部门负责人', name: 'grade', inputValue: '3' },
        { boxLabel: '项目部负责人', name: 'grade', inputValue: '4' },
        { boxLabel: '系统管理员', name: 'grade', inputValue: '99' },
        { boxLabel: '系统监察员', name: 'grade', inputValue: '98' },
        ]
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
                    },
                    failure: function(form, action){
                        Ext.Msg.alert('温馨提示', action.result.msg);
                    }
                });
            }
        }
    }]
});
        