<?php
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings - MUST be set BEFORE session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

// Session state detection
$isUserLoggedIn = isset($_SESSION['user']) || isset($_SESSION['user_id']);
$isAmbassadorLoggedIn = isset($_SESSION['ambassador_id']);
$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']) || 
                    (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') || 
                    (isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['admin', 'super_admin', 'moderator']));

$displayName = '';
if (!empty($_SESSION['user']['name'])) {
    $displayName = $_SESSION['user']['name'];
} elseif (!empty($_SESSION['user_name'])) {
    $displayName = $_SESSION['user_name'];
} elseif (!empty($_SESSION['ambassador_name'])) {
    $displayName = $_SESSION['ambassador_name'];
}
$firstName = $displayName ? htmlspecialchars(explode(' ', trim($displayName))[0]) : 'User';

// Determine dashboard and logout links
$dashboardUrl = $isAmbassadorLoggedIn && !$isUserLoggedIn ? 'ambassador_dashboard.php' : 'dashboard.php';
$logoutUrl = $isAmbassadorLoggedIn && !$isUserLoggedIn ? 'ambassador_logout.php' : 'logout.php';
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SENTEC | NED University Official Society</title>
    
    <link rel="icon" href="images/favicon2.png" type="image/png">
    <link rel="shortcut icon" href="images/favicon2.png" type="image/png">
    <link rel="apple-touch-icon" href="images/favicon2.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
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
                            'orange-hover': '#ff6b35',
                            dark: '#080b0d',
                            surface: '#101518',
                            card: '#12161D',
                            muted: '#8e96a0',
                            line: 'rgba(255, 255, 255, 0.08)',
                            'line-strong': 'rgba(255, 255, 255, 0.16)'
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
    
    <style>
        :root {
            --orange: #f15a24;
            --dark: #080b0d;
            --surface: #101518;
            --line: rgba(255, 255, 255, 0.08);
            --accent: #f15a24;
        }

        body {
            background-color: #080b0d;
            color: #f4f1eb;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }

        /* Radar & Emblem Slow Rotations */
        @keyframes spinSlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes spinReverseSlow {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        .animate-spin-slow {
            animation: spinSlow 45s linear infinite;
            transform-origin: center;
        }
        .animate-spin-reverse-slow {
            animation: spinReverseSlow 35s linear infinite;
            transform-origin: center;
        }

        /* Ambient scanline and technical grid */
        .ambient-grid {
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* Glass panel utility for legacy compatibility */
        .glass-panel {
            background: rgba(16, 21, 24, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
        }

        /* Legacy Button Fallback */
        .btn-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            background: #f15a24;
            color: #080b0d !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
        }
        .btn-clear:hover {
            background: #ff6b35;
            color: #080b0d !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(241, 90, 36, 0.35);
        }
    </style>
</head>

<body class="bg-[#080b0d] text-[#f4f1eb] min-h-screen flex flex-col selection:bg-[#f15a24] selection:text-white">
    <!-- Top Nav Chrome matching SiteChrome.tsx -->
    <header class="sticky top-0 z-50 w-full bg-[#080b0d]/90 backdrop-blur-md border-b border-white/[0.08] transition-all duration-200" id="mainHeader">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Brand Lockup -->
            <a href="index.php" class="flex items-center gap-3 group text-decoration-none">
                <div class="w-10 h-10 rounded-lg bg-[#101518] border border-white/[0.1] flex items-center justify-center p-1.5 transition-transform group-hover:scale-105">
                    <img src="SENTEC White Logo.png" alt="SENTEC" class="w-full h-full object-contain" onerror="this.src='images/favicon2.png'">
                </div>
                <div class="flex items-baseline">
                    <span class="font-display font-extrabold text-xl sm:text-2xl tracking-tight text-white group-hover:text-white/90 transition-colors">SENTEC</span>
                    <span class="text-[#f15a24] font-black text-2xl leading-none">.</span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center gap-7 text-xs font-mono tracking-wider text-neutral-300">
                <a href="index.php" class="hover:text-[#f15a24] transition-colors py-1">HOME</a>
                <a href="index.php#about" class="hover:text-[#f15a24] transition-colors py-1">ABOUT</a>
                <a href="team.php" class="hover:text-[#f15a24] transition-colors py-1">TEAM</a>
                <a href="index.php#events" class="hover:text-[#f15a24] transition-colors py-1">EVENTS</a>
                <a href="OurPartners.php" class="hover:text-[#f15a24] transition-colors py-1">PARTNERS</a>
                <a href="gallery.php" class="hover:text-[#f15a24] transition-colors py-1">GALLERY</a>
                <a href="contact.php" class="hover:text-[#f15a24] transition-colors py-1">CONTACT US</a>
            </nav>

            <!-- Action Area / Session State Control -->
            <div class="hidden sm:flex items-center gap-3">
                <?php if ($isAdminLoggedIn): ?>
                    <!-- Admin Panel Button -->
                    <a href="admin/index.php" class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-mono font-bold tracking-wider text-[#f15a24] bg-[#f15a24]/10 border border-[#f15a24]/40 hover:bg-[#f15a24]/20 hover:border-[#f15a24] rounded-md transition-all">
                        <i class="fas fa-shield-alt text-xs"></i>
                        <span>ADMIN PANEL</span>
                    </a>
                <?php endif; ?>

                <?php if ($isUserLoggedIn || $isAmbassadorLoggedIn): ?>
                    <!-- Dashboard Link -->
                    <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center gap-2 px-4 py-1.5 text-xs font-mono font-bold tracking-wider text-[#080b0d] bg-[#f15a24] hover:bg-[#ff6b35] rounded-md transition-all shadow-[0_0_15px_rgba(241,90,36,0.25)]">
                        <i class="fas fa-user-astronaut text-xs"></i>
                        <span>DASHBOARD</span>
                        <span class="opacity-80 font-sans font-medium text-[11px]">(<?php echo $firstName; ?>)</span>
                    </a>
                    <!-- Logout Link -->
                    <a href="<?php echo $logoutUrl; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono text-neutral-400 hover:text-red-400 transition-colors" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>LOGOUT</span>
                    </a>
                <?php else: ?>
                    <!-- STRICT VISITOR STATE: Render ONLY Login button. No ghost buttons -->
                    <a href="login.php" class="inline-flex items-center gap-2 px-5 py-2 text-xs font-mono font-bold tracking-wider text-white bg-[#101518] border border-white/[0.12] hover:border-[#f15a24] hover:text-[#f15a24] rounded-md transition-all">
                        <span>LOGIN</span>
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle Button -->
            <div class="flex items-center sm:hidden gap-2">
                <button id="mobileMenuBtn" type="button" aria-label="Toggle navigation" class="w-10 h-10 flex items-center justify-center text-neutral-300 hover:text-white bg-[#101518] border border-white/[0.1] rounded-md focus:outline-none">
                    <i class="fas fa-bars text-base" id="mobileMenuIcon"></i>
                </button>
            </div>

        </div>

        <!-- Mobile Dropdown Drawer -->
        <div id="mobileMenuDropdown" class="hidden sm:hidden bg-[#0d1215] border-b border-white/[0.08] px-5 pt-3 pb-6 space-y-3 font-mono text-xs tracking-wider">
            <div class="flex flex-col space-y-2.5 pt-2">
                <a href="index.php" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">HOME</a>
                <a href="index.php#about" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">ABOUT</a>
                <a href="team.php" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">TEAM</a>
                <a href="index.php#events" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">EVENTS</a>
                <a href="OurPartners.php" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">PARTNERS</a>
                <a href="gallery.php" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">GALLERY</a>
                <a href="contact.php" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">CONTACT US</a>
            </div>

            <div class="pt-3 flex flex-col gap-2">
                <?php if ($isAdminLoggedIn): ?>
                    <a href="admin/index.php" class="flex items-center justify-center gap-2 w-full py-2.5 text-xs font-mono font-bold text-[#f15a24] bg-[#f15a24]/10 border border-[#f15a24]/40 rounded-md">
                        <i class="fas fa-shield-alt"></i> ADMIN PANEL
                    </a>
                <?php endif; ?>

                <?php if ($isUserLoggedIn || $isAmbassadorLoggedIn): ?>
                    <a href="<?php echo $dashboardUrl; ?>" class="flex items-center justify-center gap-2 w-full py-2.5 text-xs font-mono font-bold text-[#080b0d] bg-[#f15a24] rounded-md">
                        <i class="fas fa-user-astronaut"></i> DASHBOARD (<?php echo $firstName; ?>)
                    </a>
                    <a href="<?php echo $logoutUrl; ?>" class="flex items-center justify-center gap-2 w-full py-2 text-xs font-mono text-red-400 hover:underline">
                        <i class="fas fa-sign-out-alt"></i> LOGOUT
                    </a>
                <?php else: ?>
                    <a href="login.php" class="flex items-center justify-center gap-2 w-full py-2.5 text-xs font-mono font-bold text-white bg-[#101518] border border-[#f15a24]/50 rounded-md">
                        <span>LOGIN</span> <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Page Content Container Starts Here -->
    <div class="flex-grow">