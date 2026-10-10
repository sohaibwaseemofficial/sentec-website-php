<?php
/**
 * SENTEC Official Events & Competitions Portal
 * Displays Live/Upcoming Events and Historical Archives with Interactive Filtering.
 */
require_once __DIR__ . '/cache_utils.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/image_utils.php';

// Auto-sync status based on event date
if (isset($conn) && !$conn->connect_error) {
    @$conn->query("UPDATE events SET status = 'past' WHERE event_date < NOW() AND status = 'upcoming'");
    @$conn->query("UPDATE events SET status = 'upcoming' WHERE event_date >= NOW() AND status = 'past'");
}

// Fetch all events
$events = [];
if (isset($conn) && !$conn->connect_error) {
    $res = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
    }
}

// Split into upcoming and past
$upcomingEvents = [];
$pastEvents = [];
$nowTs = time();

foreach ($events as $ev) {
    $evDateTs = !empty($ev['event_date']) ? strtotime($ev['event_date']) : 0;
    $isPast = (($ev['status'] ?? '') === 'past') || ($evDateTs > 0 && $evDateTs < ($nowTs - 86400));
    if ($isPast) {
        $pastEvents[] = $ev;
    } else {
        $upcomingEvents[] = $ev;
    }
}

// Extract distinct categories
$categories = [];
foreach ($events as $ev) {
    $cat = trim($ev['category'] ?? '');
    if (!empty($cat) && !in_array($cat, $categories)) {
        $categories[] = $cat;
    }
}

include 'header.php';
?>

