<?php
// 1. ERROR REPORTING
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Adjust path if needed
if (file_exists('../db_connection.php')) {
    include '../db_connection.php';
} else {
    include 'db_connection.php';
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!$conn) {
        $error = "Database connection missing.";
    } else {
        // SECURED: Use prepared statement
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
                // Modern password hash - correct!
                $password_valid = true;
            } elseif (md5($password) == $user['password']) {
                // Legacy MD5 - correct, but we should upgrade it
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
                $_SESSION['last_activity'] = time();

                // Update last_login
                $updateLogin = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateLogin->bind_param("i", $user['id']);
                $updateLogin->execute();
                $updateLogin->close();

                // Log the login
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, 'LOGIN', 'Successful login', ?)");
                $logStmt->bind_param("is", $user['id'], $ip);
                $logStmt->execute();
                $logStmt->close();

                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="favicon2.png" type="image/png">
    <link rel="shortcut icon" href="favicon2.png" type="image/png">
    <link rel="apple-touch-icon" href="favicon2.png"  type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | SENTEC</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg: #030303;
            --accent: #00FF94;
            --text-main: #FFFFFF;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(circle at 50% 0%, #111a2e 0%, #030303 60%);
            overflow: hidden;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border-radius: 24px;
            padding: 50px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            animation: floatUp 0.8s ease-out;
        }

        @keyframes floatUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .admin-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 10px rgba(0, 255, 148, 0.3));
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
        }

        p { color: #888; font-size: 0.9rem; margin-bottom: 30px; }

        .form-control {
            background-color: #0b1120 !important;
            border: 1px solid #333 !important;
            color: #fff !important;
            padding: 15px !important;
            border-radius: 12px !important;
            margin-bottom: 20px;
        }
        .form-control:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 15px rgba(0, 255, 148, 0.2) !important;
            outline: none !important;
        }
        .form-control::placeholder { color: #555; }

        .btn-neon {
            background: transparent;
            border: 2px solid var(--accent);
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px;
            border-radius: 12px;
            width: 100%;
            transition: 0.3s ease;
        }
        .btn-neon:hover {
            background: var(--accent);
            color: #000;
            box-shadow: 0 0 30px rgba(0, 255, 148, 0.4);
        }

        .alert-danger {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid #ff4444;
            color: #ff4444;
            font-size: 0.9rem;
        }

        .back-link {
            display: block;
            margin-top: 30px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>

    <div class="login-card">
        <img src="../SENTEC White Logo.png" alt="Logo" class="admin-logo">
        
        <h2>ADMIN ACCESS</h2>
        <p>Restricted Area. Authorized Personnel Only.</p>

        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger py-2">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="text-start">
                <label class="form-label small text-muted ps-2">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter ID" required autofocus>
            </div>

            <div class="text-start">
                <label class="form-label small text-muted ps-2">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter Password" required>
            </div>

            <button type="submit" class="btn-neon mt-3">
                Secure Login <i class="fas fa-lock ms-2"></i>
            </button>
        </form>

        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left me-1"></i> Back to Website
        </a>
    </div>

</body>
</html>