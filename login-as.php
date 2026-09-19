<?php
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';

$token = $_GET['token'] ?? '';
if (empty($token)) die('Invalid request.');

$key = env('IMPERSONATE_KEY', 'default_change_me');
$data = base64_decode($token);
if ($data === false) die('Invalid token.');

$parts = explode('::', $data, 2);
if (count($parts) !== 2) die('Invalid token format.');

$iv = $parts[0];
$encrypted = $parts[1];

$payload = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
if ($payload === false) die('Decryption failed.');

$json = json_decode($payload, true);
if (!$json || !isset($json['user_id'], $json['expires'])) die('Invalid payload.');

if ($json['expires'] < time()) die('This link has expired. Please generate a new one.');

$userId = intval($json['user_id']);

$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die('User not found.');

session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];

$logMsg = date('Y-m-d H:i:s') . " | Impersonation used: Client logged in as User ID {$userId} ({$user['full_name']})";
@file_put_contents(__DIR__ . '/storage/impersonate.log', $logMsg . "\n", FILE_APPEND);

header("Location: dashboard.php");
exit;