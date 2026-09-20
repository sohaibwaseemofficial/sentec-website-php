<?php
/**
 * SENTEC Database Connection
 * Securely loads credentials from environment variables.
 * Includes IPv4 DNS caching and connection options to eliminate cloud latency.
 */

require_once __DIR__ . '/env_loader.php';

// Load credentials with environment variables prioritized
$host     = env('DB_HOST') ?: (getenv('DB_HOST') ?: 'mysql-sentec-website-sentec-website.d.aivencloud.com');
$db_user  = env('DB_USERNAME') ?: (getenv('DB_USERNAME') ?: 'avnadmin');
$db_pass  = env('DB_PASSWORD') ?: (getenv('DB_PASSWORD') ?: 'AVNS__JmdZgFuZlBQ-O-LO3v');
$db_name  = env('DB_NAME') ?: (getenv('DB_NAME') ?: 'sentec_db');
$db_port  = (int)(env('DB_PORT') ?: (getenv('DB_PORT') ?: 27510));

// Aliases for legacy scripts
$servername = $host;
$username   = $db_user;
$password   = $db_pass;
$dbname     = $db_name;
$port       = $db_port;

// Resolve and cache IPv4 DNS immediately to eliminate cloud latency & Windows IPv6 stalls
$db_ip = gethostbyname($host);

$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 4);

// Negotiate UTF-8 during initial handshake to eliminate extra roundtrip
if (defined('MYSQLI_SET_CHARSET_NAME')) {
    $conn->options(MYSQLI_SET_CHARSET_NAME, 'utf8mb4');
}

$ssl_cert = __DIR__ . '/ca.pem';
if (defined('MYSQLI_CLIENT_SSL') && file_exists($ssl_cert)) {
    $conn->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);
    if (!@$conn->real_connect($db_ip, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL)) {
        // Fallback without SSL flag if provider configuration differs
        @$conn->real_connect($db_ip, $db_user, $db_pass, $db_name, $db_port);
    }
} else {
    @$conn->real_connect($db_ip, $db_user, $db_pass, $db_name, $db_port);
}

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Unable to connect to database. Please try again later.");
}

// Set UTF-8 charset fallback if options flag unsupported
if ($conn->character_set_name() !== 'utf8mb4') {
    @mysqli_set_charset($conn, 'utf8mb4');
}

// Synchronize MySQL session time zone with Pakistan Standard Time (UTC+05:00)
$tzOffset = env('DB_TIME_OFFSET', '+05:00');
if ($tzOffset) {
    @$conn->query("SET time_zone = '$tzOffset'");
}

// Load cache utilities globally
require_once __DIR__ . '/cache_utils.php';
?>