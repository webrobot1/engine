<?php
namespace Edisom\Core;

abstract class Backend extends Frontend 
{	
	protected function __construct(string $query = null)
	{	
		parent::__construct($query);	
		
		// todo plugin систему сделать	
		
		############шаблонизатор###############
		$this->view::$backend = true;

		$this->view->assign("faq", \Edisom\App\help\model\BackendModel::getInstance()->faq(null, array('app'=>APP, 'page'=>$this->page, 'action'=>($this->action?$this->action:'index'))));
		
		if(file_exists('smarty/'))		
			$this->view->addPluginsDir('smarty');

		############шаблонизатор###############

		// если сопрут сессию то плохо, но я не стал защищать. этож прототип
		if((($login = $this->login) || ($login = $_SESSION['login'])) && (($this->password && ($hash = md5(trim($this->password)))) || ($hash = $_SESSION['hash']) || ($password = $_SESSION['password']))){
			$userModel = \Edisom\App\user\model\BackendModel::getInstance(); 
			
			if($user = $userModel->login($login, $hash, $password)){
				$this->view->assign('user', $user); 
				DEFINE('USER', $user); // пустой или нет но объявим что бы не обращаться к несуществующей константе
				
				if($this->password && APP=='backend' && USER['default_app'] && (USER['permissions'][USER['default_app']] || USER['login'] == 'admin'))
					$this->redirect('?app='.USER['default_app'], "");		
			}
			else{
				unset($_SESSION['hash']);
				unset($_SESSION['login']);
				unset($_SESSION['password']);
			}
		}	
		
		// перезапишем указав индексы (кторым параметром)
		$this->view->addTemplateDir('./theme/'.$this->view->getTheme().'/backend', 0);
		$this->view->addTemplateDir(SITE_PATH.'/theme/'.$this->view->getTheme().'/backend', 1);	
		
		if($this->view->getTheme()!='default'){
			$this->view->addTemplateDir('./theme/default/backend');
			$this->view->addTemplateDir(SITE_PATH.'/theme/default/backend');	
		}
		
		if(!defined('USER') || !USER) 
		{ 
			if(file_exists('../../app/user/theme/'.$this->view->getTheme().'/login.html'))
				$this->view->display('../../app/user/theme/'.$this->view->getTheme().'/login.html');	
			else
				$this->view->display('../../app/user/theme/default/login.html');
			
			exit();
		}
		elseif(!$userModel->check_admin($this->page, $this->action, APP) && (APP!='help' || $this->page!='backend'))
		{
			static::_403();	
		}

		$this->view->assign('applications', $this->model::applications());		
	}
	
	final function logout(){
		unset($_SESSION['login']);
		unset($_SESSION['hash']);
		$this->redirect("?", '');
	}	
}