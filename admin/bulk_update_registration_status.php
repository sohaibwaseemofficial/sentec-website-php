<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); 
    exit; 
}

header('Content-Type: application/json');
include '../db_connection.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../mailer.php';

$status = $_POST['status'] ?? '';
$ids = $_POST['ids'] ?? [];

if (!in_array($status, ['approved', 'rejected']) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$successCount = 0;
$emailCount = 0;
$errors = [];

foreach ($ids as $id) {
    $id = intval($id);
    
    // 1. Update Database
    $sql = "UPDATE event_registrations SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        $successCount++;
        
        // 2. Fetch Team Info for Email
        $q = "SELECT team_name, 
                     participant1_name, participant1_email,
                     participant2_name, participant2_email,
                     participant3_name, participant3_email,
                     participant4_name, participant4_email
              FROM event_registrations WHERE id = ?";
        $qStmt = $conn->prepare($q);
        $qStmt->bind_param("i", $id);
        $qStmt->execute();
        $data = $qStmt->get_result()->fetch_assoc();
        
        if ($data) {
            $teamName = htmlspecialchars($data['team_name']);

            // Collect Recipients
            $recipients = [];
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($data["participant{$i}_email"])) {
                    $recipients[] = [
                        'email' => $data["participant{$i}_email"],
                        'name' => $data["participant{$i}_name"]
                    ];
                }
            }

            // Send Emails
            foreach ($recipients as $r) {
                try {
                    $mail = sentec_mailer();
                    $mail->addAddress($r['email'], $r['name']);

                    if ($status === 'approved') {
                        $subject = "[APPROVED] Application Status - SENTEC 2025";
                        $heading = 'Congratulations!';
                        $bodyHtml = "<p>We are pleased to inform you that your registration for team <strong>" . $teamName . "</strong> has been officially <strong>APPROVED</strong> by the SENTEC administrative committee.</p>" .
                            "<p>You are now confirmed to participate in the upcoming event at NED University. Please keep an eye on your dashboard for schedule updates and further instructions.</p>";
                    } else {
                        $subject = "[UPDATE] Application Status - SENTEC 2025";
                        $heading = 'Application Update';
                        $bodyHtml = "<p>We appreciate your interest in SENTEC. After careful review, we regret to inform you that the registration for team <strong>" . $teamName . "</strong> could not be approved this time.</p>" .
                            "<p>You are always welcome to participate in our future events and activities.</p>";
                    }

                    $greeting = 'Dear ' . htmlspecialchars($r['name'] ?: 'Participant', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',';
                    $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/dashboard.php';

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

                    $mail->send();
                    $emailCount++;
                } catch (Exception $e) {
                    // Log error but continue loop
                    error_log("Mail fail for " . $r['email']);
                }
            }
        }
    } else {
        $errors[] = "Failed to update ID $id";
    }
}

echo json_encode([
    'success' => true,
    'message' => "Updated $successCount teams. Sent $emailCount emails."
]);
?>
