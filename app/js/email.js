var emailstore = Ext.create('Ext.data.Store', {
    autoLoad: false,
    pageSize: 100,
    fields: ['msg_id', 'size', 'uidl', 'Subject', 'From', 'Date'],
    /*
    sorters: [{
        property: 'uidl',
        direction: 'DESC'
    }],
    */
    listeners:{  
        beforeload:function(){
            //mailmsgTip.show();  
        }
    }
});
function showemailPanel(password, listnum,tab){
    var listnum = Ext.isEmpty(listnum) ? 10 : (listnum>100? 100 : listnum);
    /** 加载邮件列表 **/
    emailstore.setProxy({
        type: 'ajax',
        timeout: 100000000,
        url: base_path+'POP3/test.php?op=list&top='+listnum+'&user='+login_user.u_email+'&pass='+password,
        reader: 'json'
    });
    emailstore.load({  
        callback:function(records,options,success){
            //mailmsgTip.hide();   //加载完成，关闭提示框
            if(success==false){
                Ext.Msg.alert('提示', '密码输入错误！', function(btn, text){
                    if(btn=='ok'){
                        //var tab = Ext.getCmp("MainTabPanel").getComponent('tab-030');
                        Ext.getCmp("MainTabPanel").remove(tab, true);
                        //Ext.getCmp("tab-030").hide(); 
                        //Ext.getCmp("MainTabPanel").getComponent('tab-030').hide();
                    }
                }); 
            }else{
                //tab.setClosable(true);
            } 
        }  
    });

    //var gridHeight = document.body.clientHeight * 19;
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
    var emailgrid = Ext.create('Ext.grid.Panel', {
        autoWidth: true,
        height: gridHeight,
        frame: true,
        store: emailstore,
        multiSelect: false,
        columns: [
        {xtype: 'rownumberer'}, 
        /*{ header: 'ID', width: 80, dataIndex: 'msg_id', sortable: true, menuDisabled : true  },*/
        { header: '发件人', width: 200, dataIndex: 'From', sortable: false,menuDisabled : true },
        { header: '主题', width: 550, dataIndex: 'Subject', sortable: false,menuDisabled : true },
        { header: '大小', width: 100, dataIndex: 'size',sortable: false, renderer:formatFilesize, menuDisabled : true, align: 'right' },
        { header: '日期', width: 150, dataIndex: 'Date',sortable: false, menuDisabled : true }
        /*{ header: 'uidl', width: 300, dataIndex: 'uidl', sortable: false, menuDisabled : true }*/
        ],
        /*
        dockedItems: [{
        xtype: 'pagingtoolbar',
        store: emailstore,
        dock: 'bottom',
        displayInfo: true
        }],
        */
        listeners:{
            'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                event.stopEvent();
                view.select(rcd);
                var menu = Ext.create('Ext.menu.Menu', {
                    float: true,
                    items:[{
                        text: '收邮件',
                        iconCls: 'icon_email',
                        handler: function(){
                            showprojectdoc(rcd, password, listnum);
                        }
                    }]
                });
                menu.showAt(event.getXY());
            }
        }
    });
    Ext.getCmp('tab-030').add(emailgrid);
}

