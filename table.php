<?php
namespace Norm;

class Table{
	private $tableName="";
	private $driver=null;
	private $cols="*";
	
	function __construct(&$driver,$tableName,$cols="*"){
		$this->tableName = $tableName;
		$this->driver = $driver;
		$this->cols = $cols;
	}
	
	function add($query){
		return $this->driver->add($this->tableName,$query);
	}
	
	function get($query){
		return $this->driver->get($this->tableName,$query);
	}
	
	function find($query,$opts=[]){
		if (is_array($this->cols) && count($this->cols)>0){
			$opts['_COLUMNS'] = implode(",",$this->cols);
		}else{
			$opts['_COLUMNS'] = $this->cols;
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

	
}

?>