<!-- Industrial CSS matching SENTEC design system -->
<style>
    :root {
        --ink: #080b0d;
        --ink-soft: #101518;
        --panel: #151c20;
        --paper: #f4f1eb;
        --muted: #9aa3a3;
        --muted-dark: #5d696c;
        --steel: #7b9096;
        --orange: #f15a24;
        --orange-light: #ff8050;
        --line: rgba(235, 241, 237, 0.14);
        --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
    }

    /* Ambient Blueprint Grid */
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
        mask-image: linear-gradient(to bottom, black 0%, black 80%, transparent 95%);
        -webkit-mask-image: linear-gradient(to bottom, black 0%, black 80%, transparent 95%);
    }

    .events-page-wrap {
        position: relative;
        z-index: 1;
        width: min(1200px, 100%);
        margin: 0 auto;
        padding: clamp(100px, 10vw, 130px) clamp(20px, 4vw, 40px) 120px;
    }

    /* Hero Section */
    .events-hero {
        margin-bottom: 50px;
        border-bottom: 1px solid var(--line);
        padding-bottom: 45px;
    }
    .events-hero-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 30px;
        align-items: flex-end;
    }
    @media (max-width: 868px) {
        .events-hero-grid {
            grid-template-columns: 1fr;
        }
    }
    .events-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font: 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--orange);
        margin-bottom: 14px;
    }
    .events-eyebrow i {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: var(--orange);
        border-radius: 50%;
        box-shadow: 0 0 10px var(--orange);
    }
    .events-hero h1 {
        margin: 0 0 18px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.5rem, 5vw, 4.4rem);
        line-height: 1.02;
        letter-spacing: -0.05em;
        font-weight: 700;
        color: var(--paper);
    }
    .events-hero h1 em {
        font-style: normal;
        color: var(--orange);
    }
    .events-hero p {
        max-width: 680px;
        margin: 0;
        color: var(--muted);
        font-size: 16px;
        line-height: 1.65;
    }
    .events-hero-stats {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }
    .stat-pill {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--line);
        padding: 12px 18px;
        border-radius: 6px;
        text-align: right;
    }
    .stat-pill-num {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 26px;
        font-weight: 700;
        color: var(--paper);
        line-height: 1;
    }
    .stat-pill-label {
        font: 10px 'IBM Plex Mono', monospace;
        color: var(--muted);
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-top: 4px;
    }

    /* Filter & Search Bar */
    .events-controls {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 40px;
        padding: 22px;
        background: rgba(16, 21, 24, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid var(--line);
        border-radius: 8px;
    }
    .controls-top-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    /* Status Tabs */
    .status-tabs {
        display: flex;
        gap: 8px;
        background: rgba(0, 0, 0, 0.4);
        padding: 4px;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    .status-tab-btn {
        background: transparent;
        border: none;
        color: var(--muted);
        font: 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 9px 18px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .status-tab-btn:hover {
        color: var(--paper);
    }
    .status-tab-btn.active {
        background: var(--orange);
        color: #fff;
        font-weight: 600;
        box-shadow: 0 0 16px rgba(241, 90, 36, 0.35);
    }
    .status-tab-btn .badge-count {
        background: rgba(0, 0, 0, 0.25);
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 10px;
    }
    .status-tab-btn.active .badge-count {
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
    }

    /* Live Pulse Dot */
    .live-dot {
        width: 8px;
        height: 8px;
        background: #00ff94;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 8px #00ff94;
        animation: pulse-live 1.8s infinite;
    }
    @keyframes pulse-live {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.85); }
    }

    /* Search Box */
    .events-search-wrap {
        position: relative;
        min-width: 260px;
        flex-grow: 1;
        max-width: 360px;
    }
    .events-search-input {
        width: 100%;
        background: rgba(8, 11, 13, 0.85);
        border: 1px solid var(--line);
        color: var(--paper);
        font-family: inherit;
        font-size: 13px;
        padding: 10px 14px 10px 38px;
        border-radius: 6px;
        outline: none;
        transition: border-color 0.2s;
    }
    .events-search-input:focus {
        border-color: var(--orange);
        box-shadow: 0 0 12px rgba(241, 90, 36, 0.25);
    }
    .events-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: 13px;
        pointer-events: none;
    }

    /* Category Filter Pills */
    .category-pills-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: 16px;
    }
    .category-pills-label {
        font: 10px 'IBM Plex Mono', monospace;
        color: var(--muted);
        letter-spacing: 0.12em;
        text-transform: uppercase;
        margin-right: 6px;
    }
    .category-pill {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--muted);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .category-pill:hover {
        border-color: rgba(241, 90, 36, 0.4);
        color: var(--paper);
    }
    .category-pill.active {
        background: rgba(241, 90, 36, 0.15);
        border-color: var(--orange);
        color: var(--orange);
        font-weight: 600;
    }

    /* Event Grid & Cards */
    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 28px;
    }
    @media (max-width: 480px) {
        .events-grid {
            grid-template-columns: 1fr;
        }
    }

    .event-card {
        position: relative;
        padding: 24px;
        border: 1px solid var(--line);
        background: rgba(13, 18, 21, 0.75);
        backdrop-filter: blur(14px);
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.25s var(--ease-out), border-color 0.25s var(--ease-out), box-shadow 0.25s var(--ease-out);
        min-height: 480px;
    }
    .event-card::after {
        content: "";
        position: absolute;
        top: -1px;
        right: -1px;
        width: 46px;
        height: 46px;
        border-top: 1px solid var(--orange);
        border-right: 1px solid var(--orange);
        border-top-right-radius: 6px;
        pointer-events: none;
    }
    .event-card:hover {
        border-color: rgba(241, 90, 36, 0.5);
        transform: translateY(-5px);
        box-shadow: 0 18px 38px rgba(0, 0, 0, 0.45);
    }

    .event-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        font: 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .event-date-stamp {
        color: var(--orange);
        font-weight: 600;
    }
    .event-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 9px;
        font-weight: 600;
        letter-spacing: 0.12em;
    }
    .badge-upcoming {
        background: rgba(0, 255, 148, 0.12);
        color: #00ff94;
        border: 1px solid rgba(0, 255, 148, 0.3);
    }
    .badge-past {
        background: rgba(255, 255, 255, 0.05);
        color: var(--steel);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Event Image Box */
    .event-img-box {
        position: relative;
        width: 100%;
        height: 200px;
        border-radius: 4px;
        overflow: hidden;
        border: 1px solid rgba(241, 90, 36, 0.28);
        background: radial-gradient(circle at center, rgba(241, 90, 36, 0.08) 0%, rgba(8, 11, 13, 0.95) 100%);
        margin-bottom: 18px;
    }
    .event-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
        transition: transform 0.4s var(--ease-out), filter 0.35s ease;
        filter: brightness(0.92) contrast(1.05);
    }
    .event-card:hover .event-img {
        transform: scale(1.06);
        filter: brightness(1.04) contrast(1.08);
    }
    .event-img-gradient {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(8, 11, 13, 0) 50%, rgba(8, 11, 13, 0.8) 100%);
        pointer-events: none;
    }

    /* Event Content */
    .event-card-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: space-between;
    }
    .event-category-tag {
        color: var(--muted);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.13em;
        text-transform: uppercase;
        display: block;
        margin-bottom: 10px;
    }
    .event-card-title {
        margin: 0 0 12px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.6rem, 2.5vw, 2.1rem);
        line-height: 1.1;
        letter-spacing: -0.04em;
        font-weight: 600;
        color: var(--paper);
    }
    .event-card-desc {
        color: var(--muted);
        line-height: 1.6;
        font-size: 13.5px;
        margin-bottom: 24px;
    }

    /* Action Links */
    .event-card-footer {
        margin-top: auto;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-event-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--orange);
        font: 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        text-decoration: none;
        font-weight: 600;
        transition: gap 0.2s, color 0.2s;
    }
    .btn-event-action:hover {
        color: var(--orange-light);
        gap: 12px;
    }
    .btn-event-closed {
        color: var(--steel);
        font: 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Empty Results Container */
    .events-empty-box {
        grid-column: 1 / -1;
        padding: 60px 30px;
        text-align: center;
        border: 1px dashed var(--line);
        background: rgba(16, 21, 24, 0.4);
        border-radius: 8px;
    }
    .events-empty-box i {
        font-size: 38px;
        color: var(--muted-dark);
        margin-bottom: 16px;
    }
    .events-empty-box h3 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 20px;
        color: var(--paper);
        margin: 0 0 8px;
    }
    .events-empty-box p {
        color: var(--muted);
        font-size: 14px;
        max-width: 440px;
        margin: 0 auto 20px;
    }
