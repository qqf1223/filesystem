<?php
/**
* @name      ZF_Libs_Page.php
* @describe  page class
* @author    qinqf
* @todo       
* @changelog 
* example:
$page_list = "";
$page      = new ZF_Libs_Page;
$page->page(array('total'=>1000,'perpage'=>20));
$page_list .= 'mode:1<br>'.$page->show();
$page_list .= '<hr>mode:2<br>'.$page->show(2);
$page_list .= '<hr>mode:3<br>'.$page->show(3);
$page_list .= '<hr>mode:4<br>'.$page->show(4);
$ajaxpage  = new ZF_Libs_Page;
$ajaxpage->page(array('total'=>1000,'perpage'=>20,'ajax'=>'ajax_page','page_name'=>'test'));
$page_list .= '<hr>mode:5<br>'.$ajaxpage->show();
*/

class ZF_Libs_Page {
    
    /**
    * config ,public
    */
    var $page_name    = 'page';       //page parameter
    var $next_page    = '>';       
    var $pre_page     = '<';       
    var $first_page   = 'First';   //first
    var $last_page    = 'Last';    //last
    var $format_left  = '[';       //left sign 
    var $format_right = ']';       //right sign 
    var $is_ajax      = FALSE;     //AJAX

    /**
    * private
    *
    */ 
    var $pagebarnum  = 10;      //button num
    var $totalpage   = 0;       //total page
    var $total       = 0;       //nums
    var $perpage     = 10;      //per page
    var $nowindex    = 1;       //current page
    var $url         = "";      //url
    var $offset      = 0;
    var $ajax_action_name = ''; //AJAX action
    var $htmltype    = 0;
    var $afferpage   = "html";  //afferpage

    var $special_first_pagetype = 0;  //类型0 原装 1 有special_first_page传入 2 _1.html方式
    var $special_first_page     = ''; //默认的首页地址
    var $base_url    = '';
    
    
	function __construct($param=""){
		if(!empty($param)){
			$this->page($param);
		}
	}

    /**
    * constructor
    *
    * @param array $array['total'],$array['perpage'],$array['nowindex'],$array['url'],$array['ajax'] ,$array['htmltype']，$array['afferpage']...
    */
    function page($array) {
        if(is_array($array)) {
            if(!array_key_exists('total',$array)) {
                $this->error(__FUNCTION__,'need a param of total');
            }
            $total   = intval($array['total']);
            $perpage = (array_key_exists('perpage',$array)) ? intval($array['perpage']) : 10;
            $nowindex= (array_key_exists('nowindex',$array))? intval($array['nowindex']) : '';
            $url     = (array_key_exists('url',$array)) ? $array['url'] : '';
        } else {
            $total    = $array;                                
            $perpage  = 10;
            $nowindex = '';
            $url      = '';
        }
        if((!is_int($total))||($total<0)) {
            $this->error(__FUNCTION__,$total.' is not a positive integer!');
        }
        if((!is_int($perpage))||($perpage<=0)) {
            $this->error(__FUNCTION__,$perpage.' is not a positive integer!');
        }
        if(!empty($array['page_name'])) {
            $this->set('page_name',$array['page_name']);       //pagename
        }
        
        $this->total   = ceil($total);
        $this->perpage = ceil($perpage);
        $this->_set_nowindex($nowindex); 
        $this->special_first_pagetype = (int)@$array['special_first_pagetype'];
        $this->special_first_page     = @$array['special_first_page'];
        if (!empty($array['htmltype'])) {
            $this->htmltype = 1;
        }
        if (!empty($array['afferpage'])) {
            $this->afferpage = $array['afferpage'];
        }
        $this->base_url = $url;
        
        $baseurl = "";
        $base_url_arr = explode("/",$this->base_url);
        if (is_array($base_url_arr)) {
            $baseurl_count = count($base_url_arr)-1;
            foreach ($base_url_arr as $key=>$value) {
                if ($key>0 && $key<$baseurl_count) {
                    $baseurl .= "/".$value;
                }
            }
            $baseurl .= "/";
        }
        if ($baseurl) {
            $this->special_first_page = $baseurl;
        }

        $this->_set_url($url,$this->htmltype);                                 
        $this->totalpage = ceil($total/$perpage);           
        $this->offset    = ($this->nowindex-1)*$perpage;
        if(!empty($array['ajax'])) {
            $this->open_ajax($array['ajax']);                  
        }
        
    }

    /**
    * set var value
    *
    * @param string $var
    * @param string $value
    */
    function set($var,$value) {
        if(in_array($var,get_object_vars($this))) {
            $this->$var=$value;
        } else {
            $this->error(__FUNCTION__,$var." does not belong to PB_Page!");
        }
    }

