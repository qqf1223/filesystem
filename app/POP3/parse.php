<?
class mime_decode {
    var $content     = Array();
    function mime_encode_headers($string) {
        if($string == "") return;
        if(!eregi("^([[:print:]]*)$",$string))
            $string = "=?ISO-8859-1?Q?".str_replace("+","_",str_replace("%","=",urlencode($string)))."?=";
        return $string;
    }

    function decode_mime_string($string) {
        if(($pos = strpos($string,"=?")) === false) return $string;
        while(!($pos === false)) {
            $newresult .= substr($string,0,$pos);
            $string = substr($string,$pos+2,strlen($string));
            $intpos = strpos($string,"?");
            $charset = substr($string,0,$intpos);
            $enctype = strtolower(substr($string,$intpos+1,1));
            $string = substr($string,$intpos+3,strlen($string));
            $endpos = strpos($string,"?=");
            $mystring = substr($string,0,$endpos);
            $string = substr($string,$endpos+2,strlen($string));
            if($enctype == "q") {
                $mystring = str_replace("_"," ",$mystring);
                $mystring = $this->decode_qp($mystring);
            } else if ($enctype == "b")
                $mystring = base64_decode($mystring);
            $newresult .= $mystring;
            $pos = strpos($string,"=?");
        }
        return $newresult.$string;
    }

    function decode_header($header) {
        $headers = explode("\r\n",$header);
        $decodedheaders = Array();
        for($i=0;$i<count($headers);$i++) {
            $thisheader = $headers[$i];
            if(strpos($thisheader,": ") === false) {
                $decodedheaders[$lasthead] .= " $thisheader";
            } else {
                $dbpoint = strpos($thisheader,": ");
                $headname = strtolower(substr($thisheader,0,$dbpoint));
                $headvalue = trim(substr($thisheader,$dbpoint+1));
                if($decodedheaders[$headname] != "") $decodedheaders[$headname] .= "; $headvalue";
                else $decodedheaders[$headname] = $headvalue;
                $lasthead = $headname;
            }
        }
        return $decodedheaders;
    }


    function fetch_structure($email) {
        $ARemail = Array();
        $separador = "\r\n\r\n";
        $header = trim(substr($email,0,strpos($email,$separador)));
        $bodypos = strlen($header)+strlen($separador);
        $body = substr($email,$bodypos,strlen($email)-$bodypos);
        $ARemail["header"] = $header; $ARemail["body"] = $body;
        return $ARemail;
    }

    function get_names($strmail) {
        $ARfrom = Array();
        $strmail = stripslashes(ereg_replace("\t","",ereg_replace("\n","",ereg_replace("\r","",$strmail))));
        if(trim($strmail) == "") return $ARfrom;

        $armail = Array();
        $counter = 0;  $inthechar = 0;
        $chartosplit = ",;"; $protectchar = "\""; $temp = "";
        $lt = "<"; $gt = ">";
        $closed = 1;

        for($i=0;$i<strlen($strmail);$i++) {
            $thischar = $strmail[$i];
            if($thischar == $lt && $closed) $closed = 0;
            if($thischar == $gt && !$closed) $closed = 1;
            if($thischar == $protectchar) $inthechar = ($inthechar)?0:1;
            if(!(strpos($chartosplit,$thischar) === false) && !$inthechar && $closed) {
                $armail[] = $temp; $temp = "";
            } else
                $temp .= $thischar;
        }

        if(trim($temp) != "")
            $armail[] = trim($temp);

        for($i=0;$i<count($armail);$i++) {
            $thisPart = trim(eregi_replace("^\"(.*)\"$", "\\1", trim($armail[$i])));
            if($thisPart != "") {
                if (eregi("(.*)<(.*)>", $thisPart, $regs)) {
                    $email = trim($regs[2]);
                    $name = trim($regs[1]);
                } else {
                    if (eregi("([-a-z0-9_$+.]+@[-a-z0-9_.]+[-a-z0-9_]+)((.*))", $thisPart, $regs)) {
                        $email = $regs[1];
                        $name = $regs[2];
                    } else
                        $email = $thisPart;
                }
                $email = eregi_replace("^\<(.*)\>$", "\\1", $email);
                $name = eregi_replace("^\"(.*)\"$", "\\1", trim($name));
                $name = eregi_replace("^\((.*)\)$", "\\1", $name);
                if ($name == "") $name = $email;
                if ($email == "") $email = $name;
                $ARfrom[$i]["name"] = $this->decode_mime_string($name);
                $ARfrom[$i]["mail"] = $email;
                unset($name);unset($email);
            }
        }
        return $ARfrom;
    }

