<?php
/**
 * REST Endpoint: Live Gate Operations Statistics
 * GET /api/gate/stats.php
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

$auth = gate_require_auth();

// 1. Total Admitted Counts
$totalSocialAdmitted = 0;
$totalEngineerAdmitted = 0;
$totalDuplicates = 0;
$todayAdmitted = 0;

$countRes = $conn->query("SELECT 
    COUNT(CASE WHEN status = 'APPROVED' AND gate_type = 'social' THEN 1 END) AS social_admitted,
    COUNT(CASE WHEN status = 'APPROVED' AND gate_type = 'engineer' THEN 1 END) AS engineer_admitted,
    COUNT(CASE WHEN status = 'DUPLICATE_REJECTED' THEN 1 END) AS duplicates,
    COUNT(CASE WHEN status = 'APPROVED' AND DATE(created_at) = CURDATE() THEN 1 END) AS today_admitted
    FROM `scan_audit_logs`");

if ($countRes) {
    $row = $countRes->fetch_assoc();
    $totalSocialAdmitted = (int)$row['social_admitted'];
    $totalEngineerAdmitted = (int)$row['engineer_admitted'];
    $totalDuplicates = (int)$row['duplicates'];
    $todayAdmitted = (int)$row['today_admitted'];
}

// 2. Active Stations
$stations = [];
$stRes = $conn->query("SELECT station_id, station_name, role, is_active, last_active_at FROM `gate_stations` ORDER BY id ASC");
if ($stRes) {
    while ($r = $stRes->fetch_assoc()) {
        $stations[] = $r;
    }
}

// 3. Recent 25 Logs
$recentLogs = [];
$logRes = $conn->query("SELECT log_id, ticket_id, attendee_name, volunteer_id, station_id, gate_type, status, notes, created_at 
    FROM `scan_audit_logs` ORDER BY id DESC LIMIT 25");
if ($logRes) {
    while ($r = $logRes->fetch_assoc()) {
        $recentLogs[] = $r;
    }
}

gate_json_response([
    'success' => true,
    'server_time' => time(),
    'stats' => [
        'social_admitted' => $totalSocialAdmitted,
        'engineer_admitted' => $totalEngineerAdmitted,
        'total_admitted' => $totalSocialAdmitted + $totalEngineerAdmitted,
        'today_admitted' => $todayAdmitted,
        'duplicate_rejections' => $totalDuplicates
    ],
    'stations' => $stations,
    'recent_logs' => $recentLogs
]);
