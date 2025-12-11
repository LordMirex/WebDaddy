<?php
/**
 * Migration: Create system_updates table
 * This migration creates a table to store dynamic system updates/announcements
 */

require_once __DIR__ . '/../../includes/db.php';

function createSystemUpdatesTable() {
    try {
        $db = getDb();
        
        // Check if table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_updates'");
        if ($result->fetch()) {
            return "Table 'system_updates' already exists.";
        }
        
        // Create table
        $sql = "
        CREATE TABLE system_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            display_date TEXT
        )
        ";
        
        $db->exec($sql);
        return "Table 'system_updates' created successfully.";
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' || !empty($_GET['run_migration'])) {
    echo createSystemUpdatesTable();
}
