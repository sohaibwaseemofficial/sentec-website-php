<?php
include 'header.php';
include 'db_connection.php';

// Fetch upcoming events from live database
$sql = "SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 3";
$result = $conn->query($sql);
?>

<!-- Ambient Technical Grid Background -->
<div class="relative w-full overflow-hidden">
    <div class="ambient-grid absolute inset-0 pointer-events-none opacity-40"></div>

    <!-- ========================================================================= -->
    <!-- 1. MODERN HERO SECTION WITH ANIMATED SVG EMBLEM                           -->
    <!-- ========================================================================= -->
    <section class="relative min-h-[calc(100vh-5rem)] flex items-center justify-center py-16 lg:py-24 border-b border-white/[0.08]" id="home">
        <!-- Background Radial Glow -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] sm:w-[900px] h-[600px] sm:h-[900px] bg-gradient-to-tr from-[#f15a24]/10 via-[#00d2ff]/5 to-transparent rounded-full blur-3xl pointer-events-none -z-10"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                
                <!-- Left Column: Copy, Metadata, CTAs -->
                <div class="lg:col-span-7 space-y-7 text-left">
                    
                    <!-- Eyebrow Tag -->
                    <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-[#101518] border border-white/[0.12] text-xs font-mono text-neutral-300 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-[#f15a24] animate-pulse"></span>
                        <span class="text-neutral-400">STUDENT ENGINEERING SOCIETY</span>
                        <span class="text-neutral-600">//</span>
                        <span class="text-[#f15a24] font-semibold">SYS.01 / ONLINE</span>
                    </div>

                    <!-- Main Headline -->
                    <h1 class="font-display text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white leading-[1.08]">
                        Make the<br>
                        <span class="text-[#f15a24] italic font-normal">future</span> tangible.
                    </h1>

                    <!-- Lede / Subtitle -->
                    <p class="text-base sm:text-lg text-neutral-300 font-sans max-w-xl leading-relaxed">
                        SENTEC is where ambitious students turn questions into working systems — through research, engineering olympiads, and the kind of practice that compounds.
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <a href="#events" class="inline-flex items-center gap-2.5 px-7 py-3.5 rounded-md bg-[#f15a24] hover:bg-[#ff6b35] text-[#080b0d] font-bold text-sm tracking-wider uppercase transition-all shadow-[0_4px_25px_rgba(241,90,36,0.35)] hover:-translate-y-0.5">
                            <span>Explore the Work</span>
                            <i class="fas fa-arrow-up-right text-xs"></i>
                        </a>
                        <a href="#about" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-md bg-[#101518] hover:bg-white/[0.08] text-white border border-white/[0.12] hover:border-white/[0.25] font-semibold text-sm transition-all">
                            <span>What We Do</span>
                            <i class="fas fa-chevron-right text-xs text-[#f15a24]"></i>
                        </a>
                    </div>

                    <!-- Technical Coordinates & Metadata -->
                    <div class="pt-6 border-t border-white/[0.08] flex flex-wrap items-center gap-6 text-xs font-mono text-neutral-400">
                        <div class="flex items-center gap-2">
                            <span class="text-[#f15a24] font-bold">01</span>
                            <span>NED UNIVERSITY • EST. 1997</span>
                        </div>
                        <div class="hidden sm:block text-neutral-600">|</div>
                        <div class="flex items-center gap-2 text-neutral-500">
                            <i class="fas fa-crosshairs text-[#f15a24] text-[10px]"></i>
                            <span>24° 55′ 52″ N / 67° 06′ 44″ E</span>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Glowing Container with Animated SVG Emblem -->
                <div class="lg:col-span-5 flex justify-center lg:justify-end">
                    <div class="relative w-[320px] h-[320px] sm:w-[420px] sm:h-[420px] md:w-[460px] md:h-[460px] flex items-center justify-center p-4">
                        
                        <!-- Glowing Halo Ring -->
                        <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-[#f15a24]/20 via-[#f15a24]/5 to-transparent blur-2xl -z-10"></div>
                        <div class="absolute inset-4 rounded-full border border-white/[0.05] bg-[#101518]/60 backdrop-blur-md"></div>

                        <!-- Technical Rotating SVG Emblem -->
                        <svg class="w-full h-full relative z-10 select-none overflow-visible" viewBox="0 0 600 600" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="orangeGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ff6b35" />
                                    <stop offset="100%" stop-color="#f15a24" />
                                </linearGradient>
                                <linearGradient id="cyberCyan" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#00e7ff" />
                                    <stop offset="100%" stop-color="#0066ff" />
                                </linearGradient>
                                <filter id="neonFilter" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="3" result="blur" />
                                    <feMerge>
                                        <feMergeNode in="blur" />
                                        <feMergeNode in="SourceGraphic" />
                                    </feMerge>
                                </filter>
                            </defs>

                            <!-- ============================================== -->
                            <!-- LAYER 1: OUTER CONCENTRIC DIAL (Clockwise 45s) -->
                            <!-- ============================================== -->
                            <g class="animate-spin-slow origin-center">
                                <!-- Outermost Track -->
                                <circle cx="300" cy="300" r="280" stroke="rgba(255,255,255,0.12)" stroke-width="1.5" stroke-dasharray="6 14" />
                                <circle cx="300" cy="300" r="265" stroke="rgba(241,90,36,0.25)" stroke-width="1" />

                                <!-- Calibrated Degree Ticks -->
                                <?php for ($i = 0; $i < 360; $i += 15): 
                                    $rad = deg2rad($i);
                                    $x1 = 300 + 265 * cos($rad);
                                    $y1 = 300 + 265 * sin($rad);
                                    $len = ($i % 45 === 0) ? 14 : 7;
                                    $x2 = 300 + (265 - $len) * cos($rad);
                                    $y2 = 300 + (265 - $len) * sin($rad);
                                    $color = ($i % 90 === 0) ? "#f15a24" : "rgba(255,255,255,0.2)";
                                ?>
                                    <line x1="<?php echo $x1; ?>" y1="<?php echo $y1; ?>" x2="<?php echo $x2; ?>" y2="<?php echo $y2; ?>" stroke="<?php echo $color; ?>" stroke-width="<?php echo ($i % 90 === 0) ? '2' : '1'; ?>" />
                                <?php endfor; ?>

                                <!-- Segmented Perimeter Arcs -->
                                <path d="M 300 20 A 280 280 0 0 1 542 160" stroke="#f15a24" stroke-width="3" stroke-linecap="round" filter="url(#neonFilter)" />
                                <path d="M 300 580 A 280 280 0 0 1 58 440" stroke="#f15a24" stroke-width="3" stroke-linecap="round" filter="url(#neonFilter)" />
                                
                                <!-- Outer Circuit Nodes -->
                                <circle cx="542" cy="160" r="5" fill="#f15a24" />
                                <circle cx="58" cy="440" r="5" fill="#f15a24" />
                                <circle cx="498" cy="498" r="4" fill="rgba(255,255,255,0.4)" />
                                <circle cx="102" cy="102" r="4" fill="rgba(255,255,255,0.4)" />
                            </g>

                            <!-- ==================================================== -->
                            <!-- LAYER 2: MIDDLE REVERSE DIAL (Counter-Clockwise 35s) -->
                            <!-- ==================================================== -->
                            <g class="animate-spin-reverse-slow origin-center">
                                <!-- Middle Ring -->
                                <circle cx="300" cy="300" r="215" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" />
                                <circle cx="300" cy="300" r="185" stroke="rgba(241,90,36,0.3)" stroke-width="1" stroke-dasharray="10 18" />

                                <!-- Interconnecting Circuit Traces -->
                                <path d="M 300 85 L 300 115 L 360 175" stroke="rgba(255,255,255,0.25)" stroke-width="1.5" fill="none" />
                                <path d="M 515 300 L 485 300 L 425 360" stroke="rgba(255,255,255,0.25)" stroke-width="1.5" fill="none" />
                                <path d="M 300 515 L 300 485 L 240 425" stroke="rgba(255,255,255,0.25)" stroke-width="1.5" fill="none" />
                                <path d="M 85 300 L 115 300 L 175 240" stroke="rgba(255,255,255,0.25)" stroke-width="1.5" fill="none" />

                                <!-- Department Discipline Glyph Nodes on Middle Ring -->
                                <!-- 1. Microchip / Electronics (Top, 0°) -->
                                <g transform="translate(300, 85)">
                                    <rect x="-14" y="-14" width="28" height="28" rx="6" fill="#101518" stroke="#f15a24" stroke-width="2" />
                                    <!-- Mini chip legs -->
                                    <line x1="-8" y1="-14" x2="-8" y2="-18" stroke="#f15a24" stroke-width="1.5" />
                                    <line x1="0" y1="-14" x2="0" y2="-18" stroke="#f15a24" stroke-width="1.5" />
                                    <line x1="8" y1="-14" x2="8" y2="-18" stroke="#f15a24" stroke-width="1.5" />
                                    <circle cx="0" cy="0" r="4" fill="#f15a24" />
                                </g>

                                <!-- 2. Mechanical Gear / Robotics (Right, 90°) -->
                                <g transform="translate(515, 300)">
                                    <circle cx="0" cy="0" r="14" fill="#101518" stroke="#f15a24" stroke-width="2" />
                                    <circle cx="0" cy="0" r="6" stroke="#f15a24" stroke-width="1.5" fill="none" />
                                    <path d="M-2 -15 L2 -15 M-2 15 L2 15 M-15 -2 L-15 2 M15 -2 L15 2" stroke="#f15a24" stroke-width="2" stroke-linecap="round" />
                                </g>

                                <!-- 3. Chemical Beaker / Materials (Bottom, 180°) -->
                                <g transform="translate(300, 515)">
                                    <rect x="-14" y="-14" width="28" height="28" rx="6" fill="#101518" stroke="#f15a24" stroke-width="2" />
                                    <!-- Flask icon -->
                                    <path d="M -4 -8 L 4 -8 L 4 -4 L 8 6 C 9 8, 8 10, 6 10 L -6 10 C -8 10, -9 8, -8 6 L -4 -4 Z" fill="none" stroke="#f15a24" stroke-width="1.5" />
                                </g>

                                <!-- 4. Civil Structure / Waves (Left, 270°) -->
                                <g transform="translate(85, 300)">
                                    <circle cx="0" cy="0" r="14" fill="#101518" stroke="#f15a24" stroke-width="2" />
                                    <!-- Radio / Radar waves -->
                                    <path d="M-6 4 A 8 8 0 0 1 -6 -4" stroke="#f15a24" stroke-width="1.5" fill="none" stroke-linecap="round" />
                                    <path d="M-2 7 A 12 12 0 0 1 -2 -7" stroke="#f15a24" stroke-width="1.5" fill="none" stroke-linecap="round" />
                                    <circle cx="-9" cy="0" r="2" fill="#f15a24" />
                                </g>
                            </g>

                            <!-- ============================================== -->
                            <!-- LAYER 3: STATIONARY & PULSING CYBERNETIC CORE   -->
                            <!-- ============================================== -->
                            <!-- Central HUD Rings -->
                            <circle cx="300" cy="300" r="125" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                            <circle cx="300" cy="300" r="110" fill="#101518" stroke="url(#orangeGlow)" stroke-width="2" />
                            <circle cx="300" cy="300" r="95" stroke="rgba(241,90,36,0.3)" stroke-dasharray="5 5" stroke-width="1" />

                            <!-- Crosshair Graticule -->
                            <line x1="160" y1="300" x2="200" y2="300" stroke="#f15a24" stroke-width="1.5" />
                            <line x1="400" y1="300" x2="440" y2="300" stroke="#f15a24" stroke-width="1.5" />
                            <line x1="300" y1="160" x2="300" y2="200" stroke="#f15a24" stroke-width="1.5" />
                            <line x1="300" y1="400" x2="300" y2="440" stroke="#f15a24" stroke-width="1.5" />

                            <!-- Core Inner Glowing Emblem Shield -->
                            <circle cx="300" cy="300" r="70" fill="#080b0d" stroke="rgba(255,255,255,0.15)" stroke-width="1" />
                            <circle cx="300" cy="300" r="68" fill="url(#orangeGlow)" fill-opacity="0.12" />

                            <!-- Stylized Dynamic Bolt / Monogram Core -->
                            <path d="M 305 248 L 278 296 L 298 296 L 292 352 L 324 298 L 304 298 Z" fill="url(#orangeGlow)" filter="url(#neonFilter)" />

                            <!-- Core Radial Status -->
                            <text x="300" y="380" text-anchor="middle" font-family="'JetBrains Mono', monospace" font-size="10" fill="#f15a24" letter-spacing="3" font-weight="600">SENTEC // 1997</text>
                        </svg>

                        <!-- HUD Readout Tags on Corners -->
                        <div class="absolute bottom-2 left-2 px-2.5 py-1 rounded bg-[#080b0d]/90 border border-white/[0.08] text-[10px] font-mono text-neutral-400">
                            COORD: 24.93° N
                        </div>
                        <div class="absolute top-2 right-2 px-2.5 py-1 rounded bg-[#080b0d]/90 border border-[#f15a24]/30 text-[10px] font-mono text-[#f15a24]">
                            SYSTEM ONLINE
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 2. ABOUT / WHO WE ARE SECTION (THE BRIEF)                                 -->
    <!-- ========================================================================= -->
    <section class="py-20 border-b border-white/[0.08] bg-[#080b0d]" id="about">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-4 space-y-4">
                    <div class="inline-flex items-center gap-2 text-xs font-mono text-[#f15a24] tracking-widest uppercase">
                        <span>00</span>
                        <span class="text-neutral-600">//</span>
                        <span>THE BRIEF</span>
                    </div>
                    <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-white leading-tight">
                        Move from <span class="text-neutral-400 font-normal">“what if?”</span><br>
                        to <span class="text-[#f15a24]">“watch this.”</span>
                    </h2>
                </div>

                <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                    <p class="md:col-span-8 text-neutral-300 font-sans text-base leading-relaxed">
                        The Society for Promotion of Science Engineering and Technology (SENTEC) stands as the definitive Science and Technology Society of NED University. Throughout nearly three decades, we have served as the proving ground for undergraduate engineers to translate theoretical concepts into operating hardware, competitive software, and published research.
                    </p>
                    
                    <!-- Stats Badge -->
                    <div class="md:col-span-4 p-6 rounded-xl bg-[#101518] border border-white/[0.08] text-center space-y-1">
                        <span class="text-xs font-mono text-neutral-500">ESTABLISHED</span>
                        <div class="font-display text-4xl font-extrabold text-[#f15a24]">1997</div>
                        <p class="text-xs font-mono text-neutral-400">29 Years of Applied Engineering</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 3. UPCOMING EVENTS (LIVE PHP DATABASE LOOP PRESERVED)                      -->
    <!-- ========================================================================= -->
    <section class="py-20 border-b border-white/[0.08] bg-[#0a0f12]" id="events">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-12 gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 text-xs font-mono text-[#f15a24] tracking-widest uppercase mb-2">
                        <span>01</span>
                        <span class="text-neutral-600">//</span>
                        <span>CALENDAR</span>
                    </div>
                    <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-white">Upcoming Events</h2>
                </div>
                <div class="text-xs font-mono text-neutral-400">
                    LIVE DB SYNCHRONIZATION // ACTIVE
                </div>
            </div>

            <!-- Dynamic Event Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $title = htmlspecialchars($row['title']);
                        $date = date('d M Y', strtotime($row['event_date']));
                        $image = str_replace('../', './', $row['image_url']);
                        $event_link = !empty($row['event_link']) ? htmlspecialchars($row['event_link']) : '#';
                        $target = ($event_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : '';
                        $isProxion = (stripos($title, 'PROXION') !== false);
                        $desc = htmlspecialchars(substr($row['description'], 0, $isProxion ? 220 : 130)) . '...';
                        ?>
                        
                        <!-- Event Card -->
                        <div class="<?php echo $isProxion ? 'md:col-span-3' : 'md:col-span-1'; ?>">
                            <?php if ($isProxion): ?>
                                <!-- Flagship PROXION Card -->
                                <a href="<?php echo $event_link; ?>" <?php echo $target; ?> class="group block rounded-2xl bg-[#101518] border border-[#f15a24]/30 hover:border-[#f15a24] overflow-hidden transition-all duration-300 hover:shadow-[0_10px_35px_rgba(241,90,36,0.2)]">
                                    <div class="grid grid-cols-1 lg:grid-cols-12">
                                        <div class="lg:col-span-6 h-64 lg:h-96 relative overflow-hidden bg-neutral-900">
                                            <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='images/hero/1.webp'">
                                            <div class="absolute top-4 left-4">
                                                <span class="px-3.5 py-1.5 rounded-full text-xs font-mono font-bold bg-[#f15a24] text-[#080b0d]">FLAGSHIP OLYMPIAD</span>
                                            </div>
                                        </div>
                                        <div class="lg:col-span-6 p-8 lg:p-12 flex flex-col justify-center space-y-4">
                                            <div class="text-xs font-mono text-[#00e7ff] flex items-center gap-2">
                                                <i class="far fa-calendar-alt"></i>
                                                <span><?php echo $date; ?></span>
                                            </div>
                                            <h3 class="font-display text-2xl lg:text-3xl font-extrabold text-white group-hover:text-[#f15a24] transition-colors">
                                                <?php echo $title; ?>
                                            </h3>
                                            <p class="text-sm text-neutral-300 leading-relaxed font-sans">
                                                <?php echo $desc; ?>
                                            </p>
                                            <div class="pt-4 flex items-center gap-2 text-xs font-mono font-bold text-[#f15a24] group-hover:translate-x-1 transition-transform">
                                                <span>VIEW EVENT DETAILS & REGISTER</span>
                                                <i class="fas fa-arrow-right"></i>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php else: ?>
                                <!-- Standard Event Card -->
                                <a href="<?php echo $event_link; ?>" <?php echo $target; ?> class="group flex flex-col h-full rounded-xl bg-[#101518] border border-white/[0.08] hover:border-[#f15a24]/50 overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                                    <div class="h-48 relative overflow-hidden bg-neutral-900">
                                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='images/hero/2.webp'">
                                        <div class="absolute top-3 left-3">
                                            <span class="px-2.5 py-1 rounded text-[11px] font-mono bg-[#080b0d]/90 text-neutral-300 border border-white/[0.1]">
                                                <?php echo $date; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                                        <div>
                                            <h3 class="font-display text-xl font-bold text-white group-hover:text-[#f15a24] transition-colors mb-2">
                                                <?php echo $title; ?>
                                            </h3>
                                            <p class="text-xs text-neutral-400 font-sans leading-relaxed">
                                                <?php echo $desc; ?>
                                            </p>
                                        </div>
                                        <div class="text-xs font-mono text-[#f15a24] flex items-center gap-1.5 pt-2">
                                            <span>Learn more</span>
                                            <i class="fas fa-arrow-right text-[10px]"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php
                    }
                } else {
                    ?>
                    <div class="col-span-3 p-12 text-center rounded-2xl bg-[#101518] border border-white/[0.08]">
                        <i class="far fa-calendar-times text-3xl text-neutral-500 mb-3"></i>
                        <h4 class="font-display text-lg font-bold text-white mb-1">No Upcoming Events Scheduled</h4>
                        <p class="text-sm text-neutral-400">Stay tuned to our social platforms for forthcoming announcements and registrations.</p>
                    </div>
                    <?php
                }
                ?>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 4. FACULTY INCHARGE SECTION                                               -->
    <!-- ========================================================================= -->
    <section class="py-20 border-b border-white/[0.08] bg-[#080b0d]" id="faculty">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="max-w-4xl mx-auto rounded-2xl bg-[#101518] border border-white/[0.08] p-8 sm:p-12 relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-[#f15a24]/5 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col sm:flex-row items-center gap-8 sm:gap-12 relative z-10">
                    <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-full border-2 border-[#f15a24] p-1.5 flex-shrink-0">
                        <img src="images/facinc.png" alt="Prof. Dr. Murtuza" class="w-full h-full object-cover rounded-full bg-neutral-800" onerror="this.src='images/favicon2.png'">
                    </div>
                    <div class="space-y-4 text-center sm:text-left">
                        <i class="fas fa-quote-left text-2xl text-[#f15a24]/50"></i>
                        <p class="text-base sm:text-lg text-neutral-200 font-sans italic leading-relaxed">
                            "A good balance between academics and activities is crucial. SENTEC can be a great platform for students to develop their capabilities as engineering graduates."
                        </p>
                        <div>
                            <h3 class="font-display text-xl font-bold text-white">Prof. Dr. Murtuza</h3>
                            <span class="text-xs font-mono text-[#f15a24] tracking-wider uppercase">Faculty Incharge, SENTEC NEDUET</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 5. CONTACT & FAQ SECTION                                                  -->
    <!-- ========================================================================= -->
    <section class="py-20 bg-[#0a0f12]" id="contact">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                
                <!-- Left: Quick Contact CTA -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="inline-flex items-center gap-2 text-xs font-mono text-[#f15a24] tracking-widest uppercase">
                        <span>02</span>
                        <span class="text-neutral-600">//</span>
                        <span>INQUIRIES</span>
                    </div>
                    <h2 class="font-display text-4xl sm:text-5xl font-extrabold text-white leading-tight">
                        Get In<br><span class="text-[#f15a24]">Touch.</span>
                    </h2>
                    <p class="text-sm text-neutral-400 font-sans leading-relaxed">
                        Have questions regarding event registrations, society recruitment, or strategic corporate sponsorships? Our executive board is ready to collaborate.
                    </p>
                    <div>
                        <a href="contact.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-md bg-[#f15a24] hover:bg-[#ff6b35] text-[#080b0d] font-bold text-xs font-mono uppercase tracking-wider transition-all">
                            <span>Open Inquiries Portal</span>
                            <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- Right: Accordion FAQs -->
                <div class="lg:col-span-7 space-y-3 font-sans">
                    <details class="group rounded-xl bg-[#101518] border border-white/[0.08] p-5 cursor-pointer open:border-[#f15a24]/40 transition-colors">
                        <summary class="font-display text-base font-bold text-white flex items-center justify-between list-none">
                            <span>How do I join SENTEC as a team member?</span>
                            <span class="text-[#f15a24] group-open:rotate-180 transition-transform"><i class="fas fa-chevron-down text-xs"></i></span>
                        </summary>
                        <p class="text-xs sm:text-sm text-neutral-400 pt-3 leading-relaxed">
                            Recruitment drives are held annually at the start of the academic cycle for NED University students across all engineering disciplines. Stay tuned to our social platforms and notice boards.
                        </p>
                    </details>

                    <details class="group rounded-xl bg-[#101518] border border-white/[0.08] p-5 cursor-pointer open:border-[#f15a24]/40 transition-colors">
                        <summary class="font-display text-base font-bold text-white flex items-center justify-between list-none">
                            <span>Where is the SENTEC central office located?</span>
                            <span class="text-[#f15a24] group-open:rotate-180 transition-transform"><i class="fas fa-chevron-down text-xs"></i></span>
                        </summary>
                        <p class="text-xs sm:text-sm text-neutral-400 pt-3 leading-relaxed">
                            We are headquartered at the Student Affairs Department building within the NED University Main Campus on University Road, Karachi.
                        </p>
                    </details>

                    <details class="group rounded-xl bg-[#101518] border border-white/[0.08] p-5 cursor-pointer open:border-[#f15a24]/40 transition-colors">
                        <summary class="font-display text-base font-bold text-white flex items-center justify-between list-none">
                            <span>Do I require prior technical experience to participate?</span>
                            <span class="text-[#f15a24] group-open:rotate-180 transition-transform"><i class="fas fa-chevron-down text-xs"></i></span>
                        </summary>
                        <p class="text-xs sm:text-sm text-neutral-400 pt-3 leading-relaxed">
                            Not at all! SENTEC events and workshops are organized with tiered learning tracks, mentorship sessions, and beginner-friendly modules to help build skills from ground zero.
                        </p>
                    </details>
                </div>

            </div>
        </div>
    </section>

</div>

<?php 
if ($conn) {
    $conn->close();
}
include 'footer.php'; 
?>