<?php
namespace Norm;

class Utils{
	
	static function strToMap($str){
		$ret = [];

		$arr = explode(";",$str);
		foreach($arr as $item){
			$pair = explode("=",$item,2);
			$ret[strtolower(trim($pair[0]))] = trim($pair[1]??"");
		}
		
		return $ret;
	}
	
	
	
	static function isUUID($val,$allowEmpty=false){
		if ($allowEmpty && $val==""){
			return true;
		}else{
			if (strlen($val)==36){
				$h = explode("-",$val);
				if (count($h)==5 && ctype_xdigit($h[0]) && ctype_xdigit($h[1]) && ctype_xdigit($h[2]) && ctype_xdigit($h[3]) && ctype_xdigit($h[4])){
					return true;
				}else{
					return false;
				}
			}else{
				return false;
			}
		}
	}
	
}


?>