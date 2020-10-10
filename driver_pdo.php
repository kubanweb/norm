<?php
namespace Norm;

use Norm\Query;

class Driver_pdo extends Driver{
	private $pdo;
	private $db;
	private $driver;
	
	function __construct($config){
		
		$this->driver = strtolower($config['driver']);
		$host = $config['host'];
		$user = $config['user'];
		$password = $config['password'];
		$this->db = $config['database'];
		
		try {
			$this->pdo = new \PDO("{$this->driver}:host={$host};dbname={$this->db};charset=UTF8", $user, $password);
			$this->pdo->exec("set names utf8");
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	
	function conditionBuilder($key,$val){
		$field = trim($key);
		$operand = "=";
		$value = $val;
		$comma = "AND";
		$israw = false;
		
		if (is_null($val)){
			$operand = " IS NULL";	
			
			if (strpos($field," ")>0){
				$fld = explode(" ",$field);
				
				if (in_array(strtoupper($fld[0]),['AND','OR'])){
					$comma = strtoupper($fld[0]);
					array_shift($fld);
				}
				
				$field = $fld[0];
				
			}else{
				$operand = " IS NULL";	
				
			}
			
			
		}else{
			if (strpos($field," ")>0){
				$fld = explode(" ",$field);
				
				if (in_array(strtoupper($fld[0]),['AND','OR'])){
					$comma = strtoupper($fld[0]);
					array_shift($fld);
				}
				
				
				if (count($fld)==2){
					$field = $fld[0];
					
					if (in_array($fld[1],['<','>','<=','>=','<>','=','!='])){
						$operand=$fld[1];
					}else{
						$op = strtoupper($fld[1]);
						if($op=="IN"){
							$operand=" IN ";
							if (is_array($value)){
								$value = "('".implode("','",$value)."')";
							}else{
								$value = "(".$value.")";
							}
						}elseif($op=="NOTIN" || $op=="NOT_IN"){
							$operand=" NOT IN ";
							if (is_array($value)){
								$value = "('".implode("','",$value)."')";
							}else{
								$value = "(".$value.")";
							}
						}elseif($op=="LIKE" || $op=="%LIKE%"){
							$operand=" LIKE ";
							$value = "%".$value."%";
						}elseif($op=="%LIKE"){
							$operand=" LIKE ";
							$value = "%".$value;
						}elseif($op=="LIKE%"){
							$operand=" LIKE ";
							$value = $value."%";
						}elseif($op=="NOTLIKE" || $op=="%NOTLIKE%" || $op=="%NOT_LIKE%" ){
							$operand=" NOT LIKE ";
							$value = "%".$value."%";
						}elseif($op=="%NOTLIKE" || $op=="%NOT_LIKE"){
							$operand=" NOT LIKE ";
							$value = "%".$value;
						}elseif($op=="NOTLIKE%" || $op=="NOT_LIKE%"){
							$operand=" NOT LIKE ";
							$value = $value."%";
						}
						
					}	
				}
			}
		}
		
		
		
		return (object)[
			'field'=>$field,
			'operand'=>$operand,
			'value'=>$value,
			'comma'=>$comma,
			'israw'=>$israw,
		];
	}
	
	function filterBuilder($query, array $opts=[]){
		
		$leftjoins = $opts['_LEFTJOIN']??[];
		$table = $opts['_TABLE'];
		
		$useJoins=false;
		if (count($leftjoins)>0){
			$useJoins=true;
		}
		
		$where="";
		$vals=[];
		
		if(is_numeric($query)){
			$query = [ 'id'=>$query	];
		}elseif (!is_array($query) && Utils::isUUID($query)){
			$query = [ 'uid'=>$query ];
		}	
			
		if (is_array($query) && count($query)>0){
			$where.= "WHERE ";
			$cm="";
			$idx=0;
			$isfirst=true;
			foreach($query as $key=>$val){
				$condition = $this->conditionBuilder($key,$val);
				$operand 	= $condition->operand;
				$value  = $condition->value;
				$field  = $condition->field;
				$israw  = $condition->israw;
				//$comma 	= $condition->comma;
				
				if ($isfirst){
					$cm="";
				}else{
					$cm=" ".$condition->comma." ";
				}
				
				
				if ($useJoins){
					if (strpos($field,".")>0){
						list($tab,$field) = explode(".",$field);
						
						if (strpos($tab,".")){
							$tab = explode(".",$tab)[0];
						}
						
						$jtab = $leftjoins[$tab];
						if (strpos($jtab,".")){
							$jtab = explode(".",$leftjoins[$tab])[0];
						}
						
						
						$tabAlias = $tab."|".$jtab;						
					}else{
						$tab = $table;
						$tabAlias = $table;
						$field = $field;
					}
					
					$placeholder = $tab."_".$field;
					
					if ($israw){
						$suff="";
						if (isset($vals[$placeholder])){
							$idx++;
							$suff = $idx;
						}
						$vals[$placeholder.$suff] = $value;

						$where.=$cm."`{$tabAlias}`.`{$field}`{$operand}:{$placeholder}{$suff}";
					}else{
						$where.=$cm."`{$tabAlias}`.`{$field}`{$operand} ".$value;
					}
				}else{
					if ($israw){
						$suff="";
						if (isset($vals[$field])){
							$idx++;
							$suff = $idx;
						}
						$vals[$field] = $value;
						$where.=$cm."`{$field}`{$operand}:{$field}{$suff}";
					}else{
						$where.=$cm."`{$field}`{$operand} ".$value;
					}
				}
				
				$isfirst=false;
			}
		}else{
			//object???
			$where = "";
		}
		
		

		if (count($opts)>0){
			$options=[];
			foreach($opts as $k=>$v){
				$options[trim(strtolower($k))] = $v;
			}
			
			if (isset($options['groupby'])){
				$where.= " GROUP BY ".$options['groupby'];
			}
			
			if (isset($options['orderby'])){
				$where.= " ORDER BY ";
				if (is_array($options['orderby'])){
					$cm="";
					foreach($options['orderby'] as $k=>$v){
						$where.= $cm."`".$k."` ".strtoupper($v);
						$cm=",";
					}
				}else{
					$where.= $options['orderby'];
				}
				
			}
			
			if (isset($options['limit'])){
				$where.= " LIMIT ".(int)$options['limit'];
				
				if (isset($options['offset'])){
					$where.= " OFFSET ".(int)$options['offset'];
				}
			}
		}
		
		
		return (object)[
			'where' =>$where,
			'vals'=>$vals,
		];
	}
	
	
	function add($table, $query){
		if (is_object($query)){
			$query = (object)$query;
		}
		
		$tbl = "`{$this->db}`.`{$table}`";
		$fields=[];
		$placeholders=[];	
		$values=[];	
		
		foreach($query as $field=>$val)
		{
			$fields[] = $field;
			$placeholders[] = ":".$field;
			$values[$field] = $val;
		}
		
		$sql = "INSERT INTO {$tbl} (".implode(",",$fields).") VALUES(".implode(",",$placeholders).")";
		$sth = $this->pdo->prepare($sql);
		$sth->execute($values);

		return (int)$this->pdo->lastInsertId();
	}
	
	
	function get($table, $query){
		$res = $this->find($table,$query,['limit'=>1]);
		return $res->current();
	}
	
	
	function find($table, $query=[], array $opts=[]){
		$tbl = "`{$this->db}`.`{$table}`";
		$opts['_TABLE'] = $table;
		
		$columns = $opts['_COLUMNS']??"*";
		unset($opts['_COLUMNS']);
		
		$q = $this->filterBuilder($query,$opts);
		
		$leftjoins = $opts['_LEFTJOIN']??[];
		unset($opts['_LEFTJOIN']);

		if (count($leftjoins)==0){
			$useJoins=false;
			$sql = "SELECT {$columns} FROM {$tbl} ".$q->where;
		}else{
			$useJoins=true;
			$jsql="";
			$cols="`{$table}`.*";
			foreach($leftjoins as $k=>$v){
				$tf = explode(".",$v);
				$jtable = $tf[0];
				$jfield = $tf[1]??"id";
				
				//$jtables[]=$jtable;
				$cols.=", `{$jtable}`.*";
				$jsql.= " \nLEFT JOIN `{$jtable}` as `{$k}|{$jtable}` ON `{$table}`.`{$k}`=`{$k}|{$jtable}`.`{$jfield}`";
			}
			$sql = "SELECT * FROM `{$table}`{$jsql} ".$q->where;
		}
		
		$meta=[];
		
		//TODO: OPTION BUFFERED	
		//$sth = $this->pdo->prepare($sql,[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]); //array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false)
		//$sth = $this->pdo->prepare($sql,[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]); //array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true)
		
		$sth = $this->pdo->prepare($sql);
		$res = $sth->execute($q->vals);
		
		$colCount = $sth->columnCount();
		for($i=0; $i<$colCount; $i++){
		  $meta[] = $sth->getColumnMeta($i);
		}
		
		if ($res!=false){
			if ($useJoins){
				$sth->setFetchMode(\PDO::FETCH_NUM);
			}else{
				$sth->setFetchMode(\PDO::FETCH_ASSOC);
			}
			
			while ( $row = $sth->fetch() ){
				if ($useJoins){
					$nrow=[];
					$sub="";
					for($i=0; $i<$colCount; $i++){
						if ($meta[$i]['table']==$table){
							if (isset($leftjoins[$meta[$i]['name']]) && !isset($nrow[$meta[$i]['name']])){
								$nrow[$meta[$i]['name']] = [];
							}else{
								$nrow[$meta[$i]['name']] = $row[$i];
							}
						}else{
							if (strpos($meta[$i]['table'],"|")){
								list($fsub,$ftab) = explode("|",$meta[$i]['table']);
								$fname = $ftab.".".$meta[$i]['name'];
								$nrow[$fsub][$fname] = $row[$i];
							}
						}
					}
					yield $nrow;
				}else{
					yield $row;
				}
				
			}
		}
	}
	
	
	function edit($table, $query, $data){
		$tbl = "`{$this->db}`.`{$table}`";
		$q = $this->filterBuilder($query);
		$values=$q->vals;	
	
		$sql = "UPDATE {$tbl} SET ";
		$cm="";
		foreach($data as $key=>$val){
			$sql.= $cm."`{$key}`=:_".$key;
			$cm=",";
			$values["_".$key] = $val;
		}
		
		$sql.= " ".$q->where;
		$sth = $this->pdo->prepare($sql);
		$sth->execute($values);
		return $sth->rowCount();
	}
	
	
	function kill($table, $query){
		$tbl = "`{$this->db}`.`{$table}`";
		$q = $this->filterBuilder($query);
		$sth = $this->pdo->prepare("DELETE FROM {$tbl} ".$q->where);
		$sth->execute($q->vals);
		return $sth->rowCount(); 
	}
	
	
	function count($table, $query=[]){
		$tbl = "`{$this->db}`.`{$table}`";
		$q = $this->filterBuilder($query,$opts);

		$sql = "SELECT COUNT(*) as `cnt` FROM {$tbl} ".$q->where;
		
		$sth = $this->pdo->prepare($sql);
		$res = $sth->execute($q->vals);
		$sth->setFetchMode(\PDO::FETCH_ASSOC);
		$row = $sth->fetch();
		return $row['cnt'];
	}
	
	
	function list($table,$parent="",$opts=[]){
		
		if (isset($opts['hasParentField'])){
			$hasParentField = (bool)$opts['hasParentField'];
		}else{
			$hasParentField=false;
			$items = $this->_query("SHOW COLUMNS FROM `{$table}`");
			foreach($items as $item){
				if ($item['Field']=="_parent"){
					$hasParentField=true;
					break;
				}
			}
		}
		
		if ($parent==""){
			if ($hasParentField){
				return $this->find($table,[
					'_parent'=>0,
					'OR _parent'=>NULL,
					
				],$opts);
			}else{
				return $this->find($table,[],$opts);
			}
		}else{
			if ($hasParentField){
				return $this->find($table,[
					'_parent'=>$parent
				],$opts);
			}else{
				return null;
			}
		}
	}
	
	
	function index(){
		if ($this->driver=="mysql" || $this->driver=="mariadb"){
			$sql = "SELECT TABLE_NAME AS `name`,TABLE_TYPE AS `type`,TABLE_COMMENT AS `comment` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`='{$this->db}'";
		}else{
			$sql = "SHOW TABLES";
		}
		
		$sth = $this->pdo->prepare($sql);
		$res = $sth->execute($q->vals);
		$sth->setFetchMode(\PDO::FETCH_ASSOC);
		
		$arr=[];
		while ( $row = $sth->fetch() ){
			$row['options'] = [];
			$arr[$row['name']] = $row;
		}
		
		return $arr;
	}
	
	function _exec($command,array $data=[]){
		$sth = $this->pdo->prepare($command);
		$res = $sth->execute($data);
		
		$cmd = strtoupper(trim(explode(" ",ltrim($command),2)[0]));
		if($cmd=="INSERT"){
			return (int)$this->pdo->lastInsertId();
		}elseif($cmd=="UPDATE" || $cmd=="DELETE" ){
			return $sth->rowCount(); 
		}else{
			return;
		}
	}
	
	function _query($query,array $data=[]){
		$cmd = strtoupper(trim(explode(" ",ltrim($query),2)[0]));
		if ($cmd=="SELECT" || $cmd=="SHOW"){
			$sth = $this->pdo->prepare($query);
			$res = $sth->execute($data);
			if ($res!=false){
				$sth->setFetchMode(\PDO::FETCH_ASSOC);
				while ( $row = $sth->fetch() ){
					foreach($row as $key=>$val)	{
						if (substr($key,-1,1)==".")	{
							$row[$key] = (array)json_decode($val);
						}
					}
					yield $row;
				}
			}
		}else{
			return;
		}
	}
	
}

?>