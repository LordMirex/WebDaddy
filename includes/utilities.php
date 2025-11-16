<?php
/**
 * Utility Functions Library
 * Common helper functions used throughout the application
 * 
 * Phase 10: Code Organization & Architecture
 * - Centralized utility functions
 * - String manipulation
 * - Array helpers
 * - Date/Time utilities
 * - Validation helpers
 * 
 * @package WebDaddyEmpire
 * @since Phase 10
 */

class Utilities {
    
    /**
     * Generate a random string
     * 
     * @param int $length String length
     * @param string $characters Character set to use
     * @return string Random string
     */
    public static function randomString($length = 16, $characters = null) {
        if ($characters === null) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Generate a unique filename with timestamp and random string
     * 
     * @param string $originalName Original filename
     * @param string $prefix Optional prefix
     * @return string Unique filename
     */
    public static function generateUniqueFilename($originalName, $prefix = '') {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        
        $safeName = self::slugify($name);
        
        $timestamp = time();
        $random = self::randomString(8, '0123456789abcdef');
        
        $uniqueName = $prefix 
            ? "{$prefix}_{$safeName}_{$timestamp}_{$random}.{$extension}"
            : "{$safeName}_{$timestamp}_{$random}.{$extension}";
        
        return strtolower($uniqueName);
    }
    
    /**
     * Convert string to URL-friendly slug
     * 
     * @param string $text Text to slugify
     * @param string $separator Separator character
     * @return string URL-friendly slug
     */
    public static function slugify($text, $separator = '-') {
        $text = preg_replace('~[^\pL\d]+~u', $separator, $text);
        
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        $text = trim($text, $separator);
        
        $text = preg_replace('~-+~', $separator, $text);
        
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'n-a';
        }
        
        return $text;
    }
    
    /**
     * Truncate text to specified length with ellipsis
     * 
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append (default: '...')
     * @param bool $preserveWords Preserve whole words
     * @return string Truncated text
     */
    public static function truncate($text, $length = 100, $suffix = '...', $preserveWords = true) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = mb_substr($text, 0, $length);
        
        if ($preserveWords) {
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
        }
        
