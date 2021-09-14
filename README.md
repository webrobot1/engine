Движок и разработки MVP продуктов и прототипов с чистого листа.
Структуры к БД выдаются по запросу, проект открыт для обозрения и тестирования по 95.216.204.181:8080 (сервер для онлайн игр), ниже описание и инструкции для тех, кому БД выдана 	

Отличительные особенности:

	1  легкий в освоении (движок состоит из пары фаилов в папке core/)
	2  MVC (разделение кода и представления)  паттерн
	3  Скорость работы (в проекте "Онлайн игры" ответ сервиса передвижения составляет 0.05 секунд)
	4  Можно сделать приложения с API (пример в проекте "Онлайн игры")	
	5  Нет "лишних" зависимостей и библиотек, проект использует лишь то, что реально нужно
	6  В комплекте идет система авторизации и настрйоки прав доступами
	7  В комплекте идет приложения для создания правок к разделам
	8  В комплекте идет приложение для непрерывной интеграции
	9  На нем реализована сервер и CMS (система управления контентом) для клиентских "Онлайн игр" на PHP с API
	10 Поддержка микросервисной архитектуры и взаимодействие приложений в CLI режиме

Используемые технологии:

	PHP 8 (Reflection, Closure, Cli) , Mysql 8 , Postgre, Bootstrap 3, Smarty, Redis, Jquery, Composer, Apache (RewriteEngine)
	PS PDO я не использую (во первых мне нужно рабочее кеширование Mysql, во вторых все запросы к БД мне важно видеть на чистом SQL), однако защита от SQL инъекций имеется (все данные из вне проходят обработку в core/Controller)
	PS Memcahe и тп я не использую тк профилирую скорость работы (там где это необходимо кеширование на время работы скрипта, например при работе со справочниками , все данные я лично помещать в ассоциативный массив и брать из него)
	PS дополнительные необходимые расширения указаны в фаиле composer.json	

Общеизвестный прицип:

	S. - да,пример папка core/ , но фаил core/Model.php не в счет (в нем методы-хелперы для работы)
	O. - да, пример core/Controller.php, core/Frontend.php, core/Backend.php 
	L. - да, пример - фаилы выше и проект <a href='https://github.com/webrobot1/app-map/tree/master/model/Tiled'>Онлайн игры - Карты</a> (сохранение карт Tiled из Xml в объекты PHP , загрузка - сохранения из Mysql, формирование изображения на GD)
	I. - интервейсы не использую (ограничиваюсь абстрактными классами, тк публичность не всегда нужна, а кроме обязательной реализации методов нужны и общие для всех методы с готовым телом)
	D. - пример core/database/ , однако я не объявляю модели контроллера вручную, и у __construct модели нет параметров

Общее описание папок:

	app/ - список приложений (уже стоят ссылка на реппозитории основных)
	  ....
		cfg/			- содержит фаил int.php с доступами к бд
		controller/		- контроллер содержащий методы (action), работа с шаблонизатором доступна через $this->view, а с GET, POST данными через $this->"название переменной"
		model/			- модели приложения (одноименно с названием контроллера подгружаются автоматически и доступны в контроллера через $this->model)
		theme/			- персональные шаблоны дизайна html (используется шаблнизатор Smarty)
		label.png		- изображение приложения в админ панели
	core/ 				- фаилы движка
	  database/			- адаптеры для работы с разными SQL СУБД
	data/ 				- контент приложения что закачивается движком (метод upload) сохраняется туда
	theme/ 				- общие шаблоны дизайна
	tmp/ 				- временные фаилы , в частности кеш страниц Smarty
	vendor/ 			- сторонние библиотеки, появится когда в папке проекта выполнится "composer update" (обязательно)
	composer.json		- фаил с зависимостями Composer (в нем же указаны какие расширения php и версия необходимы)
	.gitmodules			- в нем уже прописано, что в папку app/ необходимо добавить зависимые реппозитории (Авторизацию, CI/CD, Справку)
	
Правила формирования УРЛ (статичны):

	http://ваш-сайт/<приложени из app>/<controller>/<action>/<json представление GET данных>	
	
После клонирования репозитория необходимо

	выполнить git submodule update --init
	выполнить composer update
	изменить конфигурационные фаилы приложений в их папке cfg/int.php
	выставить папкам и фаилам пользователя от которого работает Apache (Nginx) (обычно www-data)
	не доблавяйте в отслеживание фаилы :
		app/<приложение>/cfg/int.php (в них хранятся данные БД)
		.gitmodules (в них вы можете добавлять ссылки на репозитории-приложения папки app/)
		composer.json (у вас в проекте могут быть свои зависимости и билиотеки)
	прочитать README приложений в папке app/	
		
	PS В случае если они добавились в сотлеживание их можно удалить командой:	git update-index --skip-worktree path/to/file .
	PS если в родителький репозитория обновились эти фаилы для решения конфликта удалите, получите изменения, и перезапишите если не удается решить конфликт 
	Так можно и свои правки в преокт вносить и обновлять

Рекомендуемые серверные настройки: 

	#Mysql для сервера с 16Гб оперативной памяти
	[mysql]
	default-character-set=utf8mb4
	[mysqld]
	max_allowed_packet = 3G
	innodb_write_io_threads = 8
	innodb_read_io_threads = 8
	lc_time_names='ru_RU'
	innodb_file_per_table = 1			// лично мое мнение не хранить все бинарные данные баз в одном фаиле
	innodb_buffer_pool_instances = 6
	innodb_buffer_pool_size = 10G		// 80% оперативной памяти сервера
	innodb_log_file_size = 512M
	innodb_log_buffer_size = 16M
	innodb_page_size = 65536			// для работы с json полями (на котоыре можно ставить индексы) большого объема данных (потребуется переустановка Mysql)
	innodb_lock_wait_timeout=600		// блокировки строки
	lock_wait_timeout = 180				// блокировки meta data
	max_connections = 1000				// по желанию
	sort_buffer_size = 2M
	max_error_count = 65000				// если ваши приложения собирают сообщения о предупреждениях mysql
	max_execution_time = 0				
	skip-log-bin=1						//отключить репликации (При необходимости) 
	bind-address = 0.0.0.0				// доступ из вне к бд (При необходимости)

	#фаил apache2.service
	[Service]
	PrivateTemp=false		// для многопоточной работы php с временной папкой

Дополнительно рекомендую :

	1. создать cron задание для удfлания больших фаилов в папке tmp/ тк логируются warnings:
		0 6 * * * find <путь до папки>/tmp/ -type f -size +10M -exec rm -f {} \; >> /<путь до папки>/tmp/purge.log 2>&1
	2. задание на бекап баз данных 
		0 5 * * * mysql -u<пользователь Mysql> -p<пароль> -e 'show databases' | while read dbname; do if echo $dbname | grep -Eq '<префикс баз для бекапа>_'; then mysqldump -u<пользователь Mysql> -p<пароль>  --no-autocommit -l -q -e -K "$dbname"|gzip > <путь для сохранения>/`date '+\%d.\%m.\%Y'`_"$dbname".sql.gz; fi; done
	3. задание Cron на удаление старых бекапов
		0 6 * * * find /srv/backup/bingo/ -type f -mtime +14 -exec rm {} \; >> /srv/backup/bingo/purge.log 2>&1


Внимание! Если в Mysql(или другая субд) установлена на другой диск :

	в php.ini изменить pdo_mysql.default_socket = 
	в php.ini изменить mysqli.default_socket
	в apparmord mysqld поменять пути