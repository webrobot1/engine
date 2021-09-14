<?php
namespace Edisom\Core\database;

abstract class Adapters 
{	
	protected $bd;
	
	public bool $transaction = false;
	
	function __construct($config){
		$this->connect($config);
	}
	
	abstract function query(string $sql);
	
	abstract function fetch($result);
	abstract function fetch_all($result);
	abstract function free_result($result);
	abstract protected function connect(array $config);
	abstract function ping();
	abstract function last():int;
	abstract function tables();
	abstract function close():void;
	abstract function warning():int;
	abstract function affected_rows():int;
	
	function escape(string $string):string{ return str_replace(array('\r', '\n', '\t', "\'", '\"'), array("\r", "\n", "\t", "'", '"'), trim($string));}
}