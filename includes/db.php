<?php

require_once __DIR__ . '/config.php';

class Database
{
    private static $instance = null;
    private $connection;
    private $dbType = 'pgsql';

    private function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            // PostgreSQL DSN from constants
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSLMODE
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            
            if (DISPLAY_ERRORS) {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                die('A system error occurred. Please contact support.');
            }
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql)
    {
        try {
            return $this->connection->query($sql);
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    public function prepare($sql)
    {
        try {
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            error_log('Prepare Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    public function escapeString($string)
    {
        return substr($this->connection->quote($string), 1, -1);
    }

    public function getLastInsertId($sequenceName = null)
    {
        return $this->connection->lastInsertId($sequenceName);
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollBack();
    }

    
    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

function getDb()
{
    return Database::getInstance()->getConnection();
}

function getDbType()
{
    return 'pgsql';
}
