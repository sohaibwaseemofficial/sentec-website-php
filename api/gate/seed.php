<?php
/**
 * REST Endpoint: Pre-Event Whitelist Seed
 * GET /api/gate/seed.php
 * Downloads compact whitelist of all approved tickets for station's role for offline caching.
 */
require_once __DIR__ . '/../../gate_api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    gate_json_response(['ok' => true]);
}

$auth = gate_require_auth();
$stationRole = $auth['role'] ?? 'all';

$whitelist = [];

// 1. Fetch Social Attendees Whitelist (if allowed)
if ($stationRole === 'social' || $stationRole === 'all') {
    if (social_attendees_table_exists($conn)) {
        $sql = "SELECT sa.id, sa.registration_id, sa.person_index, sa.full_name, sa.cnic, sa.label, sa.face_image, sa.id_card_image, sa.attendance_status, sr.status AS parent_status
                FROM social_attendees sa
                JOIN social_registrations sr ON sr.id = sa.registration_id
                WHERE sr.status = 'approved'";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $isUsed = (strtolower($row['attendance_status'] ?? '') === 'present') ? 1 : 0;
                $item = [
                    't' => 'SOC-' . (int)$row['id'],
                    'n' => $row['full_name'] ?: 'Guest',
                    'c' => $row['cnic'] ?: '',
                    'r' => 'social',
                    'u' => $isUsed,
                    'm' => $row['label'] ?: 'Pass',
                    'p' => gate_normalize_image_url($row['face_image']),
                    'card' => gate_normalize_image_url($row['id_card_image'])
                ];
                $whitelist[] = $item;

                // Also seed the SOC-REG-X-Y alias key so registration QR passes match immediately offline!
                if (!empty($row['registration_id'])) {
                    $pIdx = $row['person_index'] ?: 1;
                    $aliasItem = $item;
                    $aliasItem['t'] = "SOC-REG-{$row['registration_id']}-{$pIdx}";
                    $whitelist[] = $aliasItem;
                }
            }
        }
    }
}

// 2. Fetch Engineer's Code Attendees Whitelist (if allowed)
if ($stationRole === 'engineer' || $stationRole === 'all') {
    if (event_attendees_table_exists($conn)) {
        $sql = "SELECT ea.id, ea.registration_id, ea.full_name, ea.cnic, ea.roll_number, ea.label, ea.face_image, ea.id_card_image, ea.day1_status, ea.day2_status,
                       er.team_name, er.module_selection, er.status AS parent_status
                FROM event_attendees ea
                JOIN event_registrations er ON er.id = ea.registration_id
                WHERE er.status = 'approved'";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $d1 = (strtolower($row['day1_status'] ?? '') === 'present') ? 1 : 0;
                $d2 = (strtolower($row['day2_status'] ?? '') === 'present') ? 1 : 0;
                $item = [
                    't' => 'ENG-' . (int)$row['id'],
                    'n' => $row['full_name'] ?: 'Participant',
                    'c' => $row['roll_number'] ?: $row['cnic'] ?: '',
                    'r' => 'engineer',
                    'u' => $d1,
                    'u2' => $d2,
                    'm' => ($row['module_selection'] ?? '') . ' (' . ($row['team_name'] ?? '') . ')',
                    'p' => gate_normalize_image_url($row['face_image']),
                    'card' => gate_normalize_image_url($row['id_card_image'])
                ];
                $whitelist[] = $item;

                if (!empty($row['registration_id'])) {
                    $aliasItem = $item;
                    $aliasItem['t'] = "ENG-REG-{$row['registration_id']}";
                    $whitelist[] = $aliasItem;
                }
            }
        }
    }
}

gate_json_response([
    'success' => true,
    'server_time' => time(),
    'role' => $stationRole,
    'count' => count($whitelist),
    'whitelist' => $whitelist
]);
