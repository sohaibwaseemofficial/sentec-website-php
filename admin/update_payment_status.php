<?php
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); 
    exit; 
}

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/admin_logger.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../mailer.php';

$response = ['success' => false, 'message' => 'Invalid ID'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Invalid registration ID.');
    }

    // 1. UPDATE PAYMENT STATUS
    $stmt = $conn->prepare("UPDATE event_registrations SET payment_status = 'confirmed' WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 2. ALSO UPDATE EVENT ATTENDEES (if table exists)
    $attStmt = $conn->prepare("UPDATE event_attendees SET status = 'approved' WHERE registration_id = ? AND status = 'pending'");
    if ($attStmt) {
        $attStmt->bind_param("i", $id);
        $attStmt->execute();
        $attStmt->close();
    }

    // 3. FETCH TEAM INFO FOR EMAIL
    $fetchStmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
    $fetchStmt->bind_param("i", $id);
    $fetchStmt->execute();
    $data = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if ($data) {
        try {
            $mail = sentec_mailer();
            for ($i = 1; $i <= 6; $i++) {
                $pName = trim($data["participant{$i}_name"] ?? '');
                $pEmail = trim($data["participant{$i}_email"] ?? '');
                if (!empty($pEmail) && filter_var($pEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($pEmail, $pName ?: "Participant {$i}");
                }
            }

            $leaderName = htmlspecialchars($data['participant1_name'] ?? 'Participant');
            $teamName = htmlspecialchars($data['team_name'] ?? 'Team');

            $mail->Subject = "Payment Confirmed - SENTEC";
            $bodyHtml = "<p>Thank you for submitting the participation fee for team <strong>{$teamName}</strong>. We are pleased to confirm that the payment has been verified by the SENTEC finance committee and your registration is now fully secured.</p>
                <p>Please keep an eye on the dashboard for further announcements, reporting times, and competition guidelines.</p>";

            $greeting = 'Dear ' . $leaderName . ' & Team,';
            $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/dashboard';

            $mail->Body = sentec_build_email_html(
                'Payment Status',
                'Payment Confirmed',
                $greeting,
                $bodyHtml,
                $dashboardUrl,
                'Access Dashboard',
                null
            );
            $mail->AltBody = "Dear {$leaderName},\n\nYour payment for team {$teamName} has been confirmed by SENTEC. Your registration is now fully secured.";
            $mail->send();
            sentec_mail_log('payment_confirmation', 'sent', "id=$id");
        } catch (Throwable $e) {
            sentec_mail_log('payment_confirmation', 'error', $e->getMessage());
        }

        log_admin_action('CONFIRM_PAYMENT', "Confirmed payment for registration ID $id (Team: {$data['team_name']})", $conn);
    }

    $response['success'] = true;
    $response['message'] = 'Payment Confirmed & Email Sent!';

} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;