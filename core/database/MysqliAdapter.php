<?php
namespace Edisom\Core\database;
ini_set('mysqli.reconnect', 1);

class MysqliAdapter extends Adapters 
{	
	function warning():int{return $this->bd->warning_count; }
	function last():int{return mysqli_insert_id($this->bd);}
	function close():void{@mysqli_close($this->bd);}	
	function escape(string $string):string{ return mysqli_real_escape_string($this->bd, parent::escape($string));}
	
	
	function query(string $sql)
	{	
		if (
			(!$result = @mysqli_query($this->bd, $sql))
				&&
			($errno = mysqli_errno($this->bd))
		){
			switch($errno){
				case 1213:
					$this->query($sql);
				break;
				case 2006:
					$this->query($sql);
				break;
				case 1062:
					$message  = "При добавлении записи в базу произошла ошибка - запись не уникальна. Запрос: ".$sql." \r\nОшибка: ".mysqli_error($this->bd);
					throw new \Exception($message);
				break;				
				case 1451:
					$message  = "При удалении записи из базы произошла ошибка - сначала удалите зависимые данные. Запрос: ".$sql." \r\nОшибка: ".mysqli_error($this->bd);
					throw new \Exception($message);
				break;
				default:
					$message  = 'Ошибка базы данных ('.$errno.') '.mysqli_error($this->bd).". Запрос:".$sql;
					throw new \Exception($message);
				break;
			}
		}
		else
			return $result;
	}
	
	function fetch($result){return mysqli_fetch_array($result, MYSQLI_ASSOC);}
	function fetch_all($result){return mysqli_fetch_all($result, MYSQLI_ASSOC);}
	function free_result($result){return mysqli_free_result($result);}
	function affected_rows():int{return $this->bd->affected_rows;}
	
	function ping(){return mysqli_ping($this->bd);}
	
	function tables(){
		if($result = $this->query('SHOW TABLES')){
			while($row = $this->fetch($result)) {	
				$content[] = reset($row);
			}
			
			return $content;
		}
	}
	
	protected function connect(array $config)
	{
		if(isset($_SERVER['SERVER_NAME']) && $config['host']==$_SERVER['SERVER_NAME']){
			$config['host'] = 'localhost';
		}
		
		if($this->bd = mysqli_connect($config['host'], $config['user'], $config['password'], $config['bd']))
		{
			// нстройка в my.cfg : character_set_connection=utf8mb4
			mysqli_set_charset($this->bd, 'utf8mb4');
			return $this->bd;
		}
		else
			throw new \Exception('Ошибка соединения с базой данных '.$config['bd'].' ('.$config['user'].'@'.$config['host'].') в '.get_class($this).': '.mysqli_error($this->bd));
	}
}