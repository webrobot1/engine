<?php
namespace Edisom\Core\database;

abstract class Adapters 
{	
	protected $bd;
	private static string $host;
	private static string $ip;
	
	public bool $transaction = false;
	
	function __construct(array $config)
	{
		if(empty(static::$host))
		{
			static::$host = gethostname();
			static::$ip = gethostbyname(static::$host);
		}
		
		if($config['host']==static::$ip || $config['host']==$_SERVER['SERVER_ADDR']){
			$config['host'] = static::$host;
		}
		else{
			    exec('ifconfig -a|grep inet|grep -v 127.0.0.1|grep -v inet6|awk \'{print $2}\'|tr -d "addr:"',$arr);
			throw new \Exception(print_r($arr, true).'|'.static::$ip);
		}
		
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