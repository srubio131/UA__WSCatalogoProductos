<?php

class Utils
{
    /**
    * Dado un array crea un strig con todos los elementos separados por un delimitador
    * @param Array $array Array a transformar
    * @param String $delimitador Delimitador 
    * @return String El array separado por delimitador o cadena vacía si array vacío.
    */
    public function imprimirArrayConDelimitador($array, $delimitador)
    {
       $cadena = '';

       if (!empty($array))
       {
           foreach ($array as $elem)
               $cadena .= $elem . $delimitador;

           $cadena = trim($cadena, $delimitador);
       }

       return $cadena;
    }

    /**
    * Determina si alguno de los elementos de un array estan incluidos en otro array
    * @param array $elementos Array de arrays para buscar
    * @param array $array Array donde buscar
    * @return boolean True si encuentra algun elemento o False si no lo encuentra o $elementos está vacio o null
    */
    public function hay_elementos_en_array(array $elementos, array $array)
    {
       if (!empty($elementos))
       {
           foreach ($elementos as $elem)
           {
               foreach ($elem as $e)
               {
                   if (in_array($e, $array) == true)
                       return true;
               }
           }
       }
       return false;
    }
    
    
    /*public function sendEmail($mailto, $from_mail)
    {
        $mailto = filter_var($mailto, FILTER_VALIDATE_EMAIL);
        $from_mail = filter_var($from_mail, FILTER_VALIDATE_EMAIL);        
        
        // Comprobar que el log tenga criticals.
        if ($this->$_hay_criticals & ($mailto !== false || $from_mail !== false))
        {
            $file_size = filesize($this->_path);
            $handle = fopen($this->_path, "r");
            $content = fread($handle, $file_size);
            fclose($handle);
            $content = chunk_split(base64_encode($content));
            $uid = md5(uniqid(time()));
            $filename = basename($this->_path);
            $subject = 'Log Errores Script';
            $message = 'Se han producido errores en el servicio.\n Se adjunta el log creado.';
            $from_name = 'Admin';
            $header = "From: ".$from_name." <".$from_mail.">\r\n";
            //$header .= "Reply-To: ".$replyto."\r\n";
            $header .= "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
            $header .= "This is a multi-part message in MIME format.\r\n";
            $header .= "--".$uid."\r\n";
            $header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
            $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $header .= $message."\r\n\r\n";
            $header .= "--".$uid."\r\n";
            $header .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n"; // use different content types here
            $header .= "Content-Transfer-Encoding: base64\r\n";
            $header .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
            $header .= $content."\r\n\r\n";
            $header .= "--".$uid."--";

            // Enviar email
            if (mail($mailto, $subject, "", $header))
                return true;
        }
            
        return false;
    }*/
    
    /**
    * Obtener la IP del cliente que provoca la escritura del log
    * @return String IP del cliente.
    */
    public static function obtener_ip()
    {
       //Just get the headers if we can or else use the SERVER global
       if (function_exists('apache_request_headers'))
           $headers = apache_request_headers();
       else
           $headers = $_SERVER;

       //Get the forwarded IP if it exists
       if (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
           $the_ip = $headers['X-Forwarded-For'];
       else if( array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
           $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
       else if (array_key_exists('REMOTE_ADDR', $headers) && filter_var($headers['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
           $the_ip = $headers['REMOTE_ADDR'];
       else //$the_ip === false
           $the_ip = 'N/A';

       return $the_ip;
    }
    
    /**
    * Comprime un directorio
    * @param string $dir Directorio a comprimir
    * @param type $zip Instancia de ZipArchive
    * @return boolean True si se ha podido comprimir el directorio y False si ha habido algÃºn error
    */
    public static function comprimir_directorio($dir, $zip)
    {
        $dir = substr($dir, -1) != "/" ? $dir . "/" : $dir;
        if (is_dir($dir))
        {
          if ($da = opendir($dir)) {
            while (($archivo = readdir($da))!== false) {
              if (is_dir($dir . $archivo) && $archivo!="." && $archivo!=".."){              
                $this->comprimir_directorio($dir.$archivo, $zip);
              }elseif(is_file($dir.$archivo) && $archivo!="." && $archivo!=".."){                                  
                $zip->addFile($dir.$archivo, $archivo);                    
              }            
            }
            closedir($da);
          }
        }  
        return file_exists(substr($dir, -1).'.zip');
    }
}
