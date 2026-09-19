<?php
include 'header.php';
include 'db_connection.php';

// Session is already started in header.php
$msg = "";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-amber-500/10 border border-amber-500/40 text-amber-300 text-xs font-mono flex items-center justify-between'>
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
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-amber-500/10 border border-amber-500/40 text-amber-300 text-xs font-mono'>Account inactive. Contact administrator.</div>";
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
                echo "<script>window.location.href='ambassador_dashboard.php';</script>"; exit;
            } else {
                $msg = "<div class='p-3.5 mb-5 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>Invalid ambassador credentials.</div>";
            }
        } else {
            $msg = "<div class='p-3.5 mb-5 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono'>No account found for provided Email / Ambassador ID.</div>";
        }
    }
}
?>

<div class="relative min-h-[calc(100vh-12rem)] flex items-center justify-center py-16 px-4">
    <div class="ambient-grid absolute inset-0 pointer-events-none opacity-30"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-[#f15a24]/10 rounded-full blur-3xl pointer-events-none -z-10"></div>

    <div class="relative w-full max-w-md">
        
        <!-- Dark Card Container -->
        <div class="p-8 sm:p-10 rounded-2xl bg-[#12161D] border border-white/[0.08] shadow-[0_20px_80px_rgba(0,0,0,0.85)]">
            
            <!-- Technical Tag & Heading -->
            <div class="mb-6">
                <span class="text-[11px] font-mono text-[#f15a24] uppercase tracking-widest block mb-1.5">
                    USER CREDENTIALS // ACCESS
                </span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white">
                    Welcome back
                </h2>
                <p class="text-xs text-neutral-400 mt-1 font-sans">
                    Access participant dashboard, check module passes, and cast ballots.
                </p>
            </div>

            <!-- Error/Status Message Display -->
            <?php echo $msg; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[11px] font-mono text-neutral-400 uppercase tracking-wider mb-2">
                        EMAIL ADDRESS OR AMBASSADOR CODE
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="email" 
                            required 
                            placeholder="you@example.com or AMB-XXXX" 
                            class="w-full px-4 py-3 rounded-lg bg-[#080b0d] border border-white/[0.12] text-white text-sm focus:outline-none focus:border-[#f15a24] focus:ring-1 focus:ring-[#f15a24] transition-all font-sans placeholder:text-neutral-600"
                            autofocus
                        >
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[11px] font-mono text-neutral-400 uppercase tracking-wider">
                            PASSWORD
                        </label>
                        <a href="forgot_password.php" class="text-[11px] font-mono text-[#f15a24] hover:underline">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="••••••••" 
                            class="w-full px-4 py-3 rounded-lg bg-[#080b0d] border border-white/[0.12] text-white text-sm focus:outline-none focus:border-[#f15a24] focus:ring-1 focus:ring-[#f15a24] transition-all font-sans placeholder:text-neutral-600"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-6 rounded-lg bg-[#f15a24] hover:bg-[#ff6b35] text-[#080b0d] font-mono font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all shadow-[0_4px_20px_rgba(241,90,36,0.3)] hover:-translate-y-0.5 mt-2"
                >
                    <span>Sign In</span>
                    <i class="fas fa-sign-in-alt text-xs"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/[0.08] text-center text-xs text-neutral-400 font-sans">
                Don't have an account yet? 
                <a href="signup.php" class="text-[#f15a24] font-semibold hover:underline ml-1">
                    Create Account
                </a>
            </div>

        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
