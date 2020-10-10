<?php
namespace Norm;

class Table{
	private $tableName="";
	private $driver=null;
	private $cols=[];
	private $leftJoin=[];
	
	function __construct(&$driver,$tableName,array $args=[]){
		$this->tableName = $tableName;
		$this->driver = $driver;
		
		if (isset($args[0])){
			$this->cols = $args[0];
		}
		
		if (isset($args[1]) && is_array($args[1])){
			$this->leftJoin = $args[1];
		}
	}
	
	function add($query){
		return $this->driver->add($this->tableName,$query);
	}
	
	function get($query){
		return $this->driver->get($this->tableName,$query);
	}
	
	function find($query=[],$opts=[]){
		if (is_array($this->cols) && count($this->cols)>0){
			$opts['_COLUMNS'] = implode(",",$this->cols);
		}else{
			$opts['_COLUMNS'] = "*";
		}
		
		if (count($this->leftJoin)>0){
			$opts['_LEFTJOIN'] = $this->leftJoin;
		}
		
		return $this->driver->find($this->tableName,$query,$opts);
	}
	
	function edit($query,$data){
		return $this->driver->edit($this->tableName,$query,$data);
	}
	
	function kill($query){
		return $this->driver->kill($this->tableName,$query);
	}
	
	function count($query=[]){
		return $this->driver->count($this->tableName,$query);
	}
	
	function list($parent="",$opts=[]){
		return $this->driver->list($this->tableName,$parent,$opts);
	}

	function index(){
		return $this->driver->index($this->tableName,$parent,$opts);
	}
	
}

?>