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

/**
 * General-Purpose File-Based Cache Class
 * Provides remember(), delete(), clear() methods for caching any data
 */
class Cache {
    private $cacheDir;
    private $defaultTtl = 3600; // 1 hour
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/data/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached value
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get($key) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || $data['expires_at'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached value
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Delete cached value
     * @param string $key Cache key
     */
    public function delete($key) {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Remember - get or set (cache-aside pattern)
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to generate value if not cached
     * @return mixed Cached or generated value
     */
    public function remember($key, $ttl, $callback) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Check if cache key exists and is valid
     * @param string $key Cache key
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Get file path for cache key
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}

/**
 * Warm cache with common data (call via cron or on startup)
 */
function warmCache() {
    $cache = new Cache();
    
    if (function_exists('getTemplates')) {
        $cache->set('templates_active', getTemplates(true), 300);
    }
    if (function_exists('getTools')) {
        $cache->set('tools_active', getTools(true), 300);
    }
    if (function_exists('getToolCategories')) {
        $cache->set('tool_categories', getToolCategories(), 3600);
    }
    if (function_exists('getTemplateCategories')) {
        $cache->set('template_categories', getTemplateCategories(), 3600);
    }
    
    error_log("Cache warmed at " . date('Y-m-d H:i:s'));
}
