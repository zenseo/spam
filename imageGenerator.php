<?php

//подключаем конфигурацию скрипта
require_once "config.php";

//функция ресайзинга изображений. работает с рессурсом
function resizeImage($originalImage,$toWidth,$toHeight) 
{ 
    //вычисляем ширину и высоту исходника
    $width = imagesx($originalImage);
    $height = imagesy($originalImage);

    //вычисляем 
    $xscale=$width/$toWidth; 
    $yscale=$height/$toHeight; 

    if ($yscale>$xscale){ 
        $new_width = round($width * (1/$yscale)); 
        $new_height = round($height * (1/$yscale)); 
    } 
    else { 
        $new_width = round($width * (1/$xscale)); 
        $new_height = round($height * (1/$xscale)); 
    } 
    
    
    $imageResized = imagecreatetruecolor($new_width, $new_height); 
    $imageTmp     = $originalImage; 
    imagecopyresampled($imageResized, $imageTmp, 0, 0, 0, 0, $new_width, $new_height, $width, $height); 

    return $imageResized; 
    

}

function fioToImg($w,$h,$fio, $font, $b_type, $b_f_path)
    {
        $img = imagecreatetruecolor($w, $h);
        $red = imagecolorallocate($img,  255, 255, 255);
        $color = ImageColorAllocate($img, 235, 102, 31);
        imagefill($img,0,0,$red);
        imagettftext($img, 23, 0, 0, 23, $color, $font, $fio);
        
        $img = resizeImage($img, 540, 45);
        
	if($b_type=="PNG")
	{
		if(imagepng($img, $b_f_path."/fio_tmp.png"))
		    {
		        return $img;
		    }
		else
		    {
		        return false;
		    }
	}
	elseif($b_type=="JPG")
	{
		if(imagejpeg($img, $b_f_path."/fio_tmp.jpg"))
		    {
		        return $img;
		    }
		else
		    {
		        return false;
		    }
	}
	else
	{
		return false;
	}

        ImageDestroy($img);
    }


function imageGen($pic_name=null, $fio=null, $b_img, $b_font, $b_type, $b_f_path, $b_f_w_path, $b_font_code)
	{

		if($pic_name==null OR $fio==null)
			{
				return false;
			}
		else
			{	
				//путь к болванке изображения
				if($b_type=="PNG")
				{
					$img = imagecreatefrompng($b_img);
				}
				elseif($b_type=="JPG")
				{
					$img = imagecreatefromjpeg($b_img);
				}
				else
				{
					return false;
				}
				
				//цвет
				$color = ImageColorAllocate($img, 235, 102, 31);


				//Путь до шрифта
				$font = $b_font;

                                //считаем каой размер будет у фамилии на нашей открытке

				//Если кодировка шрифта CP1251, то переводим её в текст!
				if($b_font_code=="CP1251")
				{
					$fio = mb_convert_encoding($fio, "cp-1251","utf-8");
				}

				$coord = imagettfbbox(
				     23,  // размер шрифта
				     0,          // угол наклона шрифта (0 = не наклоняем)
				     $font,  // имя шрифта, а если точнее, ttf-файла
				     $fio       // собственно, текст
				     );

 

				$width = $coord[2] - $coord[0];
				$height = $coord[1] - $coord[7];

                                //если ширинна фамилии на открытке будет больше 540 пикселей, то сжимаем фамилию

                                if($width>540)
                                    {

                                        $fio_pic = fioToImg($width, $height, $fio, $font, $b_type, $b_f_path);
                                        
                                        $txt_height = imagesy($fio_pic);
                                        $padding_top = 38 + (24 - $txt_height);
                                        $height = imagesy($fio_pic);
                                        imagecopyresized ($img,$fio_pic,
                                                  45,$padding_top,
                                                  0,0,
                                                  imagesx($fio_pic),imagesy($fio_pic),
                                                  imagesx($fio_pic),imagesy($fio_pic));
                                    }
                                else 
                                    {
                                        //если фамилия умещается в размеры, то просто добавляем к изображению текст
                                        imagettftext($img, 27, 0, 29, 58, $color, $font, $fio);
                                    }


				if($b_type=="PNG")
				{
					//путь и имя генерируемого файла
					$dest = $b_f_path."/{$pic_name}.png";
				
					//пытаемся сгенерировать и сохранить изображение
					if(imagepng($img,$dest))
						{
							return $b_f_w_path."/{$pic_name}.png";
						}
					else
						{
							return false;
						}
				}
				elseif($b_type=="JPG")
				{
					//путь и имя генерируемого файла
					$dest = $b_f_path."/{$pic_name}.jpg";
				
					//пытаемся сгенерировать и сохранить изображение
					if(imagejpeg($img,$dest))
						{
							return $b_f_w_path."/{$pic_name}.jpg";
						}
					else
						{
							return false;
						}
				}
				else
				{
					return false;
				}

				ImageDestroy($img);
			}
                unset($img);
	}



imageGen("2", "Дорогая Ольга!", $img_gen_bolvank, $img_gen_font_file, $img_gen_bolvanka_type, $img_gen_final_path, $img_gen_final_web_path, $img_gen_font_code);

?> 