    function build_alternative_body($ctype,$body) {
        global $mime_show_html;
        $boundary = $this->get_boundary($ctype);
        $part = $this->split_parts($boundary,$body);
        $thispart = ($mime_show_html)?$part[1]:$part[0];
        $email = $this->fetch_structure($thispart);
        $header = $email["header"];
        $body = $email["body"];
        $headers = $this->decode_header($header);
        $body = $this->compile_body($body,$headers["content-transfer-encoding"]);
        return $body;
    }

    function build_related_body($ctype,$body) {
        global $mime_show_html,$sid,$lid,$ix,$folder;
        $Rtype = trim(substr($ctype,strpos($ctype,"type=")+5,strlen($ctype)));

        if(strpos($Rtype,";") != 0)
            $Rtype = substr($Rtype,0,strpos($Rtype,";"));
        if(substr($Rtype,0,1) == "\"" && substr($Rtype,-1) == "\"")
            $Rtype = substr($Rtype,1,strlen($Rtype)-2);

        $boundary = $this->get_boundary($ctype);
        $part = $this->split_parts($boundary,$body);

        for($i=0;$i<count($part);$i++) {
            $email = $this->fetch_structure($part[$i]);
            $header = $email["header"];
            $body = $email["body"];
            $headers = $this->decode_header($header);
            $ctype = $headers["content-type"];
            $cid = $headers["content-id"];
            $Actype = split(";",$headers["content-type"]);
            $types = split("/",$Actype[0]); $rctype = strtolower($Actype[0]);
            if($rctype == "multipart/alternative")
                $msgbody = $this->build_alternative_body($ctype,$body);
            elseif($rctype == "text/plain" && strpos($headers["content-disposition"],"name") === false) {
                $body = $this->compile_body($body,$headers["content-transfer-encoding"]);
                $msgbody = $this->build_text_body($body);
            } elseif($rctype == "text/html" && strpos($headers["content-disposition"],"name") === false) {
                $body = $this->compile_body($body,$headers["content-transfer-encoding"]);
                if(!$mime_show_html) $body = $this->build_text_body(strip_tags($body));
                $msgbody = $body;
            } else {
                $thisattach = $this->build_attach($header,$body,$boundary,$i);
                if($cid != "") {
                    if(substr($cid,0,1) == "<" && substr($cid,-1) == ">")
                        $cid = substr($cid,1,strlen($cid)-2);
                    $cid = "cid:$cid";
                    $thisfile = "download.php?sid=$sid&lid=$lid&folder=".urlencode($folder)."&ix=".$ix."&bound=".base64_encode($thisattach["boundary"])."&part=".$thisattach["part"]."&filename=".urlencode($thisattach["name"]);
                    $msgbody = str_replace($cid,$thisfile,$msgbody);
                }
            }
        }
        return $msgbody;
    }

    function linesize($message="", $length=70) {
        $line = explode("\r\n",$message);
        unset($message);
        for ($i=0 ;$i < count($line); $i++) {
            $line_part = explode(" ",trim($line[$i]));
            unset($buf);
            for ($e = 0; $e<count($line_part); $e++) {
                $buf_o = $buf;
                if ($e == 0) $buf .= $line_part[$e];
                else $buf .= " ".$line_part[$e];
                if (strlen($buf) > $length and $buf_o != "") {
                    $message .= "$buf_o\r\n";
                    $buf = $line_part[$e];
                }
            }
            $message .= "$buf\r\n";
        }
        return($message);
    }
function build_text_body($body) {
        return "\n<pre>".$this->make_link_clickable($this->linesize(htmlspecialchars($body),85))."</pre>\n";
    }

