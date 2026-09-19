<?php
/**
 * Environment Variables Loader
 * Securely loads variables from .env file (local) or Azure App Settings (production)
 */

// Prevent direct access
if (!defined('ENV_LOADED')) {
    define('ENV_LOADED', true);
}

/**
 * Load environment variables from .env file
 */
function loadEnvFile($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove surrounding quotes
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return true;
}

// Load .env file from the root directory
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    loadEnvFile($envFile);
}

// Set PHP default timezone
$appTz = env('APP_TIMEZONE', 'Asia/Karachi');
if ($appTz && in_array($appTz, timezone_identifiers_list())) {
    date_default_timezone_set($appTz);
}

/**
 * Get environment variable with fallback
 * Works with both .env file and Azure App Settings
 */
function env($key, $default = null) {
    // Check various sources in order of priority
    $value = null;
    
    if (isset($_ENV[$key])) {
        $value = $_ENV[$key];
    } elseif (isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    } else {
        $value = getenv($key);
    }
    
    if ($value === false || $value === null) {
        return $default;
    }
    
    // Handle boolean values
    $lowerValue = strtolower((string)$value);
    switch ($lowerValue) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case '':
            return $default;
    }
    
    return $value;
}

/**
 * Check if running in production (Azure)
 */
function is_production() {
    return isset($_SERVER['WEBSITE_SITE_NAME']) || getenv('WEBSITE_SITE_NAME');
}
?>