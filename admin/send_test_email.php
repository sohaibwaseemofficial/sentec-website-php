<?php
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../recaptcha_config.php';
require_once __DIR__ . '/../mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$to = isset($_POST['to']) ? trim($_POST['to']) : '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php');
    exit;
}

try {
    $mail = sentec_mailer();
    $mail->addAddress($to);
    $mail->Subject = 'SENTEC Test Email';
    $mail->Body = '<div style="font-family:Arial,sans-serif;padding:16px;background:#0b132b;color:#e0e3ea;">
        <h2 style="margin:0 0 12px;color:#00e7ff;">Test Email</h2>
        <p>This is a test email from the SENTEC admin dashboard.</p>
        <p>If you received this, SMTP is working.</p>
    </div>';
    $mail->AltBody = 'This is a test email from the SENTEC admin dashboard.';
    $mail->send();
    sentec_mail_log('admin_test_email', 'sent', 'to=' . $to);
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    sentec_mail_log('admin_test_email', 'error', $e->getMessage());
    header('Location: index.php');
    exit;
}
