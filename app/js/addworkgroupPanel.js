var addworkgroupPanel = Ext.create('Ext.form.Panel', {
    title: '添加工作组',
    width: 300,
    height: 120,
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
                    },
                    failure: function(form, action){
                        Ext.Msg.alert('温馨提示', action.result.msg); 
                    }
                });
            }
        }
    },{
        text: '取消',
        handler: function(){
            addworkgroupPanel.form.reset();
        }
    }]
});