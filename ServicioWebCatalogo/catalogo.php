<?php

// - Enviar email con el csv
// - Pruebas varios hilos (Selenium)
// - Si da tiempo. Averiguar si se puede enviar el array de incremento de precios en el http body del GET
//   y como afecta al consumo del servicio

define('_WS_', 1);

require_once 'helper/Rest.inc.php';
require_once 'helper/basicAuth.php';
require_once 'lib/Utils.php';
require_once dirname(__FILE__).'/config/conf.class.php';
require_once 'helper/logger.php';
require_once 'generador.php';

class Catalogo extends REST
{
    // Instancia del logger
    private $_log = NULL;
    
    // Usuario que realiza la consulta
    private $_user = NULL;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();                                        // Instanciar controlador REST
        $this->_log = new Logger(Conf::getInstance()->getLogPath());  // Instanciar el log para registrar eventos
        
        // Autenticar usuario
        $this->_autenticarUsuario();
    }
    
    /**
     * Autenticar las credenciales del usuario
     */
    private function _autenticarUsuario()
    {        
        $auth = BasicAuth::getInstance();
        $this->_user = $auth->authenticate();
        if ($this->_user === NULL)
        {
            $this->_log->addLineLog('Se ha producido un error al intentar autenticar a un usuario desde: '.Utils::obtener_ip(),'Error');
            $this->response('', 500);   // Internal Error
        } else {
            // Procesar la consulta
            $this->_processRequest();
        }
    }

    /**
     * Procesa la petición dinámicamente
     */
    private function _processRequest()
    {
        // Cross validation if the request method is GET else it will return "Not Acceptable" status
        if($this->get_request_method() != 'GET')
                $this->response('', 406);

        $correct_types = array('csv_generico', 'csv_prestashop', 'csv_ekmtienda');
        $keys_get = array_keys($this->_request);
        
        // Capturar parámetros pasados por GET y filtrarlos        
        if (in_array('prices', $keys_get))
        {
            $INCREMENTO_PRECIO = json_decode(urldecode($this->_request['prices']), true);
            if (!empty($INCREMENTO_PRECIO) & is_array($INCREMENTO_PRECIO))
            {
                foreach ($INCREMENTO_PRECIO as $key=>$value)
                {
                    if ($value < 1 | $value > 1.50)
                    {
                        $this->_log->addLineLog('Parámetro incremento precio ('.$key.'=>'.$value.'). El incremento de precio debe estar entre 1 y 1.50','Error');
                        $this->response('', 422);   // Unprocessable Entity
                    }
                }
            } else {
                $this->_log->addLineLog('INPUT_GET incremento_precio (price) no válido: $INCREMENTO_PRECIO="'.$INCREMENTO_PRECIO.'"','Error');
                $this->response('', 422);   // Unprocessable Entity
            }
            
            $TIPO = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
            if ($TIPO !== NULL & in_array($TIPO, $correct_types) === false)
            {
                $this->_log->addLineLog('INPUT_GET tipo (type) no válido. $TIPO="'.$TIPO.'"','Error');
                $this->response('', 422);   // Unprocessable Entity
            } else {
                $TIPO = $this->get_request_content_type();
            }
            
            // La petición es correcta. Lanza el generador.
            $gen = new Generador();
            $path = $gen->startGen($TIPO, $INCREMENTO_PRECIO, $this->_user);
            if ($path === false) {
                $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Error al procesar','Error');
                $this->response('', 500); // Internal Error
            } else {
                $this->response($path, 200);
            }
        }
        else
        {
            $this->_log->addLineLog('La petición está mal formada o no tiene todos los parámetros','Error');
            $this->response('', 400);   // Bad Request
        }
    }
}

// Lanzar el Catalogo
new Catalogo;