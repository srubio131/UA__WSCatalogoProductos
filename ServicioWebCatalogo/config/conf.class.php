<?php

class Conf 
{
    private $_hostdb;
    private $_db;
    private $_userdb;
    private $_passdb;
    
    private $_genpath;
    private $_logpath;
    
    static $_instance;
    
    private function __construct()
    {
        require_once 'config.php';
        $this->_hostdb = $hostdb;
        $this->_db = $db;
        $this->_userdb = $userdb;
        $this->_passdb = $passdb;
        
        $this->_genpath = $genpath;
        $this->_logpath = $logpath;
    }
    
    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
            self::$_instance = new self();
        
        return self::$_instance;
    }
    
    public function getHostDB()
    {
        return $this->_hostdb;
    }

    public function getDB()
    {
        return $this->_db;
    }

    public function getUserDB()
    {
        return $this->_userdb;
    }

    public function getPassDB()
    {
        return $this->_passdb;
    }
    
    function getGenPath()
    {
        return $this->_genpath;
    }

    function getLogPath()
    {
        return $this->_logpath;
    }
}
