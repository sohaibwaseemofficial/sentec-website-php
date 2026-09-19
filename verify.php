<?php
include 'header.php';
include 'db_connection.php';

$email = $_GET['email'] ?? '';
$msg = "";

// Auto-verify via GET (link containing ?email=...&otp=...)
if (isset($_GET['email']) && isset($_GET['otp'])) {
    $get_email = $_GET['email'];
    $get_otp = $_GET['otp'];
    // Use the same verification logic as POST
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
            $msg = "<div class='alert alert-danger'>Couldn't verify your account — please try again or contact support.</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>Invalid or expired verification link.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle resend OTP button
    if (isset($_POST['resend']) && !empty($_POST['email'])) {
        $resend_email = $_POST['email'];
        $newOtp = rand(100000, 999999);
        $u = $conn->prepare("UPDATE users SET otp = ? WHERE email = ?");
        $u->bind_param("ss", $newOtp, $resend_email);
        if ($u->execute() && $u->affected_rows > 0) {
            require_once __DIR__ . '/mailer.php';
            // resend email using the same dark SENTEC template
            try {
                $siteUrl = env('APP_URL', 'https://sentecneduet.live');
                $verificationLink = $siteUrl . '/verify.php?email=' . urlencode($resend_email) . '&otp=' . $newOtp;

                $mail = sentec_mailer();
                $mail->addAddress($resend_email);
                $mail->Subject = 'Your NEW verification code for SENTEC';

                $greeting = 'Dear participant,';
                $bodyHtml = "<p>Here is your new one-time verification code for your SENTEC account:</p>" .
                    "<p style='margin:16px 0;font-size:22px;font-weight:700;letter-spacing:0.3em;color:#00ff94;background:#020617;display:inline-block;padding:10px 18px;border-radius:999px;'>{$newOtp}</p>" .
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
                $msg = "<div class='alert alert-success'>A new verification code has been sent.</div>";
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger'>Email Error: {$mail->ErrorInfo}</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>Unable to generate new OTP. Please try again later.</div>";
        }
    }

    $otp_input = $_POST['otp'];
    $email_input = $_POST['email'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND otp = ?");
    $stmt->bind_param("ss", $email_input, $otp_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL WHERE email = ?");
        $update->bind_param("s", $email_input);
        if ($update->execute()) {
            echo "<script>alert('Account Verified! Please Login.'); window.location.href='login.php';</script>";
        } else {
            error_log('Verify (POST) update failed: ' . $conn->error);
            $msg = "<div class='alert alert-danger'>Couldn't verify your account — please try again or contact support.</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>Invalid Verification Code!</div>";
    }
}
?>

<section style="padding-top: 140px; min-height: 80vh; display: flex; align-items: center;">
    <div class="container">
        <div class="glass-panel" style="max-width: 450px; margin: 0 auto; text-align: center;">
            <h2 class="mb-3" style="color:#fff;">Verify Email</h2>
            <p style="color:#ccc;">We sent a code to <strong><?php echo htmlspecialchars($email); ?></strong></p>
            <?php echo $msg; ?>
            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <div class="mb-4">
                    <input type="text" name="otp" class="form-control form-control-dark text-center" placeholder="Enter Code" maxlength="6" style="font-size: 1.5rem; letter-spacing: 5px;" required>
                </div>
                <button type="submit" class="btn-clear w-100">Verify Account</button>
            </form>
            <form method="POST" class="mt-3">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit" name="resend" value="1" class="btn btn-outline-light w-100">Resend Code</button>
            </form>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
