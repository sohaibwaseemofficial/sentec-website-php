<?php

include 'header.php';

require 'vendor/autoload.php';
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/mailer.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/db_connection.php';
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
            $msg = "<div class='alert alert-danger'>Email already registered! <a href='login' style='color:var(--accent);'>Login here</a>.</div>";
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
                    echo "<script>window.location.href='verify?email={$redirEmail}';</script>";
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

<div class="secondary-page">
    <main>
        <!-- Top Hero matching Signup.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        AUTHENTICATION // REGISTRATION
                    </span>
                    <h1>
                        Create your<br>
                        <em>account.</em>
                    </h1>
                    <p>
                        Join the SENTEC ecosystem — register for PROXION arenas, collaborate on engineering teams, and cast student votes.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>IDENTITY // ENROLLMENT</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Signal Form Section -->
        <section class="secondary-section" style="max-width: 480px; margin: 0 auto; padding-top: 50px; padding-bottom: 100px;">
            
            <!-- Hanging L-Bracket Above Card -->
            <div style="width: 48px; height: 48px; border-left: 1.5px solid var(--orange); border-bottom: 1.5px solid var(--orange); margin: 0 auto 24px auto;"></div>

            <form method="POST" class="signal-form" style="background: rgba(15, 20, 22, 0.75); border: 1px solid var(--line); padding: 36px 32px; box-shadow: 0 18px 80px rgba(0, 0, 0, 0.4);">
                <span class="section-kicker" style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block;">NEW IDENTITY</span>
                
                <h2 style="margin: 14px 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                    Register with SENTEC
                </h2>

                <!-- Status / Error Notification -->
                <?php if (!empty($msg)) echo $msg; ?>

                <div style="margin-bottom: 20px;">
                    <label for="nameInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">FULL NAME</label>
                    <input 
                        type="text" 
                        id="nameInput"
                        name="name" 
                        required 
                        placeholder="e.g. Zainab Khan"
                        value="<?php echo htmlspecialchars($name ?? ''); ?>"
                        autofocus
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="emailInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">EMAIL ADDRESS</label>
                    <input 
                        type="email" 
                        id="emailInput"
                        name="email" 
                        required 
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="phoneInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">PHONE NUMBER</label>
                    <input 
                        type="text" 
                        id="phoneInput"
                        name="phone" 
                        required 
                        placeholder="e.g. +92 300 1234567"
                        value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="passInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">PASSWORD</label>
                    <input 
                        type="password" 
                        id="passInput"
                        name="password" 
                        required 
                        placeholder="Minimum 6 characters"
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="margin-bottom: 24px;">
                    <label for="cpassInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">CONFIRM PASSWORD</label>
                    <input 
                        type="password" 
                        id="cpassInput"
                        name="cpassword" 
                        required 
                        placeholder="Repeat your password"
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" style="width: 100%; justify-content: center; background: var(--orange); color: #170b06; border: 0; padding: 15px 20px; font: 700 11px 'IBM Plex Mono', monospace; letter-spacing: 0.1em; text-transform: uppercase; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: background 0.2s ease;">
                        <span>CREATE ACCOUNT</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>

                <div style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--muted); font-family: 'Space Grotesk', sans-serif;">
                    Already enrolled? <a href="login" style="color: var(--orange); font-weight: 600; text-decoration: none;">Sign In</a>
                </div>
            </form>
        </section>
    </main>
</div>

<?php include 'footer.php'; ?>



