<?php include 'header.php'; ?>

<style>
    /* PAGE SPECIFIC STYLES (Cyberpunk/Neon Theme) */
    :root {
        --accent: #00FF94;
        --bg-dark: #050505;
        --glass-bg: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    body { background-color: var(--bg-dark); font-family: 'Plus Jakarta Sans', sans-serif; }

    /* 1. HERO SECTION */
    .social-hero {
        height: 100vh; /* Forces it to fill the exact screen height */
        min-height: 100vh; /* Ensures it never shrinks on mobile */
        /* Keep the rest of the lines the same */
        background: radial-gradient(circle at center, rgba(0, 255, 148, 0.1) 0%, #000 70%), url('images/social-bg-placeholder.webp');
        background-size: cover;
        background-position: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 0 20px; /* Adjusted padding to center content better */
        position: relative;
        overflow: hidden;
    }
    
    .hero-title {
        font-family: 'Outfit', sans-serif;
        font-size: 5rem;
        font-weight: 800;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 3px;
        text-shadow: 0 0 30px rgba(0, 255, 148, 0.4);
        margin-bottom: 10px;
    }
    
.hero-urdu {
        font-family: 'Outfit', sans-serif; 
        font-size: 2.5rem;                 
        color: var(--accent);
        margin-bottom: 10px;
        text-shadow: 0 0 15px var(--accent);
        letter-spacing: 1px;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        color: #ccc;
        max-width: 800px;
        margin-bottom: 40px;
        letter-spacing: 1px;
    }

    .info-pill {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--accent);
        color: #fff;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        backdrop-filter: blur(10px);
        margin: 5px;
    }
    .info-pill i { color: var(--accent); }

    /* 2. HIGHLIGHTS GRID */
    .highlight-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        padding: 30px;
        border-radius: 20px;
        text-align: center;
        transition: 0.3s;
        height: 100%;
    }
    .highlight-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent);
        box-shadow: 0 10px 40px rgba(0, 255, 148, 0.15);
    }
    .highlight-icon {
        font-size: 2.5rem;
        color: var(--accent);
        margin-bottom: 20px;
    }
    .highlight-title { color: #fff; font-weight: 700; font-size: 1.2rem; margin-bottom: 10px; }
    .highlight-text { color: #888; font-size: 0.9rem; }

    /* 3. PRICING CARDS */
    .pricing-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.02) 0%, rgba(0,0,0,0.8) 100%);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 40px 30px;
        text-align: center;
        position: relative;
        transition: 0.3s;
        height: 100%;
    }
    .pricing-card.featured {
        border-color: var(--accent);
        box-shadow: 0 0 30px rgba(0, 255, 148, 0.1);
        transform: scale(1.05);
        z-index: 2;
    }
    .pricing-badge {
        position: absolute; top: -15px; left: 50%; transform: translateX(-50%);
        background: var(--accent); color: #000; font-weight: 800;
        padding: 5px 20px; border-radius: 50px; text-transform: uppercase; font-size: 0.8rem;
    }
    .price-amount {
        font-family: 'Outfit', sans-serif;
        font-size: 3rem;
        font-weight: 800;
        color: #fff;
        margin: 20px 0;
    }
    .price-amount span { font-size: 1rem; color: #888; font-weight: 400; }
    .pricing-features {
        list-style: none; padding: 0; margin: 30px 0; color: #ccc;
    }
    .pricing-features li { margin-bottom: 10px; font-size: 0.95rem; }
    .pricing-features i { color: var(--accent); margin-right: 8px; }

    /* 4. BUTTONS */
    .btn-neon {
        background: transparent;
        border: 2px solid var(--accent);
        color: #fff;
        padding: 15px 40px;
        font-weight: 800;
        text-transform: uppercase;
        border-radius: 50px;
        transition: 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-neon:hover {
        background: var(--accent);
        color: #000;
        box-shadow: 0 0 30px rgba(0, 255, 148, 0.5);
    }

    /* 5. STEPS */
    .step-box {
        display: flex; align-items: flex-start; gap: 20px; margin-bottom: 30px;
    }
    .step-num {
        font-family: 'Outfit'; font-size: 3rem; font-weight: 800; color: rgba(255,255,255,0.1); line-height: 1;
    }
    .step-content h4 { color: #fff; margin-bottom: 5px; }
    .step-content p { color: #888; font-size: 0.9rem; }

    @media (max-width: 768px) {
        .hero-title { font-size: 3rem; }
        .pricing-card.featured { transform: scale(1); margin: 20px 0; }
    }
</style>

<div class="social-hero">
    <h2 class="hero-urdu">روحِ رقص</h2>
    <h1 class="hero-title">RUH-E-RAQS</h1>
    <p class="hero-subtitle">A Magical Journey of Melody & Rhythm. Join us for the Social Event.</p>
    
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <div class="info-pill"><i class="fas fa-calendar-alt"></i> 28th December, 2025</div>
        <div class="info-pill"><i class="fas fa-clock"></i> 04:00 PM Onwards</div>
        <div class="info-pill"><i class="fas fa-map-marker-alt"></i> NED University Main Campus</div>
    </div>

    <div class="mt-5">
        <a href="#register" class="btn-neon">Secure Your Spot</a>
    </div>
</div>

<section style="padding: 80px 0;">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 style="color: #fff; font-family:'Outfit';">Event Highlights</h2>
                <p style="color: #888;">An day curated for art and music lovers.</p>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 col-6">
                <div class="highlight-card mx-auto">
                    <i class="fas fa-music highlight-icon"></i>
                    <h4 class="highlight-title">Qawwali</h4>
                    <p class="highlight-text">Soulful Sufi melodies to mesmerize you.</p>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="highlight-card mx-auto">
                    <i class="fas fa-feather-alt highlight-icon"></i>
                    <h4 class="highlight-title">Grand Mushaira</h4>
                    <p class="highlight-text">Poetry that touches the heart.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="register" style="padding: 80px 0; background: rgba(255,255,255,0.02);">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 style="color: #fff; font-family:'Outfit';">Choose Your Pass</h2>
                <p style="color: #888;">Select the package that fits you best.</p>
            </div>
        </div>

        <div class="row align-items-center justify-content-center g-4">
            
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card">
                    <h3 style="color:#fff;">Participant</h3>
                    <p style="color:#aaa; font-size:0.9rem;">For registered competition teams</p>
                    <div class="price-amount">0 <span>PKR</span></div>
                    
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Free Social</li>
                        <li><i class="fas fa-check"></i> Full Event Access</li>
                    </ul>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register.php' : 'login.php'; ?>" class="btn btn-outline-light rounded-pill px-4">Register</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="pricing-card featured">
                    <span class="pricing-badge">BEST VALUE</span>
                    <h3 style="color:#fff;">Group of 3</h3>
                    <p style="color:#aaa; font-size:0.9rem;">Bring your friends along</p>
                    <div class="price-amount">400 <span>PKR/person</span></div>
                    
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> <strong>Total: 1200 PKR</strong></li>
                        <li><i class="fas fa-check"></i> Save 300 PKR</li>
                    </ul>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register.php' : 'login.php'; ?>" class="btn-neon w-100">Get Group Pass</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="pricing-card">
                    <h3 style="color:#fff;">Individual</h3>
                    <p style="color:#aaa; font-size:0.9rem;">Standard entry pass</p>
                    <div class="price-amount">500 <span>PKR</span></div>
                    
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Single Entry</li>
                    </ul>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register.php' : 'login.php'; ?>" class="btn btn-outline-light rounded-pill px-4">Register</a>
                </div>
            </div>

        </div>
    </div>
</section>

<section style="padding: 80px 0;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 style="color: #fff; font-family:'Outfit'; margin-bottom:30px;">How to Register?</h2>
                
                <div class="step-box">
                    <div class="step-num">01</div>
                    <div class="step-content">
                        <h4>Create Portal Account</h4>
                        <p>Sign up on the SENTEC website to access the dashboard. If you already have an account, simply login.</p>
                    </div>
                </div>

                <div class="step-box">
                    <div class="step-num">02</div>
                    <div class="step-content">
                        <h4>Select Social Event</h4>
                        <p>Go to your dashboard and click on "Get Social Pass". Choose your pass type (Individual, Group, etc).</p>
                    </div>
                </div>

                <div class="step-box">
                    <div class="step-num">03</div>
                    <div class="step-content">
                        <h4>Pay & Verify</h4>
                        <p>Upload your payment proof. Once approved by Admin, you will receive your <strong>QR Code E-Pass</strong> via email.</p>
                    </div>
                </div>

                <div class="mt-4">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="social_register.php" class="btn-neon">Open Registration Form <i class="fas fa-arrow-right ms-2"></i></a>
                    <?php else: ?>
                        <a href="signup.php" class="btn-neon">Create Account <i class="fas fa-user-plus ms-2"></i></a>
                        <a href="login.php" style="color:#aaa; margin-left:20px; text-decoration:none;">Login</a>
                    <?php endif; ?>
                </div>

            </div>
            <div class="col-lg-6 d-none d-lg-block text-center">
                <img src="images/social-poster.webp" alt="Social Event Poster" style="max-width: 80%; border-radius: 20px; border: 1px solid #333; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
