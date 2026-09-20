<?php
include 'header.php';
include 'db_connection.php';

$email = $_GET['email'] ?? '';
$msg = "";

// Auto-verify via GET (link containing ?email=...&otp=...)
if (isset($_GET['email']) && isset($_GET['otp'])) {
    $get_email = trim($_GET['email']);
    $get_otp = trim($_GET['otp']);
    
    $stmtG = $conn->prepare("SELECT * FROM users WHERE email = ? AND otp = ?");
    $stmtG->bind_param("ss", $get_email, $get_otp);
    $stmtG->execute();
    $resG = $stmtG->get_result();
    if ($resG && $resG->num_rows > 0) {
        $updateG = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL WHERE email = ?");
        $updateG->bind_param("s", $get_email);
        if ($updateG->execute()) {
            echo "<script>alert('Account Verified! Please Login.'); window.location.href='login.php';</script>";
            exit;
        } else {
            error_log('Verify (GET) update failed: ' . $conn->error);
            $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Couldn't verify your account — please try again or contact support.</div>";
        }
    } else {
        $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Invalid or expired verification link.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle resend OTP button
    if (isset($_POST['resend']) && !empty($_POST['email'])) {
        $resend_email = trim($_POST['email']);
        $newOtp = rand(100000, 999999);
        $u = $conn->prepare("UPDATE users SET otp = ? WHERE email = ?");
        $u->bind_param("ss", $newOtp, $resend_email);
        if ($u->execute() && $u->affected_rows > 0) {
            require_once __DIR__ . '/mailer.php';
            try {
                $siteUrl = env('APP_URL', 'https://sentecneduet.live');
                $verificationLink = $siteUrl . '/verify.php?email=' . urlencode($resend_email) . '&otp=' . $newOtp;

                $mail = sentec_mailer();
                $mail->addAddress($resend_email);
                $mail->Subject = 'Your NEW verification code for SENTEC';

                $greeting = 'Dear participant,';
                $bodyHtml = "<p>Here is your new one-time verification code for your SENTEC account:</p>" .
                    "<p style='margin:16px 0;font-size:22px;font-weight:700;letter-spacing:0.3em;color:#f15a24;background:#080b0d;display:inline-block;padding:10px 18px;border-radius:4px;border:1px solid #f15a24;'>{$newOtp}</p>" .
                    "<p style='font-size:12px;color:#9ca3af;margin-top:4px;'>This code expires in 15 minutes.</p>" .
                    "<p style='margin-top:18px;'>You can also verify instantly by clicking the button below.</p>";

                $mail->Body = sentec_build_email_html(
                    'Email Verification',
                    'Verify Your SENTEC Account',
                    $greeting,
                    $bodyHtml,
                    $verificationLink,
                    'Verify Account',
                    null
                );

                $mail->AltBody = "Your new SENTEC verification code is {$newOtp}. Or open: {$verificationLink}";
                $mail->send();
                $msg = "<div class='p-3 mb-4 rounded bg-green-500/10 border border-green-500/40 text-green-300 text-xs font-mono'>A new verification code has been dispatched to your email.</div>";
            } catch (Exception $e) {
                $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Email Error: {$mail->ErrorInfo}</div>";
            }
        } else {
            $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Unable to generate new OTP. Please try again later.</div>";
        }
    } elseif (isset($_POST['otp']) && isset($_POST['email'])) {
        $otp_input = trim($_POST['otp']);
        $email_input = trim($_POST['email']);

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND otp = ?");
        $stmt->bind_param("ss", $email_input, $otp_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $update = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL WHERE email = ?");
            $update->bind_param("s", $email_input);
            if ($update->execute()) {
                echo "<script>alert('Account Verified! Please Login.'); window.location.href='login.php';</script>";
                exit;
            } else {
                error_log('Verify (POST) update failed: ' . $conn->error);
                $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Couldn't verify your account — please try again or contact support.</div>";
            }
        } else {
            $msg = "<div class='p-3 mb-4 rounded bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Invalid Verification Code! Please check and retry.</div>";
        }
    }
}
?>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching VerifyEmail.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        SECURITY // EMAIL VERIFICATION
                    </span>
                    <h1>
                        Verify your<br>
                        <em>email.</em>
                    </h1>
                    <p>
                        Enter the 6-digit one-time passkey sent to your email inbox to activate your account.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>OTP // VALIDATION</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Signal Form Section -->
        <section class="secondary-section" style="max-width: 500px; margin: 0 auto; padding-top: 60px;">
            <div class="signal-form">
                <span class="section-kicker">PASSKEY CONFIRMATION</span>
                <h2 style="margin: 14px 0 16px; font-size: 26px; font-weight: 500; color: #f4f1eb; letter-spacing: -0.03em;">
                    Activate Account
                </h2>
                <p style="color: #9aa3a3; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                    We dispatched a verification code to: <strong style="color: #f4f1eb;"><?php echo htmlspecialchars($email ?: 'your email'); ?></strong>
                </p>

                <!-- Status Messages -->
                <?php if (!empty($msg)) echo $msg; ?>

                <!-- Verification Form -->
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <?php if (empty($email)): ?>
                    <div style="margin-bottom: 20px;">
                        <label for="vEmail">EMAIL ADDRESS</label>
                        <input type="email" id="vEmail" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 28px;">
                        <label for="otpCode">6-DIGIT PASSKEY</label>
                        <input 
                            type="text" 
                            id="otpCode"
                            name="otp" 
                            maxlength="6" 
                            required 
                            placeholder="••••••" 
                            style="text-align: center; font-family: 'IBM Plex Mono', monospace; font-size: 28px; letter-spacing: 0.35em; font-weight: 600; color: var(--orange);"
                            autofocus
                        >
                    </div>

                    <button type="submit" class="signal-btn" style="width: 100%;">
                        <span>VERIFY ACCOUNT</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </button>
                </form>

                <!-- Resend Form -->
                <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <span style="font-size: 11px; font-family: 'IBM Plex Mono', monospace; color: #5d696c;">DIDN'T RECEIVE CODE?</span>
                    <button type="submit" name="resend" value="1" class="signal-btn-outline" style="font-size: 10px; padding: 7px 14px;">
                        <span>RESEND PASSKEY</span>
                    </button>
                </form>

            </div>
        </section>
    </main>
</div>

<?php include 'footer.php'; ?>