    function decode_qp($text) {
        $text = quoted_printable_decode($text);
        /*
        $text = str_replace("\r","",$text);
        $text = ereg_replace("=\n", "", $text);
        $text = str_replace("\n","\r\n",$text);
        */
        $text = ereg_replace("=\r", "\r", $text);
        return $text;
    }

    function make_link_clickable($text){
        $text = eregi_replace("([[:alnum:]]+)://([^[:space:]]*)([[:alnum:]#?/&=])",
            "<a class=\"autolink\" href=\"\\1://\\2\\3\" target=\"_new\">\\1://\\2\\3</a>", $text);
        $text = eregi_replace("([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-z]{2,3})","<a class=\"autolink\"  href=\"newmsg.php?mailto=\\1&nameto=\\1\">\\1</a>", $text);
        return $text;
    }

    function process_message($header,$body) {
        global $mime_show_html;
        $mail_info = $this->get_mail_info($header);

        $ctype = $mail_info["content-type"];
        $ctenc = $mail_info["content-transfer-encoding"];

        if($ctype == "") $ctype = "text/plain";

        $type = $ctype;

        $ctype = split(";",$ctype);
        $types = split("/",$ctype[0]);

        $maintype = strtolower($types[0]);
        $subtype = strtolower($types[1]);

        switch($maintype) {
        case "text":
            $body = $this->compile_body($body,$ctenc);
            switch($subtype) {
            case "html":
                if(!$mime_show_html)
                    $body = $this->build_text_body(strip_tags($body));
                $msgbody = $body;
                break;
            default:
                $msgbody = $this->build_text_body($body);
                break;
            }
            break;
        case "multipart":
            switch($subtype) {
            case "mixed":
                $boundary = $this->get_boundary($type);
                $part = $this->split_parts($boundary,$body);

                for($i=0;$i<count($part);$i++) {
                    $thispart = trim($part[$i]);

                    if($thispart != "") {
                        $email = $this->fetch_structure($thispart);
    
                        $header = $email["header"];
                        $body = $email["body"];
                        $headers = $this->decode_header($header);
                        $ctype = $headers["content-type"];
    
                        $Actype = split(";",$headers["content-type"]);
                        $types = split("/",$Actype[0]); $rctype = strtolower($Actype[0]);
    
                        if($rctype == "multipart/alternative")
                            $msgbody = $this->build_alternative_body($ctype,$body);
                        elseif($rctype == "text/plain" && strpos($headers["content-disposition"],"name") === false) {
                            $msgbody = $this->build_text_body($this->compile_body($body,$headers["content-transfer-encoding"]));
                        } elseif($rctype == "text/html" && strpos($headers["content-disposition"],"name") === false) {
                            $body = $this->compile_body($body,$headers["content-transfer-encoding"]);
                            if(!$mime_show_html)
                                $body = $this->build_text_body(strip_tags($body));
                            $msgbody = $body;
                        } elseif($rctype == "multipart/related" && strpos($headers["content-disposition"],"name") === false) {
                            $msgbody = $this->build_related_body($ctype,$body);
                        } else {
                            $thisattach = $this->build_attach($header,$body,$boundary,$i);
                        }
                    }
                }
                break;
            case "alternative":
                $msgbody = $this->build_alternative_body($ctype[1],$body);
                break;
            case "related":
                $msgbody = $this->build_related_body($type,$body);
                break;
            default:
                $thisattach = $this->build_attach($header,$body,"",0);
            }
            break;
        default:
            $thisattach = $this->build_attach($header,$body,"",0);
        }
        return $msgbody;
    }