    /**
    * open ajax action
    *
    * @param string $action 
    */
    function open_ajax($action) {
        $this->is_ajax          = TRUE;
        $this->ajax_action_name = $action;
    }

    /**
    * get next page code 
    * 
    * @param string $style
    * @return string
    */
    function next_page($style='',$styletype='span') {
        if($this->nowindex < $this->totalpage) {
            return $this->_get_link($this->_get_url($this->nowindex+1),$this->next_page,$style);
        }
        if (empty($style)){
            return $this->next_page;
        }else{
            if ('a'== $styletype) {
                return '<a class="'.$style.'">'.$this->next_page.'</a>';
            }else{
                return '<a class="' . $style . '">' . $this->next_page. '</a>';
            }
        }
    }

    /**
    * get before page code 
    *
    * @param string $style
    * @return string
    */
    function pre_page($style='',$styletype='span') { 
        if($this->nowindex > 1) {
            return $this->_get_link($this->_get_url($this->nowindex-1),$this->pre_page,$style);
        }
        if (empty($style)){
            return $this->pre_page;
        }else{ 
            if ('a'== $styletype) { 
                return '<a class="'.$style.'">'.$this->pre_page.'</a>';
            }else{
                return '<a class="' . $style . '" >'.$this->pre_page.'</a>';
            }
        }
    }
 
    /**
    * get first page code 
    *
    * @return string
    */
    function first_page($style='') {
        if(1 == $this->nowindex) {
            if (empty($style)) {
                return $this->first_page;
            } else {
                return '<span class="'.$style.'">'.$this->first_page.'</span>';
            }
        }
        return $this->_get_link($this->_get_url(1),$this->first_page,$style);
    }

    /**
    * get last page code 
    *
    * @return string
    */
    function last_page($style='') {
        if( ($this->nowindex == $this->totalpage) || (0 == $this->totalpage) ) {
            if (empty($style)) {
                return $this->last_page;
            } else {
                return '<span class="'.$style.'">'.$this->last_page.'</span>';
            }
        }
        return $this->_get_link($this->_get_url($this->totalpage),$this->last_page,$style);
    }
 
    function nowbar($style='',$nowindex_style='') {
        $plus  = ceil($this->pagebarnum / 2);

        if ($this->nowindex <= $plus) {
            $begin  = $this->nowindex - $plus + 1;
            $begin  = ($begin >= 1) ? $begin : 1;
            $end_pagebarnum = $this->nowindex + $plus + ($plus - $this->nowindex) + 1;
            $limittotal     = $this->totalpage + 1;
            if ($end_pagebarnum > $limittotal) {
                $end_pagebarnum = $limittotal;
            }
        }else{
            $begin          = $this->nowindex - $plus + 1;
            $end_pagebarnum = $begin + $this->pagebarnum;
            $limittotal     = $this->totalpage + 1;
            if ($end_pagebarnum > $limittotal) {
                $begin = $begin - ($end_pagebarnum - $limittotal);
                $end_pagebarnum = $limittotal;
            }
            $begin          = ($begin >= 1) ? $begin : 1;
        }
        $return = '';

        for( $i=$begin;$i<$end_pagebarnum;$i++) {
            if( $i!=$this->nowindex ) {
                $return .= $this->_get_text($this->_get_link($this->_get_url($i),$i,$style));
            } else { 
                if ('span'==$nowindex_style) {
                    $return .= $this->_get_text('<span>'.$i.'</span>');
                }else{
                    $return .= $this->_get_text('<span class="'.$nowindex_style.'">'.$i.'</span>');
                }
            }
        }
        unset($begin);
        return $return;
    }

    /**
    * get jump button code 
    * onchange
    * @return string
    */
    function select($pageid='') {
		/*
        $return = '<select name="PB_Page_Select">';
        //onchange  this.value
        //ajax javascript:'.$this->ajax_action_name.'(\''.$url.'\')
        for($i=1;$i<=$this->totalpage;$i++) {
            if($i==$this->nowindex) {
                $return.= '<option value="'.$i.'" selected>'.$i.'</option>';
            }else{
                $return.= '<option value="'.$i.'">'.$i.'</option>';
            }
        }
        unset($i);
        $return.= '</select>';
        return $return;
		*/
		$return = '<td width="100">第<span class="tiaozhuan">';
		$return .= '<input class="cc1" type="text" onfocus="if(this.value==this.defaultValue){this.value=\'\';}" onblur="if(this.value==\'\'){this.value=this.defaultValue;}" value="' . $this->nowindex . '" name="page" id="page'.$pageid.'"></span>页';
		$return .= '<input type="button" name="button" value="GO" onclick="javascript:var pageno=document.getElementById(\'page'.$pageid.'\').value;pageno=parseInt(pageno)==NaN||parseInt(pageno)<=0?1:pageno;if(pageno<='.$this->totalpage.'){location.href=\''.$this->url .'\'+pageno}else{location.href=\''.$this->url .'\'+'.$this->totalpage.'}" /></td>';
		
		return $return;
    }
 
