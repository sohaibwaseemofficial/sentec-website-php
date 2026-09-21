<?php
// 1. ERROR REPORTING
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('session.cookie_httponly', 1);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.cookie_lifetime', 1800);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust database connection path
if (file_exists('../db_connection.php')) {
    include '../db_connection.php';
} else {
    include 'db_connection.php';
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin']) || isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$conn) {
        $error = "Database connection unavailable.";
    } else {
        // Use prepared statement
        $query = "SELECT * FROM admin_users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check password using modern password_verify OR legacy MD5
            $password_valid = false;
            
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            } elseif (md5($password) == $user['password']) {
                $password_valid = true;
                
                // Upgrade the password to modern hash automatically
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            if ($password_valid) {
                $_SESSION['admin'] = $username;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_full_name'] = $user['full_name'] ?? $username;
                $_SESSION['admin_role'] = $user['role'] ?? 'moderator';
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['last_activity'] = time();

                // Update last_login
                $updateLogin = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateLogin->bind_param("i", $user['id']);
                $updateLogin->execute();
                $updateLogin->close();

                // Log the login
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, 'LOGIN', 'Successful admin clearance login', ?)");
                if ($logStmt) {
                    $logStmt->bind_param("is", $user['id'], $ip);
                    $logStmt->execute();
                    $logStmt->close();
                }

                header("Location: index.php");
                exit();
            } else {
                $error = "Security gateway clearance rejected: Invalid passphrase.";
            }
        } else {
            $error = "Security gateway clearance rejected: Administrator not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Clearance | SENTEC</title>
    <link rel="icon" href="favicon2.png" type="image/png">
    <link rel="shortcut icon" href="favicon2.png" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            orange: '#f15a24',
                            dark: '#080b0d',
                            surface: '#101518'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#080b0d] text-[#f4f1eb] min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans">
    
    <!-- Ambient Radar Background -->
    <div class="absolute inset-0 pointer-events-none opacity-20" style="background-image: radial-gradient(circle at 50% 50%, #f15a24 0%, transparent 60%);"></div>
    <div class="absolute inset-0 pointer-events-none opacity-30" style="background-image: linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px); background-size: 32px 32px;"></div>

    <div class="relative w-full max-w-md z-10">
        
        <!-- Dark Card Container matching AdminLogin.tsx -->
        <div class="p-8 sm:p-10 rounded-2xl bg-[#101518] border border-[#f15a24]/50 shadow-[0_20px_80px_rgba(0,0,0,0.95)]">
            
            <!-- Shield Header -->
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-full bg-[#f15a24]/15 border border-[#f15a24] flex items-center justify-center mx-auto mb-4 text-[#f15a24] shadow-[0_0_20px_rgba(241,90,36,0.3)]">
                    <i class="fas fa-shield-alt text-2xl"></i>
                </div>
                <span class="text-[10px] font-mono text-[#f15a24] uppercase tracking-[0.2em] block mb-1">
                    SENTEC EXECUTIVE PORTAL
                </span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-white">
                    Admin Clearance
                </h2>
                <p class="text-xs text-neutral-400 mt-1 font-mono">
                    Restricted Area. Authorized Personnel Only.
                </p>
            </div>

            <!-- Error Notification Display -->
            <?php if ($error): ?>
                <div class="p-3.5 mb-6 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-xs font-mono flex items-center gap-2.5">
                    <i class="fas fa-exclamation-triangle text-red-400 text-sm"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[11px] font-mono text-neutral-400 uppercase tracking-wider mb-2">
                        ADMINISTRATOR USERNAME
                    </label>
                    <input 
                        type="text" 
                        name="username" 
                        required 
                        placeholder="Enter username" 
                        class="w-full px-4 py-3 rounded-lg bg-[#080b0d] border border-white/[0.12] text-white text-sm focus:outline-none focus:border-[#f15a24] focus:ring-1 focus:ring-[#f15a24] transition-all font-sans placeholder:text-neutral-600"
                        autofocus
                    >
                </div>

                <div>
                    <label class="block text-[11px] font-mono text-neutral-400 uppercase tracking-wider mb-2">
                        SECURITY PASSPHRASE
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="••••••••" 
                        class="w-full px-4 py-3 rounded-lg bg-[#080b0d] border border-white/[0.12] text-white text-sm focus:outline-none focus:border-[#f15a24] focus:ring-1 focus:ring-[#f15a24] transition-all font-sans placeholder:text-neutral-600"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-6 rounded-lg bg-[#f15a24] hover:bg-[#ff6b35] text-[#080b0d] font-mono font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all shadow-[0_4px_25px_rgba(241,90,36,0.35)] hover:-translate-y-0.5 mt-4"
                >
                    <span>Authorize Login</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/[0.08] text-center">
                <a href="../index" class="text-xs font-mono text-neutral-400 hover:text-[#f15a24] inline-flex items-center gap-2 transition-colors">
                    <i class="fas fa-arrow-left text-[10px]"></i>
                    <span>Back to Live Website</span>
                </a>
            </div>

        </div>

    </div>

</body>
</html>