    function build_attach($header,$body,$boundary,$part) {
        global $mail,$temporary_directory,$userfolder;

        $headers = $this->decode_header($header);
        $cdisp = $headers["content-disposition"];
        $ctype = $headers["content-type"]; $ctype2 = explode(";",$ctype); $ctype2 = $ctype2[0];
        
        $Atype = split("/",$ctype);
        $Acdisp = split(";",$cdisp);

        $tenc = $headers["content-transfer-encoding"];

        if($temp) $dir_to_save = $userfolder; //"temporary_files/";

        if($Atype[0] == "message") {
            $divpos = strpos($body,"\n\r");
            $attachheader = substr($body,0,$divpos);
            $attachheaders = $this->decode_header($attachheader);
            $filename = $this->decode_mime_string($attachheaders["subject"]);
            if($filename == "")
                $filename = uniqid("");
            $filename = substr(ereg_replace("[^A-Za-z0-9]","_",$filename),0,20).".eml";
        } else {
            $fname = $Acdisp[1];
            $filename = substr($fname,strpos($fname,"filename=")+9,strlen($fname));
            if($filename == "")
                $filename = substr($ctype,strpos($ctype,"name=")+5,strlen($ctype));
            if(substr($filename,0,1) == "\"" && substr($filename,-1) == "\"")
                $filename = substr($filename,1,strlen($filename)-2);
            $filename = $this->decode_mime_string($filename);
        }


        if($Atype[0] != "message")
            $body = $this->compile_body($body,$tenc);

        $indice = count($this->content["attachments"]);
        $this->content["attachments"][$indice]["name"] = $filename;
        $this->content["attachments"][$indice]["size"] = strlen($body);
        $this->content["attachments"][$indice]["temp"] = $temp;
        $this->content["attachments"][$indice]["content-type"] = $ctype2; //$Atype[0];
        $this->content["attachments"][$indice]["content-disposition"] = $Acdisp[0];
        $this->content["attachments"][$indice]["boundary"] = $boundary;
        $this->content["attachments"][$indice]["part"] = $part;
        return $this->content["attachments"][$indice];
    }

    function compile_body($body,$enctype) {
        $enctype = explode(" ",$enctype); $enctype = $enctype[0];
        if(strtolower($enctype) == "base64")
            $body = base64_decode($body);
        elseif(strtolower($enctype) == "quoted-printable")
            $body = $this->decode_qp($body);
        return $body;

    }

    function download_attach($header,$body,$down=1) {
        $headers = $this->decode_header($header);

        $cdisp = $headers["content-disposition"];
        $ctype = $headers["content-type"];

        $type = split(";",$ctype); $type = $type[0];
        $Atype = split("/",$ctype);
        $Acdisp = split(";",$cdisp);
        $tenc = strtolower($headers["content-transfer-encoding"]);

        if($Atype[0] == "message") {
            $divpos = strpos($body,"\n\r");
            $attachheader = substr($body,0,$divpos);
            $attachheaders = $this->decode_header($attachheader);
            $filename = $this->decode_mime_string($attachheaders["subject"]);
            if($filename == "")
                $filename = uniqid("");
            $filename = substr(ereg_replace("[^A-Za-z0-9]","_",$filename),0,20);
            $filename .= ".eml";
        } else {
            $fname = $Acdisp[1];
            $filename = substr($fname,strpos(strtolower($fname),"filename=")+9,strlen($fname));
            if($filename == "")
                $filename = substr($ctype,strpos(strtolower($ctype),"name=")+5,strlen($ctype));
            if(substr($filename,0,1) == "\"" && substr($filename,-1) == "\"")
                $filename = substr($filename,1,strlen($filename)-2);
            $filename = $this->decode_mime_string($filename);
        }

        if($Atype[0] != "message")
            $body = $this->compile_body($body,$tenc);
		echo $filename;exit;
//        $content_type = ($down)?"application/octet-stream":strtolower($type);
//        $filesize = strlen($body);
//
//        header("Content-Type: $content_type; name=\"$filename\"\r\n"
//        ."Content-Length: $filesize\r\n");
//        $cdisp = ($down)?"attachment":"inline";
//        header("Content-Disposition: $cdisp; filename=\"$filename\"\r\n");
//        echo($body);
    }

