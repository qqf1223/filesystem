function showSearchPanel(){
    if(Ext.getCmp('searchpanel')==undefined){
        var searchformPanel=Ext.create('Ext.form.Panel',{
            frame: true,
            bodyStyle: 'padding: 5 5 5 5',
            defaultType: 'textfield',
            buttonAlign: 'center',
            items: [{
                xtype:'fieldset',
                columnWidth: 0.3,
                title: '常用',
                collapsible: false,
                items:[
                {
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    //title: '按编号查找：',
                    //collapsible: true,
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items:[{
                        xtype: 'radiogroup',
                        items:[
                        { boxLabel: '全部',name: 'fs_mode', inputValue: '-1',checked:true},
                        { boxLabel: '文件',name: 'fs_mode', inputValue: '0'},  
                        { boxLabel: '文件夹',name: 'fs_mode', inputValue: '1'},  
                        ]
                    }
                    ]
                },{
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    title: '按编号查找：',
                    //collapsible: true,
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items :[{
                        fieldLabel: '文件/文件夹编号',
                        xtype: 'textfield',
                        name: 'fs_name'
                    }]
                }, {
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    title: '按名称查找：',
                    //collapsible: true,
                    defaultType: 'textfield',
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items :[{
                        fieldLabel: '文件/文件夹名称',
                        name: 'fs_intro'
                    }]
                }, {
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    title: '按用户查找：',
                    //collapsible: true,
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items :[{
                        xtype:'combo',
                        name: 'workgroup_id',
                        id: 'workgroup_id',
                        emptyText : '请选择工作组',
                        listConfig:{
                            emptyText: '请选择工作组',
                            loadingText : '正在加载……',
                            maxHeight: 100
                        },
                        triggerAction: 'all',
                        queryMode: 'remote',
                        editable: false,
                        store: new Ext.data.Store({
                            stortId: 'workgroupstore',
                            proxy : {
                                type : 'ajax',
                                url : base_path+'index.php?c=usergroup&a=listworkgroup&needalltag=1',
                                actionMethods : 'post',
                                reader : 'json'
                            },
                            fields : ['u_id', 'u_name'],
                            autoLoad:false
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
                        displayField: 'u_name'
                    }]
                },{
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    title: '类型：',
                    //collapsible: true,
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items :[{
                        xtype: 'checkboxgroup',
                        columns: 4,
                        id: 'fs_type'
                    }]
                }, {
                    xtype:'fieldset',
                    columnWidth: 0.5,
                    title: '按日期查找：',
                    //collapsible: true,
                    defaults: {anchor: '100%'},
                    layout: 'anchor',
                    items :[{
                        xtype: 'datefield',
                        fieldLabel: 'From',
                        labelWidth: 30,
                        editable:false,
                        name: 'from_date',
                        format: 'm/d/Y',
                        maxValue: new Date()  // limited to the current date or prior
                    }, {
                        xtype: 'datefield',
                        fieldLabel: 'To',
                        labelWidth: 30,
                        editable:false,
                        name: 'to_date',
                        format: 'm/d/Y',
                        value: new Date(),  // defaults to today
                        maxValue: new Date()  
                    }]
                }]

            }],
            buttons:[{
                text: '查 找',
                width: 120,
                //height:30,
                scale: 'large',
                handler: function(){
                    //Ext.getCmp('searchresult').loadMask();
                    //Ext.getBody().mask('搜索结果正在加载……');
                    if(searchformPanel.form.isValid()){
                        showsearchgrid(searchformPanel.getForm().getValues(), 'form');
                        //setsearchcookienav('searchresult');
                        Ext.util.Cookies.set("searchpath", '');
                        cookiesearcharr = new Array({"fs_id":'searchresult', "fs_code":'搜索结果', "text":'搜索结果', 'fs_parent':'searchresult'});
                        var json_rcd = Serialize(cookiesearcharr);
                        Ext.util.Cookies.set("searchpath", json_rcd);
                        Ext.util.Cookies.set("selectsearch", '');
                    }
                }
            }]

        });
        Ext.Ajax.request({   
            url:base_path+'index.php?c=document&a=listfiletype', 
            success: function(resp,opts) {  
                var items= eval('('+resp.responseText+')');
                Ext.getCmp('fs_type').add(items);
            } ,
            failure: function(resp,opts){alert('ERROR'+resp.status+'\n'+resp.responseText)}
        });  
        var searchPanel = Ext.create('Ext.panel.Panel', {
            layout: 'border',
            width : '100%',
            height: '100%',
            id: 'searchpanel',
            items: [{ 
                region: 'west',
                title: '搜索条件',
                collapsible: true,
                width:300,
                //split: true,
                layout: 'fit',
                items:[searchformPanel]
            }, {
                region: 'center',
                id: 'searchresult',
                bodyStyle: 'background:#ffffcc;',
                autoScroll: true
            }]
        });
    }else{
        var searchPanel = Ext.getCmp('searchpanel');
    }
    return  searchPanel; 

    var navcookiestore = Ext.create('Ext.data.Store', {
        fields: ['fs_id', 'fs_code', 'text', 'fs_parent'],
    });
    var formparam='';
    /*显示结果表格*/
    function showsearchgrid(param){
        if(arguments[1]!=undefined && arguments[1]=='form'){formparam=param;}
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
        var itemsPerPage = 50;
        var gridstore = Ext.create('Ext.data.Store', {
            autoLoad: { start: 0, limit: itemsPerPage },
            fields:['id', 'fs_id', 'fs_name', 'text', 'fs_intro', 'fs_fullpath', 'fs_isdir', 'managerok', 'fs_code', 'fs_isproject', 'fs_id_path', 'fs_is_share', 'fs_group', 'fs_user', 'fs_parent', 'fs_type', 'fs_create', 'fs_lastmodify', 'fs_size', 'fs_encrypt', 'fs_haspaper', 'fs_hashname', 'icon', 'u_name'],
            pageSize: itemsPerPage,
            remoteSort: true,
            proxy:{
                type:'ajax',
                url: base_path+'index.php?c=document&a=search',
                actionMethods: 'post',
                //data:rows,
                extraParams: param,
                reader: {
                    type: 'json',
                    root: 'rows',
                    totalProperty: 'total'
                }
            },
            autoLoad:true
        });
        //gridstore.load(); 
        var searchpathcookie=[];
        /*获取导航文件夹 */   
        var searchscrollMenu = Ext.create('Ext.menu.Menu');
        if(!Ext.isEmpty(Ext.util.Cookies.get('searchpath'))){
            searchpathcookie=eval("("+Ext.util.Cookies.get("searchpath")+")");
        }
        
        if(!Ext.isEmpty(searchpathcookie)){
            var tt=[];
            for (var i = 0; i < searchpathcookie.length; ++i){
                if(!Ext.Array.contains(tt, searchpathcookie[i].fs_id)){
                    searchscrollMenu.add({
                        text: searchpathcookie[i].fs_code,
                        fs_id: searchpathcookie[i].fs_id,
                        fs_parent: searchpathcookie[i].fs_parent,
                        iconCls: 'icon-doc-open', 
                        listeners: {
                            click: function(val){
                                showsearchgrid({fs_id:this.fs_id});
                                setsearchcookienav('children', searchpathcookie[i]);
                                setsearchcookienav('children', {fs_id:this.fs_id, fs_code:this.text, fs_parent:this.fs_parent, text:this.text});
                            }
                        }
                    });
                    tt.push(searchpathcookie[i].fs_id);
                }
            }
        }

        var gridHeight = $("#searchresult").innerHeight();
        var gridPanel = Ext.create('Ext.grid.Panel', {
            autoWidth: true,
            height: gridHeight,
            frame: true,
            store: gridstore,
            multiSelect: false,
            columns: [
            /*{ header: 'ID', width: 50, dataIndex: 'fs_id', sortable: true, menuDisabled : true},*/
            { header: '类型', width: 40, dataIndex: 'icon', renderer:icon, sortable: false, menuDisabled : true},
            { header: '文件编号', width: 150, dataIndex: 'text', sortable: true,menuDisabled : true },
            { header: '文件名称', width: 200, dataIndex: 'fs_intro',sortable: false, menuDisabled : true},
            { header: '是否加密', width: 70, dataIndex: 'fs_encrypt', renderer: isencrypt, sortable: true, menuDisabled : true  },
            { header: '纸版', width: 50, dataIndex: 'fs_haspaper', renderer: ishaspaper, menuDisabled : true },
            { header: '创建时间', width: 150, dataIndex: 'fs_create', menuDisabled : true },
            { header: '更新时间', width: 150, dataIndex: 'fs_lastmodify', menuDisabled : true },
            { header: '所属用户', width: 100, dataIndex: 'u_name', menuDisabled : true }
            ],
            dockedItems: [{
                xtype: 'pagingtoolbar',
                id: 'pagingtoolbar',
                store: gridstore,   // same store GridPanel is using
                dock: 'bottom',
                displayInfo: true
            },{
                xtype: 'toolbar',
                dock: 'top',
                id: 'searchnavtoolbar',
                items: [{
                    text: '',
                    xtype: 'button',
                    iconCls: 'go_history',
                    disabled: true,
                    id: 'go_history',
                    handler:function(){
                        if(selectsearch.fs_id=='searchresult'){
                            Ext.getCmp('go_history').setDisabled(true);
                            setsearchcookienav('searchresult', '', 'history');
                            showsearchgrid(formparam);
                        }else{
                            setsearchcookienav('children', '', 'history');
                            /* 20131213计划修改 */
                            showsearchgrid(selectsearch);

                        }
                    }
                },
                /*'-',{
                text: '',
                iconCls: 'go_forward',
                disabled: true,
                id:'go_forward',
                handler: function(){
                var selectdoc = !Ext.isEmpty(Ext.util.Cookies.get('selectsearch')) ? eval("("+Ext.util.Cookies.get("selectsearch")+")") : '';
                for(var i=0;i<searchpathcookie.length; i++){
                if(searchpathcookie[i].fs_id==selectdoc.fs_id){
                if(i!=searchpathcookie.length-1){
                showsearchgrid(searchpathcookie[i+1]);
                setsearchcookienav('children', searchpathcookie[i+1], 'forward');
                }else{
                Ext.getCmp('go_forward').setDisabled(true);
                }
                }else{

                }
                }
                }
                },
                */'-',{
                    text: '更多',
                    menu: searchscrollMenu 
                }]
            }],
            listeners:{
                'itemdblclick': function(view, rcd, item, index, event, eOpts){
                    event.stopEvent(); 
                    if(rcd.raw.fs_isdir=='1'){
                        gridstore.setProxy({
                            type: 'ajax',
                            url: base_path + "index.php?c=document&a=search&fs_id="+rcd.raw.fs_id, 
                            reader: {
                                type: 'json',
                                root: 'rows',
                                totalProperty: 'total'
                            }
                        });
                       
                        setsearchcookienav('children', rcd.raw);
                         showsearchgrid({fs_id:rcd.raw.fs_id});
                    }else{
                        openfile(view, rcd, item, index, event, eOpts);
                    }
                },
                'itemcontextmenu': function(view, rcd, item, index, event, eOpts){
                    event.stopEvent(); 
                    showmenu(view, rcd, item, index, event, eOpts);
                }
            }
        });
        ///对左右箭头进项可用性设置
        if(cookiesearcharr.length!=1 && selectsearch.fs_id!='searchresult'){
            Ext.getCmp('go_history').setDisabled(false);
        }else{
            Ext.getCmp('go_history').setDisabled(true);
        }
        /*动态加载本地数据*/
        Ext.getCmp('searchresult').remove(Ext.getCmp('searchresult').items.get(0));
        Ext.getCmp('searchresult').add(gridPanel).doLayout(); 
    }      
}

var cookiesearcharr=[];
var selectsearch='';
/*设置导航COOKIE*/
function setsearchcookienav(showtype, rcd){
    //if(showtype=='searchresult'){
    //cookiesearcharr.push({"fs_id":'searchresult', "fs_code":'搜索结果', "text":'搜索结果', 'fs_parent':'searchresult'});
    //}else
    /**将当前文件夹放入cookie中， 建立导航标签*/
    if(rcd){
        var _fs_id = rcd.fs_id;
        var _fs_code = rcd.fs_code;
        var _fs_parent = rcd.fs_parent;

        cookiesearcharr.push({"fs_id":_fs_id, "fs_code":_fs_code, "text":_fs_code, 'fs_parent':_fs_parent});
        var json_rcd = Serialize(cookiesearcharr);
        Ext.util.Cookies.set("searchpath", json_rcd);
        //Ext.util.Cookies.set("selectsearch", Serialize(cookiesearcharr[cookiesearcharr.length-1]));
        selectsearch = cookiesearcharr[cookiesearcharr.length-1];
    }else{
        if(cookiesearcharr.length>1){
            var s = cookiesearcharr.pop();
            var json_rcd2 = Serialize(cookiesearcharr);
            Ext.util.Cookies.set("searchpath", json_rcd2);
            selectsearch = cookiesearcharr[cookiesearcharr.length-1];
        }else{
            Ext.getCmp('go_history').setDisabled(true);
        }
    }
}