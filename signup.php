<?php

include 'header.php';
include 'db_connection.php';

require 'vendor/autoload.php';
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/mailer.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); 
    $pass = $_POST['password'];
    $cpass = $_POST['cpassword'];

    if ($pass !== $cpass) {
        $msg = "<div class='alert alert-danger'>Passwords do not match!</div>";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = "<div class='alert alert-danger'>Email already registered! <a href='login.php' style='color:var(--accent);'>Login here</a>.</div>";
        } else {
            // Generate OTP & Hash Password
            $otp = rand(100000, 999999);
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

            // Insert User (Pending Verification)
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, otp) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $hashed_pass, $otp);

            

            if ($stmt->execute()) {
                // --- SEND EMAIL USING CENTRAL MAILER + STANDARD TEMPLATE ---
                try {
                    $mail = sentec_mailer();
                    $mail->addAddress($email, $name);

                    $siteUrl = env('APP_URL', 'https://sentecneduet.live');
                    $verificationLink = $siteUrl . '/verify.php?email=' . urlencode($email) . '&otp=' . $otp;

                    $mail->Subject = 'Verify your SENTEC Account';
                    $greeting = 'Dear ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',';
                    $bodyHtml = "<p>Thank you for registering for the SENTEC portal.</p>" .
                        "<p>Your one-time verification code is:</p>" .
                        "<p style='margin:16px 0;font-size:22px;font-weight:700;letter-spacing:0.3em;color:#00ff94;background:#020617;display:inline-block;padding:10px 18px;border-radius:999px;'>{$otp}</p>" .
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

                    $mail->AltBody = "Your SENTEC verification code is {$otp}. Or open: {$verificationLink}";
                    $mail->send();
                    
                    // Redirect to Verification Page (use URL-encoded email)
                    $redirEmail = urlencode($email);
                    echo "<script>window.location.href='verify.php?email={$redirEmail}';</script>";
                    exit;

                } catch (Exception $e) {
                    $msg = "<div class='alert alert-danger'>Email Error: {$mail->ErrorInfo}</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger'>Database Error: " . $conn->error . "</div>";
            }
        }
    }
}
?>

<section style="padding-top: 140px; min-height: 100vh; display: flex; align-items: center;">
    <div class="container">
        <div class="glass-panel" style="max-width: 500px; margin: 0 auto;">
            <h2 class="text-center mb-4" style="color:#fff; font-family: 'Outfit', sans-serif; font-weight:800;">Create Account</h2>
            
            <?php echo $msg; ?>

            <form method="POST">
                <div class="mb-3">
                    <label style="color: var(--accent);">Full Name</label>
                    <input type="text" name="name" class="form-control form-control-dark" required>
                </div>
                <div class="mb-3">
                    <label style="color: var(--accent);">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-dark" required>
                </div>
                <div class="mb-3">
                    <label style="color: var(--accent);">Phone Number</label>
                    <input type="text" name="phone" class="form-control form-control-dark" placeholder="e.g. +92 300 1234567" required>
                </div>
                <div class="mb-3">
                    <label style="color: var(--accent);">Password</label>
                    <input type="password" name="password" class="form-control form-control-dark" required>
                </div>
                <div class="mb-4">
                    <label style="color: var(--accent);">Confirm Password</label>
                    <input type="password" name="cpassword" class="form-control form-control-dark" required>
                </div>
                
                <button type="submit" class="btn-clear w-100">Sign Up</button>
                
                <p class="text-center mt-4" style="color:#ccc;">
                    Already have an account? <a href="login.php" style="color:var(--accent); font-weight:bold;">Login</a>
                </p>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>



