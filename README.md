Движок для прототипирования различных приложений MVP

Отличительные особенности:

1 легкий в освоении (движок состоит из пары фаилов в папке core/)
2 MVC (разделение кода и ередставления)  паттерн
3 Скорость работы (в проекте по использованию как сервер для мобильных игр скорость обработки запроса составляет 0.05 секунд)
4 Можно сделать приложения с API (пример  - проект игрового сервера)

Используемые технологии:

PHP 8 (Reflection, Closure, Cli) , Mysql 8 , Postgre, Bootstrap 3, Smarty, Redis, Jquery, Composer, Apache (RewriteEngine)

PDO я не использую (во первых мне нужно рабочее кеширование Mysql, во вторых все запросы к БД мне важно видеть на чистом SQL)
Memcahe и тп я не использую (там где это эксктренно нужно, например при работе со справочниками , все даныне я привык помещать в ассоциативный массив и брать из него) 

S - да,пример папка core/ , но фаил core/Model.php не в счет (в нем методы-хелперы для работы)
O - да, пример core/Controller.php, core/Frontend.php, core/Backend.php 
L - да, пример - фаилы выше и проект онлайн сервера , папка app/map/Tiled и ее подпапки (сохранение карт Tiled из Xml в объекты PHP , загрузка - сохранения из Mysql, формирование изображения на GD)
I - интервейсы не использую (ограничиваюсь абстрактными классами, тк публичность не всегда нужна, а кроме обязательной реализации методов нужны и общие для всех методы с готовым телом)
D - пример core/database/ , однако я не объявляю модели контроллера вручную, и у __construct модели нет параметров


Структуру к БД я НЕ выдаю, но проект открыт для обозрения и тестирования по 95.216.204.181:8080 (сервер для онлайн игр)


Общее описание папок:

	app/ - список приложений
	core/ - движок
	data/ - контент приложения что закачивается движком (метод upload) сохраняется туда
	theme/ - общие шаблоны дизайна
	tmp/ - временные фаилы , в частности кеш страниц Smarty