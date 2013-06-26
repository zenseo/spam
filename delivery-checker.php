<?php
error_reporting(E_ALL);
/**
 * Скрипт подтверждения прочтения письма
 * Пытается определить прочитал ли получатель письмо по двум критериям: 
 *   1. Получатель нажал на кнопку "Отправить подтверждение о прочтении" и к нам на ящик пришло подтверждение о доставке, которое мы парсим на предмет наличия секретного кода, по которому определяем рассылку и получателя.
 *   2. В письме имеется ссылка на png картинку вид <img src=http://somedomen.ru/deliveryconfirm/imageXXlYY.png> где XX - это номер рассылки, а YY - IDшник email адреса. При этом с помощью mod_rewrite картинка заменяется на коварный php скрипт, который, под видом PNG картинки, распарсивает название файла. 
*/

//подключаем конфигурацию скрипта
require_once "config.php";

//если массив $_GET существует и в нем определен элемент $_GET['code'], то пользователь загрузил картинку
if(isset($_GET) AND empty($_GET['code'])==false)
	{
		header("Content-Type: image/jpeg");
                header("X-Powered-By: by Pronin;");
                header("Accept-Ranges: bytes;");
                header("Last-Modified: Tue, 11 Nov 2010 14:24:51 GMT;");

                
		$code = $_GET['code'];
		$img = imageCreate(30, 20);
		$color = imageColorAllocate($img, 255, 255, 255);
		$black = ImageColorAllocate($img, 0, 0, 0);
		imageFilledRectangle($img, 0, 0, imageSX($img), imageSY($img), $color);
		
		if(confirmDelivery("received_by_img", $code, $db, $db_prefix))
			{
				ImageString($img , 3, 5, 3, "+++", $black);
			}
		else
			{
				ImageString($img , 3, 5, 3, "---", $black);
			}

		imagejpeg($img);
		imageDestroy($img);
		
	}