        return $truncated . $suffix;
    }
    
    /**
     * Extract excerpt from HTML content
     * 
     * @param string $html HTML content
     * @param int $length Maximum length
     * @return string Plain text excerpt
     */
    public static function excerpt($html, $length = 200) {
        $text = strip_tags($html);
        
        $text = preg_replace('/\s+/', ' ', $text);
        
        $text = trim($text);
        
        return self::truncate($text, $length);
    }
    
    /**
     * Format number with thousands separator
     * 
     * @param float|int $number Number to format
     * @param int $decimals Number of decimal places
     * @return string Formatted number
     */
    public static function formatNumber($number, $decimals = 0) {
        return number_format($number, $decimals);
    }
    
    /**
     * Format currency amount (Nigerian Naira)
     * 
     * @param float|int $amount Amount to format
     * @param bool $symbol Include currency symbol
     * @return string Formatted currency
     */
    public static function formatCurrency($amount, $symbol = true) {
        $formatted = number_format($amount, 2);
        return $symbol ? '₦' . $formatted : $formatted;
    }
    
    /**
     * Format percentage
     * 
     * @param float $value Value to format as percentage
     * @param int $decimals Number of decimal places
     * @return string Formatted percentage
     */
    public static function formatPercentage($value, $decimals = 1) {
        return number_format($value, $decimals) . '%';
    }
    
    /**
     * Get relative time string (e.g., "2 hours ago")
     * 
     * @param string|int $datetime Datetime string or timestamp
     * @return string Relative time string
     */
    public static function timeAgo($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'just now';
        }
        
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        }
        
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
    
    /**
     * Format date in a human-friendly way
     * 
     * @param string|int $datetime Datetime string or timestamp
     * @param string $format Date format (default: 'M d, Y')
     * @return string Formatted date
     */
    public static function formatDate($datetime, $format = 'M d, Y') {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        return date($format, $timestamp);
    }
    
    /**
     * Format datetime with time
     * 
     * @param string|int $datetime Datetime string or timestamp
     * @return string Formatted datetime
     */
    public static function formatDateTime($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        return date('M d, Y \a\t g:i A', $timestamp);
    }
    
    /**
     * Check if array is associative
     * 
     * @param array $array Array to check
     * @return bool True if associative
     */
    public static function isAssociativeArray($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Get value from array with default fallback
     * 
     * @param array $array Source array
     * @param string $key Array key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Value or default
     */
    public static function arrayGet($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        
        return $default;
    }
    
    /**
     * Pluck specific key from array of arrays
     * 
     * @param array $array Array of arrays
     * @param string $key Key to pluck
     * @return array Values for the specified key
     */
    public static function pluck($array, $key) {
        return array_map(function($item) use ($key) {
            return is_array($item) ? ($item[$key] ?? null) : null;
        }, $array);
    }
    
    /**
     * Group array by specific key
     * 
     * @param array $array Array to group
     * @param string $key Key to group by
     * @return array Grouped array
     */
    public static function groupBy($array, $key) {
        $grouped = [];
        
        foreach ($array as $item) {
            if (is_array($item) && isset($item[$key])) {
                $groupKey = $item[$key];
                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [];
                }
                $grouped[$groupKey][] = $item;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email address to validate
     * @return bool True if valid
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate phone number (Nigerian format)
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    public static function isValidPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        return preg_match('/^(0|\+?234)?[789][01]\d{8}$/', $phone);
    }
    
    /**
     * Sanitize string for safe output
     * 
     * @param string $string String to sanitize
     * @param bool $allowHtml Allow HTML tags
     * @return string Sanitized string
     */
    public static function sanitize($string, $allowHtml = false) {
        if ($allowHtml) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return htmlspecialchars(strip_tags($string), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Check if string starts with given substring
     * 
     * @param string $haystack String to search in
     * @param string $needle Substring to find
     * @return bool True if starts with
     */
    public static function startsWith($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
    
    /**
     * Check if string ends with given substring
     * 
     * @param string $haystack String to search in
     * @param string $needle Substring to find
     * @return bool True if ends with
     */
    public static function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        
        return substr($haystack, -$length) === $needle;
    }
    
    /**
     * Convert string to title case
     * 
     * @param string $string String to convert
     * @return string Title case string
     */
    public static function titleCase($string) {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }
    
    /**
     * Convert string to camelCase
     * 
     * @param string $string String to convert
     * @return string camelCase string
     */
    public static function camelCase($string) {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }
    
    /**
     * Convert string to snake_case
     * 
     * @param string $string String to convert
     * @return string snake_case string
     */
    public static function snakeCase($string) {
        $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);
        $string = preg_replace('/[^a-zA-Z0-9]+/', '_', $string);
        return strtolower($string);
    }
    
    /**
     * Limit string to specified number of words
     * 
     * @param string $string Text to limit
     * @param int $words Number of words
     * @param string $end End string
     * @return string Limited string
     */
    public static function limitWords($string, $words = 100, $end = '...') {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $string, $matches);
        
        if (!isset($matches[0]) || mb_strlen($string) === mb_strlen($matches[0])) {
            return $string;
        }
        
        return rtrim($matches[0]) . $end;
    }
    
    /**
     * Convert bytes to human-readable format
     * 
     * @param int $bytes File size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted file size
     */
    public static function formatBytes($bytes, $precision = 2) {
        if ($bytes == 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Parse bytes from human-readable format
     * 
     * @param string $size Size string (e.g., '2MB', '500KB')
     * @return int Bytes
     */
    public static function parseBytes($size) {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
            case 'gb':
                $size *= 1024;
            case 'm':
            case 'mb':
                $size *= 1024;
            case 'k':
            case 'kb':
                $size *= 1024;
        }
        
        return $size;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public static function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool True if AJAX request
     */
    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Check if request is POST
     * 
     * @return bool True if POST request
     */
    public static function isPost() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Check if request is GET
     * 
     * @return bool True if GET request
     */
    public static function isGet() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    /**
     * Send JSON response
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
