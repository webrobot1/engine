<?php
namespace Edisom\Core;

abstract class Controller 
{	
	protected $model;
	private $vars = array();
		
	function index(){
		static::_404("Главная страница не определена");
	}	
	
	private function __clone(){}	
	public function __construct(array &$query = null)
	{		
		// присвоеим модель для работы с бд 
		if(!$this->model){
			if(($model = str_replace(array('Controller','controller'), array('Model', 'model'), get_class($this))) && class_exists($model))	
				$this->model = $model::getInstance();	
			else
				static::_404("не найден class:".$model);
		}
		
		// записываем переменные из query строки в this[vars], запретим переопределять свйоства класса				
		foreach($query as $key=>&$value){
			if(!property_exists($this, $key))
				$value = $this->$key = $this->model::prepare($value, false);		
			else
				throw new \Exception('Попытка записать данные '.$value.' в свойство '.$key);												
		}
			
		// присвоим свою функции обработки ошибок уже с заголовками (в отличие от той что в модели)
		set_exception_handler(array($this, 'controllerExceptionHandler'));		
	}
	
	public function controllerExceptionHandler($ex)
	{
		header("HTTP/1.0 400 Bad Request");
		$this->model->exceptionHandler($ex);
	}
		
	// без визуализации - только заголовки (полезно для API)
	public static function _404(string $message){	
		header("HTTP/1.0 404 Not Found");
		echo $message;
		exit(NOT_FOUND_CODE);
	}
		
	protected static function _403(){
		header("HTTP/1.0 403 Forbidden");
		exit(FORBIDDEN_CODE);		
	}	
	
	final public function __call($methodName, $args) {
		static::_404("не найден метод ".$methodName);
	}	
	
	final public static function __callStatic($methodName, $args) {
		static::_404("не найден статический метод ".$methodName);
	}
	
	// все POST и GET данные суем в $this->  заодно подготавливая их для работы с Mysql
	public function __set($key, $var) 
	{ 			
		$this->vars[$key] = $var;
    }

	// магическеи метод вызова свойств this
	public function __get($name) {  
		if (isset($this->vars[$name]) == false) { //если свойства не было добавлено через GET переменные 
			return null;
		}
		return $this->vars[$name];		
	}	
		
	public function __isset($name) 
    {
        return isset($this->vars[$name]);
    }	
	
	public function __unset($name) 
    {
        unset($this->vars[$name]);
        unset($_GET[$name]);
        unset($_POST[$name]);
    }
}