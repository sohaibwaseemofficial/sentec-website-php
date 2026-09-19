<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * MASTER DATA STRUCTURE
 * Format: [Icon, Tag, Title, Description, Prize Pool, Reg Fee, Team Limit]
 */
$event_data = [
    "Technical Modules" => [
        "desc" => "Push your limits across 12 specialized tracks designed to evaluate logic, speed, and innovation.",
        "modules" => [
            ["⚡", "Speed Programming", "Code Rush", "A fast-paced competition designed to test the quick-thinking and efficiency of programmers in solving algorithmic problems.", "10,000", "2000", "Max 4","rules/code-rush.pdf"],
            ["🧠", "Competitive Programming", "Algo Masters", "An advanced track focusing on complex data structures and high-level algorithmic efficiency for seasoned programmers.", "10,000", "2000", "Max 4","rules/algo-masters.pdf"],
            ["🏁", "Code Sprint", "Race with Code", "A timed challenge where participants must write efficient code for specific tasks against a ticking clock.", "10,000", "2000", "Max 4","rules/race-with-code.pdf"],
            ["🎨", "UI/UX Design", "Design Sprint", "A creative module where participants design intuitive user interfaces and experiences for modern digital applications.", "10,000", "2000", "Max 4","rules/design-sprint.pdf"],
            ["📱", "App Development", "App Innovate", "A comprehensive challenge where teams move from ideation to the development phase of a functional application.", "10,000", "2000", "Max 4","rules/app-innovate.pdf"],
            ["🗄️", "Database Mastery", "Query Quest", "Solve real-world SQL challenges, leveling up from basic queries to complete database mastery and optimization.", "10,000", "2000", "Max 4","rules/query-quest.pdf"],
            ["🐛", "Debugging", "Bug Busters", "A hands-on module where contestants are tasked with identifying and fixing errors in pre-written, broken code.", "10,000", "2000", "Max 4","rules/bug-busters.pdf"],
            ["🌐", "Web Development", "Web Wizards", "A contest focused on the design and functional implementation of modern, highly responsive websites.", "10,000", "2000", "Max 4","rules/web-wizards.pdf"],
            ["🤫", "Communication Test", "Blind Coding", "A unique test of mental mapping where one partner dictates logic and the other types without seeing the screen.", "10,000", "2000", "Max 4","rules/blind-coding.pdf"],
            ["📋", "Logic Mapping", "Pseudocode Builder", "Convert abstract problem-solving logic into structured, language-independent pseudocode without syntax errors.", "10,000", "2000", "Max 4","rules/pseudocode-builder.pdf"],
            ["😂", "Logic Game", "Emoji Algorithm", "A fun yet challenging module that requires participants to represent complex programming logic using only emojis.", "10,000", "2000", "Max 4","rules/emoji-algorithm.pdf"],
            ["➕", "Maths Olympiad", "Maths Clash", "A high-stakes Mathematics Olympiad where competitors race against the clock to solve mathematical challenges and mind-bending logic puzzles.", "10,000", "500", "Individual","rules/maths-clash.pdf"]
        ]
    ],
    "Exhibitions & Projects" => [
        "desc" => "Showcase your hardware and software integration skills to build impactful real-world solutions.",
        "modules" => [
            ["🔐", "IoT / Security", "Defense Support", "Building IoT solutions for national security and public safety.", "10,000", "2500", "Max 4","rules/defense-support.pdf"],
            ["⌚", "IoT / Wearables", "Sports & Fitness", "Creating wearable technology for athletic performance and health tracking.", "10,000", "1500", "Max 4","rules/sports-fitness.pdf"],
            ["🎓", "Final Year Project", "FYDP Showcase", "A grand showcase of Final Year Design Projects across various engineering and technology disciplines.", "10,000", "1000", "Max 4","rules/fydp-showcase.pdf"]
        ]
    ],
    "General Modules" => [
        "desc" => "Engage in thrilling simulations, pitch innovative business models, and test your deductive skills.",
        "modules" => [
            ["🕵️", "Scavenger Hunt", "The Heist", "Teams solve challenges and outwit opponents to pull off the perfect operation in this crime-thriller-style simulation.", "10,000", "1000", "Max 4","rules/the-heist.pdf"],
            ["🔎", "Mystery Solving", "Sherlock's Case", "Teams solve riddles and locate the real criminal in this murder-mystery style simulation.", "5,000", "1000", "Max 4","rules/sherlock-s-case.pdf"],
            ["💡", "Business Pitch", "NED@IDEA", "A competition where students pitch innovative business models to a panel of judges and industry experts.", "5,000", "350", "Max 3","rules/ned-at-idea.pdf"],
            ["🎭", "Art & Storytelling", "StoryCraft", "A creative competition where participants design and present a unique digital art piece paired with a compelling narrative.", "2,500", "350", "1-2 Members","rules/storycraft.pdf"],
            ["📷", "Photography", "Captech", "A campus-wide photography and presentation competition capturing real-world engineering phenomena in action.", "2,500", "350", "Individual","rules/captech.pdf"]
        ]
    ],
    "Battle Arena" => [
        "desc" => "Battle it out in top-tier competitive gaming tournaments to prove your mechanical skills and teamwork.",
        "modules" => [
        ["🎮", "5v5 Tactical", "Valorant", "A highly competitive 5v5 character-based tactical shooter tournament.", "5,000", "1000", "5 Members","rules/valorant.pdf"],
            ["🔫", "5v5 Classic", "CS 1.6", "The classic 5v5 tactical shooter tournament that started it all.", "5,000", "1000", "5 Members","rules/cs-16.pdf"],
            ["🥋", "1v1 Fighting", "Tekken 8", "A competitive 1v1 fighting tournament featuring the latest from the Iron Fist Tournament.", "2,500", "250", "Individual","rules/tekken-8.pdf"],
            ["⚽", "1v1 Sports", "EA FC 25", "A competitive 1v1 virtual football tournament to crown the ultimate champion.", "2,000", "250", "Individual","rules/ea-fc-25.pdf"],
            ["🏎️", "2v2 Sports", "Rocket League", "A high-octane 2v2 tournament combining soccer with rocket-powered vehicles.", "2,000", "250", "Individual","rules/rocket-league.pdf"]
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROXION 2026 | SENTEC</title>
    <link rel="icon" href="images/favicon2.png" type="image/png">
    <link rel="shortcut icon" href="images/favicon2.png" type="image/png">
    <link rel="apple-touch-icon" href="images/favicon2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Orbitron:wght@400;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="proxion-style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* DESIGNER OVERRIDES */
        :root {
            --accent-primary: #ed8507;
            --accent-secondary: #9b6fd4;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        /* The Card Design */
        .module-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(1px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .module-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-10px);
            background: rgba(237, 133, 7, 0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 20px rgba(237, 133, 7, 0.1);
        }

        .card-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .card-icon-box {
            font-size: 2.5rem;
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.2));
        }

        .card-team-pill {
            background: rgba(155, 111, 212, 0.15);
            color: rgb(221, 196, 255);
            font-family: 'Space Mono';
            font-size: 0.65rem;
            padding: 4px 12px;
            border-radius: 100px;
            border: 1px solid rgba(155, 111, 212, 0.3);
            text-transform: uppercase;
        }

        .module-title-main {
            font-family: 'Orbitron';
            font-weight: 600;
            font-size: 1.8rem;
            line-height: 1;
            margin-bottom: 5px;
            letter-spacing: 1px;
            color: #fff;
        }

        .module-tag-sub {
            font-family: 'Space Mono';
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent-primary);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            display: block;
        }

        /* The Prize Section */
        .prize-container {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .prize-label-sm {
            display: block;
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.68);
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .prize-value-lg {
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 1.15rem;
            color: var(--accent-primary);
            line-height: 1;
        }

        .fee-tag {
            font-family: 'Space Mono';
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            opacity: 0.8;
        }

        /* Modal Styles */
        .custom-modal { background: #0b071c !important; border: 1px solid var(--accent-secondary) !important; border-radius: 24px; }
        .modal-btn-reg { background: var(--accent-primary); font-family: var(--font-mono);color: #000; font-weight: 600; border-radius: 12px; padding: 15px; text-decoration: none; display: block; text-align: center; }

        /* Fix for the 'Locked' Modal issue */
        .modal {
            z-index: 10001 !important; /* Higher than navbar and canvas */
        }

        .modal-backdrop {
            z-index: 10000 !important;
        }

        /* Ensure the modal content is actually clickable */
        .modal-content {
            pointer-events: auto !important;
        }

        /* Optional: Make the close button easier to see and click */
        .btn-close-white {
            opacity: 0.8;
            z-index: 10002;
        }
        /* Cursor Glow Effect */
        .module-card {
            position: relative;
            overflow: hidden; /* Keeps the glow inside the card */
        }

        .module-card::before {
            content: "";
            position: absolute;
            mix-blend-mode: overlay;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(
                600px circle at var(--mouse-x) var(--mouse-y),
                rgba(227, 198, 239, 0.94),
                transparent 10%
            );
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.5s;
        }

        .module-card:hover::before {
            opacity: 0.3;
        }

        .module-card-inner {
            position: relative;
            z-index: 1; /* Keep content above the glow */
        }
    </style>
</head>
  <!-- Custom Cursor -->
  <div class="cursor" id="cursor"></div>
  <div class="cursor-trail" id="cursor-trail"></div>

    <nav id="navbar">
            <div class="nav-inner">
                <a href="index.php" class="nav-logo">
                    SENTEC<span>.</span>
                </a>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#technical-modules">Modules</a></li>
                    <li><a href="#register">How to Join</a></li>
                </ul>
                <a href="login.php" class="nav-cta">REGISTER NOW</a>
            </div>
    </nav>

<body>

    <canvas id="bg-canvas"></canvas>

    

    <!-- HERO -->
  <section id="hero">
    <div class="hero-content">
      <div class="hero-eyebrow reveal-up">
        <span class="eyebrow-dot"></span>SENTEC PRESENTS<span class="eyebrow-dot"></span>
      </div>
      <h1 class="hero-title">
        <span class="title-glitch" data-text="PROXION">PROXION</span>
      </h1>
      <div class="hero-year reveal-up delay-2">2026</div>
      <div class="hero-meta reveal-up delay-3">
        <div class="meta-item prize-badge">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          27th – 30th April 2026
        </div>
        <div class="meta-sep">|</div>
        <div class="meta-item prize-badge">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          NED Main Campus
        </div>
        <div class="meta-sep">|</div>
        <div class="meta-item prize-badge">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
          200K Prize Pool
        </div>
      </div>
      <div class="hero-actions reveal-up delay-4">
        <a href="#technical-modules" class="btn-primary">EXPLORE MODULES</a>
        <a href="#register" class="btn-ghost">REGISTER NOW</a>
      </div>
    </div>
    <div class="hero-scroll-hint">
      <span>SCROLL</span>
      <div class="scroll-line"></div>
    </div>
    <div class="hero-particles" id="hero-particles"></div>
  </section>

  <!-- 3D SCROLL CARD — ABOUT -->
    <section id="about">
      <div class="scroll3d-container" id="scroll3d-container">
        <div class="about-title-wrap reveal-title" id="about-title-wrap">
          <p class="section-label">ABOUT THE EVENT</p>
          <h2 class="section-heading">Where Innovation<br><em>Meets Competition</em></h2>
        </div>
        <div class="card-3d-wrap" id="card-3d-wrap">
          <div class="card-3d" id="card-3d">
            <div class="card-3d-inner">
              <img
                src="https://images.unsplash.com/photo-1518770660439-4636190af475?w=1400&q=80"
                alt="PROXION Event"
                draggable="false"
                class="card-3d-img"
              />
              <div class="card-3d-overlay">
                <div class="card-3d-text">
                  <div class="card-tag">SENTEC × NED University</div>
                  <h3>4 Days. 20+ Modules. One Champion.</h3>
                  <p>PROXION is SENTEC's flagship multi-disciplinary tech olympiad — a battleground for the sharpest minds in programming, design, engineering, and beyond.</p>
                  <div class="card-stats">
                    <div class="stat"><span>20+</span><label>Modules</label></div>
                    <div class="stat"><span>200K</span><label>Prize Pool</label></div>
                    <div class="stat"><span>4</span><label>Days</label></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

   <?php 
    $m_id = 0;
    foreach ($event_data as $cat_title => $cat_info): 
        // Split the title for the gradient effect
        $words = explode(' ', $cat_title);
        $first_word = $words[0];
        $remaining_words = implode(' ', array_slice($words, 1));
        
        // Get the unique description for this section
        $section_desc = $cat_info['desc'];
        $modules = $cat_info['modules'];
    ?>
    <section id="<?= strtolower(str_replace(' ', '-', $cat_title)) ?>" class="py-0">
        <div class="container">
            <div class="section-header text-center mb-5 reveal-up reveal-title">
                <p class="section-label">COMPETE & WIN</p>
                <h2 class="section-heading">
                    <?= $first_word ?> <em><?= $remaining_words ?></em>
                </h2>
                <p class="section-sub"><?= $section_desc ?></p>
            </div>
           <div class="row g-4">
            <?php foreach ($modules as $m): ?>
            <div class="col-lg-3 col-md-6">
                <div class="module-card esport-card reveal-card" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modal_<?= $m_id ?>" 
                    style="--delay:<?= $m_id % 4 ?>; cursor: pointer;">
                    
                    
                    <div class="esport-glow"></div>
                    

                    <div class="module-card-inner">
                        <div class="card-header-top">
                            <div class="card-icon-box"><?= $m[0] ?></div>
                            <div class="card-team-pill"><?= $m[6] ?></div>
                        </div>
                        <span class="module-tag-sub"><?= $m[1] ?></span>
                        <h3 class="module-title-main"><?= $m[2] ?></h3>
                        <p class="small opacity-100"><?= substr($m[3], 0, 80) ?>...</p>
                    </div>
                    
                    <div class="prize-container">
                        <div>
                            <span class="prize-label-sm">TOTAL PRIZE</span>
                            <span class="prize-value-lg">Rs. <?= $m[4] ?></span>
                        </div>
                        <div class="text-end">
                            <span class="prize-label-sm">FEES</span>
                            <span class="fee-tag">Rs. <?= $m[5] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php $m_id++; endforeach; ?>
        </div>
        </div>
    </section>
    <?php endforeach; ?>

    <!-- REGISTER -->
    <section id="register">
        <div class="register-inner">
        <div class="register-glow-orb" aria-hidden="true"></div>
        <div class="section-header reveal-up">
            <p class="section-label">SECURE YOUR SPOT</p>
            <h2 class="section-heading">How to <em>Register</em></h2>
        </div>
        <div class="steps-row">
            <div class="step reveal-card" style="--delay:0">
            <div class="step-num">01</div>
            <h3>Create Account</h3>
            <p>Sign up on our portal and verify your email address to get access to the dashboard.</p>
            </div>
            <div class="step-connector" aria-hidden="true"></div>
            <div class="step reveal-card" style="--delay:1">
            <div class="step-num">02</div>
            <h3>Login &amp; Register</h3>
            <p>Log in to your Dashboard, click "New Registration," and fill in your team details and module choice.</p>
            </div>
            <div class="step-connector" aria-hidden="true"></div>
            <div class="step reveal-card" style="--delay:2">
            <div class="step-num">03</div>
            <h3>Await Approval</h3>
            <p>Once submitted, our team will review your application. You will receive an email confirmation!</p>
            </div>
        </div>
        <div class="register-cta reveal-up">
            <a href="signup.php" class="btn-primary btn-large">CREATE ACCOUNT TO REGISTER</a>
            <p class="register-login">Already have an account? <a href="login.php">Login here</a></p>
        </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="proxion-script.js"></script>

  <?php 
    // Reset the counter so IDs match the cards generated in the main loop
    $m_id_fix = 0; 

    foreach ($event_data as $cat_title => $cat_info): 
        // Correctly loop through the 'modules' sub-array instead of the whole $cat_info
        foreach ($cat_info['modules'] as $m): 
    ?>
        <div class="modal fade" id="modal_<?= $m_id_fix ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content custom-modal p-4">
                    <div class="modal-header border-0">
                        <h2 class="module-title-main" style="font-size: 1.8rem; font-family:'Orbitron'; color:#fff;"><?= $m[2] ?></h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <span class="module-tag-sub" style="font-size: 1rem; color:var(--accent-primary);"><?= $m[1] ?></span>
                        <p class="mb-4 opacity-75 text-white"><?= $m[3] ?></p>

                        <div class="mb-4">
                            <?php 
                            $rulebook_path = isset($m[7]) ? $m[7] : '';
                            
                            // file_exists() checks if the file is actually in your 'rules' folder
                            if (!empty($rulebook_path) && file_exists($rulebook_path)): ?>
                                <a href="<?= htmlspecialchars($rulebook_path) ?>" target="_blank" class="btn-rulebook">
                                    <i class="fas fa-file-pdf me-2"></i> VIEW RULEBOOK
                                </a>
                            <?php else: ?>
                                <div class="rulebook-coming-soon">
                                    <i class="fas fa-clock me-2"></i> RULEBOOK COMING SOON
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="p-3 rounded-4 text-center" style="background: rgba(237, 133, 7, 0.1); border: 1px solid rgba(237, 133, 7, 0.2);">
                                    <span class="prize-label-sm" style="font-size:0.7rem; color:rgba(255,255,255,0.6);">WINNING POOL</span>
                                    <div class="prize-value-lg" style="font-family:'Orbitron'; font-weight:900; color:var(--accent-primary);">Rs. <?= $m[4] ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-4 text-center" style="background: rgba(155, 111, 212, 0.1); border: 1px solid rgba(155, 111, 212, 0.2);">
                                    <span class="prize-label-sm" style="font-size:0.7rem; color:rgba(255,255,255,0.6);">REGISTRATION</span>
                                    <div class="prize-value-lg" style="font-family:'Orbitron'; font-weight:900; color:var(--accent-secondary);">Rs. <?= $m[5] ?></div>
                                </div>
                            </div>
                        </div>
                        <a href="login.php" 
                            class="modal-btn-reg">PROCEED TO REGISTRATION
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php 
        $m_id_fix++; 
        endforeach; 
    endforeach; 
    ?>
    
</body>
</html>