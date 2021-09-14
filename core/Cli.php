<?php
/*
	В токене зашифрована вызываемая модель и ее action
*/

namespace Edisom\Core;

set_time_limit(0);
ini_set('memory_limit', -1);

final class Cli 
{
	private function __construct(){}
	private function __clone() {}
	
	// проверка что пришло из команднйо строки за токен (тока в CLI режиме доступны токены и запуск по КРОН)
	final static public function run()
	{	
		global $argv;
		
		if (\PHP_SAPI !== 'cli') {
            exit("Only run in command line mode \n");
        }
		
		if($array = static::parse($argv[1]))
		{
			call_user_func([$array['class']::getInstance(), ($array['action']?$array['action']:null)]);
		}
	}	
	
	// првоерка токена для данного приложения
	private static function parse(string $token):array
	{
		if((!$token = json_decode(base64_decode($token), true)) || !$token['class']){
			throw new \Exception('токен '.$token.' не корректен');	
		}
		
		return $token;
	}
	
	// сгенерировать токен (какое метод запускать в каком приложении с каким экшеном)
	private static function token(string $class, string $action=null):string
	{
		$token = array(
			'class'=>$class
		);	
		
		if($action)
			$token['action'] = $action;
		
		return base64_encode(json_encode($token));
	}
	
	// запуск через командную строку
	final static public function cmd($cmd)
	{
		$output = null;
		$code = null;
		
		exec($cmd, $output, $code); //(substr(php_uname(), 0, 7) == "Windows"?pclose(popen("start /B ". $cmd, "r")):
		
		switch($code)
		{				
			case EXCEPTION_CODE:
				throw new \Exception('Ошибка выполнения операции '.$cmd.': '.$cmd);	
			break;								
			case FORBIDDEN_CODE:
				throw new \Exception('Доступ закрыт для операции '.$cmd.'');	
			break;					
			default:
				return trim(implode("\r\n", $output));
			break;	
		}
	}
	
	// добавить крон задание
	public static function add(string $time, string $class, string $action=null, string $params = null, string $output = null){
		static::cmd('crontab -u '.get_current_user().' -l > mycron
			#echo new cron into cron file
			echo "'.$time.' '.static::get($class, $action, $params, $output).'" >> mycron
			#install new cron file
			crontab -u '.get_current_user().' mycron
			rm mycron'
		);
	}
	
	// удалить крон задание
	public static function delete(string $class, string $action=null, string $params = null)
	{
		static::cmd('crontab -u '.get_current_user().' -l | grep -v "^[0-9*/ ,\-]* '.static::get($class, $action, $params).'\( >.*\)*$" | crontab -');
	}
	
	// првоерить крон задание
	public static function check(string $class, string $action=null, string $params = null)
	{
		return static::cmd('crontab -u '.get_current_user().' -l | grep "^[0-9*/ ,\-]* '.static::get($class, $action, $params).'\( >.*\)*$"');
	}
	
	// получить команду Cli уже с вшитым токеном (где указана вызываемая модель и action при необходимости)
	final static public function get(string $class, string $action=null, string $params = null, string $output = null, $quiet = false):string
	{				
		return "php ".SITE_PATH."/index.php ".static::token($class, $action).($params?' '.$params:'').($output?' > '.SITE_PATH.'/tmp/'.$output:'').($quiet?' &':'');		
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