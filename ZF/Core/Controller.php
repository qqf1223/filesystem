<?php
/**
* @name      Controller.php
* @describe  MVC控制器
* @author    qinqf
* @version   1.0 
* @copyright qinqf
* @todo       
* @changelog  
*/

abstract class ZF_Core_Controller
{
    /**
     * @var 请求
     */
    protected $_setTemplate = false;
    
    /**
     * @var 模板类
     */
    protected $_view;
    
    
    public function __construct()
    {
        //定义模块对象
        $this->_view = new ZF_Core_Render();
        
        $this->ctl = ZF_Core_Request::getController();
        $this->act = ZF_Core_Request::getAction();
        $this->exe = ZF_Core_Request::getExecName();
    }
    
    /**
     * 加载环境
     */
    abstract public function prepare($args);
   
    /**
     * 回显html内容
     */
    public function display()
    {
        if ($this->_setTemplate) {
            $this->_view->display();
        }
    }
    
    /**
     * 获取模板解析后的内容
     */
    public function fetch($file)
    {
        $this->setTemplate($file);
        $content = $this->_view->fetch();
        return $content;
    }
    
    /**
     * 设置模板
     */
    public function setTemplate($file)
    {
        $this->_setTemplate = true;
        $this->_view->setTemplate($file);
    }
    
    /**
     * 魔术方法，自动给模板提供数据
     */
    public function __set($key, $val)
    {
        $this->$key = $val;
        
        //再分配给模板
        $this->_view->set($key, $val);
    }
    
    /**
     * 魔术方法，控制器中，调用没有的方法
     * 如：$this->getController();
     */
    public function __call ($method, $args) 
    {
	}
	
	/**
     * 消息提示
     */
	public function showMessage($msg, $url='', $second = 0)
	{
        //; #qinqf add  Undefined variable $second;
	    $str = '<script>';
	    if ($msg) {
	        $str  .= "alert('$msg');";
	    }
	    if($url){
    		$str .= "location.href ='$url';";
    	}else{
    		if($second){
    			$str .= "top.location.reload();";
    		}else{
    			$str .= "history.go(-1);";
    		}
    	}
    	$str .= '</script>';
    	die($str);
	}
	
	/**
     * 表单提交后，映射到相应的处理方法
     */
	public function submitMap ()
	{
	    $funcName = "_". strtolower($this->act) . $this->exe;
		if(!method_exists($this, $funcName)) {	
			$this->showMessage('参数错误，非法操作~');
		}
		//处理
		$this->$funcName();
	}

}