<?php
/**
 * Heartbeat Session Keep-Alive
 * Prevents session expiry while users are filling multi-step registration forms
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 'alive',
    'user_id' => (int)($_SESSION['user_id'] ?? 0),
    'time' => time()
]);
exit;
