<?php
/**
* @name      Document.php
* @describe  文件管理类
* @author    qinqf
* @todo       
* @changelog  
*/

class C_Document extends C_Controller
{
    /**
    * 初始化操作
    *
    */
    function prepare($request)
    {
    }

    /**
    *  界面
    * 
    */
    function doDefault() {
        $group_rs = M_Usergroup::listworkgroup();
        $this->group_rs = $group_rs;
        $this->setTemplate('document');
    }

    /**
    * 列出项目文件列表
    * 
    */
    function doListdocument(){
        $rs = $this->returnmsg(M_Document::docmenttree($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 添加项目下文件夹
    * 
    */
    function doAdddocument(){
        $rs = $this->returnmsg(M_Document::adddocument($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 创建共享文件夹
    * 
    */
    function doSharedocument(){
        $rs = $this->returnmsg(M_Document::sharedocument($_POST, $this->login_user_info));
        exit($rs);
    } 

    /**
    * 编辑文件夹
    * 
    */
    function doEditdocument(){
        $rs = $this->returnmsg(M_Document::editdocument($_POST, $this->login_user_info));
        exit($rs);
    }    

    /**
    * 编辑文件
    * 
    */
    function doEditfile(){
        $rs = $this->returnmsg(M_Document::editfile($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 删除文件
    * 
    */
    function doDeldocument(){
        $rs = $this->returnmsg(M_Document::deldocument($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 下载文件
    * 
    */
    function doDownloadfile(){
        $rs = $this->returnmsg(M_Document::downloadfile($_REQUEST, $this->login_user_info));
        exit($rs);
    }    

    /**
    * 下载历史文件
    * 
    */
    function doDownloadhistory(){
        $rs = $this->returnmsg(M_Document::downloadhistory($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 刷新文件夹
    * 
    */
    function  doRefresh(){
        $_GET['refresh']=1;
        $rs = $this->returnmsg(M_Document::docmenttree($_GET, $this->login_user_info, 1));
        exit($rs);
    }

    /**
    * 添加项目
    * 此功能只有超级管理员有这个权限
    * 
    */
    function doAddproject(){
        if(!empty($_POST)){
            $rs = $this->returnmsg(M_Document::addproject($_POST, $this->login_user_info));
            exit($rs);
        }else{
            $this->setTemplate('project_add');
        }
    }


    /**
    *  分配权限
    * 
    */
    function doAdddocpower(){
        $rs = $this->returnmsg(M_Document::adddocpower($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 移动文件
    * 
    */
    function doMovedocument(){
        $rs = $this->returnmsg(M_Document::movedocument($_POST, $this->login_user_info));
        exit($rs);
    } 

    function doOpenfile(){
        $rs = $this->returnmsg(M_Document::openfile($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 显示GRID文件数据
    * 
    */
    function doListdocumentgrid(){
        $rs = $this->returnmsg(M_Document::listdocumentgrid($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 显示GRID文件数据
    * 
    */
    function doShowhistory(){
        $rs = $this->returnmsg(M_Document::showhistory($_GET, $this->login_user_info));
        exit($rs);
    }    


    /**
    * 搜索文件数据
    * 
    */
    function doSearch(){
        $rs = $this->returnmsg(M_Document::search($_REQUEST, $this->login_user_info));
        exit($rs);
    } 

    /**
    * 列出文件类型
    * 
    */
    function doListfiletype(){
        $rs = $this->returnmsg(M_Document::listfiletype());
        exit($rs);
    }

    /**
    * 恢复文件
    * 
    */
    function doRecoverdocument(){
        $rs = $this->returnmsg(M_Document::recoverdocument($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 根据用户ID获取 权限目录树
    * 
    */
    function doListdocbyuid(){
        $rs = $this->returnmsg(M_Document::listdocbyuid($_REQUEST, $this->login_user_info));
        exit($rs);
    }


    /**
    * 生成项目目录结构
    * 
    */
    function doListUserDocument(){
        $rs = $this->returnmsg(M_Document::listUserDocument($_REQUEST, $this->login_user_info));
        $this->rs = $rs;
        #获取当前用户可以访问的组
        $usergroup = $this->returnmsg(M_Usergroup::listUserGroup($this->login_user_info));
        $this->usergroup = $usergroup;
        #获取当前用户可以访问的用户
        $user = $this->returnmsg(M_Usergroup::listUser($this->login_user_info));
        $this->user = $user;
        $this->setTemplate('generatedoc');
    }  
    
    /**
    * 生成目录结果
    * 
    */
    function doGetAlldocbySearch(){
        $rs = $this->returnmsg(M_Document::getAlldocbySearch($_REQUEST, $this->login_user_info));
        exit($rs);
    }  

    /**
    * 导出用户目录树
    * 
    */
    function doExportUserDocument(){
        $data = !empty($_POST['fs_tree_content']) ? $_POST['fs_tree_content'] : '';
        $op = isset($_GET['download']) ? $_GET['download'] : '';
        if(!$op){
            if($data){
                $path = APP_PATH . '/doctree/' . $this->login_user_info['u_id'];

                //$search_arr = array('<strong>'=>'', '</strong>'=>'', '<br>'=>'', '<span class="fs_name">'=>'', '<span class="fs_intro">'=>'', '</span>'=>'', '<span class="fs_name_1">'=>'');
                $search_arr = array("\r"=>'', "\r\n"=>'', "\n"=>'');
                $data = strtr($data, $search_arr); 
                ZF_Libs_IOFile::write($path, $data);
            }else{
                $rs['msg'] = '没有数据！';
                $rs['success'] = false;
                echo json_encode($rs);exit;
            }
        }else{

            #使用pear扩展生成Excel文件
            $path = APP_PATH . '/doctree/' . $this->login_user_info['u_id'];
            $html = ZF_Libs_IOFile::read($path);
            if(ord(substr($html, 0, 1))===0xEF && ord(substr($html, 1,1))===0xBB && ord(substr($html, 2,1))===0xBF){
                $html = substr($html, 3);
            }


            $includepath = APP_PATH . '/Spreadsheet/Excel/';
            set_include_path(get_include_path() . PATH_SEPARATOR . $includepath);
            require_once "Spreadsheet/Excel/Writer.php";
            $workbook = new Spreadsheet_Excel_Writer();
            ob_end_clean();
            $filename = '目录结构_'.date('YmdHis').'.xls';//csv
            //$workbook->send(mb_convert_encoding($filename, 'gbk', 'utf-8')); // 发送 Excel 文件名供下载
            $workbook->send($filename); // 发送 Excel 文件名供下载
            //$workbook->setVersion(8);

            //创建Worksheet
            $worksheet = $workbook->addWorksheet(date('YmdHis'));
            $worksheet->setInputEncoding('utf-8'); // 指定行编码
            $worksheet->setColumn(1,1,50);
            $worksheet->setColumn(1,2,50);
            $worksheet->setColumn(1,4,30);
            $worksheet->setColumn(1,5,30);

            $headFormat= $workbook->addFormat(array('Size' => 12, 'Align' => 'center','Color' => 'black','Bold'=>'1'));//定义格式
            $dataFormat= $workbook->addFormat(array('Align' => 'left'));//定义格式

            $arr = json_decode($html);
            if(!empty($arr)){
                #开始输出数据到EXCEL
                for($i=0; $i<count($arr); $i++){
                    for($j=0; $j<7; $j++){
                        if(empty($arr[$i][$j])){continue;}
                        if($i==0){
                            $worksheet->writeString($i, $j, mb_convert_encoding($arr[$i][$j], 'gb2312', 'utf-8'), $headFormat);
                            //$worksheet->write($i, $j, $arr[$i][$j], $headFormat);
                        } else{
                            $worksheet->writeString($i, $j, mb_convert_encoding($arr[$i][$j], 'gb2312', 'utf-8'), $dataFormat);
                            //$worksheet->write($i, $j, $arr[$i][$j], $dataFormat);
                        }
                    }
                }
            } 

            //关闭Workbook
            $workbook->close(); // 完成下载    
        }
    }

    /**
    * 获取单条文件目录信息
    * 
    */
    function doGetdocdatabyid(){
        $rs = $this->returnmsg(M_Document::getdatabyid($_REQUEST, $this->login_user_info));
        exit($rs); 
    } 



    /**
    * 根据ID获取当前目录的面包屑
    * 
    */
    function doGetnavdata(){
        $rs = $this->returnmsg(M_Document::getnavdata($_REQUEST, $this->login_user_info));
        exit($rs);
    }


    /**
    * 移除共享目录设置
    * 
    */
    function doRemovesharesetting(){
        $rs = $this->returnmsg(M_Document::removesharesetting($_REQUEST, $this->login_user_info));
        exit($rs);
    }
    
    function doCopydocstruct(){
        $rs = $this->returnmsg(M_Document::copydocstruct($_REQUEST, $this->login_user_info));
        exit($rs);
    }



    /********************************************************************************************************/
    /********************************************************************************************************/
    /***********************************######共享文件夹相关功能######************************************/
    /********************************************************************************************************/
    /********************************************************************************************************/
    /**
    * 列出项目文件列表
    * 
    */
    function doListsharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::docmenttree($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 创建共享文件夹根
    * 
    */
    function doAddsharedocroot(){
        $rs = $this->returnmsg(M_Sharedocument::addsharedocroot($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 创建共享文件夹 ,系统管理员创建系统级的共享文件夹， 组管理员创建组级别的共享文件夹
    * 
    */
    function doAddsharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::addsharedocument($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 编辑文件夹
    * 
    */
    function doEditsharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::editdocument($_POST, $this->login_user_info));
        exit($rs);
    }    

    /**
    * 编辑文件
    * 
    */
    function doEditsharefile(){
        $rs = $this->returnmsg(M_Sharedocument::editfile($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 删除文件
    * 
    */
    function doDelsharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::delsharedocument($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 下载文件
    * 
    */
    function doDownloadsharefile(){
        $rs = $this->returnmsg(M_Sharedocument::downloadfile($_REQUEST, $this->login_user_info));
        exit($rs);
    }    

    /**
    * 下载历史文件
    * 
    */
    function doDownloadsharehistory(){
        $rs = $this->returnmsg(M_Sharedocument::downloadhistory($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 刷新文件夹
    * 
    */
    function  doShareRefresh(){
        $_GET['refresh']=1;
        $rs = $this->returnmsg(M_Sharedocument::docmenttree($_GET, $this->login_user_info, 1));
        exit($rs);
    }


    /**
    *  分配权限
    * 
    */
    function doAddsharedocpower(){
        $rs = $this->returnmsg(M_Sharedocument::adddocpower($_POST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 移动文件
    * 
    */
    function doMovesharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::movedocument($_POST, $this->login_user_info));
        exit($rs);
    } 

    function doOpensharefile(){
        $rs = $this->returnmsg(M_Sharedocument::openfile($_REQUEST, $this->login_user_info));
        exit($rs);
    }

    /**
    * 显示GRID文件数据
    * 
    */
    function doListsharedocumentgrid(){
        $rs = $this->returnmsg(M_Sharedocument::listdocumentgrid($_GET, $this->login_user_info));
        exit($rs);
    }

    /**
    * 显示GRID文件数据
    * 
    */
    function doShowsharehistory(){
        $rs = $this->returnmsg(M_Sharedocument::showhistory($_GET, $this->login_user_info));
        exit($rs);
    }    


    /**
    * 生成项目目录结构
    * 
    */
    function doListUserShareDocument(){
        $rs = $this->returnmsg(M_Sharedocument::listUserDocument($_REQUEST, $this->login_user_info));
        $this->rs = $rs;
        #获取当前用户可以访问的组
        $usergroup = $this->returnmsg(M_Usergroup::listUserGroup($this->login_user_info));
        $this->usergroup = $usergroup;
        #获取当前用户可以访问的用户
        $user = $this->returnmsg(M_Usergroup::listUser($this->login_user_info));
        $this->user = $user;
        $this->setTemplate('generatedoc');
    }     

    /**
    * 获取用户可以上传的共享目录
    * 
    */
    function doListselfsharedocument(){
        $rs = $this->returnmsg(M_Sharedocument::listselfsharedocument($_REQUEST, $this->login_user_info));
        $this->rs = $rs;
        #获取当前用户可以访问的组
        $usergroup = $this->returnmsg(M_Usergroup::listUserGroup($this->login_user_info));
        $this->usergroup = $usergroup;
        #获取当前用户可以访问的用户
        $user = $this->returnmsg(M_Usergroup::listUser($this->login_user_info));
        $this->user = $user;
        $this->setTemplate('generatedoc');
    }    

    /**
    * 导出用户目录树
    * 
    */
    function doExportUserShareDocument(){
        $data = !empty($_POST['fs_tree_content']) ? $_POST['fs_tree_content'] : '';
        $op = isset($_GET['download']) ? $_GET['download'] : '';
        if(!$op){
            if($data){
                $path = APP_PATH . '/doctree/' . $this->login_user_info['u_id'];

                //$search_arr = array('<strong>'=>'', '</strong>'=>'', '<br>'=>'', '<span class="fs_name">'=>'', '<span class="fs_intro">'=>'', '</span>'=>'', '<span class="fs_name_1">'=>'');
                $search_arr = array("\r"=>'', "\r\n"=>'', "\n"=>'');
                $data = strtr($data, $search_arr); 
                ZF_Libs_IOFile::write($path, $data);
            }else{
                $rs['msg'] = '没有数据！';
                $rs['success'] = false;
                echo json_encode($rs);exit;
            }
        }else{

            #使用pear扩展生成Excel文件
            $path = APP_PATH . '/doctree/' . $this->login_user_info['u_id'];
            $html = ZF_Libs_IOFile::read($path);
            if(ord(substr($html, 0, 1))===0xEF && ord(substr($html, 1,1))===0xBB && ord(substr($html, 2,1))===0xBF){
                $html = substr($html, 3);
            }


            $includepath = APP_PATH . '/Spreadsheet/Excel/';
            set_include_path(get_include_path() . PATH_SEPARATOR . $includepath);
            require_once "Spreadsheet/Excel/Writer.php";
            $workbook = new Spreadsheet_Excel_Writer();
            ob_end_clean();
            $filename = '目录结构_'.date('YmdHis').'.xls';//csv
            //$workbook->send(mb_convert_encoding($filename, 'gbk', 'utf-8')); // 发送 Excel 文件名供下载
            $workbook->send($filename); // 发送 Excel 文件名供下载
            //$workbook->setVersion(8);

            //创建Worksheet
            $worksheet = $workbook->addWorksheet(date('YmdHis'));
            $worksheet->setInputEncoding('utf-8'); // 指定行编码
            $worksheet->setColumn(1,1,50);
            $worksheet->setColumn(1,2,50);
            $worksheet->setColumn(1,4,30);
            $worksheet->setColumn(1,5,30);

            $headFormat= $workbook->addFormat(array('Size' => 12, 'Align' => 'center','Color' => 'black','Bold'=>'1'));//定义格式
            $dataFormat= $workbook->addFormat(array('Align' => 'left'));//定义格式

            $arr = json_decode($html);
            if(!empty($arr)){
                #开始输出数据到EXCEL
                for($i=0; $i<count($arr); $i++){
                    for($j=0; $j<7; $j++){
                        if(empty($arr[$i][$j])){continue;}
                        if($i==0){
                            $worksheet->writeString($i, $j, mb_convert_encoding($arr[$i][$j], 'gb2312', 'utf-8'), $headFormat);
                            //$worksheet->write($i, $j, $arr[$i][$j], $headFormat);
                        } else{
                            $worksheet->writeString($i, $j, mb_convert_encoding($arr[$i][$j], 'gb2312', 'utf-8'), $dataFormat);
                            //$worksheet->write($i, $j, $arr[$i][$j], $dataFormat);
                        }
                    }
                }
            } 

            //关闭Workbook
            $workbook->close(); // 完成下载    
        }
    }  
    /**
    * 根据ID获取当前共享目录的面包屑
    * 
    */
    function doGetsharenavdata(){
        $rs = $this->returnmsg(M_Sharedocument::getnavdata($_REQUEST, $this->login_user_info));
        exit($rs);
    }


    /**
    * 移动项目文件或文件夹至共享目录中
    * 
    */
    function doMovetoshare(){
        $rs = $this->returnmsg(M_Document::movetoshare($_REQUEST, $this->login_user_info));
        exit($rs);
    }


    /****************************************以下程序手工设置默认值使用***********************/
    /**
    * 设置文件fs_code
    * 
    */
    function doSetFilenamepath(){
        M_Document::setFilenamepath();
    }    

    /**
    * 设置文件fs_code
    * 
    */
    function doSetFileIdpath(){
        M_Document::setFileIdpath();
    }   

    /**
    * 设置文件fs_code
    * 
    */
    function doSetshareFilenamepath(){
        M_Sharedocument::setFilenamepath();
    }    

    /**
    * 设置文件fs_code
    * 
    */
    function doSetshareFileIdpath(){
        M_Sharedocument::setFileIdpath();
    }
    
    function doSetFileLevel(){
        M_Document::setFileLevel();
    }

}

