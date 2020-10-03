<?php
namespace Norm;

use Norm\Query;

class Driver_mongo{
	private $mongoManager;
	private $db;
	
	function __construct($config){
		$driver = $config['driver'];
		$host = $config['host'];
		$user = $config['user'];
		$password = $config['password'];
		$this->database = strtolower($config['database']);
		

		if (strpos($host,':')===false)	{	$host.=":27017";}
				
		try	{
			$this->mongoManager = new \MongoDB\Driver\Manager("mongodb://{$host}");	//mongodb://username:password@localhost/test
		}catch (\MongoDB\Driver\Exception\Exception $e)	{
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	
	
	private function queryPrepare($q){
		if ($q==""){$q=[];}
		
		if (is_array($q))
		{
			if(isset($q['id']))
			{
				$oid = new \MongoDB\BSON\ObjectId($q['id']);
				$q['_id'] = $oid;
				unset($q['id']);
			}
			
			return $q;
		}
		else
		{
			return ['_id' => new \MongoDB\BSON\ObjectId($q)];
		}
	}
	
	
	private function dataPrepare($data){
		return $data;
	}
	
	
	function add($table,$data){
		$bulk = new \MongoDB\Driver\BulkWrite();
		$insertedId = $bulk->insert($this->dataPrepare($data));
		//$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
		$result = $this->mongoManager->executeBulkWrite($this->database.".".strtolower($table), $bulk); //, $writeConcern

		if ($result->getInsertedCount()>0){
			return (string)$insertedId;
		}else{
			return false;
		}
	}
	
	
	
	function get($table,$query){
		$res = $this->find($table,$query,['first'=>true]);
		$r = $res->current();
		if (is_null($r)){$r=false;}
		return $r;
	}
	
	
	
	function find($table,$query=[],$opts=[]){
		$pQuery = $this->queryPrepare($query);
		$mongoQuery = new \MongoDB\Driver\Query($pQuery);	
		$rows = $this->mongoManager->executeQuery($this->database.".".strtolower($table), $mongoQuery);
		foreach ($rows as $doc){
			$r = (array)$doc;
			if (isset($r['_id'])){
				$id = (string)$r['_id'];
				unset($r['_id']);
				$r = array_merge(['id'=>$id], $r);
			}
			yield $r;
		}
	}
	
	
	function edit($table,$query,$data){
		$bulk = new \MongoDB\Driver\BulkWrite();	
		$bulk->update($this->queryPrepare($query),['$set' => $this->dataPrepare($data)],['upsert' => false,'multi' => true]);
		//$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
		$result = $this->mongoManager->executeBulkWrite($this->database.".".strtolower($table), $bulk); //, $writeConcern
		return $result->getModifiedCount();
	}
	
	
	function kill($table,$query){
		$bulk = new \MongoDB\Driver\BulkWrite();
		$bulk->delete($this->queryPrepare($query));
		//$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
		$result = $this->mongoManager->executeBulkWrite($this->database.".".strtolower($table), $bulk); //, $writeConcern
		return $result->getDeletedCount(); 
	}
	
	
	function count($table, $query=[]){
		if (is_array($query)){
			if (count($query)==0){$query=null;}
		}else{
			if ($query==""){$query=null;}
		}
		//$pQuery = $this->queryPrepare($query);
		//$mongoQuery = new \MongoDB\Driver\Query($pQuery); //$eq !!!!!!!
		$command = new \MongoDB\Driver\Command(["count" => strtolower($table), "query"=>$query ]);
		$result = $this->mongoManager->executeCommand($this->database, $command);
		return (int)$result->toArray()[0]->n;
	}
	
	
	
	
	function _exec($query,array $data=[]){ // command with JSON
		list($command,$json) = explode("(",$query,2);
		$command = trim($command);
		$json = trim($json);
		
		if (substr(($json),-1,1)==")"){
			$json = substr($json,0,-1);
		}
		
		
		$json = $this->mongoJsonFix($json);
		$cmdData = json_decode($json,true);
		
		$cmds = explode(".",strtolower($command));
		if (count($cmds)==3 && $cmds[0]=='db'){
			$table = $cmds[1];
			$cmd = strtoupper($cmds[2]);
			if($cmd=="INSERT"){
				return $this->add($table,$cmdData[0]);
			}elseif($cmd=="UPDATE"){
				return $this->edit($table,$cmdData[0],$cmdData[1]);
			}elseif($cmd=="REMOVE"){
				return $this->kill($table,$cmdData[0]); 
			}
		}
	}
	
	
	function _query($query,array $data=[]){
		list($command,$json) = explode("(",$query,2);
		$command = trim($command);
		$json = trim($json);
		
		if (substr(($json),-1,1)==")"){
			$json = substr($json,0,-1);
		}
		
		$json = $this->mongoJsonFix($json);
		$cmdData = json_decode($json,true);
		$cmds = explode(".",strtolower(trim($command)));
		if (count($cmds)==3 && $cmds[0]=='db'){
			$table = $cmds[1];
			$cmd = strtoupper($cmds[2]);
			if($cmd=="FIND"){
				return $this->find($table,$cmdData[0]);
			}
		}
	}
	
	
	function mongoJsonFix($json){
		$json = "[".$json."]";
		$json = preg_replace('/(\w+):/i', '"\1":', $json);
		$json = str_replace('$"','"$',$json);
		return $json;
	}
	
}

?>