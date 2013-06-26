<?php
/*************************************************************************/
/***********************Конфигурация спам-скрипта*************************/
/*************************************************************************/


/********************/
/*Подключение к БД***/
/********************/
$db_host = "localhost";
$db_name = "mailing_db";
$db_user = "root";
$db_pass = "stampaviva";
$db_prefix = "spamer_";


//Таблица из БД по которой делаем рассылку
$send_table="spamer_emails_zarplata";

/********************/
/********************/


/********************/
/*Отправка сообщений*/
/********************/
$smtp_host = "192.168.155.1";
$smtp_port = 25;

/*
$smtp_user = "irina";
$smtp_pass = "Stampa123";
*/

$smtp_user = "news";
$smtp_pass = "nxxb0gt";


//Общие настройки рассылаемого сообщения
$msg_from = "news@stampaviva.ru"; //от имени какого email производится рассылка
//$msg_from = "irina@stampaviva.ru";

$msg_from_fio = "СТАМПА ВИВА"; //ФИО владельца
$msg_return_adr = "news@stampaviva.ru"; //адрес, на который будут сыпаться данные об ошибках в доставке письма
$msg_prioritet = 3; //приоритет сообщения (3 - нормальный) чем меньше цифра, тем выше приоритет

/********************/
/********************/



//*******************************//
//Настройки генератора изображений
//*******************************//

//Название файла нужно болванки
$img_gen_bolvank = dirname(__FILE__)."/templates/bolvanki/8marta2013.jpg";

//Расширение болванки, с которым работает программа (JPG,PNG)
$img_gen_bolvanka_type="JPG";

//Альтернативная картинка, если генерация изображения не удалась
$img_gen_alter="/templates/img/8marta2013.jpg";

//Название файла шрифта
//!Шрифты загружать в корень папочки fonts
$img_gen_font_file=dirname(__FILE__)."/fonts/MagistralBold.otf";
//Кодировка шрифта
$img_gen_font_code="UTF-8";

//Путь до папки, куда нужно класть сгенерированные изображения
$img_gen_final_path=dirname(__FILE__)."/templates/gen-result";

$img_gen_final_web_path="/templates/gen-result";
//*******************************//



/********************/
/*Прием сообщений****/
/********************/

//Настройки подключения по IMAP
$imap_srv = "imap.gmail.com";
$imap_port = "993";
$imap_user = "pronin1986@gmail.com";
$imap_pass = "sergey3206232";
/********************/
/********************/



//Подключаемся к серверу MySQL и выбираем БД
try {
		$db = new PDO("mysql:host={$db_host};dbname={$db_name}","{$db_user}", "{$db_pass}", array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
	}
catch(PDOException $e)
	{
		die("Подключение к БД не удалось: ".$e->getMessage());
	}



//ID рассылки
$sth = $db->prepare("SELECT MAX(`id`) as mailing_id FROM `spamer_mailing` LIMIT 1");    
$sth->execute();
$current_mailing_info = $sth->fetch(PDO::FETCH_ASSOC);
if(is_array($current_mailing_info) AND empty($current_mailing_info)==false)
{
	$current_mailing_info['mailing_id']=intval($current_mailing_info['mailing_id']);
	if($current_mailing_info['mailing_id']>0)
	{
		$mailing_id = $current_mailing_info['mailing_id'];
	}
	else
	{
		echo "ID рассылки либо не INT, либо < 1 \n";
		exit;		
	}

}
else
{
	echo "Ошибка запроса при выборе текущей рассылки \n";
	exit;
}

?>
