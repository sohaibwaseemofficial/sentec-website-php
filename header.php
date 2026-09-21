<?php
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings - MUST be set BEFORE session_start()
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

// Session state detection
$isUserLoggedIn = isset($_SESSION['user']) || isset($_SESSION['user_id']);
$isAmbassadorLoggedIn = isset($_SESSION['ambassador_id']);

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
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SENTEC | NED University Official Society</title>
    
    <script>
        (function() {
            var theme = localStorage.getItem('theme') || 'dark';
            if (theme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    
    <link rel="icon" href="images/favicon2.png" type="image/png">
    <link rel="shortcut icon" href="images/favicon2.png" type="image/png">
    <link rel="apple-touch-icon" href="images/favicon2.png">

    <!-- Google Fonts: Space Grotesk, IBM Plex Mono, Rajdhani, Inter, Syne, JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Syne:wght@700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
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
                            'orange-hover': '#ff8050',
                            dark: '#080b0d',
                            surface: '#101518',
                            card: '#12161D',
                            muted: '#8e96a0',
                            line: 'rgba(235, 241, 237, 0.12)',
                            'line-strong': 'rgba(255, 255, 255, 0.16)'
                        }
                    },
                    fontFamily: {
                        sans: ['Space Grotesk', 'Inter', 'system-ui', 'sans-serif'],
                        display: ['Space Grotesk', 'Rajdhani', 'sans-serif'],
                        mono: ['IBM Plex Mono', 'JetBrains Mono', 'monospace'],
                        body: ['Space Grotesk', 'Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --orange: #f15a24;
            --orange-light: #ff8050;
            --dark: #080b0d;
            --surface: #101518;
            --line: rgba(235, 241, 237, 0.12);
            --accent: #f15a24;
        }

        .nav-cta {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 8px 18px !important;
            background: transparent !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            color: var(--paper) !important;
            font-family: 'IBM Plex Mono', monospace !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 0.14em !important;
            text-transform: uppercase !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            transition: all 0.2s ease !important;
            border-radius: 0 !important;
            line-height: 1 !important;
        }
        .nav-cta:hover {
            border-color: var(--orange) !important;
            color: var(--orange) !important;
        }
        .nav-cta-logout {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 8px 10px !important;
            background: transparent !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: var(--muted) !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            border-radius: 0 !important;
        }
        .nav-cta-logout:hover {
            border-color: #f87171 !important;
            color: #f87171 !important;
        }

        body {
            background-color: #080b0d;
            color: #f4f1eb;
            font-family: 'Space Grotesk', system-ui, sans-serif;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
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

        /* Canonical Ambient Blueprint Grid matching Home.tsx & Team.tsx */
        .ambient-grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            opacity: 0.35;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(to bottom, black 0%, black 75%, transparent 92%);
            -webkit-mask-image: linear-gradient(to bottom, black 0%, black 75%, transparent 92%);
        }

        /* Secondary Hero & Page Architecture matching SiteChrome.tsx */
        .secondary-hero {
            padding: 100px clamp(22px, 7vw, 100px) 70px;
            border-bottom: 1px solid var(--line);
            position: relative;
            background: var(--dark);
        }
        .secondary-hero::after {
            content: "";
            position: absolute;
            pointer-events: none;
            opacity: 0.78;
            right: clamp(22px, 7vw, 100px);
            bottom: 26px;
            width: 138px;
            height: 30px;
            border-top: 1px solid var(--orange);
            border-right: 1px solid var(--orange);
            background: repeating-linear-gradient(
                90deg,
                transparent 0 11px,
                var(--orange) 12px 13px,
                transparent 14px 20px
            );
        }
        .secondary-hero-grid {
            width: min(1320px, 100%);
            margin: auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 220px;
            gap: 70px;
            align-items: end;
        }
        @media (max-width: 900px) {
            .secondary-hero-grid {
                grid-template-columns: 1fr;
                gap: 36px;
            }
        }
        @media (max-width: 640px) {
            .secondary-hero::after {
                display: none;
            }
        }
        .eyebrow, .section-kicker {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--orange);
            font: 500 10px "IBM Plex Mono", monospace;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .eyebrow i {
            width: 35px;
            height: 1px;
            display: block;
            background: var(--orange);
        }
        .secondary-hero h1 {
            max-width: 760px;
            margin: 22px 0 18px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(3.4rem, 8.5vw, 8rem);
            line-height: 0.86;
            letter-spacing: -0.085em;
            font-weight: 500;
            color: #f4f1eb;
        }
        .secondary-hero h1 em {
            color: var(--orange);
            font-style: normal;
            display: block;
        }
        .secondary-hero p {
            max-width: 560px;
            color: #9aa3a3;
            font-size: 16px;
            line-height: 1.7;
            margin: 0;
        }
        .secondary-hero-index {
            align-self: stretch;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 8px 0 8px 20px;
            min-height: 155px;
            border-left: 1px solid var(--orange);
            color: #9aa3a3;
            font: 9px "IBM Plex Mono", monospace;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .secondary-hero-index span { color: var(--orange); }
        .secondary-hero-index strong { color: #f4f1eb; font-weight: 400; }
        .secondary-hero-index small { color: #5d696c; font-size: 9px; line-height: 1.55; }

        /* Secondary Section & Corner Bracket */
        .secondary-section {
            width: min(1320px, 100%);
            margin: auto;
            padding: 80px clamp(22px, 7vw, 100px) 120px;
            position: relative;
        }
        .secondary-section::before {
            content: "";
            position: absolute;
            pointer-events: none;
            opacity: 0.78;
            left: max(22px, calc((100vw - 1320px) / 2 + 7vw));
            top: 34px;
            width: 42px;
            height: 42px;
            border-left: 1px solid var(--orange);
            border-bottom: 1px solid var(--orange);
        }

        /* Signal Form Design System (index.css lines 2085-2170) */
        .signal-form {
            padding: clamp(24px, 5vw, 40px);
            border: 1px solid var(--line);
            background: rgba(15, 20, 22, 0.75);
            box-shadow: 0 18px 80px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 2;
            backdrop-filter: blur(12px);
        }
        .signal-form label {
            display: block;
            color: #9aa3a3;
            font: 600 10px "IBM Plex Mono", monospace;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .signal-form input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
        .signal-form textarea,
        .signal-form select {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 12px 0;
            border: 0;
            border-bottom: 1px solid var(--line);
            outline: 0;
            background: transparent;
            color: #f4f1eb;
            font: 15px "Space Grotesk", sans-serif;
            transition: border-color 0.2s ease;
            border-radius: 0;
        }
        .signal-form input:focus,
        .signal-form textarea:focus,
        .signal-form select:focus {
            border-color: var(--orange);
        }
        .signal-form select option {
            background: #101518;
            color: #f4f1eb;
        }
        .signal-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            background: var(--orange);
            color: #080b0d;
            padding: 13px 22px;
            font: 700 11px "IBM Plex Mono", monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
            text-decoration: none;
        }
        .signal-btn:hover {
            background: var(--orange-light);
            color: #080b0d;
        }
        .signal-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--line);
            background: transparent;
            color: #f4f1eb;
            padding: 11px 18px;
            font: 600 11px "IBM Plex Mono", monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .signal-btn-outline:hover {
            border-color: var(--orange);
            color: var(--orange);
        }

        /* -------------------------------------------------------------
           LIGHT THEME ENGINE (Matches html:not(.dark) from index.css)
        ------------------------------------------------------------- */
        html:not(.dark) {
            --ink: #f8fafc;
            --ink-soft: #f1f5f9;
            --panel: #ffffff;
            --paper: #0f172a;
            --muted: #475569;
            --muted-dark: #64748b;
            --steel: #64748b;
            --orange: #ea580c;
            --orange-light: #f97316;
            --line: rgba(15, 23, 42, 0.12);
            --border: rgba(15, 23, 42, 0.12);
            color-scheme: light;
        }
        html:not(.dark) body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }
        html:not(.dark) .ambient-grid {
            opacity: 0.12;
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.08) 1px, transparent 1px);
        }
        html:not(.dark) #mainHeader {
            background: rgba(248, 250, 252, 0.94) !important;
            border-bottom-color: rgba(15, 23, 42, 0.1) !important;
        }
        html:not(.dark) #mainHeader nav a {
            color: #475569 !important;
        }
        html:not(.dark) #mainHeader nav a:hover {
            color: #ea580c !important;
        }
        html:not(.dark) .font-display {
            color: #0f172a !important;
        }
        html:not(.dark) .secondary-hero {
            background: #f1f5f9 !important;
            border-bottom-color: rgba(15, 23, 42, 0.1) !important;
        }
        html:not(.dark) .secondary-hero h1,
        html:not(.dark) .secondary-hero-title {
            color: #0f172a !important;
        }
        html:not(.dark) .secondary-hero p,
        html:not(.dark) .secondary-hero-lead {
            color: #475569 !important;
        }
        html:not(.dark) .secondary-hero-index strong {
            color: #0f172a !important;
        }
        html:not(.dark) .signal-form {
            background: #ffffff !important;
            border-color: rgba(15, 23, 42, 0.12) !important;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.06) !important;
        }
        html:not(.dark) .signal-form h2 {
            color: #0f172a !important;
        }
        html:not(.dark) .signal-form input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
        html:not(.dark) .signal-form textarea,
        html:not(.dark) .signal-form select {
            color: #0f172a !important;
            border-bottom-color: rgba(15, 23, 42, 0.16) !important;
        }
        html:not(.dark) .signal-form label {
            color: #64748b !important;
        }
        html:not(.dark) .glass-box,
        html:not(.dark) .glass-panel {
            background: #ffffff !important;
            border-color: rgba(15, 23, 42, 0.12) !important;
            color: #0f172a !important;
        }
        html:not(.dark) .inst-btn,
        html:not(.dark) .candidate-card,
        html:not(.dark) .member-section,
        html:not(.dark) .payment-card,
        html:not(.dark) .team-card {
            background: #ffffff !important;
            border-color: rgba(15, 23, 42, 0.12) !important;
            color: #0f172a !important;
        }
        html:not(.dark) .inst-btn h5,
        html:not(.dark) .payment-header h4,
        html:not(.dark) .team-card h3 {
            color: #0f172a !important;
        }
        html:not(.dark) .theme-toggle {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: rgba(15, 23, 42, 0.15) !important;
        }
        html:not(.dark) .nav-cta {
            border-color: rgba(15, 23, 42, 0.18) !important;
            color: #0f172a !important;
        }
        html:not(.dark) .site-footer {
            background: #f1f5f9 !important;
            border-top-color: rgba(15, 23, 42, 0.1) !important;
            color: #475569 !important;
        }
    </style>
