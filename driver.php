<?php
namespace Norm;

abstract class Driver{
	abstract function add($table,$data);
	abstract function edit($table,$query,$data);
	abstract function kill($table,$query);
	
	abstract function get($table,$query);
	abstract function find($table, $query=[], array $opts=[]);
	abstract function list($table,$parent="",$opts=[]);
	
	abstract function count($table, $query=[]);
	abstract function index();
	
	abstract function _exec($query,array $data=[]);
	abstract function _query($query,array $data=[]);	
}

?>