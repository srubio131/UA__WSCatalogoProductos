<?php

/**
 * - No se liberan resultados: mysqli_free_result($result5);
 * - La base de datos la dejo abierta hasta que se destuye la instancia... a lo mejor es mejor abrir y cerrar cada vez
 */

// En esta clase se recogen los datos de conexi�n a la base de datos. Capa intermedia entre base de datos y la aplicaci�n.
// Se puede usar indistintamente para cualquier tipo de base de datos, dentro de cada m�todo se pondr�a un switch con las distintas bases de datos.

require_once dirname(__FILE__).'/../config/conf.class.php';

class Db
{
    private $servidor;
    private $base_datos;
    private $usuario;
    private $password;
    
    private $link;
            
    static $_instance;
    
    private function __construct()
    {
        $this->setConnection();
        $this->connect();
    }
    
    public function __destruct()
    {
        if (empty($this->link) !== false)
            $this->closeConnection();
    }
    
    private function setConnection()
    {        
        $conf = Conf::getInstance();
        $this->servidor = $conf->getHostDB();
        $this->base_datos = $conf->getDB();
        $this->usuario = $conf->getUserDB();
        $this->password = $conf->getPassDB();
    }
    
    /**
     * Cierra la conexi�n a la base de datos
     * @return type
     */
    private function closeConnection()
    {
        return mysqli_close($this->link);
    }
    
    public static function getInstance()
    {
      if (!(self::$_instance instanceof self))
         self::$_instance = new self();
      
         return self::$_instance;
   }
   
   private function connect()
   {
       $this->link = mysqli_connect($this->servidor, $this->usuario, $this->password, $this->base_datos);
       mysqli_query($this->link, "SET NAMES 'utf8'");
   }
   
   public function query($sql)
   {
      return mysqli_query($this->link, $sql);      
   }
   
   public function getAssocRow($result)
   {
       return mysqli_fetch_array($result, MYSQLI_ASSOC);
   }
   
   public function fechObject($result)
   {
       return mysqli_fetch_object($result);
   }
}
