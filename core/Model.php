<?php
namespace Edisom\Core;

DEFINE('MYSQl_FLAG_RESET', 1);
DEFINE('MYSQl_FLAG_ASSOC', 2);
DEFINE('EXCEPTION_CODE', 3);

abstract class Model 
{
	private static $instances = array();

	private static $adapters = array();	
	private static $methods = array();	 // дополнительные методы что можно доабвить в класс динамически
	private static \Redis $redis;
	
	private static $applications = null;
	private static $configs = array();
	
	protected function __construct()
	{		
		// повесим функции обработчики на завершение, на ошибки критические и исключения
		// если при попытке установить обработчик событий у нас уже есть из контроллера (с дизайненом вывода ошибок и заголовками) - вернем на тот что с дизайном и заголовками (тк мы не CLI интерфейсе)
		if(set_error_handler(array($this, 'errorHandler'), error_reporting())){
			restore_error_handler();
		}
		else
			register_shutdown_function(array($this, 'errorHandler'));
				
		if(set_exception_handler(array($this, 'exceptionHandler')))
			restore_exception_handler();		

		if(!is_dir(static::temp())) 
			mkdir(static::temp());		
	}
	
	// добавляет в приложение новые метод (может быть как аннимной функцией так и методом из другого класса)
	protected static function addMethod($name, \Closure $method)
    {
        static::$methods[static::app()][$name] = $method;
    }
	
	public function __call($methodName, $args) 
	{
		if(is_callable(static::$methods[static::app()][$methodName]))
			return call_user_func_array(static::$methods[static::app()][$methodName], $args);
	   else
			throw new \Exception('не объявлен model method: '.$methodName.($args?' с аргументами '.print_r($args, true):''));  			
	}
	
	final function __clone(){}
		
	final public static function getInstance():static
	{	
        if (!isset($instances[static::class]))
        {
			if(class_exists(static::class))
				$instances[static::class] = new (static::class)();
			else
				throw new \Exception("не найден class instance:".static::class);
        }

        return $instances[static::class];
    }	
		
	final protected static function redis():\Redis
	{
		if(static::config('redis'))
		{
			static::$redis = new \Redis();			
			static::$redis->pconnect(static::config('redis')['host'], static::config('redis')['port']);
		}		
		return static::$redis;
	}
		
	// получает информацуию что за приложение для этой модели
	protected static function app():string
	{
		return explode('\\', str_replace('Edisom\\App\\', '', static::class))[0];
	}	
	
	// получает информацуию что за приложение для этой модели
	public static function temp():string
	{
		return SITE_PATH.'/tmp/'.static::app().'/';
	}
	
	final public static function config($key)
	{ 
		if(empty(static::$configs[static::app()]))
		{
			$config_file = SITE_PATH.'/app/'.static::app().'/cfg/int.php';
			if(!file_exists($config_file)) throw new \Exception("не найден config ".$config_file);
			
			include($config_file);
			if(!$config)
				 throw new \Exception("не найден config в фаиле ".$config_file);
			 
			static::$configs[static::app()] = $config;	
		}
		
		if(!empty(static::$configs[static::app()][$key]))
			return static::$configs[static::app()][$key];
	}	
	
	public static function __callStatic($methodName, $args) {
		throw new \Exception('не найден model static: '.$methodName);
	}	
		
	public function exceptionHandler($ex)
	{
		// оставляем вывод что бы мы могли видеть ошибки в консоле
		echo $ex->getMessage();
		static::log($ex, 'error.log');
		exit(EXCEPTION_CODE);
	}	
	
	public function errorHandler($errno = null, $errstr = null, $errfile = null, $errline = null)
	{	
		if($errno == null && ($error = error_get_last()))
			list($errno, $errstr, $errfile, $errline) = array_values($error);
		
		if($errno && ((error_reporting() & $errno) == $errno)){
			$message = $errno.':'.htmlspecialchars($errstr).' ('.$errfile.'-'. $errline.")";
				
			if($errno == E_ERROR || preg_match('/Error while sending QUERY packet/',$errstr)){
				throw new \Exception($message);
			}
			else
				static::log($message, 'error.log');
		}	
	}
	
