<?php
/**
 * Helpers for event attendee tracking (multi-day onsite check-in for team registrations).
 */

if (!function_exists('event_attendees_table_exists')) {
    function event_attendees_table_exists(mysqli $conn): bool {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $result = $conn->query("SHOW TABLES LIKE 'event_attendees'");
        $cache = ($result && $result->num_rows > 0);
        return $cache;
    }

    function event_registration_attendance_columns_exist(mysqli $conn): bool {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $result = $conn->query("SHOW COLUMNS FROM event_registrations LIKE 'attendance_day1_status'");
        $cache = ($result && $result->num_rows > 0);
        return $cache;
    }

    function event_attendees_from_registration_row(array $row): array {
        $participants = [];
        for ($i = 1; $i <= 4; $i++) {
            $nameKey = "participant{$i}_name";
            $emailKey = "participant{$i}_email";
            $phoneKey = "participant{$i}_contact";
            $cnicKey = "participant{$i}_cnic";
            $rollKey = "participant{$i}_roll_number";
            $faceKey = "participant{$i}_face_image";
            $cardKey = "participant{$i}_id_card";

            $name = trim($row[$nameKey] ?? '');
            $email = trim($row[$emailKey] ?? '');
            $phone = trim($row[$phoneKey] ?? '');
            $cnic = trim($row[$cnicKey] ?? '');
            $roll = trim($row[$rollKey] ?? '');
            $face = $row[$faceKey] ?? null;
            $card = $row[$cardKey] ?? null;

            if ($name === '' && $email === '' && $phone === '') {
                continue;
            }

            $participants[] = [
                'label' => ($i === 1) ? 'Leader' : 'Member ' . $i,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'cnic' => $cnic,
                'roll' => $roll,
                'face' => $face,
                'card' => $card,
                'person_index' => $i
            ];
        }
        return $participants;
    }

    function event_attendees_sync(mysqli $conn, int $registrationId, array $participants, string $registrationStatus = 'pending'): void {
        if (!event_attendees_table_exists($conn)) {
            return;
        }

        // Strictly enforce allowed ENUM('pending','approved','rejected') to prevent MySQL strict truncation errors
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        $cleanStatus = in_array(strtolower(trim($registrationStatus)), $allowedStatuses, true) 
            ? strtolower(trim($registrationStatus)) 
            : 'pending';

        $del = $conn->prepare('DELETE FROM event_attendees WHERE registration_id = ?');
        $del->bind_param('i', $registrationId);
        $del->execute();
        $del->close();

        $sql = 'INSERT INTO event_attendees (
                    registration_id, group_code, person_index, label, full_name, email, phone, cnic, roll_number,
                    face_image, id_card_image, status, day1_status, day2_status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = $conn->prepare($sql);
        $groupCode = 'evt-' . $registrationId;
        $defaultAttendance = 'pending';
        $types = 'i' . 's' . 'i' . str_repeat('s', 11);

        foreach ($participants as $idx => $person) {
            $personIndex = isset($person['person_index']) ? (int) $person['person_index'] : ($idx + 1);
            $label = $person['label'] ?? ('Member ' . $personIndex);
            $name = $person['name'] ?? '';
            if (trim((string) $name) === '') {
                continue;
            }
            $email = $person['email'] ?? null;
            $phone = $person['phone'] ?? null;
            $cnic = $person['cnic'] ?? null;
            $roll = $person['roll'] ?? null;
            $face = $person['face'] ?? null;
            $card = $person['card'] ?? null;
            $stmt->bind_param(
                $types,
                $registrationId,
                $groupCode,
                $personIndex,
                $label,
                $name,
                $email,
                $phone,
                $cnic,
                $roll,
                $face,
                $card,
                $cleanStatus,
                $defaultAttendance,
                $defaultAttendance
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    function event_attendees_fetch_group(mysqli $conn, int $registrationId): array {
        if (!event_attendees_table_exists($conn)) {
            return [];
        }
        $stmt = $conn->prepare('SELECT * FROM event_attendees WHERE registration_id = ? ORDER BY person_index ASC');
        $stmt->bind_param('i', $registrationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    function event_attendee_fetch_with_registration(mysqli $conn, int $attendeeId): ?array {
        if (!event_attendees_table_exists($conn)) {
            return null;
        }
        $sql = 'SELECT ea.*, er.team_name, er.module_selection, er.status AS registration_status
                FROM event_attendees ea
                JOIN event_registrations er ON er.id = ea.registration_id
                WHERE ea.id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $attendeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    function event_refresh_parent_attendance(mysqli $conn, int $registrationId): void {
        if (!event_attendees_table_exists($conn) || !event_registration_attendance_columns_exist($conn)) {
            return;
        }
        $stmt = $conn->prepare('SELECT day1_status, day2_status FROM event_attendees WHERE registration_id = ?');
        $stmt->bind_param('i', $registrationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $day1 = [];
        $day2 = [];
        while ($row = $res->fetch_assoc()) {
            $day1[] = $row['day1_status'];
            $day2[] = $row['day2_status'];
        }
        $stmt->close();
        if (empty($day1) && empty($day2)) {
            return;
        }
        $d1 = (count(array_unique($day1)) === 1 && $day1[0] === 'present') ? 'present' : 'pending';
        $d2 = (count(array_unique($day2)) === 1 && $day2[0] === 'present') ? 'present' : 'pending';
        $now = date('Y-m-d H:i:s');
        $update = $conn->prepare('UPDATE event_registrations SET attendance_day1_status = ?, attendance_day2_status = ?, attendance_last_seen = ? WHERE id = ?');
        $update->bind_param('sssi', $d1, $d2, $now, $registrationId);
        $update->execute();
        $update->close();
    }

    function event_mark_attendance(mysqli $conn, int $attendeeId, int $day): bool {
        if (!event_attendees_table_exists($conn)) {
            return false;
        }
        if (!in_array($day, [1, 2], true)) {
            return false;
        }
        $statusCol = $day === 1 ? 'day1_status' : 'day2_status';
        $timeCol = $day === 1 ? 'day1_entry_time' : 'day2_entry_time';
        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE event_attendees SET {$statusCol} = 'present', {$timeCol} = COALESCE({$timeCol}, ?) WHERE id = ? AND {$statusCol} = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $now, $attendeeId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            // Already marked; treat as success for idempotency.
            return true;
        }

        $regIdStmt = $conn->prepare('SELECT registration_id FROM event_attendees WHERE id = ?');
        $regIdStmt->bind_param('i', $attendeeId);
        $regIdStmt->execute();
        $res = $regIdStmt->get_result();
        $regRow = $res ? $res->fetch_assoc() : null;
        $regIdStmt->close();

        if (!empty($regRow['registration_id'])) {
            event_refresh_parent_attendance($conn, (int) $regRow['registration_id']);
        }

        return true;
    }
}
?>
