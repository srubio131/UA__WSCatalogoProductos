<?php

class REST 
{
        public $_content_type = NULL;
        private $_size = NULL;
        public $_request = array();	
        private $_code = 200;

        /**
         * Constructor
         */
        public function __construct()
        {
            $this->inputs();
        }

        /**
         * Codifica y prepara la respuesta a servir.
         * @param String $content_type Content type de los datos
         * @param String $filename Nombre del fichero con los datos y que se va a descargar
         * @param Int $status Estado de la respuesta HTTP
         */
        public function response($path, $status)
        {
            $this->_code = ($status) ? $status : 200;   
            $this->_size = filesize($path);
            
            if ($this->setContentType($path))
            {
                if ($this->_content_type === 'application/json')    // El Json se devuelve en Raw
                {
                    $this->set_headers('');
                    echo file_get_contents($path);  
                    exit;
                } else {                
                    $filename = basename($path);
                    $this->set_headers($filename);      // Set Headers OK
                }                
                readfile($path);                        // Descargar archivo
            } else {
                $this->_code = 404;                     // El archivo no se ha generado o no se encuentra.
                $this->set_headers('');      
            }
            exit;
        }

        /**
         * Obtiene el mensaje del código que se enviará en la respuesta
         * @return String Mensaje de estado
         */
        private function get_status_message()
        {
            $status = array(
                            200 => 'OK',                    // [Correcto] Respuesta a un exitoso GET, PUT, PATCH o DELETE. Puede ser usado también para un POST que no resulta en una creación.
                            201 => 'Created',               // [Creada] Respuesta a un POST que resulta en una creación. Debería ser combinado con un encabezado Location, apuntando a la ubicación del nuevo recurso.
                            204 => 'No Content',            // [Sin Contenido] Respuesta a una petición exitosa que no devuelve un body (como una petición DELETE)
                            304 => 'Not Modified',          // [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
                            400 => 'Bad Request',           // [Petición Errónea] La petición está malformada, como por ejemplo, si el contenido no fue bien parseado.
                            401 => 'Unauthorized',          // [Desautorizada] Cuando los detalles de autenticación son inválidos o no son otorgados. También útil para disparar un popup de autorización si la API es usada desde un navegador.
                            403 => 'Forbidden',             // [Prohibida] Cuando la autenticación es exitosa pero el usuario no tiene permiso al recurso en cuestión.
                            404 => 'Not Found',             // [No encontrada] Cuando un recurso no existente es solicitado.
                            405 => 'Method Not Allowed',    // [Método no permitido] Cuando un método HTTP que está siendo pedido no está permitido para el usuario autenticado.
                            409 => 'Conflict',              // [Retirado] Indica que el recurso en ese endpoint ya no está disponible. Útil como una respuesta en blanco para viejas versiones de la API
                            410 => 'Gone',                  // [Método no permitido] Cuando un método HTTP que está siendo pedido no está permitido para el usuario autenticado.
                            415 => 'Unsupported Media Type',// [Tipo de contenido no soportado] Si el tipo de contenido que solicita la petición es incorrecto
                            422 => 'Unprocessable Entity',  // [Entidad improcesable] Utilizada para errores de validación
                            428 => 'Too Many Requests',     // [Demasiadas peticiones] Cuando una petición es rechazada debido a la tasa límite.
                            500 => 'Internal Server Error');// [Error interno] Error interno en el servidor
            
            return ($status[$this->_code]) ? $status[$this->_code] : $status[500];
        }

        /**
         * Obtiene el Request Method de la petición al servicio.
         * @return String Request Method
         */
        public function get_request_method()
        {
            return htmlentities($_SERVER['REQUEST_METHOD'],  ENT_QUOTES,  "utf-8");
        }
        
        public function get_request_content_type()
        {
            return htmlentities($_SERVER['CONTENT_TYPE'], ENT_QUOTES, "utf-8");
        }

        /**
         * Inicializa la variable $this->_request según el Request Method de la petición.
         */
        private function inputs()
        {
            switch($this->get_request_method())
            {
                case "POST":
                        $this->_request = $this->cleanInputs($_POST);
                        break;
                case "GET":
                case "DELETE":
                        $this->_request = $this->cleanInputs($_GET);
                        break;
                case "PUT":
                        parse_str(file_get_contents("php://input"), $this->_request);
                        $this->_request = $this->cleanInputs($this->_request);
                        break;
                default:
                        $this->response('', 406);
                        break;
            }
        }		

        /**
         * Limpia los parámetros de consulta enviados en la petición.
         * Evitando valores corruptos o malintencionados.
         * @param Array $data Información de los parámetros enviados con el Method Request
         * @return Array Información limpiada
         */
        private function cleanInputs($data)
        {
            $clean_input = array();
            if(is_array($data))
            {
                foreach($data as $k => $v)
                        $clean_input[$k] = $this->cleanInputs($v);
            }else{
                if(get_magic_quotes_gpc())
                        $data = trim(stripslashes($data));

                $data = strip_tags($data);
                $clean_input = trim($data);
            }
            return $clean_input;
        }	
        
        /**
         * Setea la variable $this->_content_type con el Content Type correspondiente
         * @param String $path Ruta del archivo
         * @return boolean True en caso de éxito y False si el archivo no se encuentra o no existe
         */
        private function setContentType($path)
        {
            if (file_exists($path) & is_file($path))
            {
                $filename = basename($path);
                // Asignar content type
                $extension = substr($filename, strrpos($filename, '.')+1);
                switch ($extension)
                {
                    case 'zip':
                                $this->_content_type = 'application/zip';
                                break;
                    case 'xml':
                                $this->_content_type = 'application/xml';
                                break;
                    case 'csv': 
                                $this->_content_type = 'text/csv';
                                break;
                    
                    case 'json':
                                $this->_content_type = 'application/json';
                                break;
                    default:
                                $this->_content_type = 'text/plain';
                                
                }
                return true;
            }    
            return false;
        }
        
        /**
         * Configura los header que se van a mandar.
         * Si no se indica ningun $filename significa que ha habido algún error y por lo tanto no se debe descargar nada.
         * @param String $filename Nombre del archivo
         */
        private function set_headers($filename)
        {
            header('HTTP/1.1 '.$this->_code.' '.$this->get_status_message());
            header('Content-Type:'.$this->_content_type.'; charset=UTF-8');

            if ($filename !== '')   // No incluir el archivo en el response
            {
                header("Content-Disposition: attachment; filename=$filename");                
                //header("Content-Transfer-Encoding: binary");
                header("Connection: close");
                header("Content-Length: " . $this->_size);  
            }
        }
}	