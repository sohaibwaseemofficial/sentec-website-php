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

// Check what type of user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isAmbassador = isset($_SESSION['ambassador_id']);
$userName = '';
$userType = '';

if ($isLoggedIn) {
    $userName = $_SESSION['user_name'] ?? 'User';
    $userType = 'user';
} elseif ($isAmbassador) {
    $userName = $_SESSION['ambassador_name'] ?? 'Ambassador';
    $userType = 'ambassador';
}

// Get first name for display
$firstName = explode(' ', trim($userName))[0];
?>

<!doctype html>
<html lang="en">
<head>
    <title>SENTEC | Official Website</title>
    <link rel="icon" href="/images/favicon2.png" type="image/png">
    <link rel="shortcut icon" href="/images/favicon2.png" type="image/png">
    <link rel="apple-touch-icon" href="/images/favicon2.png">
    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style.css">
    
        <style>
        /* Navigation alignment fixes */
        .nav-links {
            display: flex !important;
            align-items: center !important;
            gap: 20px !important;
            list-style: none !important;
            margin-bottom: 0 !important;
        }
        
        .nav-links li {
            display: flex !important;
            align-items: center !important;
        }
        
        .nav-links a {
            display: flex !important;
            align-items: center !important;
            text-decoration: none !important;
            color: #ddd !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            transition: 0.3s !important;
            position: relative !important;
            line-height: 1 !important;
        }
        
        .nav-links > li > a::after {
            content: '' !important;
            position: absolute !important;
            width: 0% !important;
            height: 2px !important;
            bottom: -5px !important;
            left: 0 !important;
            background-color: var(--accent) !important;
            transition: 0.3s !important;
        }
        
        .nav-links > li > a:hover::after {
            width: 100% !important;
        }
        
        /* Dashboard User Button Style - ALIGNED PROPERLY */
        .nav-user-btn {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            background: rgba(0, 255, 148, 0.12) !important;
            border: 1px solid rgba(0, 255, 148, 0.4) !important;
            color: #fff !important;
            padding: 6px 14px !important;
            border-radius: 999px !important;
            font-weight: 600 !important;
            transition: 0.3s ease !important;
            line-height: 1.4 !important;
            height: auto !important;
            margin: 0 !important;
        }
        
        .nav-user-btn:hover {
            background: rgba(0, 255, 148, 0.2) !important;
            border-color: var(--accent) !important;
            box-shadow: 0 0 15px rgba(0, 255, 148, 0.3) !important;
        }
        
        .nav-user-btn i {
            color: var(--accent) !important;
            font-size: 0.9rem !important;
        }
        
        .nav-user-btn .user-name {
            color: var(--accent) !important;
            font-weight: 700 !important;
        }
        
        .nav-user-btn.ambassador i {
            color: #FFD700 !important;
        }
        
        .nav-user-btn.ambassador .user-name {
            color: #FFD700 !important;
        }
        
        .nav-user-btn.ambassador {
            border-color: rgba(255, 215, 0, 0.4) !important;
        }
        
        .nav-user-btn.ambassador:hover {
            border-color: #FFD700 !important;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3) !important;
        }
        
        /* Login Button - Match alignment */
        .nav-btn {
            padding: 6px 16px !important;
            border: 1px solid var(--accent) !important;
            border-radius: 999px !important;
            background: transparent !important;
            color: var(--accent) !important;
            font-weight: 600 !important;
            transition: 0.3s ease !important;
            line-height: 1.4 !important;
        }
        
        .nav-btn:hover {
            background: rgba(0, 255, 148, 0.1) !important;
            box-shadow: 0 0 15px rgba(0, 255, 148, 0.3) !important;
        }
        
        .nav-btn::after {
            display: none !important;
        }
        
        .nav-user-btn::after {
            display: none !important;
        }
        
        /* Logout Button - Aligned */
        .nav-logout-btn {
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            color: #ff6b6b !important;
            font-weight: 600 !important;
            padding: 6px 8px !important;
            transition: 0.3s ease !important;
            line-height: 1.4 !important;
        }
        
        .nav-logout-btn:hover {
            color: #ff4444 !important;
            text-shadow: 0 0 10px rgba(255, 68, 68, 0.5) !important;
        }
        
        .nav-logout-btn::after {
            display: none !important;
        }
        
        .nav-logout-btn i {
            font-size: 0.9rem !important;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .nav-links {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
                padding: 20px !important;
            }
            
            .nav-links li {
                width: 100% !important;
            }
            
            .nav-links a {
                padding: 8px 0 !important;
                width: 100% !important;
            }
            
            .nav-user-btn {
                justify-content: flex-start !important;
                width: fit-content !important;
            }
            
            .nav-logout-btn {
                justify-content: flex-start !important;
                width: fit-content !important;
            }
            
            .nav-btn {
                display: inline-block !important;
                width: fit-content !important;
            }
        }
    </style>
</head>

<body>
    <nav>
        <a href="index" class="nav-logo">
            SENTEC<span>.</span>
        </a>

        <div class="menu-toggle" id="mobile-menu">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </div>

        <ul class="nav-links">
            <li><a href="index">Home</a></li>
            <li><a href="index#about">About</a></li>
            <li><a href="team">Team</a></li>
            <li><a href="index#events">Events</a></li>
            <li><a href="OurPartners">Partners</a></li>
            <li><a href="gallery">Gallery</a></li>
            <li><a href="contact">Contact Us</a></li>

            <?php if ($isLoggedIn): ?>
                <!-- Regular User Logged In -->
                <li>
                    <a href="dashboard" class="nav-user-btn">
                        <i class="fas fa-user-astronaut"></i>
                        <span>Dashboard</span>
                        <span class="user-name"><?php echo htmlspecialchars($firstName); ?></span>
                    </a>
                </li>
                <li>
                    <a href="logout" class="nav-logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            <?php elseif ($isAmbassador): ?>
                <!-- Ambassador Logged In -->
                <li>
                    <a href="ambassador_dashboard" class="nav-user-btn ambassador">
                        <i class="fas fa-user-tie"></i>
                        <span>Ambassador</span>
                        <span class="user-name"><?php echo htmlspecialchars($firstName); ?></span>
                    </a>
                </li>
                <li>
                    <a href="ambassador_logout" class="nav-logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            <?php else: ?>
                <!-- Not Logged In -->
                <li><a href="login">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>