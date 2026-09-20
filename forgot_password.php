<?php
session_start();
include 'header.php';
include 'db_connection.php';

// Load centralized mailer
require 'vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email) {
        // SECURED: Use prepared statement
        $check = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Create Token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 1800);

            // Create Table if missing
            $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(150) NOT NULL,
                token VARCHAR(100) NOT NULL,
                expires_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // SECURED: Save Token with prepared statement
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $email, $token, $expires);
            
            if ($stmt->execute()) {
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                
                try {
                    // Use centralized mailer
                    $mail = sentec_mailer();
                    $mail->addAddress($email);
                    $mail->Subject = 'Reset Your Password - SENTEC';
                    
                    $greeting = 'Hello ' . htmlspecialchars($user['full_name']) . ',';
                    $bodyHtml = "<p>We received a request to reset your password for your SENTEC account.</p>
                        <p>Click the button below to create a new password. This link expires in 30 minutes.</p>
                        <p style='font-size:12px; color:#888; margin-top:20px;'>If you didn't request this, please ignore this email.</p>";
                    
                    $mail->Body = sentec_build_email_html(
                        'Password Reset',
                        'Reset Your Password',
                        $greeting,
                        $bodyHtml,
                        $resetLink,
                        'Reset Password',
                        null
                    );
                    $mail->AltBody = "Reset your password: $resetLink";
                    
                    $mail->send();
                    sentec_mail_log('forgot_password', 'sent', "to=$email");
                    $msg = '<div class="alert alert-success">Reset link sent! Check your inbox.</div>';
                } catch (Exception $e) {
                    sentec_mail_log('forgot_password', 'error', $e->getMessage());
                    $msg = '<div class="alert alert-success">If the email exists, a reset link has been sent.</div>';
                }
            }
            $stmt->close();
        } else {
            // Don't reveal if email exists
            $msg = '<div class="alert alert-success">If the email exists, a reset link has been sent.</div>';
        }
        $check->close();
    }
}
?>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        SECURITY // RECOVERY
                    </span>
                    <h1>
                        Recover your<br>
                        <em>access.</em>
                    </h1>
                    <p>
                        Initiate a cryptographic password reset dispatch to restore account clearance across the SENTEC portal.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>KEY // DISPATCH</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Signal Form Section -->
        <section class="secondary-section" style="max-width: 520px; margin: 0 auto; padding-top: 60px;">
            <form method="POST" class="signal-form">
                <span class="section-kicker">CREDENTIAL RECOVERY</span>
                <h2 style="margin: 14px 0 24px; font-size: 26px; font-weight: 500; color: #f4f1eb; letter-spacing: -0.03em;">
                    Forgot Password
                </h2>
                <p style="color: #9aa3a3; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                    Enter your registered email address and our dispatch server will send you a secure verification link.
                </p>

                <!-- Status Message -->
                <?php if (!empty($msg)) echo $msg; ?>

                <div style="margin-bottom: 28px;">
                    <label for="recoveryEmail">REGISTERED EMAIL ADDRESS</label>
                    <input 
                        type="email" 
                        id="recoveryEmail"
                        name="email" 
                        required 
                        placeholder="you@example.com"
                        autofocus
                    >
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--line);">
                    <a href="login.php" style="font-size: 11px; font-family: 'IBM Plex Mono', monospace; color: #9aa3a3; text-decoration: none;">
                        ← RETURN TO <span style="color: var(--orange); text-decoration: underline;">SIGN IN</span>
                    </a>
                    <button type="submit" class="signal-btn">
                        <span>DISPATCH RESET LINK</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

<?php include 'footer.php'; ?>