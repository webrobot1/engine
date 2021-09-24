<?php
namespace Edisom\Core\database;

class PostgreAdapter extends Adapters 
{	
	function warning():int{return $this->bd->warning_count; }
	function last():int{return mysqli_insert_id($this->bd);}
	function close():void{@pg_close($this->bd);}
	function escape(string $string):string{ return pg_escape_string($this->bd, parent::prepare($string));}
	
	function query(string $sql)
	{
		if (
			(!$result = @pg_query($this->bd, $sql))
				&&
			($error = pg_last_error($this->bd))
		){
			switch($errno){
				default:
					$message  = 'Ошибка базы данных '.$error." в запросе ".$sql."\n";
					throw new \Exception($message);
				break;
			}
		}
		else
			return $result;	
	}
	
	function fetch($result){return pg_fetch_array($result, PGSQL_ASSOC);}
	function fetch_all($result){return pg_fetch_all($result);}

	function ping(){return pg_ping($this->bd);}
	function free_result($result){return pg_free_result($result);}
	function affected_rows():int{return $this->bd->affected_rows;}
	
	function tables(){
		if($result = $this->query("SELECT concat('views.', tablename) as tablename FROM pg_catalog.pg_tables")){
			return array_column($this->fetch_all($result), 'tablename');
		}
	}
	
	protected function connect(array $config)
	{
		$string = "host=".$config['host']." port=".$config['port']." dbname=".$config['bd']." user=".$config['user']." password=".$config['password'];
		if(isset($config['sslmode']))
			$string .= ' sslmode='.$config['sslmode'];		
		if(isset($config['sslcert']))
			$string .= ' sslcert='.$config['sslcert'];
		if(isset($config['sslkey']))
			$string .= ' sslkey='.$config['sslkey'];		
		if(isset($config['sslrootcert']))
			$string .= ' sslrootcert='.$config['sslrootcert'];
		
		if($this->bd = pg_connect($string))
		{
			return $this->bd;
		}
		else
			throw new \Exception('Ошибка соединения с базой данных '.$config['bd'].' ('.$config['user'].'@'.$config['host'].') в '.get_class($this).': '.pg_last_error($this->bd));
	}
}