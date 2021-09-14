<?php
namespace Edisom\Core;

abstract class Controller 
{	
	static $instances = array();
	
	protected $model;
	private $vars = array();
		
	function index(){
		static::_404("Главная страница не определена");
	}	
	
	// query - необязательный пакет с параметрами (полезен когда в Cli режиме идет все) 
	// те есть GET есть POST а есть этот пакет являющийся частью адресной строки
	// сделан что бы скрыть от пользователя что там за парметры мы передаем (те не голым GET)
	// нуждается в кодировании  и декодировании из вне (если вход через Frontend или Api контроллер - там Json )
	
	final static public function getInstance($query = null):static
	{	
		$calledClass = get_called_class();
        if (!isset($instances[$calledClass]))
        {
			if(class_exists($calledClass))
				$instances[$calledClass] = new $calledClass($query);
			else
				throw new \Exception("не найден class instance:".$calledClass);
        }
        return $instances[$calledClass];
    }
	
	final function __clone(){}
	
	protected function __construct(array $query = null)
	{		
		// присвоеим модель для работы с бд 
		if(!$this->model){
			if(($model = str_replace(array('Controller','controller'), array('Model', 'model'), get_class($this))) && class_exists($model))	
				$this->model = $model::getInstance();	
			else
				static::_404("не найден class:".$model);
		}
		
		if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'PUT'){
			$_POST['request'] = file_get_contents("php://input");
		}
		
		// записываем переменные из query строки в this[vars], запретим переопределять свйоства класса				
		if($query = array_replace_recursive((array)$query, (array)$_GET, (array)$_POST)){
			foreach($query as $key=>$value)	{
				if(!property_exists($this, $key))
					$this->$key = $value;		
				else
					throw new \Exception('Попытка записать данные '.$value.' в свойство '.$key);												
			}
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
		if($var = $this->model::prepare($var, false))		
			$this->vars[$key] = $var;
		
		// для smarty добавить из декодированной адресной строки переменные
		if(empty($_POST[$key]))
			$_GET[$key] = $this->vars[$key];	
		
        return true;
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