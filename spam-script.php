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


require_once "imageGenerator.php";

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

				
 				//Выбираем юзеров, которым не отправляли письмо
				$stmt = $db->prepare("SELECT email.id, email.email, email.name
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
				
				$ins_count=0;
                                
                                    
				//запускаем цикл, который проходится по email адресам
				foreach($emails as $index=>$mail)
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
						
						//Загружаем шаблон письма
						$tpl = new  template();
						if($mailing['tpl_path']!=NULL)
							{
								$tpl->setTemplateAsFile($mailing['tpl_path']);
							}
						elseif($mailing['html_code']!=NULL)
							{
								$tpl->setTemplateAsHtml($mailing['html_code']);
							}

						//уникальный ID отправляемого письма
						$letter_uid = "{$mailing['id']}l{$mail['id']}";
						
						//встявляем секретный код в ссылку отписывания от рассылок
						$tpl->setUnsubscribeCode($letter_uid);
						
						//заголовок письма
						$letter_title = "{$mail['name']} {$mailing['name']}";
						

						//выдергиваем окончательный вариант шаблона письма
						$mailbody = $tpl->getTemplate();
						
						$mailbody = str_replace("[::MAILTITLE::]", $letter_title, $mailbody);
						
						//Это все части рассылки с кастомными картинками
						if(is_null($mail['name']) OR empty($mail['name']))
						{
							$pic_path=false;
						}
						else
						{
							$pic_path = imageGen($mail['id'], trim($mail['name']), $img_gen_bolvank, $img_gen_font_file, $img_gen_bolvanka_type, $img_gen_final_path, $img_gen_final_web_path, $img_gen_font_code);
						}

						if($pic_path==false OR empty($pic_path))
						{
							$mailbody = str_replace("[::MAILCARD::]", $img_gen_alter, $mailbody);
						}
						else
						{
							$mailbody = str_replace("[::MAILCARD::]", $pic_path, $mailbody);
						}

                                                $tpl->setTemplateAsHtml($mailbody);
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

								echo "\n\r";
							}
unset($mailbody);
unset($pic_path);
					}
				
				if($ins_count==0)
					{
						echo "Все что нужно было разослать - разослано!!!";
					}
				
			}
	}
else
	{
		echo "Рассылка с ID = {$mailing_id} not found";
		exit;
	}
        
echo "Успешно отправлено: ".$ins_count."+1 писмо :-) \n\r";
?>
