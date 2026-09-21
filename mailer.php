<?php
/**
 * Centralized Mailer using Resend HTTP API (cURL)
 * Eliminates SMTP port blocks on cloud hosts like Render.
 */

require_once __DIR__ . '/env_loader.php';

/**
 * Resend API Mailer Adapter
 * Mimics PHPMailer interface for 100% backwards compatibility.
 */
class SentecResendMailer {
    public string $Subject = '';
    public string $Body = '';
    public string $AltBody = '';
    public string $ErrorInfo = '';
    public int $Port = 443;
    public string $Host = 'api.resend.com';
    public string $SMTPSecure = 'ssl';

    protected array $to = [];
    protected array $replyTo = [];
    protected string $fromEmail = 'noreply@sentecneduet.live';
    protected string $fromName = 'SENTEC';
    protected string $apiKey = '';

    public function __construct() {
        // Pull API Key securely from environment
        $this->apiKey = trim((string)(getenv('RESEND_API_KEY') ?: (env('RESEND_API_KEY') ?: '')));

        // Configurable authenticated From address (defaults to domain noreply@sentecneduet.live)
        $envFrom = getenv('FROM_EMAIL') ?: (env('FROM_EMAIL') ?: 'noreply@sentecneduet.live');
        $this->fromEmail = $envFrom;
        $this->fromName  = getenv('FROM_NAME') ?: (env('FROM_NAME') ?: 'SENTEC');
    }

    public function isSMTP(): void {
        // Compatibility no-op
    }

    public function isHTML(bool $isHtml = true): void {
        // Compatibility no-op
    }

    public function setFrom(string $email, string $name = ''): void {
        if (!empty($email)) {
            $this->fromEmail = $email;
        }
        if (!empty($name)) {
            $this->fromName = $name;
        }
    }

    public function addAddress(string $email, string $name = ''): void {
        $cleanEmail = trim($email);
        if ($cleanEmail) {
            $this->to[] = $cleanEmail;
        }
    }

    public function addReplyTo(string $email, string $name = ''): void {
        $cleanEmail = trim($email);
        if ($cleanEmail) {
            $this->replyTo[] = $cleanEmail;
        }
    }

    public function send(): bool {
        if (empty($this->to)) {
            $this->ErrorInfo = "No recipient email addresses specified.";
            sentec_mail_log('resend_api', 'error', $this->ErrorInfo);
            throw new \Exception($this->ErrorInfo);
        }

        if (empty($this->apiKey)) {
            $this->ErrorInfo = "RESEND_API_KEY is not configured in the environment.";
            sentec_mail_log('resend_api', 'error', $this->ErrorInfo);
            throw new \Exception($this->ErrorInfo);
        }

        $fromHeader = $this->fromName ? "{$this->fromName} <{$this->fromEmail}>" : $this->fromEmail;
        $cleanTo = array_values(array_unique($this->to));

        $payload = [
            'from'    => $fromHeader,
            'to'      => $cleanTo,
            'subject' => $this->Subject,
            'html'    => $this->Body,
        ];

        if (!empty($this->AltBody)) {
            $payload['text'] = $this->AltBody;
        }

        if (!empty($this->replyTo)) {
            $payload['reply_to'] = array_values(array_unique($this->replyTo));
        }

        $jsonPayload = json_encode($payload);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: SENTEC-PHP-Mailer/2.0',
            ],
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $recipientStr = implode(',', $cleanTo);

        if ($curlError) {
            $this->ErrorInfo = "Resend cURL Error: " . $curlError;
            sentec_mail_log('resend_api', 'error', "to={$recipientStr} error=" . $this->ErrorInfo);
            throw new \Exception($this->ErrorInfo);
        }

        $resData = json_decode((string)$response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $resendId = $resData['id'] ?? 'ok';
            sentec_mail_log('resend_api', 'sent', "to={$recipientStr} resend_id={$resendId}");
            return true;
        }

        $errMsg = $resData['message'] ?? ($resData['error']['message'] ?? "HTTP {$httpCode}: " . substr((string)$response, 0, 200));
        $this->ErrorInfo = "Resend API Error ({$httpCode}): " . $errMsg;
        sentec_mail_log('resend_api', 'error', "to={$recipientStr} error=" . $this->ErrorInfo);
        throw new \Exception($this->ErrorInfo);
    }
}

/**
 * Get configured Mailer instance
 */
function sentec_mailer(): SentecResendMailer {
    return new SentecResendMailer();
}

/**
 * Functional helper to send email directly via Resend API
 */
function sentec_send_email(
    string|array $to,
    string $subject,
    string $htmlBody,
    ?string $textBody = null,
    ?string $fromEmail = null,
    ?string $fromName = null
): bool {
    $mailer = sentec_mailer();
    if ($fromEmail) {
        $mailer->setFrom($fromEmail, $fromName ?? '');
    }
    if (is_array($to)) {
        foreach ($to as $recipient) {
            $mailer->addAddress($recipient);
        }
    } else {
        $mailer->addAddress($to);
    }
    $mailer->Subject = $subject;
    $mailer->Body    = $htmlBody;
    if ($textBody) {
        $mailer->AltBody = $textBody;
    }
    return $mailer->send();
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