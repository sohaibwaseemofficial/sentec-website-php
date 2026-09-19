<?php
/**
 * SENTEC Database Connection
 * Securely loads credentials from environment variables only.
 * No hardcoded fallbacks.
 */

require_once __DIR__ . '/env_loader.php';

// Load all credentials from environment
$servername = env('DB_HOST');
$username   = env('DB_USERNAME');
$password   = env('DB_PASSWORD');
$dbname     = env('DB_NAME');
$port       = env('DB_PORT', 3306);

// Validate that all required credentials exist
if (!$servername || !$username || !$dbname) {
    die("Database configuration error: Missing required credentials. Please check your .env file.");
}

// Initialize the connection
$conn = mysqli_init();

// Configure SSL for Aiven
$ssl_cert = __DIR__ . "/ca.pem";
if (file_exists($ssl_cert)) {
    mysqli_ssl_set($conn, NULL, NULL, $ssl_cert, NULL, NULL);
}

// Establish connection with SSL
if (!mysqli_real_connect($conn, $servername, $username, $password, $dbname, (int)$port, NULL, MYSQLI_CLIENT_SSL)) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Unable to connect to database. Please try again later.");
}

// Set MySQL session time zone
$tzOffset = env('DB_TIME_OFFSET', '+05:00');
@mysqli_query($conn, "SET time_zone = '" . mysqli_real_escape_string($conn, $tzOffset) . "'");

// Set UTF-8 charset
mysqli_set_charset($conn, 'utf8mb4');
?>