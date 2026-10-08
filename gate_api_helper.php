<?php
/**
 * SENTEC Gate Management & Scanning Engine Helper
 * Core logic for authentication, QR payload resolution, atomic check-ins, and delta syncing.
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/social_attendees_helper.php';
require_once __DIR__ . '/event_attendees_helper.php';

// Helper: Standard JSON response with optional gzip and CORS
function gate_json_response($data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // Enable gzip compression if supported
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Secret key for gate token signatures
function gate_get_secret(): string {
    return env('GATE_AUTH_SECRET', env('APP_KEY', 'sentec_gate_super_secret_key_2026'));
}

// Generate a signed gate session token (valid for 48 hours)
function gate_create_token(array $payload): string {
    $payload['exp'] = time() + (48 * 3600); // 48 hours validity
    $payload['iat'] = time();
    $json = json_encode($payload);
    $b64 = base64_encode($json);
    $sig = hash_hmac('sha256', $b64, gate_get_secret());
    return $b64 . '.' . $sig;
}

// Verify a signed gate session token
function gate_verify_token(?string $token): ?array {
    if (empty($token) || strpos($token, '.') === false) {
        return null;
    }
    [$b64, $sig] = explode('.', $token, 2);
    $expectedSig = hash_hmac('sha256', $b64, gate_get_secret());
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }
    $json = base64_decode($b64);
    $payload = json_decode($json, true);
    if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
        return null; // Expired or malformed
    }
    return $payload;
}

// Require valid station authorization for an API request
function gate_require_auth(): array {
    $token = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (isset($_REQUEST['token'])) {
        $token = trim($_REQUEST['token']);
    }
    
    $auth = gate_verify_token($token);
    if (!$auth) {
        gate_json_response([
            'success' => false,
            'status' => 'UNAUTHORIZED',
            'message' => 'Invalid or expired station authorization. Please sign in with station PIN.'
        ], 401);
    }
    return $auth;
}

/**
 * Intelligent QR Parser
 * Handles:
 * 1. Full URLs: https://sentecneduet.live/gate/verify_social.php?attendee=42
 * 2. Full URLs: https://sentecneduet.live/gate_event/verify_event.php?attendee=108
 * 3. Compact IDs: "SOC-42", "ENG-108", "social:42", "engineer:108"
 * 4. Numeric string: assumes current station role
 */
function gate_parse_qr(string $rawInput, string $defaultRole = 'all'): array {
    $rawInput = trim($rawInput);
    if (empty($rawInput)) {
        return ['valid' => false, 'error' => 'Empty code'];
    }

    // 1. Check if it is a URL
    if (filter_var($rawInput, FILTER_VALIDATE_URL) || strpos($rawInput, 'verify_social') !== false || strpos($rawInput, 'verify_event') !== false) {
        $parts = parse_url($rawInput);
        parse_str($parts['query'] ?? '', $queryParams);

        if (strpos($rawInput, 'verify_social') !== false || strpos($rawInput, '/gate/') !== false) {
            $attendeeId = (int)($queryParams['attendee'] ?? 0);
            $regId = (int)($queryParams['id'] ?? 0);
            $memberIdx = (int)($queryParams['member'] ?? 1);
            return [
                'valid' => true,
                'role' => 'social',
                'attendee_id' => $attendeeId,
                'registration_id' => $regId,
                'member_index' => $memberIdx,
                'ticket_id' => $attendeeId > 0 ? ('SOC-' . $attendeeId) : ('SOC-REG-' . $regId . '-' . $memberIdx)
            ];
        }

        if (strpos($rawInput, 'verify_event') !== false || strpos($rawInput, 'gate_event') !== false) {
            $attendeeId = (int)($queryParams['attendee'] ?? 0);
            $regId = (int)($queryParams['id'] ?? 0);
            return [
                'valid' => true,
                'role' => 'engineer',
                'attendee_id' => $attendeeId,
                'registration_id' => $regId,
                'ticket_id' => $attendeeId > 0 ? ('ENG-' . $attendeeId) : ('ENG-REG-' . $regId)
            ];
        }
    }

    // 2. Check compact prefixes
    if (preg_match('/^(SOC|SOCIAL)[-_:](\d+)/i', $rawInput, $m)) {
        return [
            'valid' => true,
            'role' => 'social',
            'attendee_id' => (int)$m[2],
            'ticket_id' => 'SOC-' . (int)$m[2]
        ];
    }
    if (preg_match('/^(ENG|ENGINEER|EVENT)[-_:](\d+)/i', $rawInput, $m)) {
        return [
            'valid' => true,
            'role' => 'engineer',
            'attendee_id' => (int)$m[2],
            'ticket_id' => 'ENG-' . (int)$m[2]
        ];
    }

    // 3. Fallback: Pure numeric ID
    if (ctype_digit($rawInput)) {
        $numId = (int)$rawInput;
        $role = in_array($defaultRole, ['engineer', 'social']) ? $defaultRole : 'social';
        $prefix = ($role === 'engineer') ? 'ENG-' : 'SOC-';
        return [
            'valid' => true,
            'role' => $role,
            'attendee_id' => $numId,
            'ticket_id' => $prefix . $numId
        ];
    }

    return ['valid' => false, 'error' => 'Unrecognized ticket format'];
}

