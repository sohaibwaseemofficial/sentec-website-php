<?php
/**
 * Utility helpers for the social_attendees table so we can treat every guest as an individual
 * while still grouping by their original social_registrations record.
 */

if (!function_exists('social_attendees_table_exists')) {
    function social_attendees_table_exists(mysqli $conn): bool {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $result = $conn->query("SHOW TABLES LIKE 'social_attendees'");
        $cache = ($result && $result->num_rows > 0);
        return $cache;
    }

    function social_attendees_sync(mysqli $conn, int $registrationId, array $people, string $paymentProof, string $paymentStatus = 'submitted'): void {
        if (!social_attendees_table_exists($conn)) {
            return;
        }

        $groupCode = 'grp-' . $registrationId;
        $del = $conn->prepare("DELETE FROM social_attendees WHERE registration_id = ?");
        $del->bind_param('i', $registrationId);
        $del->execute();
        $del->close();

        $sql = "INSERT INTO social_attendees (
                    registration_id, group_code, person_index, label, full_name, email, phone,
                    cnic, face_image, id_card_image, payment_proof, payment_status, status, attendance_status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'pending','pending')";
        $stmt = $conn->prepare($sql);
        foreach ($people as $index => $person) {
            $personIndex = $index + 1;
            $label = $person['label'] ?? ('Person ' . $personIndex);
            $name = $person['name'] ?? '';
            if (empty(trim($name))) {
                continue;
            }
            $email = $person['email'] ?? null;
            $phone = $person['phone'] ?? null;
            $cnic = $person['cnic'] ?? null;
            $face = $person['face'] ?? null;
            $card = $person['card'] ?? null;
            $stmt->bind_param(
                'isisssssssss',
                $registrationId,
                $groupCode,
                $personIndex,
                $label,
                $name,
                $email,
                $phone,
                $cnic,
                $face,
                $card,
                $paymentProof,
                $paymentStatus
            );
            $stmt->execute();
        }
        $stmt->close();
        social_refresh_parent_status($conn, $registrationId);
    }

    function social_attendees_fetch_group(mysqli $conn, int $registrationId): array {
        if (!social_attendees_table_exists($conn)) {
            return [];
        }
        $stmt = $conn->prepare("SELECT * FROM social_attendees WHERE registration_id = ? ORDER BY person_index ASC");
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

    function social_attendee_fetch_with_registration(mysqli $conn, int $attendeeId): ?array {
        if (!social_attendees_table_exists($conn)) {
            return null;
        }
        $sql = "SELECT sa.*, sr.payment_status AS group_payment_status, sr.status AS group_status,
                       sr.id AS registration_id, sr.payment_proof AS group_payment_proof
                FROM social_attendees sa
                JOIN social_registrations sr ON sr.id = sa.registration_id
                WHERE sa.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $attendeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    function social_refresh_parent_status(mysqli $conn, int $registrationId): void {
        if (!social_attendees_table_exists($conn)) {
            return;
        }
        $stmt = $conn->prepare("SELECT status, attendance_status FROM social_attendees WHERE registration_id = ?");
        $stmt->bind_param('i', $registrationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $statuses = [];
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $statuses[] = $row['status'];
            $attendance[] = $row['attendance_status'];
        }
        $stmt->close();
        if (empty($statuses)) {
            return;
        }
        $allApproved = count(array_unique($statuses)) === 1 && $statuses[0] === 'approved';
        $allRejected = count(array_unique($statuses)) === 1 && $statuses[0] === 'rejected';
        $newStatus = 'pending';
        if ($allApproved) {
            $newStatus = 'approved';
        } elseif ($allRejected) {
            $newStatus = 'rejected';
        }
        $allPresent = count(array_unique($attendance)) === 1 && $attendance[0] === 'present';
        $attendanceStatus = $allPresent ? 'present' : 'pending';
        $stmt = $conn->prepare("UPDATE social_registrations SET status = ?, attendance_status = ? WHERE id = ?");
        $stmt->bind_param('ssi', $newStatus, $attendanceStatus, $registrationId);
        $stmt->execute();
        $stmt->close();
    }

    function social_mark_attendance(mysqli $conn, int $attendeeId): bool {
        if (!social_attendees_table_exists($conn)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE social_attendees SET attendance_status = 'present', entry_time = ? WHERE id = ?");
        $stmt->bind_param('si', $now, $attendeeId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $stmt = $conn->prepare("SELECT registration_id FROM social_attendees WHERE id = ?");
            $stmt->bind_param('i', $attendeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if (!empty($row['registration_id'])) {
                social_refresh_parent_status($conn, (int) $row['registration_id']);
            }
        }
        return $ok;
    }
}
