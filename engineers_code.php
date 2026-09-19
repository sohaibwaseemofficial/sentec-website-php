<?php include 'header.php'; ?>

<style>
    /* BUTTON STYLE (Outline -> Glow) */
    .btn-neon {
        background: transparent !important;
        border: 2px solid var(--accent) !important; /* Green Border */
        color: #fff !important;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 50px;
        transition: all 0.3s ease;
        display: inline-block;
        text-decoration: none;
    }

    .btn-neon:hover {
        background: var(--accent) !important; /* Fill Green on Hover */
        color: #000 !important;              /* Text turns Black */
        box-shadow: 0 0 30px rgba(0, 255, 148, 0.6); /* Strong Glow */
        transform: translateY(-3px);
    }
    /* 1. EVENT HERO (Matches Home Page) */
    .event-hero {
        height: 100vh; /* Slightly shorter than home */
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        text-align: center;
        padding-bottom: 100px;
        padding-top: 60px;
        overflow: hidden;
    }
    
    /* 2. MODULE CARDS */
    .module-card {
        background: rgba(255, 255, 255, 0.03); /* Very transparent */
        backdrop-filter: blur(15px);          /* This adds the Blur effect */
        -webkit-backdrop-filter: blur(15px);  /* For Safari */
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 30px;
        height: 100%;
        transition: 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .module-card:hover {
        background: rgba(255, 255, 255, 0.08); /* Lighter on hover */
        border-color: var(--accent);
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 255, 148, 0.15);
    }
    .module-badge {
        background: var(--accent);
        color: #000;
        font-weight: 800;
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 4px;
        text-transform: uppercase;
        margin-bottom: 15px;
        display: inline-block;
    }
    .module-title {
        color: #fff;
        font-family: 'Outfit', sans-serif;
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .module-desc {
        color: #aaa;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    /* 3. REGISTRATION STEPS (Attractive UI) */
    .step-card {
        background: rgba(255, 255, 255, 0.03); /* Glass background */
        backdrop-filter: blur(15px);           /* Blur effect */
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 40px 30px;
        border-radius: 24px;
        text-align: center;
        position: relative;
        height: 100%;
        transition: 0.3s;
    }
    .step-card:hover {
        border-color: var(--accent);
        box-shadow: 0 0 30px rgba(0, 255, 148, 0.1);
        transform: translateY(-5px);
    }
    .step-icon {
        width: 70px; height: 70px;
        background: rgba(0, 255, 148, 0.1);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 25px auto;
        color: var(--accent);
        font-size: 1.8rem;
    }
    .step-number {
        position: absolute;
        top: 20px; right: 30px;
        font-size: 4rem;
        font-weight: 900;
        color: rgba(255,255,255,0.03);
        font-family: 'Outfit', sans-serif;
        line-height: 1;
    }

    /* 4. TABS */
    .tab-btn {
        background: transparent;
        border: 1px solid #444;
        color: #888;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        margin: 0 10px;
    }
    .tab-btn.active {
        background: var(--accent);
        color: #000;
        border-color: var(--accent);
        box-shadow: 0 0 15px rgba(0, 255, 148, 0.4);
    }
</style>

<div class="event-hero" id="home">
    <div class="bg-slideshow">
        <div class="slide active" style="background-image: url('images/e1.webp');"></div>
        <div class="slide" style="background-image: url('images/e2.webp');"></div>
        <div class="slide" style="background-image: url('images/e4.webp');"></div>
        <div class="slide" style="background-image: url('images/e3.webp');"></div>
    </div>
    <div class="overlay-gradient"></div>

    <span style="color: var(--accent); font-weight: 700; letter-spacing: 2px; margin-bottom: 10px; z-index: 2;">THE PREMIER AI COMPETITION</span>
    <h1 style="z-index: 2;">THE ENGINEER'S<br>CODE</h1>
    
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4" style="z-index: 2;">
        <div class="pill"><i class="fas fa-calendar-alt me-2"></i> 23, 24 & 28 DEC</div>
        <div class="pill"><i class="fas fa-map-marker-alt me-2"></i> NED MAIN CAMPUS</div>
        <div class="pill"><i class="fas fa-trophy me-2"></i> 60K PRIZE POOL</div>
    </div>

    <a href="#register" class="btn-neon mt-5" style="font-size: 1.2rem; padding: 15px 50px; z-index: 2;">REGISTER NOW</a>
</div>

<section id="modules">
    <div class="container">
        <div class="section-header text-center mb-4">
            <h2>Competition Modules</h2>
            <p style="color: var(--accent); font-size: 1.2rem; font-weight: 600; margin-top: 10px;">
                Choose your challenge level below
            </p>
        </div>

        <div class="d-flex justify-content-center mb-5">
            <button class="tab-btn active" onclick="showCategory('university', this)">University Level</button>
            <button class="tab-btn" onclick="showCategory('college', this)">College Level</button>
        </div>

        <div id="university-grid" class="row g-4">
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
                <div class="col-md-6 col-lg-3">
                    <div class="module-card">
                        <span class="module-badge">University</span>
                        <h3 class="module-title">'.$mod["title"].'</h3>
                        <p class="module-desc">'.$mod["desc"].'</p>
                    </div>
                </div>';
            }
            ?>
        </div>

        <div id="college-grid" class="row g-4" style="display: none;">
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
                <div class="col-md-6 col-lg-3">
                    <div class="module-card">
                        <span class="module-badge">College</span>
                        <h3 class="module-title">'.$mod["title"].'</h3>
                        <p class="module-desc">'.$mod["desc"].'</p>
                    </div>
                </div>';
            }
            ?>
        </div>

    </div>
</section>

<section id="register">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2>How to Register</h2>
            <p style="color: #888;">Follow these simple steps to secure your spot.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                    <h3 style="color:#fff; margin-bottom:15px;">Create Account</h3>
                    <p style="color:#aaa;">Sign up on our portal and verify your email address to get access to the dashboard.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">02</div>
                    <div class="step-icon"><i class="fas fa-sign-in-alt"></i></div>
                    <h3 style="color:#fff; margin-bottom:15px;">Login & Register</h3>
                    <p style="color:#aaa;">Log in to your Dashboard, click "New Registration," and fill in your team details and module choice.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">03</div>
                    <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                    <h3 style="color:#fff; margin-bottom:15px;">Await Approval</h3>
                    <p style="color:#aaa;">Once submitted, our team will review your application. You will receive an email confirmation!</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5 pt-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="event_registration.php" class="btn-neon" style="font-size: 1.3rem; padding: 20px 60px; box-shadow: 0 0 30px rgba(0,255,148,0.3);">
                    GO TO REGISTRATION FORM <i class="fas fa-arrow-right ms-2"></i>
                </a>
            <?php else: ?>
                <a href="signup.php" class="btn-neon" style="font-size: 1.3rem; padding: 20px 60px;">
                    CREATE ACCOUNT TO REGISTER
                </a>
                <p class="mt-4" style="color:#666;">Already have an account? <a href="login.php" style="color:var(--accent); font-weight:bold;">Login here</a></p>
            <?php endif; ?>
        </div>

    </div>
</section>

<script>
    // Javascript to toggle tabs
    function showCategory(cat, btn) {
        // Hide all grids
        document.getElementById('university-grid').style.display = 'none';
        document.getElementById('college-grid').style.display = 'none';
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        // Show selected grid
        document.getElementById(cat + '-grid').style.display = 'flex'; // Using flex to keep row layout
        
        // Add active class to clicked button
        btn.classList.add('active');
    }
</script>

<?php include 'footer.php'; ?>
