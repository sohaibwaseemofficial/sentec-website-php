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

<section style="padding-top:140px; min-height:80vh; display:flex; align-items:center;">
  <div class="container">
    <div class="glass-panel" style="max-width:480px; margin:0 auto;">
      <h2 class="text-center mb-3 text-white">Forgot Password</h2>
      <p class="text-center text-muted mb-4">Enter your email and we'll send you a reset link.</p>
      <?php echo $msg; ?>
      <form method="post">
        <div class="mb-3">
            <label style="color:var(--accent);">Email Address</label>
            <input class="form-control form-control-dark" type="email" name="email" required>
        </div>
        <button class="btn-clear w-100" type="submit">Send Reset Link</button>
      </form>
      <p class="text-center mt-3">
          <a href="login.php" style="color:#888; text-decoration:none;">← Back to Login</a>
      </p>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>