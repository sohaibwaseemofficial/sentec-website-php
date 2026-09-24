<?php
// Prevent accidental output buffer leaks / PHP warnings breaking JSON
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

// 1. SECURITY
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); 
    exit; 
}

// 2. CONFIG & DEPENDENCIES
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/admin_logger.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../event_attendees_helper.php';

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $id = intval($_POST['id'] ?? 0);
    $status = strtolower(trim($_POST['status'] ?? ''));

    if ($id <= 0) {
        throw new Exception('Invalid registration ID.');
    }

    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new Exception('Invalid status value provided.');
    }

    // 3. FETCH FULL REGISTRATION DATA
    $fetchStmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
    if (!$fetchStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $fetchStmt->bind_param("i", $id);
    $fetchStmt->execute();
    $data = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if (!$data) {
        throw new Exception("Registration not found.");
    }

    // 4. UPDATE STATUS SECURELY
    $updateStmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Database update prepare error: " . $conn->error);
    }
    $updateStmt->bind_param("si", $status, $id);

    if (!$updateStmt->execute()) {
        $updateErr = $updateStmt->error;
        $updateStmt->close();
        throw new Exception("Database update failed: " . $updateErr);
    }
    $updateStmt->close();

    // 5. SYNCHRONIZE WITH event_attendees
    $data['status'] = $status;
    if (function_exists('event_attendees_from_registration_row') && function_exists('event_attendees_sync')) {
        $participants = event_attendees_from_registration_row($data);
        event_attendees_sync($conn, $id, $participants, $status);
    } else {
        $attStmt = $conn->prepare("UPDATE event_attendees SET status = ? WHERE registration_id = ?");
        if ($attStmt) {
            $attStmt->bind_param("si", $status, $id);
            $attStmt->execute();
            $attStmt->close();
        }
    }

    // 6. PREPARE EMAIL LIST
    $recipients = [];
    for ($i = 1; $i <= 6; $i++) {
        $email = trim($data["participant{$i}_email"] ?? '');
        $name = trim($data["participant{$i}_name"] ?? '');
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = [
                'name' => $name ?: "Participant {$i}",
                'email' => $email
            ];
        }
    }

    $teamName = htmlspecialchars($data['team_name'] ?? 'Team');
    $emailsSent = 0;

    // Send notifications safely; mail failures never block database update
    foreach ($recipients as $r) {
        try {
            $mail = sentec_mailer();
            $mail->addAddress($r['email'], $r['name']);

            if ($status === 'approved') {
                $subject = "[APPROVED] Application Status - SENTEC";
                $heading = 'Congratulations!';
                $bodyHtml = "<p>We are pleased to inform you that your registration for team <strong>" . $teamName . "</strong> has been officially <strong>APPROVED</strong> by the SENTEC administrative committee.</p>" .
                    "<p>You are now confirmed to participate in the upcoming event at NED University. Please keep an eye on your dashboard for schedule updates and further instructions.</p>";
            } else {
                $subject = "[UPDATE] Application Status - SENTEC";
                $heading = 'Application Update';
                $bodyHtml = "<p>We appreciate your interest in SENTEC. After careful review, we regret to inform you that the registration for team <strong>" . $teamName . "</strong> could not be approved this time.</p>" .
                    "<p>You are always welcome to participate in our future events and activities.</p>";
            }

            $greeting = 'Dear ' . htmlspecialchars($r['name'] ?: 'Participant', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',';
            $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/dashboard';

            $mail->Subject = $subject;
            $mail->Body = sentec_build_email_html(
                'Application Status',
                $heading,
                $greeting,
                $bodyHtml,
                $dashboardUrl,
                'Access Dashboard',
                null
            );
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $bodyHtml));
            if ($mail->send()) {
                $emailsSent++;
            }
        } catch (Throwable $mailEx) {
            // Log but continue; do not abort database update
            error_log("Status email failed for registration ID $id (" . $r['email'] . "): " . $mailEx->getMessage());
        }
    }

    // 7. AUDIT LOGGING
    log_admin_action('UPDATE_REGISTRATION_STATUS', "Set status to $status for registration ID $id (Team: $teamName)", $conn);

    $response['success'] = true;
    $response['message'] = "Team " . strtoupper($status) . " successfully!" . ($emailsSent > 0 ? " Notification sent to {$emailsSent} member(s)." : "");

} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