	final static function guid(){
		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',mt_rand(0,65535),mt_rand(0,65535),mt_rand(0,65535),mt_rand(16384,20479),mt_rand(32768,49151),mt_rand(0,65535),mt_rand(0,65535),mt_rand(0,65535));
	}
	
	final public static function applications()
	{		
		if(static::$applications === null){
			static::$applications = array();
			$dirContent = scandir(SITE_PATH.'/app');
				
			foreach($dirContent as $content) {
				if(is_dir(SITE_PATH.'/app/'.$content) && $content!='.' && $content!='..' && file_exists(SITE_PATH.'/app/'.$content)) {
					static::$applications[strtolower($content)]['backend'] = (file_exists(SITE_PATH.'/app/'.$content.'/controller/BackendController.php'));
				}			
			}
		}	
		return static::$applications;
	}
	
	#######################
	// суем массив (можно многомерный) 
	// флаг sql - дает нам строку для запроса update или where
	final protected static function explode(array|object $callback, $delimetr=',', $sql=true):?string
	{
		if(!$callback) return null;
		$callback = static::prepare((array)$callback, $sql);
		
		array_walk($callback, 
			function(&$item,$key) use($sql, $delimetr){ 
				$item = ($sql?'`':'').$key.($sql?'`':'')
						.
						($sql && is_array($item)?'IN ':($sql && trim($delimetr) != ',' && $item=='NULL'?' IS ':' = '))
						.
						(is_array($item)?'('.implode(',', $item).')':$item); 
			}
		);
		return implode(' '.trim($delimetr).' ', $callback);
	}	
	
	static function escape($string):string
	{
		if(!empty(static::$adapters[static::app()]))
			return static::$adapters[static::app()]->escape($string);
		else
			return addslashes(\Edisom\Core\database\Adapters::prepare($string));
	} 	
	
	// Мне нужен Mysql Cache  - поэтому PDO не использую
	// А это своя обработка значений против SQL инъекций 
	final public static function prepare(array|string $data = null, $sql = true)
	{
		if(is_array($data)){
			array_walk($data, function(&$item,$key) use($sql) { 
				$item = static::prepare($item, $sql);			
			});
			if(!$filter = array_filter($data))
				$data = $filter;	
		}
		else
		{
			$data = ($data && $sql && !is_numeric($data) && $data!=='' && $data!==null && $data!==false?'"':'').
					($data!=='' && $data!==null && $data!==false?static::escape($data):($sql?'NULL':'')).
					($data &&  $sql && !is_numeric($data) && $data!=='' && $data!==null && $data!==false?'"':'');

		}
		return $data;
	}
	
	###########################	
		
