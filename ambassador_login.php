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
                        window.location.href = 'dashboard.php';
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
                echo "<script>window.location.href='ambassador_dashboard.php';</script>"; exit;
            } else {
                $msg = "<div class='alert alert-danger'>Invalid credentials.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>No account found for provided Email / ID.</div>";
        }
    }
}
?>

<section style="padding-top: 140px; min-height: 80vh; display: flex; align-items: center;">
    <div class="container">
        <div class="glass-panel" style="max-width: 450px; margin: 0 auto;">
            <h2 class="text-center mb-4" style="color:#fff;">Login</h2>
            
            <?php echo $msg; ?>

            <form method="POST">
                <div class="mb-3">
                    <label style="color: var(--accent);">Email / ID</label>
                    <input type="text" name="email" class="form-control form-control-dark" placeholder="Student Email or Ambassador Code" required>
                </div>
                <div class="mb-4">
                    <label style="color: var(--accent);">Password</label>
                    <input type="password" name="password" class="form-control form-control-dark" required>
                </div>
                
                <button type="submit" class="btn-clear w-100">Login</button>
                <div class="d-flex justify-content-between mt-3">
                    <a href="forgot_password.php" style="color:var(--accent);">Forgot password?</a>
                    <span></span>
                </div>
                
                <p class="text-center mt-4" style="color:#ccc;">
                    New here? <a href="signup.php" style="color:var(--accent); font-weight:bold;">Create Account</a>
                </p>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
