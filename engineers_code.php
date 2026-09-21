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
        margin-bottom: 12px;
    }

    .module-desc {
        color: #9aa3a3;
        font-size: 0.88rem;
        line-height: 1.6;
        margin-bottom: 0;
        flex-grow: 1;
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
            <div class="pill"><i class="fas fa-trophy"></i> 60K PRIZE POOL</div>
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
            CHOOSE YOUR CHALLENGE LEVEL BELOW
        </p>
    </div>

    <div class="tab-container">
        <button class="tab-btn active" onclick="showCategory('university', this)">University Level</button>
        <button class="tab-btn" onclick="showCategory('college', this)">College Level</button>
    </div>

    <!-- University Modules Grid -->
    <div id="university-grid" class="modules-grid">
        <?php
        $uni_modules = [
            ["title" => "AI Smart Grid RL Optimizer", "desc" => "Train an AI agent to control a virtual power grid smartly, reducing energy costs and carbon emissions using Reinforcement Learning."],
            ["title" => "AI Simple Defect Classifier", "desc" => "Use YOLOv8 to spot defective products in a factory line instantly, ensuring quality control with a small dataset."],
            ["title" => "AI Urban Insight Tool", "desc" => "Build a tool for city planners that maps accident or pollution data and generates AI-backed improvement proposals."],
            ["title" => "AI Defect Detector: Edge-Optimized", "desc" => "Optimize a vision model to run on low-power devices (Edge AI) for real-time defect detection in manufacturing."],
            ["title" => "AI RAG Security Threat Prioritizer", "desc" => "Create an AI system that reads security alerts and highlights the top critical threats with AI-generated solutions."],
            ["title" => "AI Workflow & Report Generator", "desc" => "Build a chatbot that takes raw business data and generates summaries, anomaly reports, and action plans in seconds."],
            ["title" => "AI Smart Meter Behavioral Advisor", "desc" => "An AI advisor that studies electricity usage data and gives each user a simple, personalized 7-day plan to save energy."],
            ["title" => "AI Disaster Response: Ethical RL Agent", "desc" => "Design an AI agent that decides how to share limited resources fairly and effectively during disasters while reducing bias."]
        ];
        foreach ($uni_modules as $mod) {
            echo '
            <div class="module-card">
                <span class="module-badge">University</span>
                <h3 class="module-title">'.htmlspecialchars($mod["title"]).'</h3>
                <p class="module-desc">'.htmlspecialchars($mod["desc"]).'</p>
            </div>';
        }
        ?>
    </div>

    <!-- College Modules Grid -->
    <div id="college-grid" class="modules-grid" style="display: none;">
        <?php
        $col_modules = [
            ["title" => "AI Energy Saver Dashboard", "desc" => "Build a simulation to analyze and predict energy usage using Linear Regression and suggest optimization tips."],
            ["title" => "AI Cyber Alert Classifier", "desc" => "Train a keyword-based text classifier to categorize security alerts as Low, Medium, or High risk."],
            ["title" => "AI Disaster Aid Planner", "desc" => "Use K-Means clustering to group affected regions and design an algorithm for fair supply distribution."],
            ["title" => "AI City Planner Map", "desc" => "Identify high-risk accident zones using clustering and visualize them on an interactive map."],
            ["title" => "AI Home Energy Advisor", "desc" => "Predict next week's energy usage from historical data and generate 3 personalized saving tips."],
            ["title" => "AI Defect Finder (Basic)", "desc" => "Train a simple image classifier (Teachable Machine) to detect 'Good' vs 'Defective' products."],
            ["title" => "AI Data Insight Tool", "desc" => "Create a mini ML dashboard that loads a CSV file and performs basic analytics for trend prediction."],
            ["title" => "AI Image Checker", "desc" => "Collect 20-30 images and train a simple classifier using Teachable Machine or Scikit-learn to evaluate accuracy."]
        ];
        foreach ($col_modules as $mod) {
            echo '
            <div class="module-card">
                <span class="module-badge">College</span>
                <h3 class="module-title">'.htmlspecialchars($mod["title"]).'</h3>
                <p class="module-desc">'.htmlspecialchars($mod["desc"]).'</p>
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
    // 1. Tab switching between University and College modules
    function showCategory(cat, btn) {
        var uniGrid = document.getElementById('university-grid');
        var colGrid = document.getElementById('college-grid');
        
        if (cat === 'university') {
            uniGrid.style.display = 'grid';
            colGrid.style.display = 'none';
        } else {
            uniGrid.style.display = 'none';
            colGrid.style.display = 'grid';
        }
        
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        btn.classList.add('active');
    }

    // 2. Hero Background Slideshow Autoplay
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
