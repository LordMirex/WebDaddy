#!/usr/bin/env php
<?php
/**
 * Database Backup Script
 * Creates timestamped backup of SQLite database
 * 
 * Usage: php backup_database.php
 */

$dbPath = __DIR__ . '/webdaddy.db';
$backupDir = __DIR__ . '/backups';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║             DATABASE BACKUP UTILITY                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Create backup directory if it doesn't exist
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "✓ Created backup directory\n";
}

// Check if database exists
if (!file_exists($dbPath)) {
    echo "✗ Database file not found: $dbPath\n";
    echo "  The database will be created automatically when the application first runs.\n";
    exit(1);
}

// Create backup filename with timestamp
$timestamp = date('Y-m-d_His');
$backupFile = $backupDir . '/webdaddy_backup_' . $timestamp . '.db';

echo "Creating backup...\n";
echo "  Source: $dbPath\n";
echo "  Destination: $backupFile\n\n";

// Copy database file
if (copy($dbPath, $backupFile)) {
    $originalSize = filesize($dbPath);
    $backupSize = filesize($backupFile);
    
    echo "✓ Backup created successfully!\n\n";
    echo "Details:\n";
    echo "  Original size: " . formatBytes($originalSize) . "\n";
    echo "  Backup size: " . formatBytes($backupSize) . "\n";
    echo "  Timestamp: $timestamp\n";
    echo "  Location: $backupFile\n\n";
    
    // List recent backups
    echo "Recent backups:\n";
    $backups = glob($backupDir . '/webdaddy_backup_*.db');
    rsort($backups);
    
    foreach (array_slice($backups, 0, 5) as $index => $backup) {
        $age = time() - filemtime($backup);
        $size = filesize($backup);
        echo "  " . ($index + 1) . ". " . basename($backup) . " (" . formatBytes($size) . ", " . formatAge($age) . " ago)\n";
    }
    
    // Cleanup old backups (keep last 10)
    if (count($backups) > 10) {
        echo "\nCleaning up old backups (keeping last 10)...\n";
        $toDelete = array_slice($backups, 10);
        
        foreach ($toDelete as $old) {
            if (unlink($old)) {
                echo "  ✓ Deleted: " . basename($old) . "\n";
            }
        }
    }
    
    echo "\n✓ Backup complete!\n\n";
    
} else {
    echo "✗ Failed to create backup\n";
    exit(1);
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow = floor(log($bytes) / log(1024));
    return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
}

function formatAge($seconds) {
    if ($seconds < 60) return "$seconds seconds";
    if ($seconds < 3600) return floor($seconds / 60) . " minutes";
    if ($seconds < 86400) return floor($seconds / 3600) . " hours";
    return floor($seconds / 86400) . " days";
}
