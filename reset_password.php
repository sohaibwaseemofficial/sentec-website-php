<?php
include 'header.php';
include 'db_connection.php';

$msg = "";
$token = $_GET['token'] ?? '';
$showForm = true;

// Validate token
if ($token) {
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->fetch_assoc()) {
        $msg = '<div class="alert alert-danger">Invalid or expired token. Please request a new reset link.</div>';
        $showForm = false;
    }
    $stmt->close();
} else {
    $showForm = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $token = $_POST['token'];
    $pass = $_POST['password'];
    $cpass = $_POST['cpassword'];

    if ($pass !== $cpass) {
        $msg = '<div class="alert alert-danger">Passwords do not match.</div>';
    } elseif (strlen($pass) < 6) {
        $msg = '<div class="alert alert-danger">Password must be at least 6 characters.</div>';
    } else {
        // Verify Token again
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            
            // Update User Password
            $new_hash = password_hash($pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update->bind_param("ss", $new_hash, $email);
            
            if ($update->execute()) {
                // Delete used token
                $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $del->bind_param("s", $email);
                $del->execute();
                $del->close();
                
                $msg = '<div class="alert alert-success">Password Updated! <a href="login">Login Now</a></div>';
                $showForm = false;
            }
            $update->close();
        } else {
            $msg = '<div class="alert alert-danger">Invalid or Expired Token.</div>';
            $showForm = false;
        }
        $stmt->close();
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
                        SECURITY // CREDENTIALS
                    </span>
                    <h1>
                        Set new<br>
                        <em>password.</em>
                    </h1>
                    <p>
                        Establish a secure cryptographic credential to re-authorize your profile across the SENTEC system.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>CREDENTIAL // UPDATE</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Signal Form Section -->
        <section class="secondary-section" style="max-width: 520px; margin: 0 auto; padding-top: 60px;">
            <div class="signal-form">
                <span class="section-kicker">CREDENTIAL UPDATE</span>
                <h2 style="margin: 14px 0 24px; font-size: 26px; font-weight: 500; color: #f4f1eb; letter-spacing: -0.03em;">
                    Set New Password
                </h2>

                <!-- Status / Error Notification -->
                <?php if (!empty($msg)) echo $msg; ?>

                <?php if ($showForm): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div style="margin-bottom: 22px;">
                        <label for="newPass">NEW PASSWORD</label>
                        <input 
                            type="password" 
                            id="newPass"
                            name="password" 
                            required 
                            minlength="6"
                            placeholder="Minimum 6 characters"
                            autofocus
                        >
                    </div>

                    <div style="margin-bottom: 28px;">
                        <label for="confirmPass">CONFIRM NEW PASSWORD</label>
                        <input 
                            type="password" 
                            id="confirmPass"
                            name="cpassword" 
                            required 
                            minlength="6"
                            placeholder="Repeat new password"
                        >
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--line);">
                        <a href="login" style="font-size: 11px; font-family: 'IBM Plex Mono', monospace; color: #9aa3a3; text-decoration: none;">
                            ← RETURN TO <span style="color: var(--orange); text-decoration: underline;">SIGN IN</span>
                        </a>
                        <button type="submit" class="signal-btn">
                            <span>UPDATE PASSWORD</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--line);">
                    <a href="login" class="signal-btn" style="width: 100%; text-align: center;">
                        <span>RETURN TO SIGN IN</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php include 'footer.php'; ?>