<?php
/**
 * Simple File-Based Cache System
 * Reduces database queries by caching product data
 */

class ProductCache {
    private static $cacheDir = __DIR__ . '/../cache';
    private static $cacheTTL = 3600; // 1 hour
    
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        if (file_exists($file)) {
            $cacheTime = filemtime($file);
            if (time() - $cacheTime < self::$cacheTTL) {
                $data = unserialize(file_get_contents($file));
                return $data;
            } else {
                unlink($file);
            }
        }
        
        return null;
    }
    
    public static function set($key, $data) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        file_put_contents($file, serialize($data));
    }
    
    public static function delete($key) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function flush() {
        self::init();
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
