<?php include 'header.php'; ?>

<style>
    /* ========================================================
       THE ENGINEER'S CODE - DEDICATED DARK UI/UX STYLES
       ======================================================== */
    :root {
        --accent: #f15a24;
        --accent-hover: #ff7a45;
        --bg-dark: #080b0d;
        --card-bg: #11161a;
        --card-border: rgba(255, 255, 255, 0.08);
        --font-mono: 'IBM Plex Mono', monospace;
    }

    /* 1. NAVBAR DARK MODE CONSISTENCY */
    #mainHeader {
        background-color: rgba(8, 11, 13, 0.94) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    nav, #mainHeader nav {
        background: transparent !important;
        background-color: transparent !important;
    }
    #mobileMenuDropdown {
        background-color: #080b0d !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    /* 2. PRIMARY BUTTON STYLE (Orange Glow) */
    .btn-neon {
        background: var(--accent) !important;
        border: 2px solid var(--accent) !important;
        color: #ffffff !important;
        font-weight: 700;
        font-family: var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.12em;
        border-radius: 50px;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 0 25px rgba(241, 90, 36, 0.35);
        cursor: pointer;
    }

    .btn-neon:hover {
        background: var(--accent-hover) !important;
        border-color: var(--accent-hover) !important;
        color: #ffffff !important;
        box-shadow: 0 0 35px rgba(241, 90, 36, 0.65);
        transform: translateY(-3px);
    }

    /* 3. EVENT HERO SECTION */
    .event-hero {
        min-height: 90vh;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 120px 20px 80px 20px;
        overflow: hidden;
    }

    .bg-slideshow {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 1;
        overflow: hidden;
    }

    .slide {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background-size: cover;
        background-position: center;
        opacity: 0;
        transition: opacity 1.8s ease-in-out, transform 8s ease-out;
        filter: brightness(0.35);
        transform: scale(1);
    }

    .slide.active {
        opacity: 1;
        transform: scale(1.06);
    }

    .overlay-gradient {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: radial-gradient(circle at center, rgba(8, 11, 13, 0.4) 0%, rgba(8, 11, 13, 0.95) 85%);
        z-index: 2;
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 3;
        max-width: 900px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 16px;
        border-radius: 9999px;
        background: rgba(241, 90, 36, 0.1);
        border: 1px solid rgba(241, 90, 36, 0.3);
        color: var(--accent);
        font-family: var(--font-mono);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        margin-bottom: 20px;
        box-shadow: 0 0 15px rgba(241, 90, 36, 0.15);
    }

    .hero-title {
        font-family: 'Space Grotesk', 'Rajdhani', sans-serif;
        font-size: clamp(2.5rem, 6.5vw, 5rem);
        font-weight: 900;
        line-height: 1.05;
        letter-spacing: -0.02em;
        color: #ffffff;
        text-transform: uppercase;
        margin-bottom: 18px;
        text-shadow: 0 4px 30px rgba(0, 0, 0, 0.8);
    }

    .text-accent-glow {
        color: var(--accent);
        text-shadow: 0 0 35px rgba(241, 90, 36, 0.6);
    }

    .hero-tagline {
        font-family: var(--font-mono);
        font-size: clamp(0.95rem, 2vw, 1.3rem);
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #f4f1eb;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 10px 24px;
        border-radius: 8px;
        margin-top: 4px;
        margin-bottom: 28px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
    }

    .hero-pills {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
        margin-bottom: 35px;
    }

    .pill {
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(16, 21, 24, 0.7);
        backdrop-filter: blur(12px);
        padding: 8px 18px;
        border-radius: 50px;
        font-family: var(--font-mono);
        font-size: 0.82rem;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .pill i {
        color: var(--accent);
        margin-right: 8px;
    }

    /* 4. SECTION WRAPPERS & VERTICAL RHYTHM */
    .section-wrapper {
        padding: 100px 20px;
        max-width: 1280px;
        margin: 0 auto;
        position: relative;
        z-index: 2;
    }

    .section-header {
        text-align: center;
        margin-bottom: 48px;
    }

    .section-header h2 {
        font-family: 'Space Grotesk', 'Rajdhani', sans-serif;
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 12px;
    }

    .section-subtitle {
        color: var(--accent);
        font-family: var(--font-mono);
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.06em;
    }

    /* 5. TABS */
    .tab-container {
        display: flex;
        justify-content: center;
        margin-bottom: 45px;
        gap: 12px;
    }

    .tab-btn {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #9aa3a3;
        padding: 12px 32px;
        border-radius: 50px;
        font-family: var(--font-mono);
        font-size: 0.9rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .tab-btn:hover {
        border-color: rgba(241, 90, 36, 0.4);
        color: #ffffff;
    }

    .tab-btn.active {
        background: var(--accent);
        color: #ffffff;
        border-color: var(--accent);
        box-shadow: 0 0 20px rgba(241, 90, 36, 0.4);
    }

    /* 6. MODULE CARDS GRID */
    .modules-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
    }

    @media (max-width: 1150px) {
        .modules-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .modules-grid {
            grid-template-columns: 1fr;
        }
    }

    .module-card {
        background: linear-gradient(180deg, rgba(20, 26, 31, 0.9) 0%, rgba(13, 17, 21, 0.95) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--card-border);
        border-radius: 18px;
        padding: 28px 24px;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }

    .module-card:hover {
        background: linear-gradient(180deg, rgba(26, 33, 40, 0.98) 0%, rgba(17, 22, 27, 0.98) 100%);
        border-color: rgba(241, 90, 36, 0.45);
        transform: translateY(-5px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45), 0 0 25px rgba(241, 90, 36, 0.14);
    }

    .module-badge {
        background: var(--accent);
        color: #ffffff;
        font-family: var(--font-mono);
        font-weight: 700;
        font-size: 0.68rem;
        letter-spacing: 0.1em;
        padding: 4px 10px;
        border-radius: 6px;
        text-transform: uppercase;
        margin-bottom: 16px;
        display: inline-block;
        width: fit-content;
        box-shadow: 0 0 10px rgba(241, 90, 36, 0.3);
    }

    .module-title {
        color: #ffffff;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.35;
        margin-top: 4px;
        margin-bottom: 12px;
    }

    .module-desc {
        color: #9aa3a3;
        font-size: 0.88rem;
        line-height: 1.6;
        margin-bottom: 0;
        flex-grow: 1;
    }

    .module-meta {
        margin-top: 18px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .module-meta-pill {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 8px 10px;
        font-family: var(--font-mono);
        color: #eef2f5;
        font-size: 0.68rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .module-register {
        margin-top: 20px;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        font-size: 0.72rem;
        line-height: 1.2;
        letter-spacing: 0.14em;
        border-radius: 50px;
        box-shadow: 0 0 25px rgba(241, 90, 36, 0.35);
    }

    /* 7. REGISTRATION STEPS */
    .steps-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
    }

    @media (max-width: 960px) {
        .steps-grid {
            grid-template-columns: 1fr;
        }
    }

    .step-card {
        background: linear-gradient(180deg, rgba(20, 26, 31, 0.9) 0%, rgba(13, 17, 21, 0.95) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--card-border);
        padding: 44px 32px;
        border-radius: 22px;
        text-align: center;
        position: relative;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .step-card:hover {
        border-color: rgba(241, 90, 36, 0.45);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45), 0 0 25px rgba(241, 90, 36, 0.14);
        transform: translateY(-5px);
    }

    .step-icon {
        width: 72px; height: 72px;
        background: rgba(241, 90, 36, 0.12);
        border: 1px solid rgba(241, 90, 36, 0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px auto;
        color: var(--accent);
        font-size: 1.8rem;
        transition: transform 0.3s ease;
    }

    .step-card:hover .step-icon {
        transform: scale(1.08);
        box-shadow: 0 0 20px rgba(241, 90, 36, 0.3);
    }

    .step-number {
        position: absolute;
        top: 20px; right: 28px;
        font-size: 3.5rem;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.05);
        font-family: var(--font-mono);
        line-height: 1;
    }

    .step-title {
        color: #ffffff;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .step-desc {
        color: #9aa3a3;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 0;
    }

    /* 8. REGISTRATION CTA SECTION */
    .reg-action-area {
        text-align: center;
        margin-top: 60px;
        padding-top: 20px;
    }
</style>

<!-- ================= HERO SECTION ================= -->
<div class="event-hero" id="home">
    <div class="bg-slideshow">
        <div class="slide active" style="background-image: url('images/e1.webp');"></div>
        <div class="slide" style="background-image: url('images/e2.webp');"></div>
        <div class="slide" style="background-image: url('images/e4.webp');"></div>
        <div class="slide" style="background-image: url('images/e3.webp');"></div>
    </div>
    <div class="overlay-gradient"></div>

    <div class="hero-content">
        <div class="hero-kicker">
            <i class="fas fa-bolt"></i> THE PREMIER AI COMPETITION
        </div>

        <h1 class="hero-title">
            THE ENGINEER'S<br><span class="text-accent-glow">CODE</span>
        </h1>

        <div class="hero-tagline">
            "CODE THE LOGIC, ENGINEER THE IMPOSSIBLE"
        </div>
        
        <div class="hero-pills">
            <div class="pill"><i class="fas fa-calendar-alt"></i> 14, 15, 16 October 2026</div>
            <div class="pill"><i class="fas fa-map-marker-alt"></i> NED MAIN CAMPUS</div>
            <div class="pill"><i class="fas fa-trophy"></i>PRIZE POOL (tbd)</div>
        </div>

        <a href="#register" class="btn-neon" style="font-size: 1.15rem; padding: 16px 54px;">
            REGISTER NOW
        </a>
    </div>
</div>

<!-- ================= COMPETITION MODULES SECTION ================= -->
<section id="modules" class="section-wrapper">
    <div class="section-header">
        <h2>Competition Modules</h2>
        <p class="section-subtitle">
            UNIVERSITY LEVEL CHALLENGES
        </p>
    </div>

    <div id="university-grid" class="modules-grid">
        <?php
        $uni_modules = [
            ["title" => "Line Following Robot (LFR)", "desc" => "A practical robotics competition in which autonomous robots follow a predefined track using sensors and control logic.", "team_size" => "Team of 4", "price" => "PKR 1,200"],
            ["title" => "Circuit Designing Competition", "desc" => "An electronics and digital-logic focused competition involving circuit design, problem solving, and circuit debugging through simulation.", "team_size" => "Team of 4", "price" => "PKR 1,200"],
            ["title" => "CYBER WAR ROOM", "desc" => "Cyber War Room is a direct Attack & Defense Web Security Competition. Teams must first build and secure their own functional web application, package it using Docker, and submit it to the organizers. The application is then randomly assigned to another team.", "team_size" => "Team of 3-4", "price" => "PKR 1,400"],
            ["title" => "RAG CHATBOT BUILDER", "desc" => "Build a Retrieval-Augmented Generation chatbot from a provided PDF that answers accurately and stays polite under a live adversarial roleplay.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "AGENT SPRINT: LIVE GMAIL AUTOMATION", "desc" => "Build an agent that reads real emails from a provided Gmail account, classifies them, drafts policy-based replies, and displays live status on a dashboard.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "AI COURT: FAKE OR REAL", "desc" => "Classify six curated items as real or AI-generated and defend the verdict before a judging panel.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE", "desc" => "Digitize and clean a single messy hardcopy dataset, build a dashboard, and catch a live injected anomaly.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "BREAK THE RULES", "desc" => "Break a locked chatbot's hidden behavioral rules through conversation alone, across a minimum of three rule categories.", "team_size" => "Team of 1", "price" => "PKR 1000"],
            ["title" => "PitchFest", "desc" => "Students can come up with their ideas and projects, then present them to a panel of evaluators who assess innovation, feasibility, and impact. The module rewards bold thinking, clear communication, and the ability to turn an idea into a compelling solution.", "team_size" => "Team of 4", "price" => "PKR 500"],
            ["title" => "AI DEBATE COLOSSEUM", "desc" => "Build a competing AI debate persona and face another team's persona live, with a live-updating public transcript and an AI judge deciding the winner.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "Web Forces", "desc" => "Teams ship a working full stack app against a live spec that is only revealed at the start of the module. Partway through, a twist is dropped in (a broken API, a new requirement) to test how well the team adapts, not just how fast they can build.", "team_size" => "Team of 3-4", "price" => "PKR 1,400"],
            ["title" => "Reactor Zero", "desc" => "A high-stakes competition where teams must design and build a reactor from scratch, facing real-world challenges and constraints.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"],
            ["title" => "Fault Line", "desc" => "Teams get mixed data, concrete stress tests and fabric tensile tests, and must build one classifier pipeline that generalizes across material types.", "team_size" => "Team of 2-3", "price" => "PKR 1,200"]
        ];
        foreach ($uni_modules as $mod) {
            echo '
            <div class="module-card">
                <h3 class="module-title mt-2">' . htmlspecialchars($mod["title"]) . '</h3>
                <p class="module-desc">' . htmlspecialchars($mod["desc"]) . '</p>
                <div class="module-meta">
                    <span class="module-meta-pill">' . htmlspecialchars($mod["team_size"]) . '</span>
                    <span class="module-meta-pill">' . htmlspecialchars($mod["price"]) . '</span>
                </div>
                <a href="event_registration?module=' . urlencode($mod["title"]) . '" class="btn-neon module-register">Register Here</a>
            </div>';
        }
        ?>
    </div>
</section>

<!-- ================= HOW TO REGISTER SECTION ================= -->
<section id="register" class="section-wrapper" style="padding-top: 60px; padding-bottom: 120px;">
    <div class="section-header">
        <h2>How to Register</h2>
        <p style="color: #9aa3a3; font-family: var(--font-mono); font-size: 0.95rem; margin-top: 8px;">
            Follow these simple steps to secure your spot in the competition.
        </p>
    </div>

    <div class="steps-grid">
        <div class="step-card">
            <div class="step-number">01</div>
            <div class="step-icon"><i class="fas fa-user-plus"></i></div>
            <h3 class="step-title">Create Account</h3>
            <p class="step-desc">Sign up on our portal and verify your email address to unlock your participant dashboard.</p>
        </div>

        <div class="step-card">
            <div class="step-number">02</div>
            <div class="step-icon"><i class="fas fa-sign-in-alt"></i></div>
            <h3 class="step-title">Login & Register</h3>
            <p class="step-desc">Log in to your Dashboard, click "New Registration," and submit your team details and chosen module.</p>
        </div>

        <div class="step-card">
            <div class="step-number">03</div>
            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="step-title">Await Approval</h3>
            <p class="step-desc">Once submitted, our team will review your application. You will receive an official email confirmation.</p>
        </div>
    </div>

    <div class="reg-action-area">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="event_registration" class="btn-neon" style="font-size: 1.15rem; padding: 18px 56px;">
                GO TO REGISTRATION FORM <i class="fas fa-arrow-right ms-2"></i>
            </a>
        <?php else: ?>
            <a href="signup" class="btn-neon" style="font-size: 1.15rem; padding: 18px 56px;">
                CREATE ACCOUNT TO REGISTER
            </a>
            <p class="mt-4" style="color: #88929b; font-family: var(--font-mono); font-size: 0.9rem;">
                Already have an account? <a href="login" style="color: var(--accent); font-weight: 700; text-decoration: none; margin-left: 4px;">Login here</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ================= JAVASCRIPT LOGIC ================= -->
<script>
    // Hero background slideshow autoplay
    (function() {
        var slides = document.querySelectorAll('.bg-slideshow .slide');
        if (slides.length > 1) {
            var currentIndex = 0;
            setInterval(function() {
                slides[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % slides.length;
                slides[currentIndex].classList.add('active');
            }, 5000);
        }
    })();
</script>

<?php include 'footer.php'; ?>
