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
                
                $msg = '<div class="alert alert-success">Password Updated! <a href="login.php">Login Now</a></div>';
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

<section style="padding-top:140px; min-height:80vh; display:flex; align-items:center;">
  <div class="container">
    <div class="glass-panel" style="max-width:480px; margin:0 auto;">
      <h2 class="text-center mb-3 text-white">Set New Password</h2>
      <?php echo $msg; ?>
      
      <?php if ($showForm): ?>
      <form method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-3">
            <label style="color:var(--accent);">New Password</label>
            <input class="form-control form-control-dark" type="password" name="password" required minlength="6">
        </div>
        <div class="mb-4">
            <label style="color:var(--accent);">Confirm Password</label>
            <input class="form-control form-control-dark" type="password" name="cpassword" required minlength="6">
        </div>
        <button class="btn-clear w-100" type="submit">Update Password</button>
      </form>
      <?php else: ?>
      <p class="text-center mt-3">
          <a href="login.php" style="color:var(--accent); text-decoration:none;">← Back to Login</a>
      </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>