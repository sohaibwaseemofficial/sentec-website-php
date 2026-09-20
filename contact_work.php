<?php
include 'header.php';
include 'db_connection.php';

// Load centralized mailer
require 'vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

// Function to display messages
// Function to display messages matching dark industrial design
function displayMessage($message, $type = 'danger') {
    $isSuccess = $type === 'success';
    $icon = $isSuccess ? 'fa-check' : 'fa-exclamation-triangle';
    $color = $isSuccess ? '#f15a24' : '#ff4444';
    $title = $isSuccess ? 'Transmission Received' : 'Transmission Alert';
    $tag = $isSuccess ? 'STATUS // CONFIRMED' : 'STATUS // ERROR';
    echo '
    <div class="secondary-page">
        <main>
            <section style="padding: 120px 20px 80px; max-width: 640px; margin: 0 auto;">
                <div style="background: #101518; border: 1px solid rgba(235,241,237,0.14); border-top: 2px solid ' . $color . '; padding: 40px 32px; text-align: center; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: rgba(241,90,36,0.12); color: ' . $color . '; margin-bottom: 20px;">
                        <i class="fas ' . $icon . '" style="font-size: 22px;"></i>
                    </div>
                    <div style="font: 600 10px \"IBM Plex Mono\", monospace; letter-spacing: 0.14em; color: #7b9096; margin-bottom: 8px;">' . $tag . '</div>
                    <h2 style="font-family: \"Space Grotesk\", sans-serif; font-size: 28px; color: #f4f1eb; margin: 0 0 16px;">' . $title . '</h2>
                    <p style="color: #9aa3a3; font-size: 14px; line-height: 1.6; margin: 0 0 28px;">' . htmlspecialchars($message) . '</p>
                    <a href="contact.php" class="signal-btn" style="display: inline-flex; text-decoration: none;">
                        <span>RETURN TO CONTACT // OPEN CHANNEL</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                    </a>
                </div>
            </section>
        </main>
    </div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. GET & SANITIZE DATA
    $fullname = trim($_POST['fullname'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));
    
    if (empty($fullname) || empty($email) || empty($message)) {
        displayMessage('Please fill in all required fields.');
        include 'footer.php';
        exit;
    }

    // 2. PERSIST IN DATABASE FIRST
    $savedToDb = false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($conn) && !$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sssss", $fullname, $email, $phone, $message, $ip);
            if ($stmt->execute()) {
                $savedToDb = true;
            }
            $stmt->close();
        }
    }

    // 3. SEND EMAIL NOTIFICATION VIA CENTRALIZED MAILER
    try {
        $mail = sentec_mailer();
        
        // Deliver to both neduetsentec@gmail.com and admin email
        $mail->addAddress('neduetsentec@gmail.com', 'SENTEC Secretariat');
        $adminEmail = env('ADMIN_EMAIL');
        if ($adminEmail && strtolower($adminEmail) !== 'neduetsentec@gmail.com') {
            $mail->addAddress($adminEmail, 'SENTEC Administration');
        }
        $mail->addReplyTo($email, $fullname);
        
        $mail->Subject = "New Signal / Contact Message from $fullname";
        $safeMessage = nl2br($message);
        
        $mail->Body = "
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin:0; background-color:#080b0d; padding:40px 0; font-family:\"Space Grotesk\", \"Segoe UI\", Arial, sans-serif;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='640' style='width:100%; max-width:640px; background:#101518; border-radius:8px; border:1px solid rgba(235,241,237,0.14); border-top: 3px solid #f15a24;'>
                            <tr>
                                <td style='padding:32px 36px 20px 36px; text-align:left;'>
                                    <div style='font-size:24px; font-weight:800; letter-spacing:2px; color:#ffffff;'>SENTEC<span style='color:#f15a24;'>.</span></div>
                                    <p style='margin:8px 0 0 0; color:#7b9096; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; font-family:monospace;'>COMMUNICATION // DISPATCH FORM</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:0 36px 32px 36px;'>
                                    <div style='background:#151c20; border-radius:6px; border:1px solid rgba(255,255,255,0.06); padding:24px 24px 28px 24px; color:#f4f1eb;'>
                                        <p style='margin:0 0 16px 0; font-size:12px; color:#f15a24; text-transform:uppercase; letter-spacing:1.2px; font-family:monospace;'>Message Details</p>
                                        <p style='margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#f4f1eb;'><strong style='color:#7b9096;'>Name:</strong> $fullname</p>
                                        <p style='margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#f4f1eb;'><strong style='color:#7b9096;'>Email:</strong> $email</p>
                                        <p style='margin:0 0 16px 0; font-size:14px; line-height:1.6; color:#f4f1eb;'><strong style='color:#7b9096;'>Phone:</strong> $phone</p>
                                        <p style='margin:0 0 8px 0; font-size:14px; line-height:1.6; color:#7b9096;'><strong>Message:</strong></p>
                                        <div style='background:rgba(0,0,0,0.3); border-left:2px solid #f15a24; padding:14px 18px; font-size:14px; line-height:1.7; color:#f4f1eb;'>$safeMessage</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:0 36px 32px 36px; text-align:left; color:#5d696c; font-size:11px; line-height:1.6; font-family:monospace; border-top:1px solid rgba(255,255,255,0.06); padding-top:20px;'>
                                    SENTEC &mdash; Society for Promotion of Science, Engineering &amp; Technology<br/>
                                    NED University of Engineering &amp; Technology, Karachi
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>";
        $mail->AltBody = "Name: $fullname\nEmail: $email\nPhone: $phone\n\nMessage:\n$message";
        
        $mail->send();
        sentec_mail_log('contact_form', 'sent', "from=$email to=neduetsentec@gmail.com,$adminEmail");
        
        displayMessage('Thank you! Your message has been sent successfully. The SENTEC secretariat will review and respond shortly.', 'success');

    } catch (Exception $e) {
        sentec_mail_log('contact_form', 'error', $e->getMessage());
        if ($savedToDb) {
            displayMessage('Thank you! Your message has been recorded directly into our administrative portal. Our team will get back to you shortly.', 'success');
        } else {
            displayMessage('Sorry, we could not record your message due to a technical error. Please email us directly at info@sentecneduet.live.', 'danger');
        }
    }

} else {
    header('Location: contact.php');
    exit;
}

include 'footer.php';
?>