    function get_mail_info($header) {
        $myarray = Array();
        $headers = $this->decode_header($header);

        /*
        echo("<pre>");
        print_r($headers);
        echo("</pre>");
        */

        $message_id = $headers["message-id"];

        if(substr($message_id,0,1) == "<" && substr($message_id,-1) == ">")
            $message_id = substr($message_id,1,strlen($message_id)-2);

        $myarray["content-type"] = $headers["content-type"];
        $myarray["content-transfer-encoding"] = str_replace("GM","-",$headers["content-transfer-encoding"]);
        $myarray["message-id"] = $message_id;

        $received = $headers["received"];

        if($received != "") {
            $received = explode(";",$received);
            $mydate = $received[1];
        } else
            $mydate = $headers["date"];

        $myarray["date"] = $this->build_mime_date($mydate);
        $myarray["subject"] = $this->decode_mime_string($headers["subject"]);
        $myarray["from"] = $this->get_names($headers["from"]);
        $myarray["to"] = $this->get_names($headers["to"]);
        $myarray["cc"] = $this->get_names($headers["cc"]);
        $myarray["status"] = $headers["status"];
        $myarray["read"] = ($headers["status"] == "N")?0:1;

        return $myarray;

    }

    function build_mime_date($mydate) {

        $mydate = explode(",",$mydate);
        $mydate = trim($mydate[count($mydate)-1]);
        $parts = explode(" ",$mydate);
        if(count($parts) < 4) { return time(); }
        $day = $parts[0];

        switch(strtolower($parts[1])) {
            case "jan": $mon = 1; break;
            case "feb":    $mon = 2; break;
            case "mar":    $mon = 3; break;
            case "apr":    $mon = 4; break;
            case "may":    $mon = 5; break;
            case "jun": $mon = 6; break;
            case "jul": $mon = 7; break;
            case "aug": $mon = 8; break;
            case "sep": $mon = 9; break;
            case "oct": $mon = 10; break;
            case "nov": $mon = 11; break;
            case "dec": $mon = 12; break;
        }
        
        $year = $parts[2];
        $ahours = explode(":",$parts[3]);
        $hour = $ahours[0]; $min = $ahours[1]; $sec = $ahours[2];

        return mktime ($hour, $min, $sec, $mon, $day, $year);

    }

    function initialize($email) {
        $email = $this->fetch_structure($email);
        $body = $email["body"];
        $header = $email["header"];
        $mail_info = $this->get_mail_info($header);

        $this->content["headers"] = $header;
        $this->content["date"] = $mail_info["date"];
        $this->content["subject"] = $mail_info["subject"];
        $this->content["message-id"] = $mail_info["message-id"];
        $this->content["from"] = $mail_info["from"];
        $this->content["to"] = $mail_info["to"];
        $this->content["cc"] = $mail_info["cc"];
        $this->content["body"] = $this->process_message($header,$body);
        $this->content["read"] = $mail_info["read"];
    }

    function split_parts($boundary,$body) {
        $startpos = strpos($body,"$boundary")+strlen("$boundary")+2;
        $lenbody = strpos($body,"\r\n$boundary--") - $startpos;
        $body = substr($body,$startpos,$lenbody);
        return split($boundary."\r\n",$body);
    }

    function get_boundary($ctype){
        $boundary = trim(substr($ctype,strpos(strtolower($ctype),"boundary=")+9,strlen($ctype)));
        $boundary = split(";",$boundary);$boundary = $boundary[0];

        if(substr($boundary,0,1) == "\"" && substr($boundary,-1) == "\"")
            $boundary = substr($boundary,1,strlen($boundary)-2);
        $boundary = "--".$boundary;
        return $boundary;
    }

    function set_as($email,$type=1) {
        $status = ($type)?"Y":"N";
        $tempmail = $this->fetch_structure($email);
        $thisheader = $tempmail["header"];
        $mail_info = $this->get_mail_info($thisheader);
        $decoded_headers = $this->decode_header($thisheader);

        while(list($key,$val) = each($decoded_headers))
            if (eregi("status",$key)) {
                $newmail .= ucfirst($key).": $status\r\n"; $headerok = 1;
            } else $newmail .= ucfirst($key).": ".trim($val)."\r\n";
        if(!$headerok) $newmail .= "Status: $status\r\n";
        $newmail = trim($newmail)."\r\n\r\n".trim($tempmail["body"]);
        return $newmail;
    }

}
?>