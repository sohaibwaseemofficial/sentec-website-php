<?php
include 'header.php';
include 'db_connection.php';

// Load centralized mailer
require 'vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

// Function to display messages
function displayMessage($message, $type = 'danger') {
    $icon = $type === 'success' ? 'check-circle' : 'exclamation-triangle';
    $color = $type === 'success' ? '#00FF94' : '#ff4444';
    echo '<section style="padding-top: 140px;">
            <div class="container mb-5">
            <div class="glass-panel" style="text-align:center; max-width:600px; margin:0 auto;">
                <div style="color: ' . $color . '; margin-bottom:20px;">
                    <i class="fas fa-' . $icon . '" style="font-size: 4rem;"></i>
                </div>
                <h3 style="color: #fff; margin-bottom:10px;">' . ($type === 'success' ? 'Message Sent!' : 'Error') . '</h3>
                <p style="color: #ccc; margin-bottom:30px;">' . $message . '</p>
                <a href="contact.php" class="btn-clear">Go Back</a>
            </div>
          </div>
        </section>';
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
        
        // Send to Admin
        $adminEmail = env('ADMIN_EMAIL', 'presidentsentec@gmail.com');
        $mail->addAddress($adminEmail);
        $mail->addReplyTo($email, $fullname);
        
        $mail->Subject = "New Contact Message from $fullname";
        $safeMessage = nl2br($message);
        
        $mail->Body = "
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin:0; background-color:#020205; padding:40px 0; font-family:\"Plus Jakarta Sans\", \"Segoe UI\", Arial, sans-serif;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='640' style='width:100%; max-width:640px; background:#070910; border-radius:20px; border:1px solid rgba(0,255,148,0.08); box-shadow:0 28px 65px rgba(0,0,0,0.6);'>
                            <tr>
                                <td style='padding:38px 40px 28px 40px; text-align:center;'>
                                    <div style='font-size:28px; font-weight:800; letter-spacing:4px; color:#ffffff; font-family:Outfit, Arial, sans-serif;'>SENTEC<span style='color:#00ff94;'>.</span></div>
                                    <p style='margin:14px 0 0 0; color:#7c899f; font-size:12px; letter-spacing:1.3px; text-transform:uppercase;'>Contact Form Submission</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:0 40px 38px 40px;'>
                                    <div style='background:#05060d; border-radius:16px; border:1px solid rgba(0,255,148,0.08); padding:28px 28px 32px 28px; color:#d9e6ff; box-shadow:0 18px 48px rgba(0,0,0,0.45);'>
                                        <p style='margin:0 0 18px 0; font-size:14px; color:#9cade3; text-transform:uppercase; letter-spacing:1.1px;'>Message Details</p>
                                        <p style='margin:0 0 12px 0; font-size:15px; line-height:1.7; color:#f4f8ff;'><strong style='color:#00ffad;'>Name:</strong> $fullname</p>
                                        <p style='margin:0 0 12px 0; font-size:15px; line-height:1.7; color:#f4f8ff;'><strong style='color:#00ffad;'>Email:</strong> $email</p>
                                        <p style='margin:0 0 18px 0; font-size:15px; line-height:1.7; color:#f4f8ff;'><strong style='color:#00ffad;'>Phone:</strong> $phone</p>
                                        <p style='margin:0 0 10px 0; font-size:15px; line-height:1.8; color:#d9e6ff;'><strong style='color:#00ffad;'>Message:</strong></p>
                                        <p style='margin:0; font-size:15px; line-height:1.8; color:#d9e6ff;'>$safeMessage</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:0 40px 40px 40px; text-align:center; color:#64718d; font-size:12px; line-height:1.6;'>
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
        sentec_mail_log('contact_form', 'sent', "from=$email");
        
        displayMessage('Thank you! Your message has been sent successfully. We will get back to you shortly.', 'success');

    } catch (Exception $e) {
        sentec_mail_log('contact_form', 'error', $e->getMessage());
        if ($savedToDb) {
            displayMessage('Thank you! Your message has been recorded into our system. We will get back to you shortly.', 'success');
        } else {
            displayMessage('Sorry, we could not send your message due to a technical error. Please try emailing us directly.', 'danger');
        }
    }

} else {
    header('Location: contact.php');
    exit;
}

include 'footer.php';
?>