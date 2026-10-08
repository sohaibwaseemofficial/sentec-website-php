<?php
/**
 * REST Endpoint: Batch Push Sync (Outbox Flush)
 * POST /api/gate/sync_push.php
 * Flushes offline scans to central database with monotonic ordering and duplicate detection.
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gate_json_response(['success' => false, 'message' => 'Method Not Allowed. Use POST.'], 405);
}

$auth = gate_require_auth();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$logs = $input['logs'] ?? [];

if (!is_array($logs) || empty($logs)) {
    gate_json_response(['success' => true, 'ack_ids' => [], 'message' => 'No logs to sync.']);
}

$ackIds = [];
$syncedTs = time() * 1000;

foreach ($logs as $item) {
    $logId = trim($item['log_id'] ?? $item['id'] ?? '');
    $ticketId = trim($item['t'] ?? $item['ticket_id'] ?? '');
    $volunteerId = trim($item['v'] ?? $item['volunteer_id'] ?? $auth['volunteer_name']);
    $role = trim($item['r'] ?? $item['gate_type'] ?? $auth['role']);
    $clientStatus = trim($item['s'] ?? $item['status'] ?? 'APPROVED');
    $deviceTs = (int)($item['ts'] ?? $item['device_timestamp'] ?? $syncedTs);
    $day = (int)($item['day'] ?? 1);

    if (empty($logId) || empty($ticketId)) {
        continue;
    }

    // Check if this log_id was already received and recorded
    $checkStmt = $conn->prepare("SELECT id FROM `scan_audit_logs` WHERE `log_id` = ? LIMIT 1");
    $checkStmt->bind_param("s", $logId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        $ackIds[] = $logId;
        continue;
    }

    // Process check-in using the central resolver
    $res = gate_verify_and_checkin($conn, [
        'raw_code' => $ticketId,
        'station_role' => $auth['role'],
        'station_id' => $auth['station_id'],
        'volunteer_id' => $volunteerId,
        'device_id' => $auth['device_id'],
        'device_timestamp' => $deviceTs,
        'day' => $day,
        'log_id' => $logId
    ]);

    $ackIds[] = $logId;
}

gate_json_response([
    'success' => true,
    'synced_count' => count($ackIds),
    'ack_ids' => $ackIds,
    'server_time' => time()
]);
