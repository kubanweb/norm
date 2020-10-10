<?php
namespace Norm;

use Norm\Query;

class Driver_mongo extends Driver{
	private $mongoManager;

	
	function __construct($config){
		$driver = $config['driver'];
		$user = $config['user']??"";
		$password = $config['password']??"";
		$this->database = strtolower($config['database']);
		$host = $config['host']??'localhost';
		if (strpos($host,':')===false)	{	$host.=":27017";}
				
		try	{
			if ($user!="" && $password!=""){
				$conStr = "mongodb://{$user}:{$password}@{$host}";
			}else{
				$conStr = "mongodb://{$host}";
			}
			$this->mongoManager = new \MongoDB\Driver\Manager($conStr);
		}catch (\MongoDB\Driver\Exception\Exception $e)	{
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	
	
	function injectionValFilter($val){
		// BLOCK NESTED ARRAYS (?name[$ne]=Alice)
		if (is_numeric($val) || is_bool($val) || is_string($val)){
			return $val;
		}else{
			if (is_array($val)){
				if (count($val)==0){
					return $val;
				}elseif ( array_keys($val)===range(0,count($val)-1) ){
					return $val;
				}
			}
		}
	}
	
	
	function injectionKeyFilter($key){
		// BLOCK eval
		$key = trim($key);
		if (substr($key,0,1)=="$"){
			$key = substr($key,1);
		}
		
		if (strtolower(trim($key))=="eval"){
			$key = "";
		}
			
		return $key;
	}
	
	function isAssoc(array $arr)
	{
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	
	function conditionBuilder($key,$val){
		$field = $this->injectionKeyFilter($key);
		$operand = "=";
		$value = $this->injectionValFilter($val);
		$comma = "AND";
		$israw = false;
		
		if (strpos($field," ")>0){
			$fld = explode(" ",$field);
			
			if (in_array(strtoupper($fld[0]),['AND','OR'])){
				$comma = strtoupper($fld[0]);
				array_shift($fld);
			}
			
			if (count($fld)==2){
				$field = $fld[0];
				$opsLogic=[
					'=' =>'$eq',
					'<>'=>'$ne',
					'!='=>'$ne',
					'>' =>'$gt',
					'>='=>'$gte',
					'<' =>'$lt',
					'<='=>'$lte',
					'IN'=>'$in',
					'NOTIN'=>'$nin',
					'NOT_IN'=>'$nin',
				];
				
				$logic = strtoupper($fld[1]);
				if (isset($opsLogic[$logic])){
					return [
						$field,
						[
							$opsLogic[$logic]=>$value
						]
					];
				}else{
					if($logic=="LIKE" || $logic=="%LIKE%"){
						return [
							$field,
							new \MongoDB\BSON\Regex(''.$value.'', 'i')
						];
					}elseif($logic=="%LIKE"){
						return [
							$field,
							new \MongoDB\BSON\Regex(''.$value.'$', 'i')
						];
					}elseif($logic=="LIKE%"){
						return [
							$field,
							new \MongoDB\BSON\Regex('^'.$value.'', 'i')
						];
					}elseif($logic=="NOTLIKE" || $logic=="%NOTLIKE%" || $logic=="%NOT_LIKE%" ){
						return [
							$field,
							[
								'$not'=>new \MongoDB\BSON\Regex(''.$value.'', 'i')
							]
						];
					}elseif($logic=="%NOTLIKE" || $logic=="%NOT_LIKE"){
						return [
							$field,
							[
								'$not'=>new \MongoDB\BSON\Regex(''.$value.'$', 'i')
							]
						];
					}elseif($logic=="NOTLIKE%" || $logic=="NOT_LIKE%"){
						return [
							$field,
							[
								'$not'=>new \MongoDB\BSON\Regex('^'.$value.'', 'i')
							]
						];
					}
				}	
			}
		}else{
			return [$field,$value];
		}
	}
	
	
	private function queryPrepare($query){
		if ($query==""){$query=[];}
		
		if (is_array($query))
		{
			$q=[];
			if (count($query)>0){
				foreach($query as $key=>$val){	
					list($nkey,$nval) = $this->conditionBuilder($key,$val);
					
					if ($nkey==""){
						$nkey = "!";
					}
					
					if (substr($nkey,0,1)!="$" && substr($nkey,0,1)!="eval" ){
						$q[$nkey] = $nval;
					}
				}
			}
			
			return $q;
		}
		else{
			return ['_id' => new \MongoDB\BSON\ObjectId($query)];
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
	
	
	function dereferencing($doc){
		
	}
	
	function renderDocument(array $doc){
		$r = $doc;
		if (isset($r['_id'])){
			$id = (string)$r['_id'];
			unset($r['_id']);
			$r = array_merge(['id'=>$id], $r);
		}
		
		return $r;
	}
	
	function find($table, $query=[], array $opts=[]){
		$dereferencing = (int)($opts['dereferencing']??0);
		$dereferencingKeepRef = (bool)($opts['dereferencingKeepRef']??false);
		$dereferencingCacheSize = (int)($opts['dereferencingCache']??0);
		
		$dereferencingFieldsAll=true;
		$dereferencingFields=[];
		
		if (isset($opts['dereferencingFields']) && is_array($opts['dereferencingFields'])){
			$dereferencingFieldsAll=false;
			$dereferencingFields = $opts['dereferencingFields'];
		}
		
		$pRef = '$ref';
		$pId = '$id';
		
		$pQuery = $this->queryPrepare($query);
		
		$mongoQuery = new \MongoDB\Driver\Query($pQuery);	
		$rows = $this->mongoManager->executeQuery($this->database.".".strtolower($table), $mongoQuery);
		
		$cnt=0;
		$refsCache=[];
		$maxRefsCacheSize=0;
		
		if ($dereferencing==0){
			foreach ($rows as $doc){
				yield $this->renderDocument((array)$doc);
			}
		}elseif ($dereferencing==1){
			foreach ($rows as $doc){
				$arr = (array)$doc;
				foreach($arr as $key=>$val){
					if (is_object($val) && ($dereferencingFieldsAll||in_array($key,$dereferencingFields) ) && property_exists($val,'$ref') && property_exists($val,'$id')){
						$refTab =$val->$pRef;
						if ($refTab=="."){
							$refTab = $table;
						}
						
						if (!isset($refsCache[$refTab."|".$val->$pId])){
							$refsCache[$refTab."|".$val->$pId] = $this->get($refTab,$val->$pId);

							$sz = count($refsCache);
							if ($sz>$maxRefsCacheSize){
								$maxRefsCacheSize = $sz;
							}
						}
						$refDoc = $refsCache[$refTab."|".$val->$pId];
											
						if ($dereferencingKeepRef){
							$refDoc['$ref'] = $refTab;
						}
						$arr[$key] = $refDoc;
					}
				}
				yield $this->renderDocument($arr);
			}
		}else{
			$docBuf=[];
			$refQ=[];
			$refsCache=[];
			$k=0;

			foreach ($rows as $dkey=>$doc){
				$arr = (array)$doc;
				$docBuf[] = $arr;
				
				foreach($arr as $key=>$val){
					if (is_object($val) && ($dereferencingFieldsAll||in_array($key,$dereferencingFields) ) && property_exists($val,'$ref') && property_exists($val,'$id')){
						if (!isset($refQ[$val->$pRef][$val->$pId])){
							if (!isset( $refsCache[$key."|".$val->$pRef][$val->$pId] )){
								$refQ[$key."|".$val->$pRef][$val->$pId] = new \MongoDB\BSON\ObjectId($val->$pId);
							}
						}
					}
				}
				
				if ($k<$dereferencing){
					$k++;
				}else{
					foreach($refQ as $Q=>$rIds){					
						list($rfield,$rtb) = explode("|",$Q);
						if ($rtb=="."){
							$rtb = $table;
						}
						
						$rr = $this->mongoManager->executeQuery($this->database.".".$rtb, new \MongoDB\Driver\Query([
							'_id'=>[
								'$in'=>array_values($rIds)
							]
						]));
						foreach($rr as $ritem){
							$ritem = (array)$ritem;
							$ritem['_id'] = (string)$ritem['_id'];
							$refsCache[$Q][$ritem['_id']] = $this->renderDocument($ritem);
						}
					}
									
					foreach($docBuf as $bdoc){
						foreach($bdoc as $bkey=>$bval){
							if (is_object($bval)&& ($dereferencingFieldsAll||in_array($bkey,$dereferencingFields) ) && property_exists($bval,'$ref') && property_exists($bval,'$id')){
								$bdoc[$bkey] = $refsCache[$bkey."|".$bval->$pRef][$bval->$pId];
								if ($dereferencingKeepRef){
									$bdoc[$bkey]['$ref'] = $bval->$pRef;
								}
							}
						}
												
						yield $this->renderDocument($bdoc);
					}
					
					$docBuf=[];
					$refQ=[];
					$k=0;
				}
			}
			
			if (count($docBuf)>0){
				
				foreach($refQ as $Q=>$rIds){					
					list($rfield,$rtb) = explode("|",$Q);
					if ($rtb=="."){
						$rtb = $table;
					}
					
					$rr = $this->mongoManager->executeQuery($this->database.".".$rtb, new \MongoDB\Driver\Query([
						'_id'=>[
							'$in'=>array_values($rIds)
						]
					]));
					foreach($rr as $ritem){
						$ritem = (array)$ritem;
						$ritem['_id'] = (string)$ritem['_id'];
						$refsCache[$Q][$ritem['_id']] = $this->renderDocument($ritem);
					}
				}
								
				foreach($docBuf as $bdoc){
					foreach($bdoc as $bkey=>$bval){
						if (is_object($bval)&& ($dereferencingFieldsAll||in_array($bkey,$dereferencingFields) ) && property_exists($bval,'$ref') && property_exists($bval,'$id')){
							$bdoc[$bkey] = $refsCache[$bkey."|".$bval->$pRef][$bval->$pId];
							if ($dereferencingKeepRef){
								$bdoc[$bkey]['$ref'] = $bval->$pRef;
							}
						}
					}
					yield $this->renderDocument($bdoc);
				}
				
			}
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
		$command = new \MongoDB\Driver\Command(["count" => strtolower($table), "query"=>$query ]);
		$result = $this->mongoManager->executeCommand($this->database, $command);
		return (int)$result->toArray()[0]->n;
	}
	
	
	function list($table,$parent="",$opts=[]){
		if ($parent==""){
			$query = [
				'_parent'=>[
					'$exists'=>false
				]
			];
		}else{
			$query = [
				'_parent.$id'=>$parent
			];
		}
		
		return $this->find($table,$query,$opts);
	}
	
	
	function index(){
		$cursor = $this->mongoManager->executeReadCommand($this->database, new \MongoDB\Driver\Command(['listCollections' => 1]));
		$arr=[];
		foreach ($cursor as $obj){
			$arr[$obj->name] = [
				'name'=> $obj->name,
				'type'=> $obj->type,
				'comment'=> '',
				'options'=> (array)$obj->options,
			];
		}
		return $arr;
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