<?php
/**
 * Google reCAPTCHA Configuration
 * Loads keys securely from environment variables only.
 */

require_once __DIR__ . '/env_loader.php';

// Load configuration from environment variables (no hardcoded fallbacks)
define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY', ''));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY', ''));

/**
 * Check if valid reCAPTCHA keys are configured
 */
if (!function_exists('recaptcha_enabled')) {
    function recaptcha_enabled(): bool {
        $site = RECAPTCHA_SITE_KEY;
        $secret = RECAPTCHA_SECRET_KEY;
        
        if (!$site || !$secret) {
            return false;
        }
        
        // Reject placeholder values
        if (stripos($site, 'Your_Site_Key') !== false || 
            stripos($secret, 'Your_Secret_Key') !== false) {
            return false;
        }
        
        // Basic validation: v2 keys start with 6L and are > 25 chars
        return (strlen($site) > 25 && strlen($secret) > 25 && 
                strpos($site, '6L') === 0);
    }
}

// Email configuration
define('ADMIN_EMAIL', env('ADMIN_EMAIL', ''));
define('FROM_EMAIL', env('FROM_EMAIL', ''));
define('FROM_NAME', env('FROM_NAME', 'SENTEC'));
?>