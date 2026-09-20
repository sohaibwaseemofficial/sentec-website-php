<?php
/**
 * Centralized PHPMailer Configuration
 * All email functionality should use this file.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env_loader.php';

/**
 * Get configured PHPMailer instance
 */
function sentec_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    
    // Load SMTP configuration from environment
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    
    // Support both SMTP_USERNAME / SMTP_USER and SMTP_PASSWORD / SMTP_PASS
    $user = env('SMTP_USERNAME') ?: (env('SMTP_USER') ?: 'neduetsentec@gmail.com');
    $pass = env('SMTP_PASSWORD') ?: (env('SMTP_PASS') ?: 'csmwddumnqgbczcn');
    // Strip spaces that often exist in copied Google App Passwords
    $pass = str_replace(' ', '', (string)$pass);
    
    $mail->Username   = $user;
    $mail->Password   = $pass;
    
    $port = (int)env('SMTP_PORT', 587);
    $mail->Port       = $port;
    
    // Set encryption
    $secure = strtolower((string)env('SMTP_SECURE', 'tls'));
    if ($port === 465 || $secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    // Prevent long hangs on SMTP connections (12s max)
    $mail->Timeout = 12;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    
    // Set default sender
    $fromEmail = env('FROM_EMAIL', 'neduetsentec@gmail.com');
    $fromName = env('FROM_NAME', 'SENTEC');
    $mail->setFrom($fromEmail, $fromName);
    
    return $mail;
}

/**
 * Log email activity
 */
function sentec_mail_log($context, $status, $details = ''): void {
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/mail.log';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] [$context] [$status] $details\n";
    @file_put_contents($file, $line, FILE_APPEND);
}

/**
 * Build standardized SENTEC HTML email template
 */
function sentec_build_email_html(
    string $title,
    string $heading,
    string $greetingLine,
    string $bodyHtml,
    ?string $buttonUrl = null,
    ?string $buttonText = null,
    ?string $footerNote = null
): string {
    $safeTitle   = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeFooter  = $footerNote !== null
        ? htmlspecialchars($footerNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : 'SENTEC - Society for Promotion of Science Engineering and Technology<br>NED University of Engineering &amp; Technology, Karachi.';

    $buttonHtml = '';
    if ($buttonUrl && $buttonText) {
        $safeUrl  = htmlspecialchars($buttonUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeText = htmlspecialchars($buttonText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $buttonHtml = "<div class=\"sentec-email-button\" style=\"margin-top:32px; text-align:center;\"><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:14px 40px;border-radius:999px;background:#00ff94;color:#000000;font-weight:600;font-size:14px;text-decoration:none;text-transform:uppercase;letter-spacing:0.16em;\">{$safeText}</a></div>";
    }

    $responsiveStyles = '<style>@media only screen and (max-width: 520px){.sentec-email-wrapper{padding:24px 12px !important;}.sentec-email-card{padding:28px 20px 24px 20px !important;border-radius:12px !important;}.sentec-email-heading{font-size:18px !important;}.sentec-email-button a{display:block !important;width:100% !important;text-align:center !important;}.sentec-email-table{width:100% !important;}.sentec-email-footer{padding:18px 8px 0 8px !important;font-size:10px !important;line-height:1.5 !important;}}</style>';

    return "<div class='sentec-email-wrapper' style='margin:0;padding:40px 16px;background:#000000;color:#f9fafb;font-family:system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;'>".
        $responsiveStyles.
        "<table class='sentec-email-table' role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' width='100%' style='max-width:720px;margin:0 auto;background:#000000;'>".
        "<tr><td align='center' style='padding-bottom:24px;'>".
        "<div style='font-size:24px;font-weight:800;letter-spacing:0.24em;color:#ffffff;'>SENTEC<span style='color:#00ff94;'>.</span></div>".
        "</td></tr>".
        "<tr><td class='sentec-email-card' style='background:#07090c;border-radius:8px;padding:40px 40px 32px 40px;border:1px solid #20252f;'>".
        "<div style='font-size:11px;letter-spacing:0.24em;text-transform:uppercase;color:#7f8ea3;margin-bottom:16px;'>{$safeTitle}</div>".
        "<h1 class='sentec-email-heading' style='margin:0 0 16px 0;font-size:22px;line-height:1.4;color:#00ff94;'>{$safeHeading}</h1>".
        "<p style='margin:0 0 12px 0;font-size:14px;color:#e5e7eb;line-height:1.7;text-align:justify;'>{$greetingLine}</p>".
        "<div style='font-size:14px;color:#d1d5db;line-height:1.7;text-align:justify;'>{$bodyHtml}</div>".
        $buttonHtml.
        "</td></tr>".
        "<tr><td class='sentec-email-footer' align='center' style='padding:24px 16px 8px 16px;color:#6b7280;font-size:11px;line-height:1.6;'>".
        $safeFooter.
        "<div style='margin-top:8px;color:#4b5563;'>&copy; ".date('Y')." SENTEC. All rights reserved.</div>".
        "</td></tr>".
        "</table></div>";
}
?>