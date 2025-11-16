<?php
/**
 * PHPUnit Bootstrap File
 * Sets up testing environment for WebDaddy Empire
 */

// Set testing environment
define('TESTING', true);
putenv('TESTING=true');
putenv('APP_ENV=testing');

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load application configuration
require_once __DIR__ . '/../includes/config.php';

// Override config for testing
define('TEST_DB_PATH', __DIR__ . '/../database/test_webdaddy.db');
define('TEST_UPLOADS_DIR', __DIR__ . '/Fixtures/uploads/');

// Create test uploads directory if it doesn't exist
if (!file_exists(TEST_UPLOADS_DIR)) {
    mkdir(TEST_UPLOADS_DIR, 0755, true);
    mkdir(TEST_UPLOADS_DIR . 'thumbnails', 0755, true);
    mkdir(TEST_UPLOADS_DIR . 'videos', 0755, true);
    mkdir(TEST_UPLOADS_DIR . 'temp', 0755, true);
}

/**
 * Create a fresh test database
 */
function createTestDatabase() {
    // Remove old test database
    if (file_exists(TEST_DB_PATH)) {
        unlink(TEST_DB_PATH);
    }
    
    // Copy production database structure
    $prodDb = __DIR__ . '/../database/webdaddy.db';
    if (file_exists($prodDb)) {
        copy($prodDb, TEST_DB_PATH);
    }
}

/**
 * Clean up test database
 */
function cleanupTestDatabase() {
    if (file_exists(TEST_DB_PATH)) {
        unlink(TEST_DB_PATH);
    }
}

/**
 * Get test database connection
 */
function getTestDb() {
    try {
        $db = new PDO('sqlite:' . TEST_DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        die('Test database connection failed: ' . $e->getMessage());
    }
}

/**
 * Create a test image file
 */
function createTestImage($width = 800, $height = 600) {
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 255, 0, 0);
    imagefilledrectangle($image, 0, 0, $width, $height, $color);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
    imagejpeg($image, $tempFile, 90);
    imagedestroy($image);
    
    return $tempFile;
}

/**
 * Clean up test files
 */
function cleanupTestFiles() {
    $dir = TEST_UPLOADS_DIR;
    if (is_dir($dir)) {
        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

// Register shutdown function to cleanup (only in CLI mode)
if (PHP_SAPI === 'cli') {
    register_shutdown_function('cleanupTestFiles');
}
