<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

class User extends Base
{
    public $logIn = false;
    public $id = 0;
    public $data = array();
    protected $auth = false;

    public function __construct($auth = true)
    {
        if ($auth)
            $this->auth();
    }

    protected function auth()
    {
        $this->auth = true;

        $id = isset($_SESSION['uid']) ? abs(intval($_SESSION['uid'])) : false;
        $pass = isset($_SESSION['upw']) ? $_SESSION['upw'] : false;
        if (!$id || !$pass)
            return $this->setUser();

        $q = parent::$pdo->prepare("SELECT * FROM `user` WHERE `user_id` = ?");
        $q->execute(array($id));
        if ($q->rowCount() == 0) {
            return $this->setUser();
        }
        $user = $q->fetch();
        $stored_hash = $user['password'];
        $raw_password_attempt = $pass; // Assuming $pass from $_SESSION['upw'] is the raw password

        // Try verifying with modern password hashing
        if (password_verify($raw_password_attempt, $stored_hash)) {
            $this->logIn = true;
            $this->id = $user['user_id'];
            $this->data = $user;

            // Check if the hash needs to be updated to the latest algorithm
            if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
                $new_hash = password_hash($raw_password_attempt, PASSWORD_DEFAULT);
                if ($new_hash) {
                    $update_q = parent::$pdo->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                    $update_q->execute(array($new_hash, $this->id));
                    // Optionally, update session password if it's stored hashed (not the case here)
                    // $_SESSION['upw'] = $new_hash; // This would be incorrect as upw is raw pass
                }
            }
            return;
        }
        // Else, if password_verify fails, check for old MD5 hash (md5(md5(password)))
        elseif ($stored_hash == md5(md5($raw_password_attempt))) {
            $this->logIn = true;
            $this->id = $user['user_id'];
            $this->data = $user;

            // Upgrade the MD5 hash to the new standard
            $new_hash = password_hash($raw_password_attempt, PASSWORD_DEFAULT);
            if ($new_hash) {
                $update_q = parent::$pdo->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                $update_q->execute(array($new_hash, $this->id));
                // Optionally, update session password if it's stored hashed (not the case here)
            }
            return;
        }

        // Both modern and old hash verification failed
        return $this->setUser();
    }

    public function redirect($redir = '')
    {
        header('Location: ' . parent::$set['baseurl'] . '/site/login?redirect=' . $redir);
        exit();
    }

    public function logOut()
    {
        if (isset($_SESSION['uid']))
            unset($_SESSION['uid']);
        if (isset($_SESSION['upw']))
            unset($_SESSION['upw']);
        session_destroy();
        $this->logIn = false; // Corrected typo from $this->logged to $this->logIn
        $this->setUser();
    }

    protected function setUser()
    {
        $this->data['user_id'] = 0;
        $this->data['name'] = 'Tamu';
    }

    public function getId()
    {
        if (!$this->auth)
            return false;

        return $this->data['user_id'];
    }

    public function getName()
    {
        if (!$this->auth)
            return false;

        return $this->data['name'];
    }

    public function getUser($id)
    {
        $q = parent::$pdo->prepare("SELECT * FROM `user` WHERE `user_id` = ?");
        $q->execute(array($id));
        if ($q->rowCount() == 0)
            return false;
        return $q->fetch();
    }
}

?>