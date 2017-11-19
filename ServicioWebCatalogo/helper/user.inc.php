<?php

/**
 * Obtiene datos de usuarios accediendo directamente a la base de datos de Joomla.
 * Sin utilizar las clases de Joomla. * 
 */

require_once 'db.class.php';

class User
{
    /**
     * Verificar las credenciales de un usuario de Joomla.
     * @param String $username Usuario a verificar
     * @param String $password Contrase�a en plain text
     * @return boolean False el usuario no existe o la contrase�a es incorrecta. True verificado correctamente.
     */
    public static function verifyCredentials($username, $password)
    {
        $db = Db::getInstance();       
        $result = $db->query("SELECT password FROM eya0t_users WHERE username='$username' LIMIT 1");
        
        if ($result !== false & $result->num_rows !== 0)
        {
            $row = $db->fechObject($result);
            $hash = $row->password;

            // Si la password est� cifrada con phpass
            if (strpos($hash, '$P$') === 0)
            {
                require_once dirname(__FILE__).'/../lib/PasswordHash.php';
                $phpass = new PasswordHash(10, true);   // Como Joomla
                $match = $phpass->CheckPassword($password, $hash);

            } else {
                // Password+':'+Salt -> Forma antigua de Joomla, ya no se usa así
                $parts = explode(':', $row->password);
                $crypt = $parts[0];
                $salt = @$parts[1];

                $testcrypt = md5($password.$salt) . ($salt ? ':'.$salt : '');
                $match = ($testcrypt === $hash) ? true : false;
            }
            
            return $match;
        }
        
        return false;
    }
    
    /**
     * Obtener informaci�n de un usuario desde Joomla.
     * @param String $username Usuario a verificar
     * @return Mixed False si el usuario no existe. Array(username, email, password) si existe
     */
    public static function getUser($username)
    {
        $db = Db::getInstance();        
        $result = $db->query("SELECT username, email FROM eya0t_users WHERE username='$username' LIMIT 1");
        
        if ($result !== false & $result->num_rows !== 0)
        {
            $row = $db->fechObject($result);
            return array('username'=>$row->username, 'email'=>$row->email);
        }
        
        return false;
    }
}