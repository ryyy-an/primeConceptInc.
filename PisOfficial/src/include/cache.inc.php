<?php

declare(strict_types=1);

/**
 * Prime-In-Sync Caching Utility
 * Provides lightweight file-based caching for expensive database queries.
 */

if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', __DIR__ . '/../cache/');
}

// Ensure cache directory exists
if (!is_dir(CACHE_PATH)) {
    mkdir(CACHE_PATH, 0755, true);
}

/**
 * Fetches data from cache if it exists and hasn't expired.
 */
function cache_get(string $key) {
    $filename = CACHE_PATH . md5($key) . '.json';
    
    if (!file_exists($filename)) {
        return null;
    }

    $content = file_get_contents($filename);
    $cache = json_decode($content, true);

    if (!$cache || !isset($cache['expires']) || time() > $cache['expires']) {
        @unlink($filename); // Clean up expired cache
        return null;
    }

    return $cache['data'];
}

/**
 * Stores data in cache with a specific TTL (seconds).
 */
function cache_set(string $key, $data, int $ttl = 300): bool {
    $filename = CACHE_PATH . md5($key) . '.json';
    $cache = [
        'expires' => time() + $ttl,
        'data' => $data
    ];

    $content = json_encode($cache);
    return file_put_contents($filename, $content) !== false;
}

/**
 * Deletes a specific cache entry.
 */
function cache_delete(string $key): bool {
    $filename = CACHE_PATH . md5($key) . '.json';
    if (file_exists($filename)) {
        return unlink($filename);
    }
    return true;
}

/**
 * Clears all cache files.
 */
function cache_clear(): bool {
    $files = glob(CACHE_PATH . '*.json');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    return true;
}
