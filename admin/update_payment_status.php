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
    // SECURED: Use prepared statement
    $stmt = $conn->prepare("UPDATE event_registrations SET payment_status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // SECURED: Get Email Info with prepared statement
    $stmt = $conn->prepare("SELECT team_name, participant1_name, participant1_email, participant2_name, participant2_email, participant3_name, participant3_email, participant4_name, participant4_email, participant5_name, participant5_email, participant6_name, participant6_email FROM event_registrations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    
    // Send Email using centralized mailer
    try {
        $mail = sentec_mailer();
        for ($i = 1; $i <= 6; $i++) {
            $pName = "participant{$i}_name";
            $pEmail = "participant{$i}_email";
            
            if (!empty($data[$pEmail])) {
                $mail->addAddress($data[$pEmail], $data[$pName]);
            }
        }
        $mail->Subject = "Payment Confirmed - SENTEC";

        $leaderName = htmlspecialchars($data['participant1_name']);
        $teamName = htmlspecialchars($data['team_name']);
        
        $bodyHtml = "<p>Thank you for submitting the participation fee for team <strong>{$teamName}</strong>. We are pleased to confirm that the payment has been verified by the SENTEC finance committee and your registration is now fully secured.</p>
            <p>Please keep an eye on the dashboard for further announcements, reporting times, and competition guidelines.</p>";
        
        $greeting = 'Dear ' . $leaderName . ' & Team,';
        // FIXED: Correct URL - changed from sentenceduet.live to sentecneduet.live
        $dashboardUrl = env('APP_URL', 'https://sentecneduet.live') . '/dashboard.php';
        
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
    } catch (Exception $e) {
        sentec_mail_log('payment_confirmation', 'error', $e->getMessage());
    }
    
    echo json_encode(['success'=>true, 'message'=>'Payment Confirmed & Email Sent!']);
} else {
    echo json_encode(['success'=>false, 'message'=>'Invalid ID']);
}
?>