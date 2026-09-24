<?php
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['sent' => 0, 'errors' => ['Unauthorized access.']]);
    exit;
}

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../event_attendees_helper.php';

if (!event_attendees_table_exists($conn)) {
    ob_end_clean();
    echo json_encode(['sent' => 0, 'errors' => ['event_attendees table is missing. Run the migration.']]);
    exit;
}

// Auto-backfill any approved registrations that do not have attendee rows yet
$unsynced = $conn->query(
    "SELECT er.* FROM event_registrations er
     LEFT JOIN event_attendees ea ON ea.registration_id = er.id
     WHERE er.status = 'approved' AND ea.id IS NULL"
);
if ($unsynced && $unsynced->num_rows > 0 && function_exists('event_attendees_from_registration_row') && function_exists('event_attendees_sync')) {
    while ($row = $unsynced->fetch_assoc()) {
        $participants = event_attendees_from_registration_row($row);
        event_attendees_sync($conn, (int)$row['id'], $participants, 'approved');
    }
}

$appUrl = env('APP_URL', 'https://sentecneduet.live');
$sent = 0;
$errors = [];

$sql = "SELECT ea.id, ea.full_name, ea.email, ea.label, ea.person_index, ea.day1_status, ea.day2_status,
               er.team_name, er.module_selection, er.status AS registration_status
        FROM event_attendees ea
        JOIN event_registrations er ON er.id = ea.registration_id
        WHERE er.status = 'approved'";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    ob_end_clean();
    echo json_encode(['sent' => 0, 'errors' => ['No approved registrations found.'], 'message' => 'No approved registrations found.']);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $email = trim($row['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $attendeeId = (int)$row['id'];
    $name = $row['full_name'] ?: 'Participant';
    $teamName = $row['team_name'] ?? '';
    $module = $row['module_selection'] ?? '';
    $role = $row['label'] ?? ('Member ' . ($row['person_index'] ?? ''));

    $qrLink = rtrim($appUrl, '/') . '/gate_event/verify_event.php?attendee=' . $attendeeId;
    $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&color=000000&bgcolor=00ff94&data=' . urlencode($qrLink);

    $bodyHtml = "<p>You are cleared for gate entry. Show this QR at the entrance for Day 1 and Day 2 check-in.</p>"
        . "<div style='text-align:center; margin:22px 0;'><img src='{$qrImg}' alt='Gate QR' style='width:220px;height:220px;border:2px solid #00ff94;border-radius:12px;background:#000;' loading='lazy'></div>"
        . "<p style='margin:0 0 10px 0;'><strong>Team:</strong> " . htmlspecialchars($teamName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>"
        . "<strong>Module:</strong> " . htmlspecialchars($module, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>"
        . "<strong>Role:</strong> " . htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>"
        . "<strong>Venue:</strong> Syed Mahmood Alam Auditorium (Main Auditorium), NED University</p>"
        . "<ul style='color:#d1d5db;line-height:1.6;padding-left:18px;'>"
        . "<li>Carry your student ID/CNIC. Name must match the registration.</li>"
        . "<li>Each member has a unique QR; do not share screenshots.</li>"
        . "<li>If the QR does not scan, provide your CNIC at the gate.</li>"
        . "</ul>";

    try {
        $mail = sentec_mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = "Your Engineer's Code 2025 Gate Pass";
        $mail->Body = sentec_build_email_html(
            'Gate Access',
            "Your Engineer's Code 2025 E-Pass",
            'Hello ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',',
            $bodyHtml,
            $qrLink,
            'Open Pass',
            null
        );
        $mail->AltBody = "Hello {$name},\n\nShow this QR at the gate: {$qrLink}\nTeam: {$teamName}\nModule: {$module}\nVenue: Syed Mahmood Alam Auditorium (Main Auditorium), NED University";
        $mail->send();
        $sent++;
        sentec_mail_log('send_event_gatepass_all', 'sent', 'attendee_id=' . $attendeeId . ' email=' . $email);
    } catch (Throwable $e) {
        $errors[] = 'Failed for ' . $name . ' (' . $email . ')';
        sentec_mail_log('send_event_gatepass_all', 'error', $e->getMessage());
    }
}

$message = $sent . ' gate pass email(s) sent.';
if (!empty($errors)) {
    $message .= ' Errors: ' . implode('; ', $errors);
}

ob_end_clean();
echo json_encode(['sent' => $sent, 'errors' => $errors, 'message' => $message]);
exit;