</style>

<div class="ambient-grid"></div>

<div class="events-page-wrap">
    
    <!-- Top Hero Section -->
    <header class="events-hero">
        <div class="events-hero-grid">
            <div>
                <span class="events-eyebrow">
                    <i></i> 02 // PUBLIC EVENTS & EXPERIENCES
                </span>
                <h1>
                    Events &<br>
                    <em>Olympiads.</em>
                </h1>
                <p>
                    From multi-disciplinary technical competitions and robotics challenges to soulful cultural qawwali evenings, discover all active and past flagship happenings hosted by SENTEC at NED University.
                </p>
            </div>
            <div class="events-hero-stats">
                <div class="stat-pill">
                    <div class="stat-pill-num"><?php echo count($upcomingEvents); ?></div>
                    <div class="stat-pill-label">Live / Upcoming</div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-num"><?php echo count($pastEvents); ?></div>
                    <div class="stat-pill-label">Past Archive</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Filter & Search Controls -->
    <section class="events-controls">
        <div class="controls-top-row">
            <!-- Status Tabs -->
            <div class="status-tabs" role="tablist">
                <button type="button" class="status-tab-btn active" data-status="all">
                    <span>All Events</span>
                    <span class="badge-count"><?php echo count($events); ?></span>
                </button>
                <button type="button" class="status-tab-btn" data-status="upcoming">
                    <span class="live-dot"></span>
                    <span>Live & Upcoming</span>
                    <span class="badge-count"><?php echo count($upcomingEvents); ?></span>
                </button>
                <button type="button" class="status-tab-btn" data-status="past">
                    <span>Past Archive</span>
                    <span class="badge-count"><?php echo count($pastEvents); ?></span>
                </button>
            </div>

            <!-- Real-time Search Box -->
            <div class="events-search-wrap">
                <i class="fas fa-search events-search-icon"></i>
                <input type="text" id="eventSearchInput" class="events-search-input" placeholder="Search event by name or keyword...">
            </div>
        </div>

        <!-- Category Pills -->
        <?php if (!empty($categories)): ?>
        <div class="category-pills-row">
            <span class="category-pills-label">Filter Category:</span>
            <button type="button" class="category-pill active" data-category="all">All</button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="category-pill" data-category="<?php echo htmlspecialchars(strtolower($cat)); ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Events Grid -->
    <div class="events-grid" id="eventsGrid">
        <?php if (!empty($events)): ?>
            <?php foreach ($events as $idx => $ev): 
                $evId = (int)($ev['id'] ?? 0);
                $evTitle = htmlspecialchars($ev['title'] ?? 'Event');
                $evCategory = htmlspecialchars($ev['category'] ?? 'General');
                $evDesc = htmlspecialchars($ev['description'] ?? '');
                $evDateRaw = $ev['event_date'] ?? '';
                $evDateTs = !empty($evDateRaw) ? strtotime($evDateRaw) : 0;
                $evDateFormatted = $evDateTs > 0 ? date('M d, Y', $evDateTs) : 'TBA';
                
                $isPast = (($ev['status'] ?? '') === 'past') || ($evDateTs > 0 && $evDateTs < ($nowTs - 86400));
                $evStatusKey = $isPast ? 'past' : 'upcoming';
                
                $evRawImg = trim((string)($ev['image_url'] ?? ''));
                $evImage = !empty($evRawImg) ? resolve_image_url($evRawImg, '', '') : '';
                
                $regLink = !empty($ev['event_link']) ? htmlspecialchars($ev['event_link']) : 'event_registration?id='.$evId;
            ?>
                <article class="event-card" 
                         data-status="<?php echo $evStatusKey; ?>" 
                         data-category="<?php echo htmlspecialchars(strtolower($evCategory)); ?>"
                         data-title="<?php echo htmlspecialchars(strtolower($ev['title'] ?? '')); ?>"
                         data-desc="<?php echo htmlspecialchars(strtolower($ev['description'] ?? '')); ?>">
                    
                    <div>
                        <!-- Header with date & status -->
                        <div class="event-card-header">
                            <span class="event-date-stamp"><?php echo $evDateFormatted; ?></span>
                            <?php if ($isPast): ?>
                                <span class="event-status-badge badge-past">
                                    <i class="fas fa-history"></i> CONCLUDED
                                </span>
                            <?php else: ?>
                                <span class="event-status-badge badge-upcoming">
                                    <span class="live-dot"></span> REGISTRATIONS ACTIVE
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Framed Image Box -->
                        <?php if (!empty($evImage)): ?>
                        <div class="event-img-box">
                            <img src="<?php echo htmlspecialchars($evImage); ?>" alt="<?php echo $evTitle; ?>" class="event-img" loading="lazy" onerror="this.closest('.event-img-box').style.display='none';">
                            <div class="event-img-gradient"></div>
                        </div>
                        <?php endif; ?>

                        <!-- Card Body -->
                        <div class="event-card-body">
                            <div>
                                <span class="event-category-tag"><?php echo $evCategory; ?></span>
                                <h3 class="event-card-title"><?php echo $evTitle; ?></h3>
                                <p class="event-card-desc"><?php echo $evDesc; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="event-card-footer">
                        <?php if ($isPast): ?>
                            <span class="btn-event-closed">
                                <i class="fas fa-lock"></i> Registrations Closed
                            </span>
                            <a href="gallery" class="btn-event-action" style="color: var(--steel);">
                                <span>View Gallery</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo $regLink; ?>" class="btn-event-action">
                                <span>Register Now</span>
                                <i class="fas fa-arrow-up-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Empty Results Message (Toggled via JS) -->
        <div class="events-empty-box" id="eventsEmptyBox" style="<?php echo empty($events) ? 'display:block;' : 'display:none;'; ?>">
            <i class="fas fa-calendar-times"></i>
            <h3>No Events Found</h3>
            <p>No events match the selected status or search filter criteria.</p>
            <button type="button" class="btn-neon-solid" id="resetFiltersBtn" style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; font-size:12px; cursor:pointer;">
                <i class="fas fa-sync-alt"></i> Reset All Filters
            </button>
        </div>

    </div>

