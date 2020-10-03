<?php
namespace Norm;

use Norm\Query;

class Driver_pdo{
	private $pdo;
	private $db;
	
	function __construct($config){
		
		$driver = $config['driver'];
		$host = $config['host'];
		$user = $config['user'];
		$password = $config['password'];
		$this->db = $config['database'];
		
		try {
			$this->pdo = new \PDO("{$driver}:host={$host};dbname={$this->db};charset=UTF8", $user, $password);
			$this->pdo->exec("set names utf8");
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	
	
	
	function filterBuilder($query, array $opts=[]){
		$where="";
		$vals=[];

		if(is_numeric($query)){
			$where.= "WHERE `id`=:id";
			$vals['id'] = $query;
		}elseif (is_array($query)){
			if (count($query)>0){
				$where.= "WHERE ";
				$cm="";
				foreach($query as $key=>$val){
					$logic = "=";
					$lk = substr($key,-1,1);
					if ($lk=="%"){
						$logic = " LIKE ";
						$val = "%{$val}%";
						$key = substr($key,0,-1);
					}

					$where.=$cm."`{$key}`{$logic}:{$key}";
					$cm=" AND ";
					$vals[$key] = $val;
				}
			}
		}elseif (Utils::isUUID($query)){
			$where = "WHERE `uid`=:uid";
			$vals['uid'] = $query;
		}else{
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
	
	
	function find($table, $query, array $opts=[]){
		$tbl = "`{$this->db}`.`{$table}`";
		
		$columns = $opts['_COLUMNS']??"*";
		unset($opts['_COLUMNS']);
		
		$q = $this->filterBuilder($query,$opts);

		$sql = "SELECT {$columns} FROM {$tbl} ".$q->where;
			
		print $sql."<HR>";
		
		$sth = $this->pdo->prepare($sql);
		$res = $sth->execute($q->vals);
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