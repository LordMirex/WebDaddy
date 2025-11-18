<?php

class UrlUtils {
    
    public static function toRelativeUrl($url) {
        if (empty($url)) {
            return $url;
        }
        
        if (strpos($url, '/uploads/') === 0) {
            return $url;
        }
        
        if (preg_match('#^https?://[^/]+(/uploads/.+)$#i', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        
        if (strpos($url, 'uploads/') === 0) {
            return '/' . $url;
        }
        
        return $url;
    }
    
    public static function toAbsoluteUrl($url) {
        if (empty($url)) {
            return $url;
        }
        
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        
        if (strpos($url, '/uploads/') === 0) {
            return SITE_URL . $url;
        }
        
        if (strpos($url, 'uploads/') === 0) {
            return SITE_URL . '/' . $url;
        }
        
        return $url;
    }
    
    public static function isExternalUrl($url) {
        if (empty($url)) {
            return false;
        }
        
        if (strpos($url, '/uploads/') === 0 || strpos($url, 'uploads/') === 0) {
            return false;
        }
        
        if (preg_match('#^https?://#i', $url)) {
            $parsedUrl = parse_url($url);
            $currentHost = parse_url(SITE_URL, PHP_URL_HOST);
            
            if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $currentHost) {
                return true;
            }
            
            return false;
        }
        
        return false;
    }
    
    public static function normalizeUploadUrl($url) {
        if (empty($url)) {
            return $url;
        }
        
        if (self::isExternalUrl($url)) {
            return $url;
        }
        
        return self::toRelativeUrl($url);
    }
}
