<?php

/**
 * HTTP Basic Authentication class
 *
 * @author Ali S
 * @version 1.0
 */

// - Revisar seguridad de header Authentication, haber si se puede mejorar algo
// - Limitar cantidad de accesos a 3. El problema es que cada vez que se formatean la cabeceras hay que hacer exit; para que 
//   se vuelva a pedir la autenticación y ahí se pierde el estado de la clase.

require 'user.inc.php';


class BasicAuth
{
    private $realm;
    private $authorization;
    
    private static $_instance;
    
    /**
     * Class initialization
     * @access public
     */
    private function __construct()
    {
        $this->realm = 'Acceso Restringido. Por favor, identifíquese'; // Si el realm cambia, obliga al navegador a pedir las credenciales de nuevo ;)        
    }
    
    public function __destruct() 
    {
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }
    
    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
            self::$_instance = new self();
      
        return self::$_instance;
   }
       
   /**
    * Proceso de autenticación de un usuario
    * @return Array|NULL Array con los datos del usuario logueado | NULL si no está registrado
    */
    public function authenticate()
    {       
        $user = null;
        
        $isAuthSet = $this->authHeader();
        if($isAuthSet === false)
            $this->setAuthHeader("basic");

        $data = $this->getBasic();
        if ($data === false)
            $this->setAuthHeader("fail");
        else
        {
            $user = $this->getUser($data);
            if(empty($user))
                $this->setAuthHeader("fail"); 
        }
        return $user;
    }
    
    /**
     * Method to separate email and secret key from Authorization header
     * @return Mixed either $data or false
     * @access private
     */
    private function getBasic()
    {
        if(isset($_SERVER['PHP_AUTH_USER']))
        {
            $username = filter_var(trim(htmlspecialchars($_SERVER['PHP_AUTH_USER'])), FILTER_SANITIZE_STRING);
            $password = filter_var(trim(htmlspecialchars($_SERVER['PHP_AUTH_PW'])), FILTER_SANITIZE_STRING);
            
            if (empty($username) === false & empty($password) === false)
                return array('username' => $username, 'secret' => $password);
        }
        return false;
    }
    
    /**
     * Check the sent email and secret data with our user records
     * @param Array $data
     * @return Mixed either Array $user || Boolean false
     * @access private
     */
    private function getUser($data)
    {
        $userExist = User::verifyCredentials($data['username'], $data['secret']);
        
        return ($userExist) ? User::getUser($data['username']) : false;
    }
    
    /**
     * Method to check if Authorization header has been set
     * @return Boolean
     * @access private
     */
    private function authHeader()
    {
        if(array_key_exists('Authorization', $_SERVER))
        {
            $this->authorization = $_SERVER['Authorization'];
            return true;
        }
        else if(function_exists('apache_request_headers'))
        {
            $header = apache_request_headers();
            if(array_key_exists('Authorization',$header))
            {
                $this->authorization = $header['Authorization'];
                return true;
            }
        }
        return false;
    }
    
    /**
     * Setting HTTP Authorization header
     * @access private
     * @return void
     */
    private function setAuthHeader($name)
    {
        switch(strtolower($name))
        {
            case 'basic':
                header("WWW-Authenticate: Basic realm='" . $this->realm . "'");
                break;
            case 'fail':
                header("WWW-Authenticate: Basic realm='" . $this->realm . "'");
                header('HTTP/1.0 401 Unauthorized');
                //echo 'Acceso restringido. Credenciales incorrectas'; // El usuario pulsa el botón cancelar
                break;
        }
        exit;
    }
}
            