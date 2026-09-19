<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); 
    exit; 
}

include '../db_connection.php';
require_once __DIR__ . '/../mailer.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if ($id) {
    // Update payment status to rejected
    $stmt = $conn->prepare("UPDATE event_registrations SET payment_status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // Get team info for email
    $stmt = $conn->prepare("SELECT team_name, participant1_name, participant1_email, participant2_name, participant2_email, participant3_name, participant3_email, participant4_name, participant4_email, participant5_name, participant5_email, participant6_name, participant6_email FROM event_registrations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    
    if ($data) {
        try {
            $mail = sentec_mailer();
            for ($i = 1; $i <= 6; $i++) {
                $pName = "participant{$i}_name";
                $pEmail = "participant{$i}_email";
                
                if (!empty($data[$pEmail])) {
                    $mail->addAddress($data[$pEmail], $data[$pName]);
                }
            }
            $mail->Subject = "Payment Verification Update - SENTEC";

            $leaderName = htmlspecialchars($data['participant1_name']);
            $teamName = htmlspecialchars($data['team_name']);
            
            $bodyHtml = "<p>We have reviewed the payment proof submitted for team <strong>{$teamName}</strong>.</p>
                <p>Unfortunately, we could not verify the payment. This could be due to:</p>
                <ul style='color: #ccc;'>
                    <li>Unclear screenshot or transaction details</li>
                    <li>Incorrect amount transferred</li>
                    <li>Transaction not reflecting in our account</li>
                </ul>
                <p>Please upload a clearer payment proof from your dashboard.</p>";
            
            $greeting = 'Dear ' . $leaderName . ' & Team,';
            $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/payment_upload.php?id=' . $id;
            
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
        } catch (Exception $e) {
            // Silent fail - just log
        }
    }
    
    echo json_encode(['success'=>true, 'message'=>'Payment rejected. Team notified to upload again.']);
} else {
    echo json_encode(['success'=>false, 'message'=>'Invalid ID']);
}
?>