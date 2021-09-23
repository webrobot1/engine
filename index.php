<?php
declare(strict_types=1);
setlocale(LC_TIME, 'ru_RU.UTF-8');

// настроить в php.ini - слишком много времени занмиает вызов
//date_default_timezone_set('Europe/Moscow');

if(PHP_SAPI !== 'cli')
	DEFINE('START_TIME_CHECK', getrusage()); // для счетчика

require_once __DIR__ . '/vendor/autoload.php';
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED );

bcscale(2);
DEFINE('DEBUG', 1);

if(DEFINED("DEBUG") && DEBUG){
	ini_set('display_errors', 'On');
	ini_set('display_startup_errors', 'On');
}

DEFINE('SITE_PATH', rtrim(str_replace("\\", "/", realpath(dirname(__FILE__))), '/')); 

// коды возврата ошибок для CLI режима
DEFINE('NOT_FOUND_CODE', 2);
DEFINE('FORBIDDEN_CODE', 3);
DEFINE('PAGE_LIMIT', 50); 

if(PHP_SAPI === 'cli' && $argv[1]) { // cli режим
	Edisom\Core\Cli::run();
}else{
	ini_set('session.gc_maxlifetime', '28800');
	set_time_limit(430);
	
	// приложение контроллер и action по умолчанию
	$path = array('backend', 'backend', 'index');
	
	if(($url = parse_url("//".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], PHP_URL_PATH)) && ($url = ltrim(strtolower($url), '/'))){	
		$path = array_replace_recursive($path, array_filter(explode('/', $url)));
	}
	
	@list($app, $page, $action, $query) = array_merge($path, [null,null,null,null]);	

	if(is_dir(SITE_PATH.'/app/'.$app) && ($_GET['page'] = $page) && ($_GET['action'] = $action))
	{	
		chdir(SITE_PATH.'/app/'.$app);			
	}
	else
		\Edisom\Core\Controller::_404('не найдено приложение с адресом /'.urldecode($url));

	// используется в дизайне (в моделях там через статическую переменную)
	DEFINE('APP', $app);

	if(($file = 'controller/'.ucfirst($page).'Controller.php') && file_exists($file)){
		if(($class='\\Edisom\\App\\'.$app.'\\controller\\'.ucfirst($page).'Controller') && class_exists($class))
			$class::getInstance($query)->$action(); // пусть контроллер сам првоеряем сущестсование метода и если его нет что то делает с этим магическими методами (по умолчанию выдаст 404)
		else
			\Edisom\Core\Controller::_404("не найден класс контроллера ".$class.' в фаиле '.$file);
	}
	else
		\Edisom\Core\Controller::_404("не найден фаил контроллера ".$file);
}