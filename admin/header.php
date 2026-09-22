<?php
// Session settings must be applied before session_start().
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 1800);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../env_loader.php';
$timeout_duration = 1800; // 30 minutes

if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
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

// Include shared logger
require_once __DIR__ . '/admin_logger.php';

// Make database connection available for auto-logger
include_once __DIR__ . '/../db_connection.php';

// ============================================================
// Auto-log all admin POST requests (global action capture)
// ============================================================
if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') && isset($_SESSION['admin_id'])) {
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

// Navigation structure
$navItems = [
    ['label' => 'Dashboard', 'href' => 'index.php', 'icon' => 'fas fa-th-large'],
    ['label' => 'Registrations', 'href' => 'manage_registrations.php', 'icon' => 'fas fa-users'],
    ['label' => 'Social Passes', 'href' => 'manage_social.php', 'icon' => 'fas fa-ticket-alt'],
    ['label' => 'Events Manager', 'href' => 'add_event.php', 'icon' => 'fas fa-calendar-alt'],
    ['label' => 'Team', 'href' => 'manage_team.php', 'icon' => 'fas fa-user-friends'],
    ['label' => 'Gallery', 'href' => 'manage_gallery.php', 'icon' => 'fas fa-images'],
    ['label' => 'Partners', 'href' => 'admin_partners.php', 'icon' => 'fas fa-handshake'],
    ['label' => 'Ambassadors', 'href' => 'manage_ambassadors.php', 'icon' => 'fas fa-award'],
    ['label' => 'Elections', 'href' => 'manage_elections.php', 'icon' => 'fas fa-vote-yea'],
    ['label' => 'Email Broadcast', 'href' => 'email_center.php', 'icon' => 'fas fa-paper-plane'],
    ['label' => 'Contact Inquiries', 'href' => 'manage_contacts.php', 'icon' => 'fas fa-envelope-open-text'],
    ['label' => 'Logs', 'href' => 'view_logs.php', 'icon' => 'fas fa-file-alt'],
];

if (($_SESSION['admin_role'] ?? '') === 'super_admin') {
    $navItems[] = ['label' => 'Staff & Access', 'href' => 'manage_admins.php', 'icon' => 'fas fa-shield-alt'];
}

$tz = getenv('APP_TIMEZONE') ?: 'Asia/Karachi';
$adminUser = $_SESSION['admin_full_name'] ?? ($_SESSION['admin'] ?? 'Administrator');
$adminRole = $_SESSION['admin_role'] ?? 'moderator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENTEC Admin Portal</title>
    
    <link rel="icon" href="favicon2.png" type="image/png">
    <link rel="shortcut icon" href="favicon2.png" type="image/png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS for legacy admin form components -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Admin Custom Modern Dark Stylesheet -->
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body class="bg-[#080b0d] text-[#f4f1eb]">

<div class="admin-overlay" id="adminOverlay"></div>

<!-- Sidebar inspired by AdminLayout.tsx -->
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="index" class="logo-lockup">
            <span class="logo-text">SENTEC</span>
            <span class="logo-badge">ADMIN</span>
        </a>
        <button type="button" class="btn btn-sm btn-link text-muted d-md-none p-0" id="closeSidebarBtn">
            <i class="fas fa-times text-white"></i>
        </button>
    </div>
    
    <nav class="sidebar-menu">
        <?php foreach ($navItems as $item): 
            $isActive = ($currentPage === $item['href']);
        ?>
            <a href="<?php echo $item['href']; ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>

        <a href="admin_logout" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <span style="font-size: 9px; color: var(--text-muted); font-family: var(--font-mono); letter-spacing: 0.1em; display: block;">LOGGED IN AS</span>
                <span style="font-size: 13px; font-weight: 600; color: #fff;"><?php echo htmlspecialchars($adminUser); ?></span>
            </div>
            <?php if ($adminRole === 'super_admin'): ?>
                <span class="badge bg-warning text-dark font-mono text-[10px]">Super</span>
            <?php endif; ?>
        </div>
        <a href="../index" target="_blank" class="d-flex align-items-center justify-content-center gap-1 text-decoration-none py-1.5 px-2 rounded" style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); border: 1px solid var(--line); background: #080b0d;">
            <span>View Live Website</span>
            <i class="fas fa-external-link-alt" style="font-size: 9px;"></i>
        </a>
    </div>
</aside>

<!-- Main Content Area -->
<div class="main-content">
    
    <!-- Modern Topbar -->
    <header class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-outline-secondary d-md-none text-white border-0 p-0" style="font-size: 18px;">
                <i class="fas fa-bars"></i>
            </button>
            <div class="d-none d-sm-flex align-items-center gap-2" style="font-size: 12px; font-family: var(--font-mono); color: var(--text-muted);">
                <i class="far fa-clock text-warning"></i>
                <span id="adminHeaderClock">--:--:--</span>
                <span class="text-muted">(<?php echo htmlspecialchars($tz); ?>)</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="d-none d-sm-inline-block px-3 py-1 rounded-pill" style="font-size: 11px; font-family: var(--font-mono); color: var(--orange); background: rgba(241, 90, 36, 0.1); border: 1px solid rgba(241, 90, 36, 0.3);">
                SYSTEM ACTIVE // 2026
            </span>
            <div style="font-size: 13px; color: var(--text-muted);">
                <strong style="color: #fff;"><?php echo htmlspecialchars($adminUser); ?></strong>
            </div>
        </div>
    </header>

    <script>
        // Live Clock Script
        (function(){
            const TZ = <?php echo json_encode($tz); ?>;
            function updateClock() {
                try {
                    const el = document.getElementById('adminHeaderClock');
                    if (!el) return;
                    const now = new Date();
                    const str = now.toLocaleTimeString('en-US', { timeZone: TZ, hour12: true });
                    el.textContent = str;
                } catch(e) {}
            }
            setInterval(updateClock, 1000);
            updateClock();
        })();

        // Mobile Sidebar Toggle
        const sidebar = document.getElementById('adminSidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('closeSidebarBtn');
        const overlay = document.getElementById('adminOverlay');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('show');
            });
        }
        if (closeBtn && sidebar) {
            closeBtn.addEventListener('click', function() {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('show');
            });
        }
        if (overlay && sidebar) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            });
        }
    </script>