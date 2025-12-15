<?php

require_once __DIR__ . '/config.php';

class Database
{
    private static $instance = null;
    private $connection;
    private $dbType = 'sqlite';

    private function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            // SQLite DSN - single file database
            $dbPath = __DIR__ . '/../database/webdaddy.db';
            $dsn = 'sqlite:' . $dbPath;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->connection = new PDO($dsn, null, null, $options);
            
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign key constraints (critical for SQLite)
            $this->connection->exec('PRAGMA foreign_keys = ON;');
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
    static $migrationsApplied = false;
    $db = Database::getInstance()->getConnection();
    
    // Apply pending schema migrations once per request
    if (!$migrationsApplied) {
        $migrationsApplied = true;
        applyPendingMigrations($db);
    }
    
    return $db;
}

function getDbType()
{
    return 'sqlite';
}

/**
 * Apply pending schema migrations for SQLite
 * Checks if columns exist and adds them if missing
 */
function applyPendingMigrations($db) {
    try {
        // Check for payment_notified columns in pending_orders
        $result = $db->query("PRAGMA table_info(pending_orders)")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($result, 'name');
        
        if (!in_array('payment_notified', $columns)) {
            $db->exec("ALTER TABLE pending_orders ADD COLUMN payment_notified INTEGER DEFAULT 0");
            error_log("Migration: Added payment_notified column to pending_orders");
        }
        
        if (!in_array('payment_notified_at', $columns)) {
            $db->exec("ALTER TABLE pending_orders ADD COLUMN payment_notified_at TEXT");
            error_log("Migration: Added payment_notified_at column to pending_orders");
        }
    } catch (Exception $e) {
        // Log but don't fail - migrations are optional enhancements
        error_log("Migration check error: " . $e->getMessage());
    }
}
