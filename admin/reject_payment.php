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
    $stmt = $conn->prepare("UPDATE event_registrations SET payment_status = 'rejected' WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 2. FETCH TEAM INFO FOR NOTIFICATION
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

            $mail->Subject = "Payment Verification Update - SENTEC";
            $bodyHtml = "<p>We have reviewed the payment proof submitted for team <strong>{$teamName}</strong>.</p>
                <p>Unfortunately, we could not verify the payment. This could be due to:</p>
                <ul style='color: #ccc;'>
                    <li>Unclear screenshot or transaction details</li>
                    <li>Incorrect amount transferred</li>
                    <li>Transaction not reflecting in our account</li>
                </ul>
                <p>Please upload a clearer payment proof from your dashboard.</p>";

            $greeting = 'Dear ' . $leaderName . ' & Team,';
            $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/payment_upload?id=' . $id;

            $mail->Body = sentec_build_email_html(
                'Payment Update',
                'Payment Verification Failed',
                $greeting,
                $bodyHtml,
                $dashboardUrl,
                'Upload New Proof',
                null
            );
            $mail->AltBody = "Dear {$leaderName},\n\nYour payment proof for team {$teamName} could not be verified. Please upload a clearer screenshot from your dashboard.";
            $mail->send();
            sentec_mail_log('payment_rejection', 'sent', "id=$id");
        } catch (Throwable $e) {
            sentec_mail_log('payment_rejection', 'error', $e->getMessage());
        }

        log_admin_action('REJECT_PAYMENT', "Rejected payment for registration ID $id (Team: {$data['team_name']})", $conn);
    }

    $response['success'] = true;
    $response['message'] = 'Payment rejected. Team notified to upload again.';

} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;