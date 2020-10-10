<?php
namespace Norm;

use Norm\Table;

class Provider{
	private $config;
	private $driver=null;
	
	function __construct($config){
		$this->config = $config;
		$drv = $config['driver'];
		
		if (in_array($drv,['mysql','maria','mssql','postgres','sqlite'])){
			$this->driver = new Driver_pdo($config);
		}elseif (in_array($drv,['mongo','mongodb'])){
			$this->driver = new Driver_mongo($config);
		}else{
			die("Unsupported driver:".$drv);
		}
	}
	
	function __get($tableName){
		return new Table($this->driver,$tableName,[]);
	}
	
	
	function __call($tableName,$args){
		return new Table($this->driver,$tableName,$args[0]);
	}
	
	function _exec($command,array $data=[]){
		return $this->driver->_exec($command,$data);
	}
	
	function _query($query,array $data=[]){
		return $this->driver->_query($query,$data);
	}
	
	
	function index(){
		return $this->driver->index();
	}
	
	
	
	
}


?>