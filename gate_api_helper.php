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
function gate_normalize_image_url(?string $path): ?string {
    if (empty($path)) return null;
    $path = trim($path);
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    return 'https://sentecneduet.live/' . ltrim($path, '/');
}

/**
 * Universal Intelligent QR Parser
 * Handles:
 * 1. Station Setup JSON: {"station_id":"...", "pin":"..."}
 * 2. Full URLs: https://sentecneduet.live/gate/verify_social.php?id=3&member=1 or attendee=42
 * 3. Compact IDs: "SOC-42", "ENG-108", "SOC-REG-3-1", "ENG-REG-5"
 * 4. Numeric string: assumes current station role
 */
function gate_parse_qr(string $rawInput, string $defaultRole = 'all'): array {
    $rawInput = trim($rawInput);
    if (empty($rawInput)) {
        return ['valid' => false, 'error' => 'Empty code'];
    }

    // 1. Check if it is a URL
    if (filter_var($rawInput, FILTER_VALIDATE_URL) || strpos($rawInput, 'verify_social') !== false || strpos($rawInput, 'verify_event') !== false || strpos($rawInput, '/gate/') !== false || strpos($rawInput, '/gate_event/') !== false) {
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

    // 2. Check compact registration prefixes: SOC-REG-3-1 or ENG-REG-5
    if (preg_match('/^(SOC|SOCIAL)[-_:]REG[-_:](\d+)(?:[-_:](\d+))?/i', $rawInput, $m)) {
        $regId = (int)$m[2];
        $memberIdx = isset($m[3]) ? (int)$m[3] : 1;
        return [
            'valid' => true,
            'role' => 'social',
            'attendee_id' => 0,
            'registration_id' => $regId,
            'member_index' => $memberIdx,
            'ticket_id' => "SOC-REG-{$regId}-{$memberIdx}"
        ];
    }
    if (preg_match('/^(ENG|ENGINEER|EVENT)[-_:]REG[-_:](\d+)/i', $rawInput, $m)) {
        $regId = (int)$m[2];
        return [
            'valid' => true,
            'role' => 'engineer',
            'attendee_id' => 0,
            'registration_id' => $regId,
            'ticket_id' => "ENG-REG-{$regId}"
        ];
    }

    // 3. Check compact ID prefixes: SOC-42, ENG-108
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

    // 4. Fallback: Pure numeric ID
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
 * Look up attendee record, photos, ID cards, and admission eligibility
 * Does NOT mark attendance yet — used for pre-admission verification.
 */
function gate_lookup_attendee(mysqli $conn, string $rawInput, string $stationRole = 'all', int $eventDay = 1): array {
    $parsed = gate_parse_qr($rawInput, $stationRole);
    if (!$parsed['valid']) {
        return [
            'valid' => false,
            'can_admit' => false,
            'status' => 'INVALID_FORMAT',
            'error' => $parsed['error'] ?? 'Unrecognized pass format',
            'ticket_id' => substr($rawInput, 0, 64) ?: 'UNKNOWN',
            'attendee' => null
        ];
    }

    $ticketRole = $parsed['role'];
    $attendeeId = $parsed['attendee_id'] ?? 0;
    $regId = $parsed['registration_id'] ?? 0;
    $memberIdx = $parsed['member_index'] ?? 1;
    $ticketId = $parsed['ticket_id'] ?? 'UNKNOWN';

    $attendeeData = null;

    // --- CASE A: SOCIAL ATTENDEE LOOKUP ---
    if ($ticketRole === 'social') {
        if ($attendeeId > 0 && social_attendees_table_exists($conn)) {
            $row = social_attendee_fetch_with_registration($conn, $attendeeId);
            if ($row) {
                $attendeeData = [
                    'id' => (int)$row['id'],
                    'registration_id' => (int)$row['registration_id'],
                    'name' => $row['full_name'],
                    'cnic' => $row['cnic'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'roll_number' => '',
                    'team' => '',
                    'category' => $row['label'] ?? 'Guest Pass',
                    'event_name' => 'RUH-E-RAQS (Sufi Musical Evening)',
                    'face_image' => gate_normalize_image_url($row['face_image']),
                    'id_card_image' => gate_normalize_image_url($row['id_card_image']),
                    'payment_proof' => gate_normalize_image_url($row['payment_proof'] ?? $row['group_payment_proof'] ?? null),
                    'payment_status' => $row['payment_status'] ?? $row['group_payment_status'] ?? 'confirmed',
                    'registration_status' => strtolower($row['group_status'] ?? $row['status'] ?? 'pending'),
                    'attendance_status' => strtolower($row['attendance_status'] ?? 'pending'),
                    'entry_time' => $row['entry_time'] ?? null
                ];
            }
        }

        if (!$attendeeData && $regId > 0) {
            if (social_attendees_table_exists($conn)) {
                $group = social_attendees_fetch_group($conn, $regId);
                if (!empty($group)) {
                    $targetIdx = max(0, min(count($group) - 1, $memberIdx - 1));
                    $row = $group[$targetIdx];
                    $attendeeId = (int)$row['id'];
                    $attendeeData = [
                        'id' => $attendeeId,
                        'registration_id' => $regId,
                        'name' => $row['full_name'],
                        'cnic' => $row['cnic'] ?? '',
                        'phone' => $row['phone'] ?? '',
                        'email' => $row['email'] ?? '',
                        'roll_number' => '',
                        'team' => '',
                        'category' => $row['label'] ?? 'Guest Pass',
                        'event_name' => 'RUH-E-RAQS (Sufi Musical Evening)',
                        'face_image' => gate_normalize_image_url($row['face_image']),
                        'id_card_image' => gate_normalize_image_url($row['id_card_image']),
                        'payment_proof' => gate_normalize_image_url($row['payment_proof'] ?? null),
                        'payment_status' => $row['payment_status'] ?? 'confirmed',
                        'registration_status' => strtolower($row['status'] ?? 'pending'),
                        'attendance_status' => strtolower($row['attendance_status'] ?? 'pending'),
                        'entry_time' => $row['entry_time'] ?? null
                    ];
                }
            }

            if (!$attendeeData) {
                $stmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $regId);
                $stmt->execute();
                $sr = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($sr) {
                    $name = $sr['full_name'];
                    $cnic = $sr['cnic'] ?? '';
                    $phone = $sr['phone'] ?? '';
                    $email = $sr['email'] ?? '';
                    $face = $sr['face_image'] ?? null;
                    $card = $sr['id_card_image'] ?? null;

                    if ($memberIdx === 2 && !empty($sr['participant2_name'])) {
                        $name = $sr['participant2_name'];
                        $cnic = $sr['participant2_cnic'] ?? '';
                        $phone = $sr['participant2_phone'] ?? '';
                        $email = $sr['participant2_email'] ?? '';
                        $face = $sr['participant2_face'] ?? null;
                        $card = $sr['participant2_card'] ?? null;
                    } elseif ($memberIdx === 3 && !empty($sr['participant3_name'])) {
                        $name = $sr['participant3_name'];
                        $cnic = $sr['participant3_cnic'] ?? '';
                        $phone = $sr['participant3_phone'] ?? '';
                        $email = $sr['participant3_email'] ?? '';
                        $face = $sr['participant3_face'] ?? null;
                        $card = $sr['participant3_card'] ?? null;
                    }

                    $attendeeData = [
                        'id' => 0,
                        'registration_id' => $regId,
                        'member_index' => $memberIdx,
                        'name' => $name,
                        'cnic' => $cnic,
                        'phone' => $phone,
                        'email' => $email,
                        'roll_number' => '',
                        'team' => '',
                        'category' => "Pass #{$memberIdx}",
                        'event_name' => 'RUH-E-RAQS (Sufi Musical Evening)',
                        'face_image' => gate_normalize_image_url($face),
                        'id_card_image' => gate_normalize_image_url($card),
                        'payment_proof' => gate_normalize_image_url($sr['payment_proof'] ?? null),
                        'payment_status' => $sr['payment_status'] ?? 'confirmed',
                        'registration_status' => strtolower($sr['status'] ?? 'pending'),
                        'attendance_status' => strtolower($sr['attendance_status'] ?? 'pending'),
                        'entry_time' => $sr['entry_time'] ?? null
                    ];
                }
            }
        }
    }

    // --- CASE B: ENGINEER ATTENDEE LOOKUP ---
    if ($ticketRole === 'engineer') {
        if ($attendeeId > 0 && event_attendees_table_exists($conn)) {
            $row = event_attendee_fetch_with_registration($conn, $attendeeId);
            if ($row) {
                $dayCol = ($eventDay === 2) ? 'day2_status' : 'day1_status';
                $attendeeData = [
                    'id' => (int)$row['id'],
                    'registration_id' => (int)($row['registration_id'] ?? 0),
                    'name' => $row['full_name'],
                    'cnic' => $row['cnic'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'roll_number' => $row['roll_number'] ?? '',
                    'team' => $row['team_name'] ?? 'Team',
                    'category' => $row['label'] ?? 'Participant',
                    'module' => $row['module_selection'] ?? 'Olympiad',
                    'event_name' => "Engineer's Code: " . ($row['module_selection'] ?? 'Olympiad'),
                    'face_image' => gate_normalize_image_url($row['face_image'] ?? null),
                    'id_card_image' => gate_normalize_image_url($row['id_card_image'] ?? null),
                    'payment_proof' => null,
                    'payment_status' => 'confirmed',
                    'registration_status' => strtolower($row['registration_status'] ?? $row['status'] ?? 'pending'),
                    'attendance_status' => strtolower($row[$dayCol] ?? 'pending'),
                    'day1_status' => strtolower($row['day1_status'] ?? 'pending'),
                    'day2_status' => strtolower($row['day2_status'] ?? 'pending'),
                    'entry_time' => $row['entry_time'] ?? null
                ];
            }
        }
        if (!$attendeeData && $regId > 0) {
            $stmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $er = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($er) {
                $attendeeData = [
                    'id' => 0,
                    'registration_id' => $regId,
                    'name' => $er['leader_name'] ?? $er['team_name'] ?? 'Participant',
                    'cnic' => $er['leader_cnic'] ?? '',
                    'phone' => $er['leader_phone'] ?? '',
                    'email' => $er['leader_email'] ?? '',
                    'roll_number' => $er['leader_roll'] ?? '',
                    'team' => $er['team_name'] ?? 'Team',
                    'category' => 'Team Leader',
                    'module' => $er['module_selection'] ?? $er['module_name'] ?? 'Olympiad',
                    'event_name' => "Engineer's Code: " . ($er['module_selection'] ?? $er['module_name'] ?? 'Olympiad'),
                    'face_image' => gate_normalize_image_url($er['leader_face'] ?? null),
                    'id_card_image' => gate_normalize_image_url($er['leader_card'] ?? null),
                    'payment_proof' => null,
                    'payment_status' => 'confirmed',
                    'registration_status' => strtolower($er['status'] ?? 'pending'),
                    'attendance_status' => 'pending',
                    'entry_time' => null
                ];
            }
        }
    }

    if (!$attendeeData) {
        return [
            'valid' => true,
            'can_admit' => false,
            'status' => 'NOT_FOUND',
            'ticket_id' => $ticketId,
            'role' => $ticketRole,
            'error' => "Pass #{$ticketId} not found in database.",
            'attendee' => null
        ];
    }

    $attendeeData['ticket_id'] = $ticketId;
    $attendeeData['gate_type'] = $ticketRole;

    // Check Role Mismatch (Strict Gate Restriction)
    if ($stationRole !== 'all' && $stationRole !== $ticketRole) {
        $targetGate = ($ticketRole === 'social') ? "RUH-E-RAQS Social Night Gate" : "Engineer's Code Gate";
        $passRoleName = ($ticketRole === 'social') ? "RUH-E-RAQS (Social Night)" : "Engineer's Code Registration";
        return [
            'valid' => true,
            'can_admit' => false,
            'status' => 'INVALID_ROLE',
            'ticket_id' => $ticketId,
            'role' => $ticketRole,
            'error' => "RESTRICTION HIT! This pass belongs to {$passRoleName}. Entry is NOT permitted at this gate! Please direct attendee to {$targetGate}.",
            'attendee' => $attendeeData
        ];
    }

    // Check Registration Approval Status
    if ($attendeeData['registration_status'] !== 'approved') {
        return [
            'valid' => true,
            'can_admit' => false,
            'status' => 'NOT_APPROVED',
            'ticket_id' => $ticketId,
            'role' => $ticketRole,
            'error' => "RESTRICTION HIT! Registration status is '" . strtoupper($attendeeData['registration_status']) . "'. Must be approved at Admin Desk.",
            'attendee' => $attendeeData
        ];
    }

    // Check Duplicate Attendance
    $isAttended = ($attendeeData['attendance_status'] === 'present' || $attendeeData['attendance_status'] === 'attended');
    if ($isAttended) {
        $entryTimeStr = $attendeeData['entry_time'] ? date('h:i A', strtotime($attendeeData['entry_time'])) : 'earlier today';
        return [
            'valid' => true,
            'can_admit' => false,
            'status' => 'DUPLICATE',
            'ticket_id' => $ticketId,
            'role' => $ticketRole,
            'error' => "RESTRICTION HIT // DUPLICATE ENTRY! Pass already scanned and admitted at {$entryTimeStr}.",
            'attendee' => $attendeeData
        ];
    }

    // All clear -> Ready to admit
    return [
        'valid' => true,
        'can_admit' => true,
        'status' => 'READY_TO_ADMIT',
        'ticket_id' => $ticketId,
        'role' => $ticketRole,
        'message' => "Pass Verified. Verify attendee photo & ID card before admitting.",
        'attendee' => $attendeeData
    ];
}

/**
 * Atomic Check-In Processor
 * Validates, checks duplicates, updates status in database, and logs into scan_audit_logs.
 */
function gate_verify_and_checkin(mysqli $conn, array $params): array {
    $rawInput = trim($params['raw_code'] ?? '');
    $stationRole = $params['station_role'] ?? 'all';
    $stationId = $params['station_id'] ?? 'GATE_UNKNOWN';
    $volunteerId = $params['volunteer_id'] ?? 'Volunteer';
    $deviceId = $params['device_id'] ?? 'UnknownDevice';
    $deviceTs = (int)($params['device_timestamp'] ?? (time() * 1000));
    $eventDay = (int)($params['day'] ?? 1);
    $logId = !empty($params['log_id']) ? $params['log_id'] : ('log_' . bin2hex(random_bytes(10)));
    $syncedTs = time() * 1000;

    $lookup = gate_lookup_attendee($conn, $rawInput, $stationRole, $eventDay);

    $ticketId = $lookup['ticket_id'] ?? substr($rawInput, 0, 64) ?: 'UNKNOWN';
    $attendee = $lookup['attendee'];
    $attendeeName = $attendee['name'] ?? 'Unknown';
    $ticketRole = $lookup['role'] ?? $stationRole;

    if (!$lookup['valid']) {
        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => $ticketId,
            'attendee_name' => $attendeeName,
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'device_id' => $deviceId,
            'gate_type' => $ticketRole,
            'status' => 'INVALID_FORMAT',
            'notes' => $lookup['error'] ?? 'Unrecognized pass format',
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);
        return [
            'success' => false,
            'status' => 'INVALID_FORMAT',
            'ticket_id' => $ticketId,
            'attendee' => $attendee,
            'message' => $lookup['error'] ?? 'Unrecognized QR Code'
        ];
    }

    if (!$lookup['can_admit']) {
        $logStatus = ($lookup['status'] === 'DUPLICATE') ? 'DUPLICATE_REJECTED' : $lookup['status'];
        gate_insert_audit_log($conn, [
            'log_id' => $logId,
            'ticket_id' => $ticketId,
            'attendee_name' => $attendeeName,
            'volunteer_id' => $volunteerId,
            'station_id' => $stationId,
            'device_id' => $deviceId,
            'gate_type' => $ticketRole,
            'status' => $logStatus,
            'notes' => $lookup['error'] ?? 'Check-in restricted',
            'device_ts' => $deviceTs,
            'synced_ts' => $syncedTs
        ]);
        return [
            'success' => false,
            'status' => $logStatus,
            'ticket_id' => $ticketId,
            'attendee' => $attendee,
            'message' => $lookup['error']
        ];
    }

    // PERFORM DATABASE WRITE & ADMIT
    $now = date('Y-m-d H:i:s');
    if ($ticketRole === 'social') {
        $saId = (int)($attendee['id'] ?? 0);
        $srId = (int)($attendee['registration_id'] ?? 0);
        if ($saId > 0 && social_attendees_table_exists($conn)) {
            $upd = $conn->prepare("UPDATE social_attendees SET attendance_status = 'present', entry_time = ? WHERE id = ?");
            $upd->bind_param("si", $now, $saId);
            $upd->execute();
            $upd->close();
        } elseif ($srId > 0) {
            $upd = $conn->prepare("UPDATE social_registrations SET attendance_status = 'present', entry_time = ? WHERE id = ?");
            $upd->bind_param("si", $now, $srId);
            $upd->execute();
            $upd->close();
        }
    } elseif ($ticketRole === 'engineer') {
        $eaId = (int)($attendee['id'] ?? 0);
        if ($eaId > 0 && function_exists('event_mark_attendance')) {
            event_mark_attendance($conn, $eaId, $eventDay);
        }
    }

    // Insert approved audit record with REAL attendee name!
    gate_insert_audit_log($conn, [
        'log_id' => $logId,
        'ticket_id' => $ticketId,
        'attendee_name' => $attendeeName,
        'volunteer_id' => $volunteerId,
        'station_id' => $stationId,
        'device_id' => $deviceId,
        'gate_type' => $ticketRole,
        'status' => 'APPROVED',
        'notes' => ($ticketRole === 'engineer') ? "Admitted for Day {$eventDay}" : "Admitted for Ruh-e-Raqs",
        'device_ts' => $deviceTs,
        'synced_ts' => $syncedTs
    ]);

    $attendee['attendance_status'] = 'present';
    $attendee['entry_time'] = $now;

    return [
        'success' => true,
        'status' => 'APPROVED',
        'ticket_id' => $ticketId,
        'event' => $attendee['event_name'] ?? 'SENTEC Admission',
        'attendee' => $attendee,
        'message' => 'Attendance Verified & Entry Granted!'
    ];
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
