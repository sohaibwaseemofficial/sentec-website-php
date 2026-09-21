<?php
include 'header.php';
include 'db_connection.php';

// Session is already started in header.php, so we don't need session_start() here again.
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Identity can be student email OR ambassador code/email
    $identity = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $identity);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($pass, $user['password'])) {
            if ($user['is_verified'] == 1) {
                // LOGIN SUCCESS!
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                
                // --- FIX: USE JAVASCRIPT REDIRECT INSTEAD OF PHP HEADER ---
                echo "<script>
                        window.location.href = 'dashboard';
                      </script>";
                exit;
            } else {
                // Use the identity (email) in the verify link — avoid undefined variable and encode value
                $verifyLink = 'verify.php?email=' . urlencode($identity);
                $msg = "<div class='alert alert-warning'>Account not verified. <a href='$verifyLink' style='color:#000; font-weight:bold;'>Verify Now</a></div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>Incorrect Password.</div>";
        }
    } else {
        // Ambassador login by code or email
        $stmt2 = $conn->prepare("SELECT id, name, code, status, password_hash, ambassador_type FROM brand_ambassadors WHERE code = ? OR email = ? LIMIT 1");
        $stmt2->bind_param("ss", $identity, $identity);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2 && $res2->num_rows) {
            $amb = $res2->fetch_assoc();
            if ($amb['status'] !== 'active') {
                $msg = "<div class='alert alert-warning'>Account inactive. Contact admin.</div>";
            } elseif ($amb['password_hash'] && password_verify($pass, $amb['password_hash'])) {
                $_SESSION['ambassador_id'] = $amb['id'];
                $_SESSION['ambassador_name'] = $amb['name'];
                $ambType = strtolower($amb['ambassador_type'] ?? '');
                if (!in_array($ambType, ['volunteer', 'brand'], true)) {
                    $ambType = 'brand';
                }
                $_SESSION['ambassador_code'] = $amb['code'];
                $_SESSION['ambassador_type'] = $ambType;
                $conn->query("UPDATE brand_ambassadors SET last_login = NOW() WHERE id=" . (int)$amb['id']);
                echo "<script>window.location.href='ambassador_dashboard';</script>"; exit;
            } else {
                $msg = "<div class='alert alert-danger'>Invalid credentials.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>No account found for provided Email / ID.</div>";
        }
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
                        AMBASSADOR // NETWORK
                    </span>
                    <h1>
                        Ambassador<br>
                        <em>access.</em>
                    </h1>
                    <p>
                        Authorized portal for NED campus ambassadors, student liaisons, and outreach coordinators.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>AMBASSADOR // CLEARANCE</strong>
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
                <span class="section-kicker">AMBASSADOR CREDENTIALS</span>
                <h2 style="margin: 14px 0 24px; font-size: 26px; font-weight: 500; color: #f4f1eb; letter-spacing: -0.03em;">
                    Ambassador Sign In
                </h2>

                <!-- Status / Error Notification -->
                <?php if (!empty($msg)) echo $msg; ?>

                <div style="margin-bottom: 24px;">
                    <label for="ambEmailInput">EMAIL OR AMBASSADOR CODE</label>
                    <input 
                        type="text" 
                        id="ambEmailInput"
                        name="email" 
                        required 
                        placeholder="e.g. AMB-1002 or you@example.com"
                        value="<?php echo htmlspecialchars($identity ?? ''); ?>"
                        autofocus
                    >
                </div>

                <div style="margin-bottom: 28px;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <label for="ambPassInput">PASSWORD</label>
                        <a href="forgot_password" style="font-size: 10px; font-family: 'IBM Plex Mono', monospace; color: var(--orange); text-decoration: none; letter-spacing: 0.06em;">
                            FORGOT PASSWORD?
                        </a>
                    </div>
                    <input 
                        type="password" 
                        id="ambPassInput"
                        name="password" 
                        required 
                        placeholder="••••••••"
                    >
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--line);">
                    <a href="login" style="font-size: 11px; font-family: 'IBM Plex Mono', monospace; color: #9aa3a3; text-decoration: none;">
                        PARTICIPANT? <span style="color: var(--orange); text-decoration: underline;">SIGN IN HERE</span>
                    </a>
                    <button type="submit" class="signal-btn">
                        <span>SIGN IN</span>
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