</div>

<!-- Client-side Interactive Filtering Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusTabs = document.querySelectorAll('.status-tab-btn');
    const categoryPills = document.querySelectorAll('.category-pill');
    const searchInput = document.getElementById('eventSearchInput');
    const eventCards = document.querySelectorAll('.event-card');
    const emptyBox = document.getElementById('eventsEmptyBox');
    const resetBtn = document.getElementById('resetFiltersBtn');

    let currentStatus = 'all';
    let currentCategory = 'all';
    let currentQuery = '';

    // Check URL parameters (e.g. ?tab=upcoming or ?cat=qawwali)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        const tabParam = urlParams.get('tab').toLowerCase();
        if (['upcoming', 'past', 'all'].includes(tabParam)) {
            currentStatus = tabParam;
            statusTabs.forEach(tab => {
                tab.classList.toggle('active', tab.getAttribute('data-status') === currentStatus);
            });
        }
    }
    if (urlParams.has('cat')) {
        const catParam = urlParams.get('cat').toLowerCase();
        currentCategory = catParam;
        categoryPills.forEach(pill => {
            pill.classList.toggle('active', pill.getAttribute('data-category') === currentCategory);
        });
    }

    function applyFilters() {
        let visibleCount = 0;

        eventCards.forEach(card => {
            const cardStatus = card.getAttribute('data-status');
            const cardCat = card.getAttribute('data-category');
            const cardTitle = card.getAttribute('data-title') || '';
            const cardDesc = card.getAttribute('data-desc') || '';

            // Match Status
            const matchStatus = (currentStatus === 'all') || (cardStatus === currentStatus);

            // Match Category
            const matchCat = (currentCategory === 'all') || (cardCat === currentCategory);

            // Match Search
            const matchQuery = !currentQuery || cardTitle.includes(currentQuery) || cardDesc.includes(currentQuery) || cardCat.includes(currentQuery);

            if (matchStatus && matchCat && matchQuery) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (emptyBox) {
            emptyBox.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    // Status Tab Click Handler
    statusTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            statusTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.getAttribute('data-status');
            applyFilters();
        });
    });

    // Category Pill Click Handler
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function () {
            categoryPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-category');
            applyFilters();
        });
    });

    // Search Input Handler
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentQuery = this.value.trim().toLowerCase();
            applyFilters();
        });
    }

    // Reset Filters Button
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            currentStatus = 'all';
            currentCategory = 'all';
            currentQuery = '';
            if (searchInput) searchInput.value = '';

            statusTabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-status') === 'all'));
            categoryPills.forEach(p => p.classList.toggle('active', p.getAttribute('data-category') === 'all'));

            applyFilters();
        });
    }

    // Run initial filter check
    applyFilters();
});
</script>

<?php include 'footer.php'; ?>
