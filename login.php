<?php
include 'header.php';

// Session is already started in header.php
$msg = "";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_once __DIR__ . '/db_connection.php';
    // Identity can be student email OR ambassador code/email
    $identity = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

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
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'email' => $identity,
                    'role' => 'user'
                ];
                
                echo "<script>
                        window.location.href = 'dashboard.php';
                      </script>";
                exit;
            } else {
                $verifyLink = 'verify.php?email=' . urlencode($identity);
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-[#f15a24]/10 border border-[#f15a24]/40 text-[#ff8050] text-xs font-mono flex items-center justify-between'>
                            <span>Account not verified.</span>
                            <a href='$verifyLink' class='font-bold underline hover:text-white'>Verify Now</a>
                        </div>";
            }
        } else {
            $msg = "<div class='p-3.5 mb-5 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono flex items-center gap-2'>
                        <i class='fas fa-exclamation-circle text-sm'></i>
                        <span>Incorrect Password. Please check credentials.</span>
                    </div>";
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
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-[#f15a24]/10 border border-[#f15a24]/40 text-[#ff8050] text-xs font-mono'>Account inactive. Contact administrator.</div>";
            } elseif ($amb['password_hash'] && password_verify($pass, $amb['password_hash'])) {
                $_SESSION['ambassador_id'] = $amb['id'];
                $_SESSION['ambassador_name'] = $amb['name'];
                $ambType = strtolower($amb['ambassador_type'] ?? '');
                if (!in_array($ambType, ['volunteer', 'brand'], true)) {
                    $ambType = 'brand';
                }
                $_SESSION['ambassador_code'] = $amb['code'];
                $_SESSION['ambassador_type'] = $ambType;
                $_SESSION['user'] = [
                    'id' => $amb['id'],
                    'name' => $amb['name'],
                    'code' => $amb['code'],
                    'role' => 'ambassador'
                ];
                $conn->query("UPDATE brand_ambassadors SET last_login = NOW() WHERE id=" . (int)$amb['id']);
                echo "<script>window.location.href='ambassador_dashboard';</script>"; exit;
            } else {
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Invalid ambassador credentials.</div>";
            }
        } else {
            $msg = "<div class='p-3.5 mb-5 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>No account found for provided Email / Ambassador ID.</div>";
        }
    }
}
?>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching Login.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        AUTHENTICATION // ACCESS
                    </span>
                    <h1>
                        Sign in to<br>
                        <em>SENTEC.</em>
                    </h1>
                    <p>
                        Access your participant dashboard, check module registration status, and participate in student council ballots.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>SECURITY // CLEARANCE</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Signal Form Section matching Screenshot 1 -->
        <section class="secondary-section" style="max-width: 480px; margin: 0 auto; padding-top: 50px; padding-bottom: 100px;">
            
            <!-- Hanging L-Bracket Above Card -->
            <div style="width: 48px; height: 48px; border-left: 1.5px solid var(--orange); border-bottom: 1.5px solid var(--orange); margin: 0 auto 24px auto;"></div>

            <form method="POST" class="signal-form" style="background: rgba(15, 20, 22, 0.75); border: 1px solid var(--line); padding: 36px 32px; box-shadow: 0 18px 80px rgba(0, 0, 0, 0.4);">
                <span class="section-kicker" style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block;">USER CREDENTIALS</span>
                
                <h2 style="margin: 14px 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                    Welcome back
                </h2>

                <!-- Status / Error Notification -->
                <?php if (!empty($msg)) echo $msg; ?>

                <div style="margin-bottom: 20px;">
                    <label for="emailInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">
                        EMAIL ADDRESS
                    </label>
                    <input 
                        type="text" 
                        id="emailInput"
                        name="email" 
                        required 
                        placeholder="you@example.com" 
                        value="<?php echo htmlspecialchars($identity ?? ''); ?>"
                        autofocus
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="margin-bottom: 14px;">
                    <label for="passInput" style="display: block; color: var(--muted); font: 600 10px 'IBM Plex Mono', monospace; letter-spacing: 0.13em; margin-bottom: 8px; text-transform: uppercase;">
                        PASSWORD
                    </label>
                    <input 
                        type="password" 
                        id="passInput"
                        name="password" 
                        required 
                        placeholder="••••••••"
                        style="width: 100%; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--paper); padding: 12px 0; font-family: 'Space Grotesk', sans-serif; font-size: 15px; outline: none; border-radius: 0;"
                    >
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <a href="forgot_password" style="font-size: 11px; font-family: 'IBM Plex Mono', monospace; color: var(--orange); text-decoration: none;">
                        Forgot password?
                    </a>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" style="width: 100%; justify-content: center; background: var(--orange); color: #000; border: 0; padding: 14px 20px; font: 800 12px 'IBM Plex Mono', monospace; letter-spacing: 0.16em; text-transform: uppercase; display: flex; align-items: center; gap: 6px; cursor: pointer; transition: opacity 0.2s ease;">
                        <span>SIGN IN</span>
                        <span style="font-size: 14px; font-weight: 800; font-family: monospace;">&rarr;]</span>
                    </button>
                </div>

                <div style="margin-top: 28px; text-align: center; font-size: 13px; color: var(--muted); font-family: 'Space Grotesk', sans-serif; line-height: 1.6;">
                    Don’t have an account yet? <a href="signup" style="color: var(--orange); font-weight: 700; text-decoration: none; display: block; margin-top: 2px;">Create Account</a>
                </div>
            </form>
        </section>
    </main>
</div>

<?php include 'footer.php'; ?>
