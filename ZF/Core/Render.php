<?php
/**
* @name      Render.php
* @describe  view
* @author    qinqf
* @version   1.0 
* @copyright qinqf
* @todo       
* @changelog  
*/

class ZF_Core_Render
{   
     /**
      * @var array  提取的内容数据
      */
     protected  $_viewData;
     protected  $_templateFile;

    /**
     * 构造函数
     *
     * @param string $templateFile    模板文件(扩展名为:tpl.php)
     * @param string $templateEngine  模板引擎
     */
    public function __construct($templateFile=null, $templateEngine="php")
    {
        if (!$templateEngine) $templateEngine = "php";
        if (!is_null($templateFile)) $this->setTemplate($templateFile);
    }
    
    /**
     * 显示解析模板内容
     */
    public function display()
    {
        $content =  $this->render();
        echo $content;  
    }
    
    /**
     * 包含模板文件
     */
    public function embedFile($file)
    {
        if ($this->checkTemplateFile($file)){
            if ($this->_viewData) {
                extract($this->_viewData);
            }
            include_once(APP_TEMPLATE . '/' . $file . '.html');
        }
    }
    
    
    /**
     * 获取模板内容
     */
    public function fetch()
    {
        return $this->render();
    }
    
    
    /**
     * 设置$_viewData数组值
     */
    public function set($key, $val)
    {
        $this->_viewData[$key] = $val;  
    }
    
    /**
     * 魔术函数  给类属性赋值
     */
    public function __set($key, $val){
        $this->set($key, $val);
    }
        
    /**
     * 设置模板文件
     * 
     * @param  string $templateFile   缺损模板文件名
     */
    public function setTemplate($file)
    {
        $this->_templateFile = (defined('APP_TEMPLATE') ? APP_TEMPLATE . '/' : "") . $file . ".html";
    }
    
    /**
     * 获取模板文件名
     */
    public function getTemplate()
    {
        return $this->_templateFile;
    }
    
    /**
     * 解析模板并返回内容
     *
     * @return string 解析内容
     */
    public function render()
    {
        if (!$this->checkTemplateFile($this->_templateFile)) {
            trigger_error("($this->_templateFile)模板文件不存在");
        }
        
        if ($this->_viewData) {
            extract($this->_viewData);
        }                
        //开始缓存
        ob_start();
        include($this->_templateFile);
        $content = ob_get_clean();
        return $content;
    }

    /**
      * 验证文件是否存在
      *
      * @return boolean
      */
     public function checkTemplateFile()
     {
 
         if (!$this->_templateFile)
         {      
             return false;
         }

         if (!is_file($this->_templateFile))
         {
             return false;
         }
         
         return true;
     }
} 