/**
 * Atomic Check-In Processor
 * Validates, checks duplicates, updates status, and logs into scan_audit_logs.
 */
function gate_verify_and_checkin(mysqli $conn, array $params): array {
    $rawInput = trim($params['raw_code'] ?? '');
    $stationRole = $params['station_role'] ?? 'all';
    $stationId = $params['station_id'] ?? 'GATE_UNKNOWN';
    $volunteerId = $params['volunteer_id'] ?? 'Volunteer';
    $deviceId = $params['device_id'] ?? 'UnknownDevice';
    $deviceTs = (int)($params['device_timestamp'] ?? (time() * 1000));
    $eventDay = (int)($params['day'] ?? 1); // For Engineer's code (Day 1 vs Day 2)
    $logId = !empty($params['log_id']) ? $params['log_id'] : ('log_' . bin2hex(random_bytes(10)));
    $syncedTs = time() * 1000;

    $parsed = gate_parse_qr($rawInput, $stationRole);
    if (!$parsed['valid']) {
        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => substr($rawInput, 0, 64) ?: 'UNKNOWN',
            'attendee_name' => 'Unknown',
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'device_id' => $deviceId,
            'gate_type' => $stationRole,
            'status' => 'INVALID_FORMAT',
            'notes' => $parsed['error'] ?? 'Unrecognized code',
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);
        return [
            'success' => false,
            'status' => 'INVALID_FORMAT',
            'message' => 'Unrecognized QR Code format'
        ];
    }

    $ticketRole = $parsed['role'];
    $attendeeId = $parsed['attendee_id'] ?? 0;
    $regId = $parsed['registration_id'] ?? 0;
    $ticketId = $parsed['ticket_id'] ?? ('TCK-' . $attendeeId);

    // Role Enforcement: If this station is locked to engineer or social
    if ($stationRole !== 'all' && $stationRole !== $ticketRole) {
        $expected = ($stationRole === 'engineer') ? "Engineer's Code" : "RUH-E-RAQS Social Night";
        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => $ticketId,
            'attendee_name' => 'Unknown',
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'gate_type' => $ticketRole,
            'status' => 'INVALID_ROLE',
            'notes' => "Scanned at {$stationRole} gate",
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);
        return [
            'success' => false,
            'status' => 'INVALID_ROLE',
            'message' => "Wrong Gate: This pass belongs to {$ticketRole}, but this station only accepts {$expected}."
        ];
    }

    // =========================================================================
    // CASE A: RUH-E-RAQS SOCIAL EVENT CHECK-IN
    // =========================================================================
    if ($ticketRole === 'social') {
        $attendee = null;
        if ($attendeeId > 0 && social_attendees_table_exists($conn)) {
            $attendee = social_attendee_fetch_with_registration($conn, $attendeeId);
        } elseif ($regId > 0) {
            $stmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $attendee = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$attendee) {
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => 'Not Found',
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'social',
                'status' => 'NOT_FOUND',
                'notes' => 'Social attendee record not found in database',
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'NOT_FOUND',
                'message' => 'Pass record not found in system.'
            ];
        }

        $attendeeName = $attendee['full_name'] ?? 'Attendee';
        $attendeeCnic = $attendee['cnic'] ?? '';
        $parentStatus = strtolower($attendee['group_status'] ?? $attendee['status'] ?? 'pending');
        $currentAttendance = strtolower($attendee['attendance_status'] ?? 'pending');

        // Check if registration was approved
        if ($parentStatus !== 'approved') {
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => $attendeeName,
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'social',
                'status' => 'PENDING_APPROVAL',
                'notes' => "Status is {$parentStatus}",
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'PENDING_APPROVAL',
                'attendee' => [
                    'name' => $attendeeName,
                    'cnic' => $attendeeCnic,
                    'status' => $parentStatus
                ],
                'message' => "Registration is {$parentStatus}. Must be approved by Admin desk before entry."
            ];
        }

        // Check for Duplicate Check-In
        if ($currentAttendance === 'present' || $currentAttendance === 'attended') {
            $entryTime = $attendee['entry_time'] ?? '';
            $timeMsg = $entryTime ? ("Already entered at " . date('h:i A', strtotime($entryTime))) : "Already used.";
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => $attendeeName,
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'social',
                'status' => 'DUPLICATE_REJECTED',
                'notes' => "Duplicate entry attempt. Initial entry: {$entryTime}",
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'DUPLICATE_REJECTED',
                'attendee' => [
                    'name' => $attendeeName,
                    'cnic' => $attendeeCnic,
                    'entry_time' => $entryTime
                ],
                'message' => "DUPLICATE PASS: {$timeMsg}"
            ];
        }

        // Mark as Present
        $now = date('Y-m-d H:i:s');
        if (social_attendees_table_exists($conn) && !empty($attendee['id'])) {
            $upd = $conn->prepare("UPDATE social_attendees SET attendance_status = 'present', entry_time = ? WHERE id = ?");
            $upd->bind_param("si", $now, $attendee['id']);
            $upd->execute();
            $upd->close();
        } else {
            $upd = $conn->prepare("UPDATE social_registrations SET attendance_status = 'present', entry_time = ? WHERE id = ?");
            $upd->bind_param("si", $now, $attendee['id']);
            $upd->execute();
            $upd->close();
        }

        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => $ticketId,
            'attendee_name' => $attendeeName,
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'gate_type' => 'social',
            'status' => 'APPROVED',
            'notes' => 'Successfully admitted',
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);

        return [
            'success' => true,
            'status' => 'APPROVED',
            'ticket_id' => $ticketId,
            'event' => 'RUH-E-RAQS (Social Evening)',
            'attendee' => [
                'name' => $attendeeName,
                'cnic' => $attendeeCnic,
                'phone' => $attendee['phone'] ?? '',
                'tier' => $attendee['label'] ?? 'Individual Pass',
                'photo' => !empty($attendee['face_image']) ? ('uploads/social_faces/' . $attendee['face_image']) : null
            ],
            'message' => 'Access Approved'
        ];
    }

    // =========================================================================
    // CASE B: ENGINEER'S CODE (EVENT / OLYMPIAD) CHECK-IN
    // =========================================================================
    if ($ticketRole === 'engineer') {
        $attendee = null;
        if ($attendeeId > 0 && event_attendees_table_exists($conn)) {
            $attendee = event_attendee_fetch_with_registration($conn, $attendeeId);
        }

        if (!$attendee) {
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => 'Not Found',
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'engineer',
                'status' => 'NOT_FOUND',
                'notes' => 'Event attendee record not found',
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'NOT_FOUND',
                'message' => "Engineer's Code attendee record not found."
            ];
        }

        $attendeeName = $attendee['full_name'] ?: 'Participant';
        $teamName = $attendee['team_name'] ?? 'Team';
        $module = $attendee['module_selection'] ?? 'Module';
        $dayCol = ($eventDay === 2) ? 'day2_status' : 'day1_status';
        $currentAttendance = strtolower($attendee[$dayCol] ?? 'pending');
        $regStatus = strtolower($attendee['registration_status'] ?? $attendee['status'] ?? 'pending');

        if ($regStatus !== 'approved') {
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => $attendeeName,
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'engineer',
                'status' => 'PENDING_APPROVAL',
                'notes' => "Team status is {$regStatus}",
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'PENDING_APPROVAL',
                'attendee' => ['name' => $attendeeName, 'team' => $teamName, 'module' => $module],
                'message' => "Team registration is {$regStatus}. Not yet approved."
            ];
        }

        if ($currentAttendance === 'present') {
            gate_insert_audit_log($conn, [
                'log_id' => $logId,
                'ticket_id' => $ticketId,
                'attendee_name' => $attendeeName,
                'volunteer_id' => $volunteerId,
                'station_id' => $stationId,
                'gate_type' => 'engineer',
                'status' => 'DUPLICATE_REJECTED',
                'notes' => "Day {$eventDay} already marked present",
                'device_ts' => $deviceTs,
                'synced_ts' => $syncedTs
            ]);
            return [
                'success' => false,
                'status' => 'DUPLICATE_REJECTED',
                'attendee' => ['name' => $attendeeName, 'team' => $teamName, 'module' => $module],
                'message' => "ALREADY ADMITTED: Day {$eventDay} attendance was already marked."
            ];
        }

        // Mark Day attendance
        event_mark_attendance($conn, $attendee['id'], $eventDay);

        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => $ticketId,
            'attendee_name' => $attendeeName,
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'gate_type' => 'engineer',
            'status' => 'APPROVED',
            'notes' => "Admitted for Day {$eventDay}",
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);

        return [
            'success' => true,
            'status' => 'APPROVED',
            'ticket_id' => $ticketId,
            'event' => "Engineer's Code 2026",
            'day' => $eventDay,
            'attendee' => [
                'name' => $attendeeName,
                'team' => $teamName,
                'module' => $module,
                'role' => $attendee['label'] ?? 'Participant',
                'roll_number' => $attendee['roll_number'] ?? '',
                'photo' => !empty($attendee['face_image']) ? ('uploads/faces/' . $attendee['face_image']) : null
            ],
            'message' => "Cleared for Day {$eventDay}"
        ];
    }

    return ['success' => false, 'status' => 'ERROR', 'message' => 'Invalid event type'];
}

// Helper: Insert audit log safely
function gate_insert_audit_log(mysqli $conn, array $data): void {
    $deviceId = $data['device_id'] ?? 'device_unknown';
    $stmt = $conn->prepare("INSERT INTO `scan_audit_logs` 
        (`log_id`, `ticket_id`, `attendee_name`, `volunteer_id`, `station_id`, `device_id`, `gate_type`, `status`, `notes`, `device_timestamp`, `synced_timestamp`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param(
            "sssssssssii",
            $data['log_id'],
            $data['ticket_id'],
            $data['attendee_name'],
            $data['volunteer_id'],
            $data['station_id'],
            $deviceId,
            $data['gate_type'],
            $data['status'],
            $data['notes'],
            $data['device_ts'],
            $data['synced_ts']
        );
        $stmt->execute();
        $stmt->close();
    }
}
