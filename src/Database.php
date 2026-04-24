<?php

namespace App;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    private $host;
    private $db;
    private $user;
    private $pass;
    private $charset = 'utf8';

    private function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db   = getenv('DB_NAME') ?: 'yourgame';
        $this->user = getenv('DB_USER') ?: 'user';
        $this->pass = getenv('DB_PASS') ?: '';

        $dsn = "pgsql:host=$this->host;dbname=$this->db;options='--client_encoding=$this->charset'";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
