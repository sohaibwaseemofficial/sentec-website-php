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
require_once __DIR__ . '/../event_attendees_helper.php';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $status = strtolower(trim($_POST['status'] ?? ''));
    $rawIds = $_POST['ids'] ?? [];

    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new Exception('Invalid status specified.');
    }

    if (!is_array($rawIds) || empty($rawIds)) {
        throw new Exception('No teams selected.');
    }

    // Filter valid positive integer IDs
    $validIds = [];
    foreach ($rawIds as $val) {
        $id = intval($val);
        if ($id > 0) {
            $validIds[] = $id;
        }
    }
    $validIds = array_values(array_unique($validIds));

    if (empty($validIds)) {
        throw new Exception('No valid team IDs selected.');
    }

    $successCount = 0;
    $emailCount = 0;

    $updateStmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE id = ?");
    $fetchStmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");

    foreach ($validIds as $id) {
        $updateStmt->bind_param("si", $status, $id);
        if ($updateStmt->execute()) {
            $successCount++;

            // Fetch registration info
            $fetchStmt->bind_param("i", $id);
            $fetchStmt->execute();
            $data = $fetchStmt->get_result()->fetch_assoc();

            if ($data) {
                // Sync event_attendees
                $data['status'] = $status;
                if (function_exists('event_attendees_from_registration_row') && function_exists('event_attendees_sync')) {
                    $participants = event_attendees_from_registration_row($data);
                    event_attendees_sync($conn, $id, $participants, $status);
                }

                $teamName = htmlspecialchars($data['team_name'] ?? 'Team');

                // Collect Recipients
                $recipients = [];
                for ($i = 1; $i <= 6; $i++) {
                    $email = trim($data["participant{$i}_email"] ?? '');
                    $name = trim($data["participant{$i}_name"] ?? '');
                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = [
                            'email' => $email,
                            'name' => $name ?: "Participant {$i}"
                        ];
                    }
                }

                // Send Emails safely
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
                            $emailCount++;
                        }
                    } catch (Throwable $e) {
                        // Suppress individual email errors in bulk loop
                    }
                }
            }
        }
    }

    if ($updateStmt) $updateStmt->close();
    if ($fetchStmt) $fetchStmt->close();

    log_admin_action('BULK_UPDATE_REGISTRATION_STATUS', "Set status to $status for " . count($validIds) . " teams: [" . implode(',', $validIds) . "]", $conn);

    $response['success'] = true;
    $response['message'] = "Successfully updated {$successCount} team(s) to " . strtoupper($status) . "!" . ($emailCount > 0 ? " Sent {$emailCount} notification email(s)." : "");

} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
