<?php
namespace Edisom\Core;

abstract class Frontend extends Controller 
{	
	private $redirect;
	protected $view;
			
	// вообще можно делать все без query и писать просто в GET
	// но я решил делать с этим изначально кодируя get переменные в виде части адресной строки json (и base64 ее дополнительно)
	protected function __construct(string $query = null)
	{	 
		session_start();	
		unset($_SESSION['profiling']);
		
		// распарсим урл в массив данных
		if($query){
			if(!DEFINED("DEBUG") || !DEBUG)
				$query = json_decode(base64_decode($query), true);
			else
				$query = json_decode(urldecode($query), true);
		}
				
		// а теперь запишем из адресной строки параметры (я их в json пакую)
		parent::__construct($query);	
	
		$this->view = Template::getInstance();
		$this->view->compile_dir = $this->model::temp().'/compiled/';
		$this->view->cache_dir = $this->model::temp().'/cache/';
				
		if(!is_dir($this->view->compile_dir)) 
			mkdir($this->view->compile_dir);		
		if(!is_dir($this->view->cache_dir)) 
			mkdir($this->view->cache_dir);
				
		if($this->view->getTheme()!='default'){
			$this->view->addTemplateDir(SITE_PATH.'/theme/default');		
			$this->view->addTemplateDir('./theme/default');		
		}
		
		$this->view->registerPlugin('modifier', "translate", array(\Edisom\App\help\model\BackendModel::getInstance(), 'translate'));
		
		$this->view->addTemplateDir(SITE_PATH.'/theme/'.$this->view->getTheme());		
		$this->view->addTemplateDir('./theme/'.$this->view->getTheme());	
	}

	// переопределим с дизайном
	final public function controllerExceptionHandler($ex){
		header("HTTP/1.0 400 Bad Request");
		if(!@getallheaders()['X-Requested-With'])
			require_once SITE_PATH.'/400.html';
		else
			echo $ex->getMessage();
		exit(EXCEPTION_CODE);
	}
	
	public static function _404($message)
	{
		header("HTTP/1.0 404 Not Found");
		if(!@getallheaders()['X-Requested-With'])
			require_once SITE_PATH.'/404.html';
		else
			echo $message;
			
		exit(NOT_FOUND_CODE);		
	}
	
	protected static function _403(){	
		header("HTTP/1.0 403 Forbidden");
		if(!@getallheaders()['X-Requested-With'])
			require_once SITE_PATH.'/403.html';
		else
			echo 'доступ закрыт';
		exit(FORBIDDEN_CODE);		
	}
	
		
	// отложенные (при деструкте) редирект в рамках контроллера
	// оставляем только те переменные в GET Б которые обьявлены в классе контроллера
	
	// true = возвращает на главную приложения
	// false = на пред страницу в истории
	// pattetn = согласно правилам set_query
	final protected function redirect(string $pattern = null, string $msg = 'Успешно'){
		if($msg)
			$_SESSION['__result'] = $msg;
		
		if($pattern)
			$this->redirect = "http://".$_SERVER['HTTP_HOST'].Template::getInstance()->set_query($pattern);
		elseif($this->referer)
			$this->redirect = urldecode($this->referer);
		elseif(!$this->redirect){
			//if(getallheaders()['X-Requested-With'])
			//	$this->redirect = $_SESSION['HTTP_REFERER'];
			//else
				if(!$this->redirect = $_SERVER['HTTP_REFERER'])
					$this->redirect	= Template::getInstance()->set_query("?page=backend");
		}
		static::__destruct();
	}
			
	function __destruct()
	{	
		if($this->redirect){
			header("Location: ".$this->redirect);	
		}
	}	
}