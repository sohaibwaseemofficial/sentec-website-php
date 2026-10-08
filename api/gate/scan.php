<?php
/**
 * REST Endpoint: Single Live Scan Verification
 * POST /api/gate/scan.php
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gate_json_response(['success' => false, 'message' => 'Method Not Allowed. Use POST.'], 405);
}

// Require valid station authorization
$auth = gate_require_auth();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$qrCode = trim($input['qr_payload'] ?? $input['qr_code'] ?? $input['code'] ?? '');
$action = strtolower(trim($input['action'] ?? 'admit')); // 'lookup' or 'admit'
$day = (int)($input['day'] ?? 1);
$deviceTs = (int)($input['device_timestamp'] ?? (time() * 1000));
$logId = trim($input['log_id'] ?? ('live_' . bin2hex(random_bytes(8))));

if (empty($qrCode)) {
    gate_json_response(['success' => false, 'message' => 'Missing QR code data.'], 400);
}

// Mode 1: Pre-Admission Lookup (Inspection Mode: loads photo, ID card, status without marking attendance)
if ($action === 'lookup') {
    $lookup = gate_lookup_attendee($conn, $qrCode, $auth['role'], $day);
    gate_json_response([
        'success' => $lookup['valid'] && !empty($lookup['attendee']),
        'action' => 'lookup',
        'can_admit' => $lookup['can_admit'] ?? false,
        'status' => $lookup['status'],
        'ticket_id' => $lookup['ticket_id'],
        'role' => $lookup['role'] ?? $auth['role'],
        'attendee' => $lookup['attendee'],
        'message' => $lookup['message'] ?? ($lookup['error'] ?? 'Pass Loaded')
    ], 200);
}

// Mode 2: Atomic Check-In & Admission
$result = gate_verify_and_checkin($conn, [
    'raw_code' => $qrCode,
    'station_role' => $auth['role'],
    'station_id' => $auth['station_id'],
    'volunteer_id' => $auth['volunteer_name'],
    'device_id' => $auth['device_id'],
    'device_timestamp' => $deviceTs,
    'day' => $day,
    'log_id' => $logId
]);

gate_json_response($result, $result['success'] ? 200 : 422);
