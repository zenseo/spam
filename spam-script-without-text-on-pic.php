#!/usr/bin/php
<?php
/**
 * Собсна скрипт для рассылки. Работает просто: 
 * 1. Передаем ID рассылки, которую надо разослать
 * 2. Все.
*/

//объявляем константу ROOT_PATH которая будет храть абсолютный путь от корня сервера до этого скрипта
define("ROOT_PATH", dirname(__FILE__));

//подключаем конфигурацию скрипта
require_once "config.php";


//подключаем свифт мэйлер которым будем отправлять почту
require_once ROOT_PATH."/lib/swift_required.php";

/**
 * подключаем шаблонизатор, которым будем допиливать HTML код шаблона рассылки: 
 *  - подключать изображения
 *  - генерировать ссылку для отказа от рассылки
*/
require_once ROOT_PATH."/lib/classes/template.class.php";


//получаем данные о текущей рассылке из БД
$stmt = $db->prepare("SELECT * FROM `{$db_prefix}mailing` WHERE `id`= :m_id LIMIT 1");
$stmt->bindValue(':m_id', $mailing_id, PDO::PARAM_INT);
$stmt->execute();
$mailing = $stmt->fetch(PDO::FETCH_ASSOC);

if($mailing!=false)
	{
		//проверяем, есть ли шаблон, который нужно разослать
		if($mailing['html_code']==NULL AND $mailing['tpl_path']==NULL)
			{
				echo "Рассылка с ID = {$mailing_id} не содержит шаблона, который нужно разослать";
				exit;
			}
		else
			{
				//транспорт
				$transport = Swift_SmtpTransport::newInstance($smtp_host, $smtp_port)->setUsername($smtp_user)->setPassword($smtp_pass);
				
				//мэйлер
				$mailer = Swift_Mailer::newInstance($transport);

				
				//Вытаскиваем из БД пользователей, которым рассылка не дошла
				$stmt = $db->prepare("SELECT * 
                                                        FROM  `{$send_table}` AS email
                                                        WHERE email.status =1
                                                        AND (

                                                        SELECT COUNT( * ) 
                                                        FROM  `{$db_prefix}mailing_result` 
                                                        WHERE  `email_id` = email.id
                                                        AND  `mailing_id` ={$mailing['id']}
                                                        ) =0");    


				$stmt->execute();
				$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
				
				
				//массив для значений полей mailing_id и email_id
				$ins_values = array();
				
				//Счетчик, который считает успешно отправленные письма
				$ins_count=0;

				//запускаем цикл, который проходится по email адресам
				foreach($emails as $index=>$mail)
					{

						//Проверка по СТОП-ЛИСТУ
						$query = "
						SELECT COUNT( * ) AS blocked_mail 
							FROM  `mail_stop_list` 
							WHERE  `email_hash` = MD5(  '{$mail['email']}' )
						";
						$stmt = $db->prepare($query);
						$stmt->execute();
						$stop = $stmt->fetch(PDO::FETCH_ASSOC);
						if(isset($stop['blocked_mail']) AND $stop['blocked_mail']>0)
						{
							echo "СТОП ЛИСТ: {$mail['email']} (ID:{$mail['id']})! Пользователь отписан!\n\r";
						}
						else
						{
						//создаем письмо и инициализируем несколько постоянных его настроек
						$message = Swift_Message::newInstance();
				
						//адрес отправителя
						$message->setFrom(array($msg_from => $msg_from_fio));
				
						//обратный адрес, если вдруг что случится с письмом
						$message->setReturnPath($msg_return_adr);

						//Приоритет письма 3 - обычный приоритет. Чем меньше цифра, тем больше приоритет
						$message->setPriority($msg_prioritet);
				
						//На всякий случай - строка в письме не должная быть больше 1000 символов, т.к. это нарушает RFC
						$message->setMaxLineLength(1000);


						

						//Инициализируем переменную,где будем хранить шаблон письма
						$tpl = new  template();


						//выдергиваем окончательный вариант шаблона письма

						if($mailing['tpl_path']!=NULL)
							{
								$tpl->setTemplateAsFile($mailing['tpl_path']);
							}
						elseif($mailing['html_code']!=NULL)
							{
								$tpl->setTemplateAsHtml($mailing['html_code']);
							}
						
						$mailbody = $tpl->getTemplate();


/*
Собирали тему письма и ФИО.
Закоомментировано на прошлой рассылке (22.05.2013) за ненадобностью
						if(isset($mail['name']) AND empty($mail['name'])==false AND  $mail['name']!=="" AND is_null($mail['name'])==false)
                                                    {
							//patronimic
							$fio = ", ".$mail['name']."";
							$l_fio = $mail['name'];
							if(isset($mail['patronimic']) AND empty($mail['patronimic'])==false AND is_null($mail['patronimic'])==false)
							{
								$fio = $fio." ".$mail['patronimic'];
								$l_fio = $l_fio." ".$mail['patronimic'];
							}
                                                        $mailbody = str_replace("[::GREATINGS::]", $fio, $mailbody);
							//заголовок письма
							$letter_title = "{$l_fio}, {$mailing['name']}";
                                                    }
                                                else
                                                    {
                                                        $mailbody = str_replace("[::GREATINGS::]", "", $mailbody);
							$letter_title = "Ваша реклама может быть интересной";
                                                    }
*/
						

						if(isset($mail['name']) AND empty($mail['name'])==false)					
						{
							$greatings ="Здравствуйте, ".$mail['name'];

							$letter_title = $mail['name'];

							if(isset($mail['patronimic']) AND empty($mail['patronimic'])==false)
							{
								$greatings .=" ".$mail['patronimic'];
								$letter_title .=" ".$mail['patronimic'];
							}
							$greatings .="!";
							$letter_title .=", печатайте выгодно! Плюс 10% тиража бесплатно!";
						}
						else
						{
							$greatings = "Здравствуйте!";
							$letter_title = $mailing['name'];
						}

						$mailbody = str_replace("[::GREATINGS::]", $greatings, $mailbody);
						


                                               	$mailbody = str_replace("[::MAILTITLE::]", $letter_title, $mailbody);
						$mailbody = str_replace("[::MAILCODE::]", md5(strtolower($mail['email'])), $mailbody);



                                                $tpl->setTemplateAsHtml($mailbody);

						//уникальный ID отправляемого письма
						$letter_uid = "{$mailing['id']}l{$mail['id']}";

						//встявляем секретный код в ссылку отписывания от рассылок
						$tpl->setUnsubscribeCode($letter_uid);
						


                                                $tpl->imgReplace($message);
                                                $mailbody = $tpl->getTemplate();

                                                echo "Пытаемся отправить письмо на {$mail['email']} (ID:{$mail['id']}): ";
						//echo $mailbody;
						
						if($mailbody!=false)
							{
								if(filter_var($mail['email'], FILTER_VALIDATE_EMAIL)==false)
									{
										echo "Неудача";
									}
								else
									{

										$message->setSubject($letter_title);
										$message->setTo($mail['email']);
										$message->setReadReceiptTo($mail['email']);
										$message->setBody($mailbody, 'text/html');
										if($mailer->send($message))
											{
				                                                            //начало запроса на добавление записей в mailing_result
				                                                            $query = "INSERT INTO `{$db_prefix}mailing_result` (mailing_id, email_id) VALUES (:mailing_id, :email_id)";
				                                                            $stmt = $db->prepare($query);
				                                                            $stmt->bindValue(':mailing_id', $mailing['id'], PDO::PARAM_INT);
				                                                            $stmt->bindValue(':email_id', $mail['id'], PDO::PARAM_INT);
				                                                            if($stmt->execute()==false)
				                                                                {
				                                                                    echo "Неудача";
				                                                                }
				                                                            else
				                                                                {
				                                                                
				                                                                    echo "Успех";
				                                                                    $ins_count++;
				                                                                    sleep(rand(30, 50));    
				                                                                }
											}
										else
											{
												echo "Неудача";
											}


									}
//exit;
								echo "\n\r";
							}
unset($mailbody);
unset($pic_path);
						}
					
					}
				
				if($ins_count==0)
					{
						echo "Все что нужно было разослать - разослано!!!";
					}
			}
	}
else
	{
		echo "Рассылка с ID = {$mailing_id} отсутствует в БД";
		exit;
	}
        
echo "Успешно отправлено: ".$ins_count." писмо :-) \n\r";
