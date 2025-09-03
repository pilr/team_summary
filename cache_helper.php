<?php
// Simple file-based caching system for performance optimization
class CacheHelper {
    private $cache_dir;
    private $default_ttl;
    
    public function __construct($cache_dir = 'cache', $default_ttl = 3600) {
        $this->cache_dir = __DIR__ . '/' . $cache_dir;
        $this->default_ttl = $default_ttl;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Create .htaccess to prevent direct access
        $htaccess_file = $this->cache_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        
        if ($data === null) {
            $this->delete($key);
            return null;
        }
        
        // Check if cache has expired
        if ($data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->default_ttl;
        $filename = $this->getFilename($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filename, json_encode($data), LOCK_EX) !== false;
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Get or set cached value with callback
     */
    public function remember($key, $callback, $ttl = null) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Clean expired cache entries
     */
    public function cleanExpired() {
        $files = glob($this->cache_dir . '/*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cache_dir . '/*.cache');
        $total = count($files);
        $expired = 0;
        $total_size = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] < time()) {
                $expired++;
            }
        }
        
        return [
            'total_entries' => $total,
            'expired_entries' => $expired,
            'active_entries' => $total - $expired,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'cache_dir' => $this->cache_dir
        ];
    }
    
    /**
     * Generate cache filename
     */
    private function getFilename($key) {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }
    
    /**
     * Cache database query results
     */
    public function cacheQuery($key, $callback, $ttl = 300) {
        return $this->remember("query_" . $key, $callback, $ttl);
    }
    
    /**
     * Cache API responses
     */
    public function cacheApi($key, $callback, $ttl = 600) {
        return $this->remember("api_" . $key, $callback, $ttl);
    }
}

// Global cache instance
$cache = new CacheHelper();

// Auto-cleanup expired cache entries (1% chance per request)
if (random_int(1, 100) === 1) {
    $cache->cleanExpired();
}
?>