</head>

<body class="bg-[#080b0d] text-[#f4f1eb] min-h-screen flex flex-col selection:bg-[#f15a24] selection:text-white relative">
    <!-- Canonical Ambient Blueprint Grid (Inherited Globally) -->
    <div class="ambient-grid" aria-hidden="true"></div>

    <!-- Top Nav Chrome matching SiteChrome.tsx -->
    <header class="sticky top-0 z-50 w-full bg-[#080b0d]/90 backdrop-blur-md border-b border-white/[0.08] transition-all duration-200" id="mainHeader">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Brand Lockup matching SiteChrome.tsx -->
            <a href="index" class="flex items-center gap-3 group text-decoration-none">
                <div class="w-10 h-10 rounded-full bg-transparent border border-white/[0.12] flex items-center justify-center p-1 transition-transform group-hover:scale-105 overflow-hidden">
                    <img id="brandHeaderLogo" src="images/SENTECNEWWHITELOGO.webp" alt="SENTEC" class="w-full h-full object-contain">
                </div>
                <div class="flex items-baseline">
                    <span class="font-display font-extrabold text-xl sm:text-2xl tracking-tight text-white group-hover:text-white/90 transition-colors">SENTEC</span>
                    <span class="text-[#f15a24] font-black text-2xl leading-none">.</span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center gap-7 text-xs font-mono tracking-wider text-neutral-300 bg-transparent">
                <a href="index" class="hover:text-[#f15a24] transition-colors py-1">HOME</a>
                <a href="index#about" class="hover:text-[#f15a24] transition-colors py-1">ABOUT</a>
                <a href="team" class="hover:text-[#f15a24] transition-colors py-1">TEAM</a>
                <a href="index#events" class="hover:text-[#f15a24] transition-colors py-1">EVENTS</a>
                <a href="OurPartners" class="hover:text-[#f15a24] transition-colors py-1">PARTNERS</a>
                <a href="gallery" class="hover:text-[#f15a24] transition-colors py-1">GALLERY</a>
                <a href="contact" class="hover:text-[#f15a24] transition-colors py-1">CONTACT US</a>
            </nav>

            <!-- Action Area / Session State Control -->
            <div class="hidden sm:flex items-center gap-2.5">
                <!-- Theme Toggle Icon matching SiteChrome.tsx -->
                <button id="globalThemeToggle" type="button" aria-label="Toggle visual theme" class="w-9 h-9 flex items-center justify-center rounded-full border border-white/[0.12] bg-[#101518]/60 hover:border-[#f15a24] text-neutral-300 hover:text-white transition-all">
                    <!-- Sun Icon -->
                    <svg id="themeIconSun" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
                    </svg>
                    <!-- Moon Icon -->
                    <svg id="themeIconMoon" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                    </svg>
                </button>

                <script>
                    function syncThemeIcons() {
                        var isDark = document.documentElement.classList.contains('dark');
                        var sun = document.getElementById('themeIconSun');
                        var moon = document.getElementById('themeIconMoon');
                        var logo = document.getElementById('brandHeaderLogo');
                        if (sun && moon) {
                            if (isDark) {
                                sun.classList.remove('hidden');
                                moon.classList.add('hidden');
                                if (logo) logo.src = 'images/SENTECNEWWHITELOGO.webp';
                            } else {
                                sun.classList.add('hidden');
                                moon.classList.remove('hidden');
                                if (logo) logo.src = 'images/SENTECNEWLOGO.webp';
                            }
                        }
                    }
                    syncThemeIcons();
                    var toggleBtn = document.getElementById('globalThemeToggle');
                    if (toggleBtn) {
                        toggleBtn.addEventListener('click', function() {
                            var isDark = document.documentElement.classList.contains('dark');
                            if (isDark) {
                                document.documentElement.classList.remove('dark');
                                localStorage.setItem('theme', 'light');
                            } else {
                                document.documentElement.classList.add('dark');
                                localStorage.setItem('theme', 'dark');
                            }
                            syncThemeIcons();
                        });
                    }
                </script>

                <?php if ($isUserLoggedIn || $isAmbassadorLoggedIn): ?>
                    <!-- DASHBOARD CTA button when logged in -->
                    <a href="<?php echo $dashboardUrl; ?>" class="nav-cta" title="Go to Dashboard">
                        <span>DASHBOARD</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                    <!-- Logout Button -->
                    <a href="<?php echo $logoutUrl; ?>" class="nav-cta-logout" title="Logout">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" x2="9" y1="12" y2="12"></line>
                        </svg>
                    </a>
                <?php else: ?>
                    <!-- STRICT VISITOR STATE: Render exact Login button matching SiteChrome.tsx -->
                    <a href="login" class="nav-cta">
                        <span>LOGIN</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle Button -->
            <div class="flex items-center md:hidden gap-2">
                <button id="mobileMenuBtn" type="button" aria-label="Toggle navigation" class="w-10 h-10 flex items-center justify-center text-neutral-300 hover:text-white bg-[#101518] border border-white/[0.1] rounded-md focus:outline-none">
                    <i class="fas fa-bars text-base" id="mobileMenuIcon"></i>
                </button>
            </div>

        </div>

        <!-- Mobile Dropdown Drawer -->
        <div id="mobileMenuDropdown" class="hidden md:hidden bg-[#0d1215] border-b border-white/[0.08] px-5 pt-3 pb-6 space-y-3 font-mono text-xs tracking-wider">
            <div class="flex flex-col space-y-2.5 pt-2">
                <a href="index" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">HOME</a>
                <a href="index#about" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">ABOUT</a>
                <a href="team" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">TEAM</a>
                <a href="index#events" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">EVENTS</a>
                <a href="OurPartners" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">PARTNERS</a>
                <a href="gallery" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">GALLERY</a>
                <a href="contact" class="text-neutral-300 hover:text-[#f15a24] py-1.5 border-b border-white/[0.04]">CONTACT US</a>
            </div>

            <div class="pt-3 flex flex-col gap-2">
                <?php if ($isUserLoggedIn || $isAmbassadorLoggedIn): ?>
                    <a href="<?php echo $dashboardUrl; ?>" class="nav-cta" style="width:100%; justify-content:center; padding:10px 14px;">
                        <span>DASHBOARD</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                    <a href="<?php echo $logoutUrl; ?>" class="flex items-center justify-center gap-2 w-full py-2 text-xs font-mono text-red-400 hover:underline">
                        <i class="fas fa-sign-out-alt"></i> LOGOUT
                    </a>
                <?php else: ?>
                    <a href="login" class="nav-cta" style="width:100%; justify-content:center; padding:10px 14px;">
                        <span>LOGIN</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Page Content Container Starts Here -->
    <div class="flex-grow">