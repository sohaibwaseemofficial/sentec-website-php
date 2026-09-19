<?php
// Session security settings - MUST be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Set session lifetime to 30 minutes (1800 seconds)
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.cookie_lifetime', 1800);

session_start();

require_once __DIR__ . '/../env_loader.php';
$timeout_duration = 1800; // 30 minutes

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php");
    exit();
}
$_SESSION['last_activity'] = time();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentType = strtolower($_GET['type'] ?? '');

/**
 * Log admin action to database
 * @param string $action  e.g. 'DELETE_REGISTRATION', 'APPROVE_TEAM'
 * @param string $details Description of what was done
 */
function log_admin_action($action, $details = '') {
    global $conn;
    if (!isset($_SESSION['admin_id'])) return;
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $stmt->bind_param("isss", $_SESSION['admin_id'], $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// Make database connection available for auto‑logger
include_once '../db_connection.php';
// ============================================================
// Auto‑log all admin POST requests (global action capture)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['admin_id'])) {
    $page  = basename($_SERVER['PHP_SELF']);
    $params = [];
    foreach ($_POST as $key => $value) {
        // Redact sensitive fields
        if (in_array(strtolower($key), ['password', 'cpassword', 'pass', 'token', 'secret'], true)) {
            $params[] = "$key=***";
        } else {
            $val = is_array($value) ? json_encode($value) : (string)$value;
            $val = substr($val, 0, 150); // prevent huge logs
            $params[] = "$key=$val";
        }
    }
    $details = "POST to $page | " . implode(', ', $params);
    log_admin_action('FORM_SUBMIT', $details);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-text">SENTEC<span>.</span></div>
    </div>
    
    <div class="sidebar-menu">
        <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-chart-line"></i> <span>Dashboard</span>
        </a>
        <a href="manage_team.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_team.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-users"></i> <span>Team Members</span>
        </a>
        <a href="add_event.php" <?php echo basename($_SERVER['PHP_SELF']) == 'add_event.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-calendar-plus"></i> <span>Manage Events</span>
        </a>
        <a href="manage_registrations.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_registrations.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-list-alt"></i> <span>Registrations</span>
        </a>
        <a href="manage_gallery.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_gallery.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-images"></i> <span>Gallery</span>
        </a>
        <a href="admin_partners.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_partners.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-handshake"></i> <span>Partners</span>
        </a>
        <a href="manage_ambassadors.php?type=volunteer" <?php echo ($currentPage === 'manage_ambassadors.php' && $currentType !== 'brand') ? 'class="active"' : ''; ?>>
            <i class="fas fa-hands-helping"></i> <span>Volunteers</span>
        </a>
        <a href="manage_ambassadors.php?type=brand" <?php echo ($currentPage === 'manage_ambassadors.php' && $currentType === 'brand') ? 'class="active"' : ''; ?>>
            <i class="fas fa-user-tie"></i> <span>Brand Ambassadors</span>
        </a>
        <a href="manage_social.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_social.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-music me-2"></i> <span>Social Registrations</span>
        </a>
        <a href="manage_elections.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_elections.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-vote-yea me-2"></i> <span>Elections</span>
        </a>        
        <a href="email_center.php" <?php echo basename($_SERVER['PHP_SELF']) == 'email_center.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-envelope-open-text"></i> <span>Email</span>
        </a>
                <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                <a href="manage_admins.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_admins.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-user-cog"></i> <span>Manage Admins</span>
                </a>
                <a href="view_logs.php" <?php echo basename($_SERVER['PHP_SELF']) == 'view_logs.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-history"></i> <span>Activity Logs</span>
                </a>
                <?php endif; ?>
        <a href="admin_logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3" style="color:#cfd3dc; font-size:0.9rem;">
            <i class="far fa-clock me-1" style="color:#00e7ff;"></i>
            <?php $tz = getenv('APP_TIMEZONE') ?: 'Asia/Karachi'; ?>
            <span><strong id="adminHeaderClock"></strong> <small class="text-muted" style="font-size:0.8em;">(<?php echo htmlspecialchars($tz); ?>)</small></span>
        </div>
        <button id="sidebarToggle" class="btn btn-outline-light d-inline-flex d-md-none" style="border-color: var(--glass-border);">
            <i class="fas fa-bars"></i>
        </button>
        <div style="color: #aaa; font-size: 0.9rem;">
            Logged in as <strong style="color: #fff;"><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin']); ?></strong>
            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                <span class="badge bg-warning text-dark ms-1">Super Admin</span>
            <?php endif; ?>
        </div>
    </div>

    <script>
        (function(){
            const TZ = <?php echo json_encode($tz); ?>;
            function tick(){
                try{
                    const el = document.getElementById('adminHeaderClock');
                    if(!el) return;
                    const now = new Date();
                    const opts = { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true, timeZone: TZ };
                    el.textContent = new Intl.DateTimeFormat('en-GB', opts).format(now);
                }catch(e){}
            }
            tick(); setInterval(tick, 1000);
        })();
    </script>