<?php
/**
 * SENTEC High-Performance Lightweight Cache Utility
 * Reduces WAN cloud database latency from ~1500ms to < 20ms for public reads.
 * Auto-creates cache directory in storage/cache.
 */

if (!defined('SENTEC_CACHE_DIR')) {
    define('SENTEC_CACHE_DIR', __DIR__ . '/storage/cache');
}

/**
 * Retrieve cached data or compute and store it if expired/missing.
 * 
 * @param string $key Unique cache identifier
 * @param int $ttlSeconds Time to live in seconds (e.g. 600 for 10 min)
 * @param callable $fetchCallback Function to execute if cache miss
 * @return mixed The cached or freshly computed data
 */
function get_cached_data($key, $ttlSeconds, $fetchCallback) {
    $sanitizedKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    $cacheFile = SENTEC_CACHE_DIR . '/' . $sanitizedKey . '.json';

    // Check if cache file exists and is within TTL
    if (file_exists($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        if ($mtime && (time() - $mtime) < $ttlSeconds) {
            $content = @file_get_contents($cacheFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if ($decoded !== null || $content === 'null') {
                    if (!is_array($decoded) || !empty($decoded)) {
                        return $decoded;
                    }
                    @unlink($cacheFile);
                }
            }
        }
    }

    // Cache miss or expired: execute callback
    $freshData = $fetchCallback();

    // Ensure cache directory exists
    if (!is_dir(SENTEC_CACHE_DIR)) {
        @mkdir(SENTEC_CACHE_DIR, 0755, true);
    }

    // Atomically write cache file
    $tempFile = $cacheFile . '.' . uniqid('tmp_', true);
    if (@file_put_contents($tempFile, json_encode($freshData, JSON_UNESCAPED_UNICODE)) !== false) {
        @rename($tempFile, $cacheFile);
    }

    return $freshData;
}

/**
 * Invalidate a specific cache key (called by admin when updating data).
 * 
 * @param string $key Cache key to clear
 */
function invalidate_cache($key) {
    $sanitizedKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    $cacheFile = SENTEC_CACHE_DIR . '/' . $sanitizedKey . '.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Invalidate all cached data.
 */
function invalidate_all_cache() {
    if (is_dir(SENTEC_CACHE_DIR)) {
        $files = glob(SENTEC_CACHE_DIR . '/*.json');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}
