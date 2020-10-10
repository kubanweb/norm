<?php
namespace Norm;

use Norm\Utils;
use Norm\Table;
use Norm\Provider;

class Norm{
	private static $instance=null;
	private $dbconfig=[];
	private $providers=[];
	private $defaultProvider="";

	function __construct($dbConfig){
		if (is_null(self::$instance)){
			self::$instance = $this;
		}
		
		$this->decodeConfig($dbConfig);
	}
	
	
	static function init($dbConfig){
		if (is_null(self::$instance)){
			self::$instance = new Norm($dbConfig);
		}
		
		return self::$instance;
	}
	
	private function decodeConfig($dbConfig){
		if (!is_array($dbConfig)){
			$dbConfig = [$dbConfig];
		}

		$names=[];
		
		for($i=0; $i<count($dbConfig); $i++){
			$cfg = Utils::strToMap($dbConfig[$i]);
			if (isset($cfg['driver'])){
				$name = strtolower(trim($cfg['name']??""));
				if ($name==""){
					$name = strtolower(trim($cfg['database']??""));
					if (isset($this->dbconfig[$name])){
						$name .= "_".$cfg['driver'];
					}
				}
				
				$this->dbconfig[$name] = $cfg;
			}
		}
		
		$this->defaultProvider = array_key_first($this->dbconfig);
	}
	
	
	function __get($arg){ // $nrm->sometable (with default database)
		return $this->db($this->defaultProvider)->$arg;
	}
	
	function __invoke($arg){ // $nrm(somedb)->sometable (selected database)
		return $this->db($arg);
	}
	
	function __call($tableName,$args){ // $nrm->users("id","name","COUNT(*)");
		if (count($args)==0){
			$args[0]="*";
		}
		return $this->db($this->defaultProvider)->$tableName($args);
	}
	
	function db($dbName){
		if (!isset($this->providers[$dbName])){
			if (isset($this->dbconfig[$dbName])){
				$this->providers[$dbName] = new Provider($this->dbconfig[$dbName]);
			}else{
				die("NO SUCH CONNECTION:".$dbName);
			}
		}
		
		return $this->providers[$dbName];
	}
	
	function index($dbName=""){
		if ($dbName==""){
			$dbName = $this->defaultProvider;
		}
		
		return $this->db($dbName)->index();
	}
	
	function __toString(){
		return implode(",",array_keys($this->dbconfig));
	}
	
	
	
	
}

?>