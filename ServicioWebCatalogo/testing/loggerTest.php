<?php

/**
 * Tests para comprobar que se generan entradas en el log cuando se producen errores
 */

define("_WS_", 1);
require_once dirname(__FILE__).'/../helper/logger.php';
require_once dirname(__FILE__).'/../generador.php';

class loggerTest extends PHPUnit_Framework_TestCase
{
    private $_path;
    private $_log;
    
    protected function setUp()
    {    
        $this->_path = dirname(__FILE__).'/../logs/';
        $this->_log = new Logger($this->_path);
    }
    
    protected function tearDown() {        
    }
    
    public function testAddLineLog()
    {
        $linea_esperada = "INPUT_GET tipo (type) no válido. \$TIPO=\"tipo_inventado\"";
        $this->_log->addLineLog($linea_esperada, 'Error');
        
        // Leer log generado
        $fp = fopen($this->_path.'log'.date('dmY').'.log', "r");
        if ($fp)
        {
            $linea = fgets($fp);
            fclose($fp);
            
            // Obtener el mensaje (sin la fecha/hora, IP y tipo de error)
            if (!is_bool(strpos($linea, ']: ')))
                $linea = substr($linea, strpos($linea,']: ')+strlen(']: '));
            
            $this->assertEquals($linea_esperada, $linea, 'Log. Tipo no válido');
        } else {
            // Si no se abre el fichero fuerzas un error
            $this->assertTrue(false);
        }
    }
}