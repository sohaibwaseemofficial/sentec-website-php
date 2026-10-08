<?php
/**
 * REST Endpoint: Station Authentication (4-Digit PIN or Setup QR)
 * POST /api/gate/auth_pin.php
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gate_json_response(['success' => false, 'message' => 'Method Not Allowed. Use POST.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pin = trim($input['pin'] ?? '');
$volunteerName = trim($input['volunteer_name'] ?? 'Gate Volunteer');
$deviceId = trim($input['device_id'] ?? 'Device_' . substr(md5($_SERVER['REMOTE_ADDR'] ?? 'local'), 0, 8));

if (empty($pin)) {
    gate_json_response(['success' => false, 'message' => 'Please enter a 4-digit station PIN.'], 400);
}

// 1. Check if station PIN exists in gate_stations
$stmt = $conn->prepare("SELECT * FROM `gate_stations` WHERE `station_pin` = ? AND `is_active` = 1 LIMIT 1");
$stmt->bind_param("s", $pin);
$stmt->execute();
$station = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$station) {
    gate_json_response([
        'success' => false,
        'message' => 'Invalid station PIN. Please check your assigned gate code.'
    ], 401);
}

// Update last active timestamp
$upd = $conn->prepare("UPDATE `gate_stations` SET `last_active_at` = NOW() WHERE `id` = ?");
$upd->bind_param("i", $station['id']);
$upd->execute();
$upd->close();

// Create signed session token
$tokenPayload = [
    'station_id' => $station['station_id'],
    'station_name' => $station['station_name'],
    'role' => $station['role'],
    'volunteer_name' => $volunteerName,
    'device_id' => $deviceId
];

$signedToken = gate_create_token($tokenPayload);

gate_json_response([
    'success' => true,
    'message' => 'Station Activated Successfully',
    'token' => $signedToken,
    'station' => [
        'station_id' => $station['station_id'],
        'station_name' => $station['station_name'],
        'role' => $station['role'],
        'volunteer_name' => $volunteerName,
        'device_id' => $deviceId
    ]
]);
