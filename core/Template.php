<?php
namespace Edisom\Core;
use \Edisom\App\help\model\BackendModel as Translate;

// класс обертка для акещтеутв и backend
// может использоваться как адаптер для других шабьлонизиторов
class Template extends \Smarty
{	
	static $instances = null;
	public static $backend = false;
	
    public static function getInstance()
    {
        if (!isset(self::$instances)) {
            self::$instances = new static();
        }

        return self::$instances;
    }	
	
	private function __construct()
    {
		parent::__construct();

		$this->registerPlugin('modifier', "set_query", array($this, 'set_query'));		
	}
	
	private function __clone(){}
	
	private function _prepare_query(array $array, bool $reset = false, array &$get = null, &$receved = array()){
	
		foreach ($array as $key=>$value){
			if(!is_array($value)){
				if($value!=''){					
					$receved[$key] = $value;
				}else {
					if($reset){
						if(isset($get[$key]))
							$receved[$key] = $get[$key];
					}
					elseif(isset($get[$key]))
						unset($get[$key]);
				}
			}
			else{
				// тот случай когда &halls=6 в баузрной строке а в шаблоне мы хотим &halls[]=6 (что равнозначно halls[0]=6 а halls[0]  из halls = 6 мы не можем получить тк это строка (хотя halls есть) и наступает коллизия что в браузере halls - строка, которую нужно дополнить в halls из шаблона который там в виде массива) и есть коллизия - удалим параметр $_GET[$key]  через ссылку на этот глобальный элемент
				if(!empty($get[$key]) && is_string($get[$key]))
				{	
					$null = array();
					$this->_prepare_query($value, $reset, $null, $receved[$key]);
				}
				else{
					$this->_prepare_query($value, $reset, $get[$key], $receved[$key]);
				}
			}
		}

		return $receved;		
	}
	
	private function prepare_query($_vars):array
	{
		$ReceivedTokens = array();
		
		// если знак ? вначале то новый урл идет без текущих GET параметров , иначе - сформируется совместно с ними  
		if(substr($_vars, 0, 1) == '?'){
			$reset = true;
			$_vars = substr($_vars, 1, strlen($_vars)-1);
		}else{
			$reset = false;
		}
		
		// сохраним оригинальный GET до изъятия из него переменных для формирования url строки
		$get = $_GET;	
		if($_vars){
			parse_str($_vars, $tokens);
			$ReceivedTokens = $this->_prepare_query($tokens, $reset, $get);			
		}

		$newGetVars = ($reset?$ReceivedTokens:($ReceivedTokens?array_replace_recursive(array_filter($_GET),$ReceivedTokens):$get));
		
		return $newGetVars;
	}	
	
	// метод что формирует URL для ссылок |set_query	
	final public function set_query($_vars)
	{
		if(!strlen($_vars))
			return $_SERVER['REQUEST_URI'];
		
		$newGetVars = $this->prepare_query($_vars);	
		$app = (!empty($newGetVars['app'])?$newGetVars['app']:APP);
		unset($newGetVars['app']);
		unset($newGetVars['referer']);
		
		if(empty($newGetVars['page'])){
			if(static::$backend && @$newGetVars['page']!='frontend')
				$newGetVars['page'] = 'backend';
			else
				$newGetVars['page'] = 'frontend';
		}
		
		if(empty($newGetVars['action']))
			$newGetVars['action'] = 'index';
		
		if(
            static::$backend 
                && 
            $newGetVars['page']!='frontend' 
                && 
            ($app!='help' || $newGetVars['page']!='backend')
                && 
            ($userModel = \Edisom\App\user\model\BackendModel::getInstance()) && !$userModel->check_admin($newGetVars['page'], $newGetVars['action'], $app)
        ){
			return "###";	
		}
		
		$RenderedURL = '/'.$app.'/';
	
		$RenderedURL .= $newGetVars['page'].'/';						
		unset($newGetVars['page']);		
		
		if($newGetVars['action'] != 'index' || count($newGetVars)>1)
			$RenderedURL .= $newGetVars['action'].'/';		
		
		unset($newGetVars['action']);
		
		$RenderedURL = rtrim($RenderedURL, '/').'/';
	
		if($newGetVars = array_filter($newGetVars))
		{		
			if(!DEFINED("DEBUG") || !DEBUG)
				$RenderedURL .= base64_encode(json_encode($newGetVars,JSON_UNESCAPED_UNICODE));
			else
				$RenderedURL .= urlencode(json_encode($newGetVars,JSON_UNESCAPED_UNICODE));
		}

		if(!empty(getallheaders()['X-Requested-With']) && $_SESSION['HTTP_REFERER'])	
			$RenderedURL .= '?referer='.urlencode($_SESSION['HTTP_REFERER']);
		
		return $RenderedURL;
	}
	
	function getTheme()
	{
		return (DEFINED('USER') && USER['default_theme']?USER['default_theme']:'default');
	}
		
