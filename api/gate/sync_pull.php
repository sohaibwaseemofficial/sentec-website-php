<?php
/**
 * REST Endpoint: High-Watermark Delta Pull Sync
 * GET /api/gate/sync_pull.php?since={timestamp}&role={role}
 * Returns only ticket IDs checked in by other devices since last sync.
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

$auth = gate_require_auth();
$since = (int)($_GET['since'] ?? 0);
if ($since > 2000000000) {
    // If timestamp was sent in milliseconds, convert to seconds
    $since = (int)($since / 1000);
}

$stationRole = $auth['role'] ?? 'all';
$usedTickets = [];

// Query logs created or updated since $since that were APPROVED
$sinceDatetime = date('Y-m-d H:i:s', $since);

$sql = "SELECT DISTINCT ticket_id FROM `scan_audit_logs` 
        WHERE `status` = 'APPROVED' 
        AND `created_at` >= ?";

if ($stationRole !== 'all') {
    $sql .= " AND `gate_type` = '" . $conn->real_escape_string($stationRole) . "'";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $sinceDatetime);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $usedTickets[] = $row['ticket_id'];
    }
}
$stmt->close();

gate_json_response([
    'success' => true,
    'server_time' => time(),
    'since' => $since,
    'used_tickets' => $usedTickets,
    'count' => count($usedTickets)
]);