/**共享目录
function showsharedoc(rcd, password, listnum){
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
url: base_path + "index.php?c=document&a=listsharedocument&type=email",   //用户可以进行上传操作的共享目录
reader: 'json'
},
autoLoad: true, 
fields:['fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'fs_code']
}),
listeners : {
//目录数右键事件
'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
event.preventDefault();
event.stopEvent();
},   
//目录树单击事件
'itemclick' : function(view, rcd, item, idx, event, eOpts) {
event.stopEvent();
if(rcd.raw.fs_isdir!=0){
Ext.getCmp('emailtreepathvalue').setValue(rcd.raw.fs_code);
Ext.getCmp('emailtreepathid').setValue(rcd.raw.fs_id);
}
},
//目录树双击击事件
'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
if(rcd.raw.fs_isdir=='0'){
opensharedocument(view, rcd, item, idx, event, eOpts);
}
},
'beforeitemexpand': function(rcd, eOpts){
this.store.setProxy({
type:'ajax', 
url:base_path + "index.php?c=document&a=listsharedocument&type=email&fs_id="+rcd.raw.fs_id,
reader:'json'
});
}
}
}],
buttons: [{
text: '确 定',
scale   : 'medium',
handler: function(){
var emailtreepathvalue = Ext.getCmp('emailtreepathvalue').getValue();
var emailtreepathid = Ext.getCmp('emailtreepathid').getValue();  
var emailmsgid = Ext.getCmp('emailmsgid').getValue();
var emailsubject = Ext.getCmp('emailsubject').getValue();
var emailuidl = Ext.getCmp('emailuidl').getValue();
if(!emailtreepathvalue){
Ext.Msg.alert('提示', '请选择邮件要存储的目录！');
return false;
}else{
win.hide();
//开始移动EMAIL文件及附件
var msgTip = new Ext.LoadMask(Ext.getCmp('tab-030'),{  
msg:'正在处理，请稍候...',  
removeMask : true                     
});  
msgTip.show();  
Ext.Ajax.request({
url: base_path + "index.php?c=email&a=tosharedocument",
params : {emailtreepathvalue:emailtreepathvalue, emailtreepathid:emailtreepathid, emailmsgid:emailmsgid, emailsubject:emailsubject, emailuidl:emailuidl, password:password},
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
id: 'emailtreepathvalue'
},{
xtype: 'hiddenfield',
id: 'emailtreepathid'
},{
xtype: 'hiddenfield',
id: 'emailmsgid',
value: rcd.data.msg_id
},{
xtype: 'hiddenfield',
id: 'emailsubject',
value: rcd.data.Subject
},{
xtype: 'hiddenfield',
id: 'emailuidl',
value: rcd.data.uidl
}]
});

win.setTitle('请选择邮件要存储的目录！');
win.show();
} 
*/
/**项目目录**/
function showprojectdoc(rcd, password, listnum){
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
                    url: base_path + "index.php?c=document&a=listdocument&showshare=1",
                    reader: 'json'
                },
                autoLoad: true, 
                fields:['fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'fs_code', 'fs_is_share']
            }),
            listeners : {
                //目录数右键事件
                'itemcontextmenu' : function(view, rcd, item, idx, event, eOpts) {
                    event.preventDefault();
                    event.stopEvent();
                },   
                //目录树单击事件
                'itemclick' : function(view, rcd, item, idx, event, eOpts) {
                    event.stopEvent();
                    if(rcd.raw.fs_isdir!=0){
                        Ext.getCmp('emailtreepathvalue').setValue(rcd.raw.fs_code);
                        Ext.getCmp('emailtreepathid').setValue(rcd.raw.fs_id);
                        Ext.getCmp('fs_is_share').setValue(rcd.raw.fs_is_share);
                    }
                },
                //目录树双击击事件
                'itemdblclick' : function(view, rcd, item, idx, event, eOpts) {
                    if(rcd.raw.fs_isdir=='0'){
                        openfile(view, rcd, item, idx, event, eOpts);
                    }
                },
                'beforeitemexpand': function(rcd, eOpts){
                    this.store.setProxy({
                        type:'ajax', 
                        url:base_path + "index.php?c=document&a=listdocument&showshare=1&fs_id="+rcd.raw.fs_id,
                        reader:'json'
                    });
                }
            }
        }],
        buttons: [{
            text: '确 定',
            scale   : 'medium',
            handler: function(){
                var emailtreepathvalue = Ext.getCmp('emailtreepathvalue').getValue();
                var emailtreepathid = Ext.getCmp('emailtreepathid').getValue();  
                var emailmsgid = Ext.getCmp('emailmsgid').getValue();
                var emailsubject = Ext.getCmp('emailsubject').getValue();
                var emailuidl = Ext.getCmp('emailuidl').getValue();
                var fs_is_share=Ext.getCmp('fs_is_share').getValue();
                if(!emailtreepathvalue){
                    Ext.Msg.alert('提示', '请选择邮件要存储的目录！');
                    return false;
                }else{
                    win.hide();
                    var msgTip = new Ext.LoadMask(Ext.getCmp('tab-030'),{  
                        msg:'正在处理，请稍候...',  
                        removeMask : true                     
                    });  
                    msgTip.show();  
                    Ext.Ajax.request({
                        url: base_path + "index.php?c=email",
                        params : {emailtreepathvalue:emailtreepathvalue, emailtreepathid:emailtreepathid, emailmsgid:emailmsgid, emailsubject:emailsubject, emailuidl:emailuidl, password:password, fs_is_share:fs_is_share},
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
            id: 'emailtreepathvalue'
        },{
            xtype: 'hiddenfield',
            id: 'emailtreepathid'
        },{
            xtype: 'hiddenfield',
            id: 'emailmsgid',
            value: rcd.data.msg_id
        },{
            xtype: 'hiddenfield',
            id: 'emailsubject',
            value: rcd.data.Subject
        },{
            xtype: 'hiddenfield',
            id: 'emailuidl',
            value: rcd.data.uidl
        },{
            xtype: 'hiddenfield',
            id: 'fs_is_share',
        }]
    });

    win.setTitle('请选择邮件要存储的目录！');
    win.show();
}

function showpasswordpanel(){
    var tab = Ext.getCmp("MainTabPanel").getComponent('tab-030');
    if(!tab){
        var pwdformPanel = Ext.create('Ext.form.Panel', {
            frame: true,
            bodyStyle: 'padding: 5 5 5 5',
            buttonAlign: 'center',
            items: [{
                xtype:'textfield',
                width:200,
                id: 'userpasswordvalue',
                inputType: 'password',
            },{
                xtype:'combo',
                width:200,
                id: 'maillistnum',
                emptyText : '10',
                triggerAction: 'all', 
                editable: false,
                valueField: 'num',
                displayField: 'numvalue',
                fieldLabel: '邮件TOP',
                labelWidth: 60,
                store: new Ext.data.Store({
                    data:[{"num":10, "numvalue":10},{"num":20, "numvalue":20},{"num":30, "numvalue":30},{"num":40, "numvalue":40},{"num":50, "numvalue":50},{"num":100, "numvalue":100}],
                    fields : ['num', 'numvalue'],
                    renderer:'json'
                })
            }],
            buttons:[{
                text: '确 定',
                style: 'padding: 0 20px',
                scale   : 'medium',
                id: 'userpasswordvaluebtn',
                handler: function(){
                    var value=Ext.getCmp('userpasswordvalue').getValue();
                    var listnum = Ext.getCmp('maillistnum').getValue();
                    if(!Ext.isEmpty(value)){
                        win.hide();
                        if(tab){
                            Ext.getCmp("MainTabPanel").setActiveTab(tab); 
                        }else{
                            tab = Ext.getCmp("MainTabPanel").add({
                                title: '查看邮件列表',
                                id: 'tab-030',
                                closable: true,
                                closeAction: 'hide'
                            }).doLayout();
                            Ext.getCmp("MainTabPanel").setActiveTab(tab);
                            //if(typeof mailmsgTip=='undefined'){
                            mailmsgTip = new Ext.LoadMask(Ext.get('tab-030-body'),{  
                                msg:'邮件列表加载中，请稍候...',
                                removeMask : true                     
                            }).bindStore(emailstore);
                            //}
                        }
                        showemailPanel(value, listnum, tab);    
                    }
                }
            }],
            // 监听回车
            listeners: {
                afterRender: function(thisForm, options){
                    this.keyNav = Ext.create('Ext.util.KeyNav', this.el, {
                        enter: function(){
                            var btn = Ext.getCmp('userpasswordvaluebtn');
                            btn.handler() ;
                        },
                        scope: this
                    });
                }
            }
        }); 
        var win = Ext.create('Ext.window.Window',{
            layout:'fit',
            autoWidth: true,
            closeAction:'hide',
            resizable: false,
            shadow: true,
            modal: true,
            closable : true,
            items: pwdformPanel
        });
        win.setTitle('请输入邮箱密码！');
        win.show();
    }else{
        Ext.getCmp("MainTabPanel").setActiveTab(tab);
    } 
}

