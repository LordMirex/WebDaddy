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
            $databaseUrl = getenv('DATABASE_URL');
            
            if ($databaseUrl && strpos($databaseUrl, 'postgresql://') === 0) {
                $parsedUrl = parse_url($databaseUrl);
                $queryParams = [];
                if (isset($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $queryParams);
                }
                
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                    $parsedUrl['host'],
                    $parsedUrl['port'] ?? 5432,
                    ltrim($parsedUrl['path'], '/'),
                    $queryParams['sslmode'] ?? 'prefer'
                );
                
                $this->connection = new PDO(
                    $dsn,
                    $parsedUrl['user'] ?? '',
                    $parsedUrl['pass'] ?? ''
                );
                $this->dbType = 'pgsql';
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                $this->dbType = 'mysql';
            }
            
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
            if ($this->dbType === 'pgsql') {
                $sql = $this->convertMySQLToPgSQL($sql);
            }
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            error_log('Prepare Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    private function convertMySQLToPgSQL($sql)
    {
        return $sql;
    }

    public function escapeString($string)
    {
        return substr($this->connection->quote($string), 1, -1);
    }

    public function getLastInsertId($sequenceName = null)
    {
        if ($this->dbType === 'pgsql' && $sequenceName) {
            return $this->connection->lastInsertId($sequenceName);
        }
        return $this->connection->lastInsertId();
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

    public function getDbType()
    {
        return $this->dbType;
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
    return Database::getInstance()->getDbType();
}
