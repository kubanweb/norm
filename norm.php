<?php
namespace Norm;

use Norm\Utils;
use Norm\Table;
use Norm\Provider;

class Norm{
	private static $instance=null;
	private $dbconfig=[];
	private $providers=[];
	private $defaultProvider="main";

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
					$names[$cfg['driver']]++;
					if ($names[$cfg['driver']]==1){
						$name = $cfg['driver'];
					}else{
						$name = $cfg['driver'].$names[$cfg['driver']];
					}
				}
				$this->dbconfig[$name] = $cfg;
			}
		}
		
		if (!isset($this->dbconfig['main'])){
			$this->defaultProvider = array_key_first($this->dbconfig);
		}
	}
	
	
	function __get($arg){ // $nrm->sometable (with default database)
		return $this->db($this->defaultProvider)->$arg;
	}
	
	function __invoke($arg){ // $nrm(somedb)->sometable (selected database)
		return $this->db($arg);
	}
	
	function __call($tableName,$args){ // $nrm->users("id","name","COUNT(*)");
		if (count($args)==0){
			$args="*";
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
	
	
	
	
}

?>