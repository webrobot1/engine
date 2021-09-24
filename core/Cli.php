<?php
namespace Edisom\Core;

set_time_limit(0);
ini_set('memory_limit', -1);

final class Cli 
{
	private function __construct(){}
	private function __clone() {}
	
	// проверка что пришло из команднйо строки за токен (тока в CLI режиме доступны токены и запуск по КРОН)
	// только модели можно запускать в кроне (у них реализован getInstance)
	// првоерять модель ли пытftvcz запустить каждый раз - больно дорогая операция
	final static public function run()
	{	
		global $argv;
		
		if (\PHP_SAPI !== 'cli') {
            exit("Only run in command line mode \n");
        }
		
		if($argv[1] = static::decode($argv[1]))
		{
			if(!$argv[1]['class'])
				throw new \Exception('не указан класс вызова');	
			if(!$action = $argv[1]['action'])
				throw new \Exception('не указан метод класса вызова');	
			
			if($argv[2])
				$argv[2] = static::decode($argv[2]);
			
			// вызываем метод
			// если нет $argv[1]['action']  значит передаем все даныне в __construct
			// если есть - передаем как аргументы метода
			
			$model = $argv[1]['class']::getInstance();
			
			if($argv[2])
				$params = array_intersect_key($argv[2], array_column((new \ReflectionClass($model))->getMethod($action)->getParameters(), 'name', 'name'));
						
			$model->$action(...$params);
		}
	}	
	
	// првоерка токена для данного приложения
	public static function decode(string $data):array
	{
		if((!$data = json_decode(base64_decode($data), true))){
			throw new \Exception('данные '.$data.' не корректены');	
		}		
		return $data;
	}
	
	// сгенерировать токен (какое метод запускать в каком приложении с каким экшеном)
	public static function encode(array $data):string
	{
		return base64_encode(json_encode(array_filter($data), JSON_NUMERIC_CHECK));
	}
	
	// запуск через командную строку
	final static public function cmd($cmd)
	{
		$output = null;
		$code = null;
		
		exec($cmd, $output, $code); //(substr(php_uname(), 0, 7) == "Windows"?pclose(popen("start /B ". $cmd, "r")):
					
		$output = trim(implode("\r\n", $output));
			
		switch($code)
		{
			case EXCEPTION_CODE:
				throw new \Exception('Ошибка выполнения операции '.$cmd.': '.$output);	
			break;								
			case FORBIDDEN_CODE:
				throw new \Exception('Доступ закрыт для операции '.$cmd.': '.$output);	
			break;					
			default:
				return $output;
			break;	
		}
	}
	
	// добавить крон задание
	public static function add(string $time, string $class, string $action, array $params = null, string $output = null, $quiet = false){
		static::cmd('crontab -u '.get_current_user().' -l > mycron
			#echo new cron into cron file
			echo "'.$time.' '.static::get($class, $action, $params, $output, $quiet).'" >> mycron
			#install new cron file
			crontab -u '.get_current_user().' mycron
			rm mycron'
		);
	}
	
	// удалить крон задание
	public static function delete(string $class, string $action, array $params = null)
	{
		static::cmd('crontab -u '.get_current_user().' -l | grep -v "^[0-9*/ ,\-]* php [^ ]* '.static::encode(['class'=>$class, 'action'=>$action]).($params?' '.static::encode($params):'').' > .*$" | crontab -');
	}
	
	// првоерить крон задание
	public static function check(string $class, string $action=null, array $params = null)
	{
		return static::cmd('crontab -u '.get_current_user().' -l | grep "^[0-9*/ ,\-]* php [^ ]* '.static::encode(['class'=>$class, 'action'=>$action]).($params?' '.static::encode($params):'').' > .*$"');
	}
	
	// получить команду Cli уже с вшитым токеном (где указана вызываемая модель и action при необходимости)
	final static public function get(string $class, string $action, array $params = null, string $output = null, $quiet = false):string
	{				
		return "php ".SITE_PATH."/index.php ".static::encode(['class'=>$class, 'action'=>$action]).($params?' '.static::encode($params):'').' > '.($output?SITE_PATH.'/tmp/'.$output.' 2>&1':($quiet?'/dev/null':'')).($quiet?' &':'');		
	}	
	
	// завершить процесс
	final static public function kill(int $pid)
	{				
		switch(substr(php_uname(), 0, 7))
		{
			case 'Windows':
				$result = \Edisom\Core\Cli::cmd("taskkill /F /PID ".$pid);
			break;
			default:
				$result = \Edisom\Core\Cli::cmd("kill -9 ".$pid);
			break;	
		}
		if($result === false)
			throw new \Exception("Ошибка остановки процесса ".$pid);		
	}
}