    /**
    * get mysql limit value
    *
    * @return string
    */
    function offset() {
        return $this->offset;
    }
 
    /**
    * show style
    *
    * @param int $mode
    * @return string
    */
    function show($mode=1, $pageid=1) {
        switch ($mode) {
            case '1':
            $this->next_page = '下一页';
            $this->pre_page  = '上一页';
            return '<table border="0" cellspacing="0" cellpadding="0" align="right"><tr><td width="65">' . $this->pre_page('fanye') . '</td>' . $this->select() . '<td width="65">' . $this->next_page('fanye') . '</td></tr></table>';
            break;
            case '2':
            $this->next_page = '下一页';
            $this->pre_page  = '上一页';
            return '<table border="0" cellspacing="0" cellpadding="0" align="center"><tr><td width="65">' . $this->pre_page('fanye') . '</td>' . $this->select($pageid) . '<td width="65">' . $this->next_page('fanye') . '</td></tr></table>';
            break;

            default:
            break;
        }
    }


    /*---------------- private function ----------------------------------------------------------*/
    /**
    * set url head
    * @param: String $url
    * @return boolean
    */
    function _set_url($url="",$htmltype=0) {
        if(!empty($url)) {
            //hand set
            if ($htmltype) {
                $this->url = $url;
            }else{
                $this->url = $url.((stristr($url,'?'))?'&':'?').$this->page_name."=";
            }
        }else{
            //auto
            if(empty($_SERVER['QUERY_STRING'])) {
                //QUERY_STRING no
                $this->url = $_SERVER['REQUEST_URI']."?".$this->page_name."=";
            }else{
                if(stristr($_SERVER['QUERY_STRING'],$this->page_name.'=')) {
                    //parameter
                 $this->url = str_replace($this->page_name.'='.$this->nowindex,'',$_SERVER['REQUEST_URI']);
                 $last      = $this->url[strlen($this->url)-1];
                 if($last=='?' || $last=='&') {
                     $this->url.= $this->page_name."=";
                 }else{
                     $this->url.= '&'.$this->page_name."=";
                 }
                }else{
                    $this->url = $_SERVER['REQUEST_URI'].'&'.$this->page_name.'=';
                }
            }
        }//end if
    }
 
    /**
    * set current index
    *
    */
    function _set_nowindex($nowindex) {
        if(empty($nowindex)) {
            //auto get
            if(isset($_GET[$this->page_name])) {
                $this->nowindex = intval($_GET[$this->page_name]);
            }
        } else {
            //hand set
            $this->nowindex     = intval($nowindex);
        }
    }

    /**
    * get url
    *
    * @param int $pageno
    * @return string $url
    */
    function _get_url($pageno=1) {
        if ($this->htmltype) {
            if (1==$pageno){
                switch ($this->special_first_pagetype) {
                    case 1 : $r_url = $this->special_first_page; break;
                    case 2 : $r_url = $this->url."_1.".$this->afferpage; break;
                    default: $r_url = $this->url.".".$this->afferpage;
                }
                return $r_url; 
            }else{
                return $this->url."_".$pageno.".".$this->afferpage;
            }
        }else{
            if (1==$pageno) { 
                switch ($this->special_first_pagetype) {
                    case 1 : $r_url = $this->special_first_page; break;
                    case 2 : $r_url = $this->url."_1."; break;
                    default: $r_url= $this->url.$pageno;
                }
                return $r_url; 
            }
            return $this->url.$pageno;
        }
    }
 
    /**
    * get page show text
    *
    * @param String $str
    * @return string $url
    */ 
    function _get_text($str) {
        return $this->format_left.$str.$this->format_right;
    }

    /**
    * get link
    */
    function _get_link($url,$text,$style='') {
        $style = (empty($style)) ? '' : 'class="'.$style.'"';
        if($this->is_ajax) {
            //ajax
            return '<a '.$style.' href="javascript:'.$this->ajax_action_name.'(\''.$url.'\')">'.$text.'</a>';
        } else {
            return '<a '.$style.' href="'.$url.'">'.$text.'</a>';
        }
    }

    /**
    * error die
    */
    function error($function,$errormsg) {
        die('Error in file <b>'.__FILE__.'</b> ,Function <b>'.$function.'()</b> :'.$errormsg);
    }

}