	final protected static function query($sql, $flag = null)
	{
		if(empty(static::$adapters[static::app()]))
		{
			if(
				static::config('database') 
					&& 
				((isset(static::config('database')['adapter']) && ($adapter = static::config('database')['adapter'])) || ($adapter='Edisom\Core\database\MysqliAdapter')) 
			)
			{
				static::$adapters[static::app()] = new $adapter(static::config('database'));
				unset(static::$configs[static::app()]['database']['password']);
				
				// добавим в данный класс методы из адаптера
				static::addMethod('last', \Closure::fromCallable([static::$adapters[static::app()], 'last']));
				static::addMethod('tables', \Closure::fromCallable([static::$adapters[static::app()], 'tables']));
				static::addMethod('warning', \Closure::fromCallable([static::$adapters[static::app()], 'warning']));
				static::addMethod('affected_rows', \Closure::fromCallable([static::$adapters[static::app()], 'affected_rows']));
			}				
		}
		elseif(!static::$adapters[static::app()]->ping()) throw new \Exception("Соединение с базой потеряно");
		
		###### профилирование во Frontend ########	
		if(DEFINED("DEBUG") && DEBUG && session_status() === PHP_SESSION_ACTIVE)
		{	
			$profiling['SQL_TEXT'] = substr($sql, 0 , 180); 
			$profiling['Duration'] = microtime(true);
			$profiling['count'] = 1; // счетчик аналогичных запросов
		}
		###### профилирование во Frontend ########	
		
		$result = static::$adapters[static::app()]->query($sql);	

		if($result!==true){
			$content = array();
			if($flag){
				while($row = @static::$adapters[static::app()]->fetch($result)) {	
					switch($flag){
						case(MYSQl_FLAG_RESET):
							$content[] = reset($row);
						break;					
						case(MYSQl_FLAG_ASSOC):
							$content[reset($row)] = next($row);
						break;
						case is_string($flag) && isset($row[trim($flag)]):
							if(isset($content[$row[$flag]]) && DEFINED("DEBUG") && DEBUG) 
								echo 'флаг '.$flag.' не уникальный в запросе '.$sql;
									
							$content[$row[$flag]] = $row;
						break;
						
						default:
							throw new \Exception('Неизвестный флаг '.$flag);
						break;	
					}
				}
			}
			else{
				$content = static::$adapters[static::app()]->fetch_all($result);
			}
			// очистим память для select запросов 
			static::$adapters[static::app()]->free_result($result);
		}
		else
			$content = static::$adapters[static::app()]->affected_rows();

		###### профилирование во Frontend ########	
		if(DEFINED("DEBUG") && DEBUG && session_status() === PHP_SESSION_ACTIVE){
			$profiling['Duration'] = microtime(true)-$profiling['Duration'];
	
			if(isset($_SESSION['profiling'][$profiling['SQL_TEXT']])){
				$_SESSION['profiling'][$profiling['SQL_TEXT']]['Duration'] += $profiling['Duration'];
				$_SESSION['profiling'][$profiling['SQL_TEXT']]['count'] ++;
			}
			else	
				$_SESSION['profiling'][$profiling['SQL_TEXT']] = $profiling;	
		}
		###### профилирование во Frontend ########	
		
		return $content;		
	}
		
	final protected static function transaction_start(bool $foreign = false)
	{
		if(!empty(static::$adapters[static::app()]->transaction))
			throw new \Exception('Транзакция уже открыта');
		
		static::$adapters[static::app()]->transaction = true;
		
		static::query("SET AUTOCOMMIT = 0");
		static::query("Start transaction");
		if($foreign){
			static::query("SET FOREIGN_KEY_CHECKS = 0");
			static::query("SET UNIQUE_CHECKS = 0");
		}		
	}		

	final protected static function transaction_stop()
	{
		static::$adapters[static::app()]->transaction = false;	
		static::query("commit");
		static::query("SET AUTOCOMMIT = 1");
		static::query("SET FOREIGN_KEY_CHECKS = 1");
		static::query("SET UNIQUE_CHECKS = 1");		
	}

	
	final protected static function transaction_rollback(){
		static::query("ROLLBACK");	
		static::transaction_stop();
	}	
	###########################	
	
	final protected static function curlRequest($url, $post='', $authHeader=array())
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if($authHeader)
			curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeader);
		if($post)
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False);
		$curlResponse = curl_exec($ch);
		curl_close($ch);
		return $curlResponse;
	}  
	
	final public static function upload(string $tmp_name, string $name, bool $override = false)
	{
		$name = basename(trim($name));
		$dir = SITE_PATH."/data/".static::app().'/';
		
		if(!file_exists($dir))
			mkdir($dir);
		
		if(!$override)
		{
			while(file_exists($dir.$name))
				$name = rand(0,9).$name;
		}
		
		if(substr($tmp_name, 0, 4)!='http' && move_uploaded_file($tmp_name, $dir.$name)!==false)
			return $name;
        elseif(substr($tmp_name, 0, 4)=='http' && ($content = file_get_contents($tmp_name)) && !strpos($content,'EDIS Online Manager') && file_put_contents($dir.$name, file_get_contents($tmp_name)))
			return $name;
	}
	
	
	// стараться избегать вызов логов в высокоскоростных системах (DateTime занимает 0,0003 сек)
	public static function log(string|array $comment, string $file = 'main.log', $append = true)
	{
		file_put_contents
		(
			static::temp().$file
				, 
			($append? (new \DateTime())->format("Y-m-d H:i:s:v").' ':'').trim(implode(($append?"\r\n":', '), (array)$comment)).($append?"\r\n":'')
				,
			($append && file_exists(static::temp().$file)?FILE_APPEND:null)
		);	
	}
}