	function display($template = NULL, $cache_id = NULL, $compile_id = NULL, $parent = NULL){
		header('Content-Type: text/html; charset=utf-8', true);
		
		if($template == 'index.html') 
			throw new \Exception('Нельзя вызывать index фаил');		
		
		$_SESSION['HTTP_REFERER'] = $_SERVER['REQUEST_URI'];
				
		if(!empty(getallheaders()['X-Requested-With']))
		{
			parent::display($template, $cache_id , $compile_id , $parent );	
		}
		else{
			if(!$content = $this->getTemplateVars('content'))
				$this->assign('content', $this->fetch($template));

			parent::display(($content?$template:'index.html'), $cache_id , $compile_id , $parent );	
		}

		if((DEFINED("DEBUG") && DEBUG && !@getallheaders()['X-Requested-With'])){
			unset($_SESSION['__result']);			
			echo '<center style="position:fixed; top:50px; right:10px; z-index: 9999999">';
				echo 'Тестовый режим <br/>';
				
				//$mysql_time = $this->model->query("SELECT sum(FORMAT(DURATION, 6)) AS DURATION FROM INFORMATION_SCHEMA.PROFILING ORDER BY SEQ")[0]['DURATION'];
				$mysql_time = 0;
				
				$string = '';
				$sqls = array();
				
				// отсортируем по времени
				if($_SESSION['profiling']){
					$profiling = array_reverse($_SESSION['profiling']);
						
					// на каждый запрос сколько тратится (ранее было  SHOW PROFILES : но там всего 100 результатов)
					foreach($profiling as $sql){
						$mysql_time += $sql['Duration'];

						$text = $sql['SQL_TEXT'];
						
						if(empty($sqls[$text]) && ($sql['Duration']>0.02 || $sql['count']>1)) { 
							$string .= "<tr><td>".$sql['Duration']."</td><td>".$sql['count']."</td><td><textarea rows='4' cols='35' class='form-control'>".htmlspecialchars($sql['SQL_TEXT'])."</textarea></td></tr>\n";
							$sqls[$text] = true;
						}
					}
				}
				echo 'Mysql total time:'.round($mysql_time, 3).'<br/>';	
				if($string){
					echo ' <button onclick="javascript:$(\'#mysql_panel\').toggle(\'display\')" class="btn btn-info">SQl запросы</button>';
					echo '<div style="max-height:700px;overflow-y:scroll;display:none;background-color:#fff;width:600px;" id="mysql_panel" >';
						echo '<table class="table table-bordered table-striped">';
							echo '<tr><th>Время</th><th>Кол-во</th><th>Запрос</th></tr>';
							echo $string;
						echo '</table>';
					echo '</div><br/><br/>';	
				}
				$getrusage = array_merge(getrusage(), ['microtime'=>microtime(true)]);
				
				$php_time = microtime(true) - START_TIME_CHECK['microtime'] - $mysql_time;
				echo 'PHP total time:'.round($php_time, 3).'<br/>';
				
		
				echo ' <button onclick="javascript:$(\'#php_panel\').toggle(\'display\')" class="btn btn-info">PHP время</button>';
				echo '<div style="max-height:700px;overflow-y:scroll;display:none;background-color:#fff;width:600px;" id="php_panel" >';
					echo '<table class="table table-bordered table-striped">';
						echo '<tr><th>Время</th><th>Кол-во</th></tr>';
						foreach($getrusage as $key=>$value)
							if($value)
							{
								switch($key)
								{
									case 'ru_utime.tv_sec':
									case 'ru_stime.tv_sec':
									case 'microtime':
										continue(2);
									break;
									
									case 'ru_utime.tv_usec':
									case 'ru_stime.tv_usec':
										$index = explode('.', $key)[0];
										$value = ((int)$getrusage[$index.'.tv_sec'] + $value/1000000) - ((int)START_TIME_CHECK[$index.'.tv_sec'] + START_TIME_CHECK[$key]/1000000) .' сек.';
									break;																					
									case 'ru_maxrss':
										$value = round($value/1024, 2) .' Мб.';
									break;	
								}
								
								echo "<tr><td>".Translate::translate($key)."</td><td colspan='2'>".$value."</td></tr>\n";
								
							}
					echo '</table>';
				echo '</div><br/><br/>';				
				
				echo 'HTML total time: <span id="html_time"></span>';
				echo '<div id="total_time" class="hidden">Total time: <span></span> сек.</div>';
				
				echo '<script type="text/javascript">
					$(function() {
						var html_time = $.now() / 1000 - '.microtime(true).'; 
						if(html_time<0) html_time = 0;
						$("#html_time").html(html_time.toPrecision(3)); 
						
						var total_time = html_time + '.($php_time + $mysql_time).';
						$("#total_time span").html(total_time.toPrecision(3)); 
						$("#total_time").removeClass("hidden"); 
					})
				</script>';
						
			echo '</center>';
			die();
		}	
		exit();
	}
}
?>