<?php
/**
* @name      ZF_Libs_String.php
* @describe  string class
* @author    qinqf
* @version   1.0 
* @todo       
* @changelog  
* $sub_str = ZF_Libs_String::perfectStr($str,12);
*/

class ZF_Libs_String
{
    /**
    * sub str ?
    *
    * @param $sentence
    * @param $length
    * @return unknown
    */
    public static function perfectStr1($sentence,$length=12) {
        if(empty($sentence)||!is_numeric($length)){
            return false;
        }
        if(strlen($sentence)<=$length){
            return $sentence;
        }
        $last_word_needed=substr($sentence,$length-1,1);
        if(!ord($last_word_needed)>128){
            $needed_sub_sentence=substr($sentence,0,$length);
            return $needed_sub_sentence;
        } else {
            for($i=0;$i<$length;$i++){ 
                if(ord($sentence[$i])>128){ 
                    $i=$i+2; 
                } 
            }//end of for 
            $needed_sub_sentence=substr($sentence,0,$i);
            return $needed_sub_sentence;
        }
    }

    public static function perfectStr($string, $length, $dot = '', $charset = 'UTF-8') {
        if(strlen($string) <= $length) {
            return $string;
        }
        $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);
        $strcut = '';
        if(strtolower($charset) == 'utf-8') {
            $n = $tn = $noc = 0;
            while($n < strlen($string)) {

                $t = ord($string[$n]);
                if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                    $tn = 1; $n++; $noc++;
                } elseif(194 <= $t && $t <= 223) {
                    $tn = 2; $n += 2; $noc += 2;
                } elseif(224 <= $t && $t <= 239) {
                    $tn = 3; $n += 3; $noc += 2;
                } elseif(240 <= $t && $t <= 247) {
                    $tn = 4; $n += 4; $noc += 2;
                } elseif(248 <= $t && $t <= 251) {
                    $tn = 5; $n += 5; $noc += 2;
                } elseif($t == 252 || $t == 253) {
                    $tn = 6; $n += 6; $noc += 2;
                } else {
                    $n++;
                }
                if($noc >= $length) {
                    break;
                }
            }
            if($noc > $length) {
                $n -= $tn;
            }
            $strcut = substr($string, 0, $n);
        } else {
            for($i = 0; $i < $length; $i++) {
                $strcut .= ord($string[$i]) > 127 ? $string[$i].$string[++$i] : $string[$i];
            }
        }

        $strcut = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);

        return $strcut.$dot;
    }

    //取得IP
    public static function getClientIp(){
        if(getenv('HTTP_CLIENT_IP')&&strcasecmp(getenv('HTTP_CLIENT_IP'),'unknown')) 
        {
            $ip=getenv('HTTP_CLIENT_IP');
        } 
        elseif(getenv('HTTP_X_FORWARDED_FOR')&&strcasecmp(getenv('HTTP_X_FORWARDED_FOR'),'unknown'))
        {
            $ip=getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif(getenv('REMOTE_ADDR')&&strcasecmp(getenv('REMOTE_ADDR'),'unknown'))
        {
            $ip=getenv('REMOTE_ADDR');
        }
        elseif(isset($_SERVER['REMOTE_ADDR'])&&$_SERVER['REMOTE_ADDR']&&strcasecmp($_SERVER['REMOTE_ADDR'],'unknown'))
        {
            $ip=$_SERVER['REMOTE_ADDR'];
        }
        $ip = preg_replace("/^([\d\.]+).*/","\\1",$ip);
        return $ip;
    }

    /**
    * rand 
    *
    * @param Int:$length
    * @return Strine
    */
    public static function rands($length, $type=1) {
        $hash = '';
        switch ($type) {
            case 2:	//num
                $chars = '0123456789';
                break;
            case 3: //letter
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
            case 4: //letter uppercase 
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 5: //letter small 
                $chars = 'abcdefghijklmnopqrstuvwxyz';
                break;
            case 1: //all
            default:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
                break;
        }
        $max = strlen($chars) - 1;
        mt_srand((double)microtime() * 1000000);
        for($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
        return $hash;
    }

    /**
    * GBK to UTF8
    */
    public static function gb2Utf8($str){
        if (function_exists('iconv')){
            return iconv("GBK", "UTF-8", $str);
        }elseif(function_exists('mb_convert_encoding')){
            return mb_convert_encoding($str, 'UTF-8', 'GBK');
        }
        return $str;
    }

    /**
    * UTF8 to GBK 
    */
    public static function utf2GB($str){
        /*
        if (function_exists('iconv')){
        return iconv("UTF-8", "GBK", $str);
        }elseif(function_exists('mb_convert_encoding')){
        return mb_convert_encoding($str, 'GBK', 'UTF-8');
        }
        */
        #由于品牌名多存在特殊字符，暂时只用mb_convert_encoding进行处理
        if(function_exists('mb_convert_encoding')){
            return mb_convert_encoding($str, 'GBK', 'UTF-8');
        }
        return $str;	
    }

    // 计算中文字符串长度
    public static function utf8strlen($string = null) {
        // 将字符串分解为单元
        preg_match_all("/./us", $string, $match);
        // 返回单元个数
        return count($match[0]);
    }

    function closetags($html) {
        // 不需要补全的标签
        $arr_single_tags = array('meta', 'img', 'br', 'link', 'area');
        // 匹配开始标签
        preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];
        // 匹配关闭标签
        preg_match_all('#</([a-z]+)>#iU', $html, $result);
        $closedtags = $result[1];
        // 计算关闭开启标签数量，如果相同就返回html数据
        $len_opened = count($openedtags);
        if (count($closedtags) == $len_opened) {
            return $html;
        }
        // 把排序数组，将最后一个开启的标签放在最前面
        $openedtags = array_reverse($openedtags);
        // 遍历开启标签数组
        for ($i = 0; $i < $len_opened; $i++) {
            // 如果需要补全的标签
            if (!in_array($openedtags[$i], $arr_single_tags)) {
                // 如果这个标签不在关闭的标签中
                if (!in_array($openedtags[$i], $closedtags)) {
                    // 直接补全闭合标签
                    $html .= '</' . $openedtags[$i] . '>';
                } else {
                    unset($closedtags[array_search($openedtags[$i], $closedtags)]);
                }
            }
        }
        return $html;
    }

    public static function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
        $ckey_length = 4;
        $key = md5($key ? $key : AUTH_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }

    }
}