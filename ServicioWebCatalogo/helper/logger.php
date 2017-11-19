<?php

require_once dirname(__FILE__).'/../lib/Utils.php';

class Logger
{
    protected $_path;

    /**
     * Constructor
     * @param string $path puede ser un directorio o una ruta
     */
    public function __construct($path) 
    {
        if (empty($path)){
            Throw new Exception("Se debe indicar una ruta");
        }
        if (!file_exists($path)) {
            Throw new Exception("La ruta indicada no existe");
        }
        if (!is_writeable($path)) {
            Throw new Exception("El directorio debe tener permisos de escritura");
        }
        $this->_path = $this->_parsePath($path);
    }

    /**
     * Valida la ruta y añade el nombre del archivo
     * @param String $path Ruta
     * @return String Ruta del archivo donde se imprimira el log. Uno por cada día.
     */
    protected function _parsePath($path)
    {
        $path = substr($path, -1) != "/" ? $path . "/" : $path;
        
        // Añadir el nombre del fichero al path (sino ha sido especificado)
        if (is_dir($path))
            $path .= 'log'.date('dmY').'.log';
        
        return $path;
    }

    /**
     * Escribir en el log una línea
     * @param String $line Línea a imprimir en el log
     */
    protected function _save($line)
    {
        $fhandle = fopen($this->_path, "a+");
        fwrite($fhandle, $line);
        fclose($fhandle);
    }

    /**
     * Añade una línea al log
     * @param String $line Texto a escribir en el log
     * @param String $type Tipo del mensaje. Error: Errores de usuario o acciones no permitidas, Critical: Errores de ejecución.
     */
    public function addLineLog($line, $type)
    {
        $line = is_array($line) ? print_r($line, true) : $line;
        $line = "[".date("d-m-Y H:i:s")." | ".Utils::obtener_ip()." - $type]: ".$line."\n";
        $this->_save($line);
    }
}