else
	{
		//если картинку не генерируем, то начинаем просматривать почтовый ящик на наличие писем с подтверждалками (пока установлено, что только мэйл.ру отправляет подтверждение о прочтении, Yandex и Google игнорируют эту штуку)
		
		
		//открываем соединение и подключаемся к нашему ящику
		$mbox = imap_open ("{{$imap_srv}:{$imap_port}/imap/ssl}", "{$imap_user}", "{$imap_pass}");
		
		//считаем кол-во входящих писем
		$msg_counter = imap_num_msg($mbox);
		
		//проходим по всем входящим письмам и просматриваем содержание письма на предмет наличия конструкции [XlY], где X - это номер рассылки, а Y - IDшник email адреса
		while($msg_counter>0)
			{
				
				//устанавливаем кодировку для mb_функций
				mb_internal_encoding("UTF-8");
				
				//получаем заголовки письма
				$header = imap_header($mbox, $msg_counter);
				
				//из заголовков нас интересует Subject, в котором тоже может находится конструкция [XlY]
				$header->subject = mb_decode_mimeheader($header->subject);
				
				//получаем только текстовую версию тела сообщения
				$st = imap_fetchstructure($mbox, $msg_counter);
				if (!empty($st->parts)) 
					{
						for ($i = 0, $j = count($st->parts); $i < $j; $i++) 
							{
								$part = $st->parts[$i];
								if ($part->subtype == 'PLAIN') 
									{
										$body = imap_fetchbody($mbox, $msg_counter, $i+1);
									}
							}
					}
				else 
					{
						$body = imap_body($mbox, $msg_counter);
					}
				
				//письма, чаще всего, бывают закодированы либо в Quoted-printable, либо в Base-64.
				//пытаемся определеить кодировку письма
				$msg_body_base64 = imap_base64($body);
				if(empty($msg_body_base64)==false)
					{
						$body = $msg_body_base64;
					}
				else
					{
						$body = quoted_printable_decode($body);
					}
				
				//объединяем заголовок письма и тело письма в одну переменную
				$full_letter = "{$header->subject}{$body}";

				//ищем вхождение UID письма в его тексте
				$code = getCode($full_letter);
				
				//если находим, то начинаем работать с БД
				if($code!==false)
					{
						//echo "<strong>Письмо: {$msg_counter}. Код: {$code}</strong> <br />";
						//обновляем в БД информацию об этом  - ставим флажок, что писмо подтверждено кнопкой "Подтвердить получение".
						if(confirmDelivery("received_by_confirm", $code, $db, $db_prefix))
							{
								//и удаляем подтверждение с нашего почтового ящика
								imap_delete($mbox, $msg_counter);
								imap_expunge($mbox);
							}
					}
				
				$msg_counter--;
			}
		imap_close($mbox);
	}

	/**
	 * Функция пытается получить из текста конструкцию XlY
	 * @param string $str строка, в которой ищем вхождение конструкции
	 * @return string|bool 
	*/
	function getCode($str)
		{
			preg_match("/\[(\d{1,}l\d{1,})\]/mui", $str, $matches);
			if(empty($matches))
				{
					return false;
				}
			else
				{
					return $matches[1];
				}
		}
	
	/**
	 * Функция пытается получить из текста конструкцию XlY
	 * @param string $type содержит имя столбца в таблице mailing_result, которое соотвествует какому-либо способу подтверждения о прочтении
	 * @param string $code UID письма в формате XlY
	 * @param object $dblink сылка на соединение с БД
	 * @return bool Вернет true, если передаваемый тип подтверждения имеет значение 1 в таблице mailing_result. Вернет false в любом другом случае. 
	*/
	function confirmDelivery($type, $code, &$dblink, $prefix)
		{

			/*
			 * разбивая UID письма на состовляющие получим: 
			 * $params[0] - UID рассылки
			 * $params[1] - UID подписчика
			*/
			$params = explode("l", $code);
			
                        $prefix = mysql_real_escape_string($prefix);
                        
                        $params[1] = intval($params[1]);
                        $params[0] = intval($params[0]);

                        

                        $sql=true;  
                        if($sql!=false)
                            {
                            
                                $type = mysql_real_escape_string($type);
                                
                                //проверяем, значение переданного типа подтверждения для отправленного письма
                                $query = "SELECT `{$type}`, `id` FROM `{$prefix}mailing_result` WHERE mailing_id ='{$params[0]}' AND email_id ='{$params[1]}' LIMIT 1";
//                                echo $query;
//                                echo "</ br>";
                                $sql = mysql_query($query);
                                
                                $confirm_flags = mysql_fetch_array($sql);
                                
                                if(is_array($confirm_flags)==false OR empty($confirm_flags))
                                    {
                                        $query = "INSERT INTO `{$prefix}mailing_result` (mailing_id, email_id) VALUES ('{$params[0]}', '{$params[1]}')";
//                                        echo $query;
//                                        echo "</ br>";
                                        $sql = mysql_query($query);
                                        if($sql==false)
                                            {
                                                return false;
                                            }
                                        else
                                            {
                                                $query = "SELECT `{$type}`, `id` FROM `{$prefix}mailing_result` WHERE mailing_id ='{$params[0]}' AND email_id ='{$params[1]}' LIMIT 1";
//                                                echo $query;
//                                                echo "</ br>";
                                                $sql = mysql_query($query);
                                                $confirm_flags = mysql_fetch_array($sql);
                                            }
                                            
                                    }
                                
                                //если доставка не подтверждена текущим методом, то пытаемся обновить информацию
                                //а если подтверждена, то просто вернем true
                                if($confirm_flags[$type]==0)
                                        {
                                                $query = "UPDATE  `{$prefix}mailing_result` SET `{$type}`=1 WHERE id ='{$confirm_flags['id']}' LIMIT 1";
                                                $sql = mysql_query($query);
                                                
                                                if($sql!=false)
                                                        {
                                                                return true;
                                                        }
                                                else
                                                        {
                                                                return false;
                                                        }

                                        }
                                else
                                        {
                                                return true;
                                        }
                            }
                         else 
                            {
                                return false;
                            }

		}
?>
