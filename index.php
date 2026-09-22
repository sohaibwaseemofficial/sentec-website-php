<?php
require_once __DIR__ . '/cache_utils.php';
include 'header.php';

// Quick Contact Handler (Supports both form POST and AJAX)
$contactFeedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_contact') {
    require_once __DIR__ . '/db_connection.php';
    $cName = trim($_POST['name'] ?? '');
    $cEmail = trim($_POST['email'] ?? '');
    $cPhone = trim($_POST['phone'] ?? '');
    $cMsg = trim($_POST['message'] ?? '');
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if (!empty($cName) && !empty($cEmail) && !empty($cMsg)) {
        if (isset($conn) && !$conn->connect_error) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            // Insert into unified contact_messages table
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, message, ip_address) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $cName, $cEmail, $cPhone, $cMsg, $ip);
                $stmt->execute();
                $stmt->close();
            }
        }
        $response = ['success' => true, 'message' => 'Thank you! Your message has been dispatched to the SENTEC secretariat.'];
    } else {
        $response = ['success' => false, 'message' => 'Please fill in name, email, and your message.'];
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $contactFeedback = $response;
}

// Fetch live events with high-performance cache (lazy db loading)
$events = get_cached_data('public_events_data', 600, function() {
    require_once __DIR__ . '/db_connection.php';
    global $conn;
    $evList = [];
    if (isset($conn) && !$conn->connect_error) {
        $eventsQuery = $conn->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 6");
        if (!$eventsQuery) {
            return null;
        }
        if ($eventsQuery->num_rows > 0) {
            while ($row = $eventsQuery->fetch_assoc()) {
                $evList[] = $row;
            }
        }
    }
    return $evList;
});
?>

<!-- Scoped Editorial Design System matching Home.tsx & index.css 1:1 -->
<style>
            <?php else: ?>
        --ink-soft: #101518;
                    <div class="project-index">01 / SOON</div>
        --paper: #f4f1eb;
        inset: 0;
                        <h3>Coming<br>Soon</h3>
                        <p>New event details will be published here when they are ready.</p>
        opacity: 0.35;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
        background-size: 72px 72px;
        background-color: var(--ink);
        background-size: cover;
        background-position: center;
    }
    .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(
            90deg,
            rgba(8, 11, 13, 0.98) 3%,
            rgba(8, 11, 13, 0.83) 45%,
            rgba(8, 11, 13, 0.24) 100%
        );
        z-index: -1;
    }
    .hero-content-wrap {
        width: min(1320px, 100%);
        height: 100%;
        margin: auto;
        padding: 105px clamp(22px, 6vw, 92px) 60px;
        display: grid;
        grid-template-columns: minmax(330px, 0.84fr) minmax(420px, 1.16fr);
        align-items: center;
        gap: 4vw;
    }
    @media (max-width: 991px) {
        .hero-section {
            height: auto;
            max-height: none;
            padding-bottom: 60px;
        }
        .hero-content-wrap {
            grid-template-columns: 1fr;
            padding-top: 40px;
            text-align: left;
        }
    }

    .eyebrow, .section-kicker {
        color: var(--orange-light);
        font: 500 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }
    .eyebrow {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--muted);
    }
    .eyebrow-line {
        width: 34px;
        height: 1px;
        background: var(--orange);
    }
    .eyebrow-status {
        margin-left: 20px;
        color: var(--orange);
    }

    .hero-copy h1 {
        margin: 22px 0 18px;
        max-width: 680px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(3.4rem, 7vw, 7.8rem);
        line-height: 0.87;
        font-weight: 600;
        letter-spacing: -0.085em;
        color: var(--paper);
    }
    .hero-copy h1 em {
        color: var(--orange);
        font-style: normal;
    }

    .hero-lede {
        max-width: 455px;
        margin: 0 0 36px;
        color: #aeb6b3;
        font-size: clamp(15px, 1.5vw, 18px);
        line-height: 1.65;
    }

    .hero-actions {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    .button {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        min-height: 47px;
        padding: 0 18px;
        font: 500 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        border: 1px solid var(--line);
        transition: transform 0.18s var(--ease-out), background 0.18s var(--ease-out), color 0.18s var(--ease-out), border 0.18s var(--ease-out);
        text-decoration: none;
    }
    .button:active {
        transform: scale(0.97);
    }
    .button-primary {
        color: var(--ink) !important;
        background: var(--orange);
        border-color: var(--orange);
    }
    .button-primary:hover {
        background: var(--orange-light);
        border-color: var(--orange-light);
        transform: translateY(-2px);
    }
    .button-quiet {
        color: var(--paper) !important;
        border-color: transparent;
        padding-left: 0;
        background: transparent;
    }
    .button-quiet:hover {
        color: var(--orange-light) !important;
    }
    .button-outline {
        color: var(--paper) !important;
        background: transparent;
        border: 1px solid var(--line);
    }
    .button-outline:hover {
        border-color: var(--orange);
        color: var(--orange-light) !important;
    }

    .scroll-cue {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        margin-top: 28px;
        color: var(--muted);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        text-decoration: none;
    }
    .scroll-cue i {
        color: var(--orange);
    }

    .hero-proof {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-top: 32px;
        color: var(--muted-dark);
        font: 10px 'IBM Plex Mono', monospace;
        line-height: 1.6;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }
    .hero-proof span {
        color: var(--orange);
    }
    .hero-proof p {
        margin: 0;
    }
    .hero-proof strong {
        color: var(--muted);
        font-weight: 400;
    }

    .hero-side-label {
        position: absolute;
        right: clamp(22px, 3.7vw, 56px);
        bottom: 82px;
        color: var(--muted-dark);
        font: 9px 'IBM Plex Mono', monospace;
        writing-mode: vertical-rl;
        letter-spacing: 0.13em;
    }

    /* ========================================================================= */
    /* AUTHENTIC CREST STAGE & AMBIENT RADAR RINGS (From Home.tsx & index.css)   */
    /* ========================================================================= */
    .hero-visual {
        height: 100%;
        min-height: 0;
        max-height: 100%;
        overflow: hidden;
        display: grid;
        place-items: center;
        align-self: stretch;
    }
    .mark-stage {
        width: min(580px, 46vw);
        max-height: 100%;
        aspect-ratio: 0.873;
        position: relative;
        display: grid;
        place-items: center;
        overflow: hidden;
    }
    @media (max-width: 991px) {
        .mark-stage {
            width: min(440px, 88vw);
            margin: 0 auto;
        }
    }
    .mark-stage::before {
        content: "";
        position: absolute;
        inset: 5%;
        border: 1px solid rgba(244, 241, 235, 0.11);
        border-radius: 50%;
        box-shadow:
            0 0 90px rgba(241, 90, 36, 0.08),
            inset 0 0 80px rgba(123, 144, 150, 0.06);
    }

    /* Rotating Radar Rings */
    .mark-orbit {
        position: absolute;
        border: 1px solid rgba(244, 241, 235, 0.22);
        border-radius: 50%;
        pointer-events: none;
    }
    .mark-orbit-outer {
        inset: 4%;
        border-style: dashed;
        animation: spin 44s linear infinite;
    }
    .mark-orbit-mid {
        inset: 13%;
        border-color: rgba(241, 90, 36, 0.55);
        border-left-color: transparent;
        animation: spin-reverse 24s linear infinite;
    }
    .mark-orbit-inner {
        inset: 27%;
        border-color: rgba(123, 144, 150, 0.4);
        border-right-color: transparent;
        animation: spin 18s linear infinite;
    }

    /* Precision Crosshairs */
    .mark-crosshair {
        position: absolute;
        background: var(--orange);
        opacity: 0.9;
        pointer-events: none;
    }
    .crosshair-top, .crosshair-bottom {
        width: 1px;
        height: 11px;
        left: 50%;
    }
    .crosshair-top { top: 2%; }
    .crosshair-bottom { bottom: 2%; }
    .crosshair-left, .crosshair-right {
        width: 11px;
        height: 1px;
        top: 50%;
    }
    .crosshair-left { left: 2%; }
    .crosshair-right { right: 2%; }

    /* Logo Layer Stack (Official White/Silver Society Crest) */
    .reference-logo-stack {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        overflow: hidden;
        z-index: 5;
    }
    .reference-logo {
        position: absolute;
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
    }
    .reference-logo-base {
        opacity: 1;
        filter: drop-shadow(0 0 16px rgba(241, 90, 36, 0.12));
        transition: filter 0.5s ease, opacity 0.5s ease;
    }
    .mark-stage:hover .reference-logo-base {
        opacity: 1;
        filter: drop-shadow(0 0 16px rgba(241, 90, 36, 0.08));
        animation: reference-base 8s ease-in-out infinite;
    }
    .reference-logo-layer {
        opacity: 0;
        pointer-events: none;
        mix-blend-mode: screen;
    }
    .mark-stage:hover .layer-scan { animation: reference-scan 8s var(--ease-smooth) infinite; }
    .mark-stage:hover .layer-frame { animation: reference-frame 8s var(--ease-smooth) infinite; }
    .mark-stage:hover .layer-upper { animation: reference-upper 8s var(--ease-out) infinite; }
    .mark-stage:hover .layer-core { animation: reference-core 8s var(--ease-out) infinite; }
    .mark-stage:hover .layer-wordmark { animation: reference-wordmark 8s var(--ease-out) infinite; }

    .layer-scan { clip-path: inset(0 100% 0 0); }
    .layer-frame { clip-path: inset(0 0 38% 0); }
    .layer-upper { clip-path: inset(7% 0 43% 0); }
    .layer-core { clip-path: inset(24% 22% 47% 22%); }
    .layer-wordmark { clip-path: inset(68% 0 0 0); }

    .element-bolt, .element-bridge, .element-tower, .element-sensor,
    .element-monitor, .element-flask, .element-gear, .element-core {
        opacity: 0;
        filter: brightness(1.55) drop-shadow(0 0 8px rgba(241, 90, 36, 0.5));
    }
    .element-bolt { clip-path: inset(10% 67% 67% 7%); }
    .element-bridge { clip-path: inset(12% 52% 70% 19%); }
    .element-tower { clip-path: inset(4% 16% 69% 54%); }
    .element-sensor { clip-path: inset(8% 10% 68% 62%); }
    .element-monitor { clip-path: inset(20% 10% 53% 59%); }
    .element-flask { clip-path: inset(28% 66% 36% 7%); }
    .element-gear { clip-path: inset(28% 7% 17% 54%); }
    .element-core { clip-path: inset(22% 28% 30% 28%); }

    .mark-stage:hover .element-bolt { animation: element-bolt 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-bridge { animation: element-bridge 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-tower { animation: element-tower 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-sensor { animation: element-sensor 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-monitor { animation: element-monitor 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-flask { animation: element-flask 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-gear { animation: element-gear 8s var(--ease-out) infinite; }
    .mark-stage:hover .element-core { animation: element-core 8s var(--ease-out) infinite; }

    .mark-sweep {
        position: absolute;
        left: 5%;
        right: 5%;
        top: 50%;
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--orange), white, var(--orange), transparent);
        box-shadow: 0 0 12px var(--orange);
        opacity: 0;
        z-index: 6;
        transition: opacity 0.3s;
    }
    .mark-stage:hover .mark-sweep {
        animation: sweep 8s var(--ease-smooth) infinite;
    }

    .mark-caption {
        position: absolute;
        bottom: 3%;
        left: 16%;
        right: 16%;
        display: flex;
        justify-content: space-between;
        color: var(--muted-dark);
        font: 8px 'IBM Plex Mono', monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    /* Keyframes */
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    @keyframes spin-reverse {
        to { transform: rotate(-360deg); }
    }
    @keyframes reference-base {
        0%, 8% { opacity: 0.24; filter: brightness(0.62) drop-shadow(0 0 0 rgba(241, 90, 36, 0)); }
        25%, 88% { opacity: 0.86; filter: brightness(1) drop-shadow(0 0 18px rgba(241, 90, 36, 0.18)); }
        100% { opacity: 0.38; }
    }
    @keyframes reference-scan {
        0%, 8% { opacity: 0; clip-path: inset(0 100% 0 0); }
        18% { opacity: 0.95; clip-path: inset(0 70% 0 0); }
        38%, 88% { opacity: 0.08; clip-path: inset(0 0 0 0); }
        100% { opacity: 0; clip-path: inset(0 0 0 0); }
    }
    @keyframes element-bolt {
        0%, 12% { opacity: 0; transform: scale(0.92) rotate(-4deg); }
        20%, 74% { opacity: 0.92; transform: scale(1) rotate(0); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-bridge {
        0%, 18% { opacity: 0; transform: translateX(-8px); }
        26%, 74% { opacity: 0.88; transform: translateX(0); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-tower {
        0%, 23% { opacity: 0; transform: translateY(-8px); }
        31%, 74% { opacity: 0.9; transform: translateY(0); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-sensor {
        0%, 28% { opacity: 0; transform: scale(0.8); }
        36%, 74% { opacity: 1; transform: scale(1.03); }
        46%, 74% { opacity: 0.72; transform: scale(1); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-monitor {
        0%, 33% { opacity: 0; transform: translateX(9px); }
        41%, 74% { opacity: 0.9; transform: translateX(0); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-flask {
        0%, 38% { opacity: 0; transform: translateY(9px); }
        46%, 74% { opacity: 0.9; transform: translateY(0); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-gear {
        0%, 43% { opacity: 0; transform: rotate(-18deg) scale(0.88); }
        51%, 74% { opacity: 0.92; transform: rotate(0) scale(1); }
        82%, 100% { opacity: 0; }
    }
    @keyframes element-core {
        0%, 48% { opacity: 0; transform: scale(0.7); }
        56%, 66% { opacity: 1; transform: scale(1.06); }
        74% { opacity: 0.6; transform: scale(1); }
        82%, 100% { opacity: 0; }
    }
    @keyframes reference-frame {
        0%, 8% { opacity: 0; transform: scale(1.04); filter: brightness(1.4) drop-shadow(0 0 0 rgba(241, 90, 36, 0)); }
        22%, 88% { opacity: 0.8; transform: scale(1); filter: brightness(1) drop-shadow(0 0 12px rgba(241, 90, 36, 0.2)); }
        100% { opacity: 0; transform: scale(1); }
    }
    @keyframes reference-upper {
        0%, 20% { opacity: 0; transform: translateY(-12px); }
        35%, 88% { opacity: 0.72; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(0); }
    }
    @keyframes reference-core {
        0%, 33% { opacity: 0; transform: scale(0.84); }
        48%, 88% { opacity: 0.8; transform: scale(1); }
        100% { opacity: 0; transform: scale(1); }
    }
    @keyframes reference-wordmark {
        0%, 45% { opacity: 0; transform: translateY(10px); }
        58%, 88% { opacity: 0.88; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(0); }
    }
    @keyframes sweep {
        0% { opacity: 0; transform: translateY(-160px); }
        10% { opacity: 0.9; }
        90% { opacity: 0.9; }
        100% { opacity: 0; transform: translateY(160px); }
    }

    /* ========================================================================= */
    /* 2. THE BRIEF / INTRO STRIP (Matching screenshot 1)                        */
    /* ========================================================================= */
    .intro-strip {
        min-height: 620px;
        display: grid;
        grid-template-columns: 110px 1fr 280px;
        gap: clamp(30px, 6vw, 100px);
        align-items: center;
        width: min(1320px, 100%);
        margin: 0 auto;
        padding: 120px clamp(22px, 6vw, 92px) 110px;
        border-bottom: 1px solid var(--line);
        position: relative;
    }
    @media (max-width: 1024px) {
        .intro-strip {
            grid-template-columns: 1fr;
            gap: 40px;
            padding: 80px 24px;
        }
    }
    .section-rail {
        align-self: stretch;
        display: flex;
        justify-content: space-between;
        flex-direction: column;
        padding: 6px 0;
        color: var(--muted-dark);
        font: 9px 'IBM Plex Mono', monospace;
        letter-spacing: 0.16em;
        writing-mode: vertical-rl;
    }
    @media (max-width: 1024px) {
        .section-rail {
            writing-mode: horizontal-tb;
            flex-direction: row;
        }
    }
    .intro-content {
        max-width: 650px;
    }
    .intro-content h2 {
        margin: 18px 0 28px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.6rem, 5.5vw, 5.5rem);
        line-height: 0.94;
        letter-spacing: -0.075em;
        font-weight: 500;
        color: var(--paper);
    }
    .intro-content h2 span {
        color: var(--muted);
    }
    .intro-content h2 strong {
        font-weight: 500;
        color: var(--paper);
    }
    .intro-copy {
        max-width: 520px;
        margin: 0 0 28px;
        color: var(--muted);
        font-size: 16px;
        line-height: 1.75;
    }
    .text-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 7px;
        border-bottom: 1px solid rgba(241, 90, 36, 0.5);
        color: var(--paper);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        text-decoration: none;
        transition: color 0.2s, border-color 0.2s;
    }
    .text-link:hover {
        color: var(--orange);
        border-color: var(--orange);
    }
    .intro-aside {
        min-height: 380px;
        position: relative;
        padding: 30px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border: 1px solid var(--line);
        background-color: #101518;
        background-size: cover;
        background-position: center;
    }
    .intro-aside span {
        color: var(--muted-dark);
        font: 9px 'IBM Plex Mono', monospace;
        letter-spacing: 0.16em;
    }
    .intro-aside strong {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(5.4rem, 10vw, 9rem);
        line-height: 0.8;
        letter-spacing: -0.09em;
        font-weight: 500;
        color: var(--paper);
        display: block;
        margin: 20px 0;
    }
    .intro-aside small {
        color: var(--muted);
        font: 9px 'IBM Plex Mono', monospace;
        letter-spacing: 0.13em;
        text-transform: uppercase;
        line-height: 1.5;
    }

    /* ========================================================================= */
    /* 3. DISCIPLINE SHOWCASE: "DIFFERENT FIELDS. ONE SIGNAL."                   */
    /* ========================================================================= */
    .domains-section {
        width: min(1320px, 100%);
        margin: auto;
        padding: 140px clamp(22px, 6vw, 92px);
        border-bottom: 1px solid var(--line);
        position: relative;
    }
    .section-heading-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 30px;
    }
    @media (max-width: 900px) {
        .section-heading-row {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    .section-heading-row h2 {
        margin: 18px 0 0;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.6rem, 5.5vw, 5.5rem);
        line-height: 0.94;
        letter-spacing: -0.075em;
        font-weight: 500;
        color: var(--paper);
    }
    .section-heading-row h2 em {
        color: var(--orange);
        font-style: normal;
    }
    .section-heading-note {
        max-width: 320px;
        margin: 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.65;
    }
    .domain-list {
        margin-top: 72px;
        border-top: 1px solid var(--line);
    }
    .domain-row {
        display: grid;
        grid-template-columns: 60px 54px 1fr 24px;
        align-items: center;
        gap: 24px;
        min-height: 126px;
        border-bottom: 1px solid var(--line);
        text-decoration: none;
        color: inherit;
        transition: padding 0.25s var(--ease-out), background 0.25s var(--ease-out);
    }
    @media (max-width: 768px) {
        .domain-row {
            grid-template-columns: 44px 44px 1fr 24px;
            gap: 16px;
            min-height: 100px;
        }
    }
    .domain-row:hover {
        padding-left: 18px;
        background: linear-gradient(90deg, rgba(241, 90, 36, 0.09), transparent 60%);
    }
    .domain-number {
        color: var(--orange);
        font: 11px 'IBM Plex Mono', monospace;
    }
    .domain-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        color: var(--steel);
        border: 1px solid var(--line);
        transition: border-color 0.2s, color 0.2s;
    }
    .domain-row:hover .domain-icon {
        border-color: var(--orange);
        color: var(--orange);
    }
    .domain-copy h3 {
        margin: 0 0 8px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 23px;
        font-weight: 500;
        letter-spacing: -0.04em;
        color: var(--paper);
    }
    .domain-copy p {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
    }
    .domain-arrow {
        color: var(--muted-dark);
        transition: color 0.2s, transform 0.2s var(--ease-out);
    }
    .domain-row:hover .domain-arrow {
        color: var(--orange);
        transform: translate(3px, -3px);
    }

    /* ========================================================================= */
    /* 4. LIVE EVENTS & OLYMPIADS SHOWCASE                                       */
    /* ========================================================================= */
    .projects-section {
        position: relative;
        background-color: #080b0d;
        background-size: cover;
        background-position: center;
        border-bottom: 1px solid var(--line);
    }
    .projects-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(
            90deg,
            rgba(8, 11, 13, 0.97),
            rgba(8, 11, 13, 0.86) 54%,
            rgba(8, 11, 13, 0.45)
        );
        z-index: 0;
    }
    .projects-inner {
        position: relative;
        z-index: 1;
        width: min(1320px, 100%);
        margin: auto;
        padding: 125px clamp(22px, 6vw, 92px) 140px;
    }
    .projects-heading {
        align-items: flex-start;
    }
    .project-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
        margin-top: 76px;
    }
    .project-card {
        min-height: 380px;
        position: relative;
        padding: 24px;
        border: 1px solid var(--line);
        background: rgba(8, 11, 13, 0.6);
        backdrop-filter: blur(12px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.25s var(--ease-out), border-color 0.25s var(--ease-out);
    }
    .project-card::after {
        content: "";
        position: absolute;
        top: -1px;
        right: -1px;
        width: 46px;
        height: 46px;
        border-top: 1px solid var(--orange);
        border-right: 1px solid var(--orange);
    }
    .project-card:hover {
        border-color: rgba(241, 90, 36, 0.4);
        transform: translateY(-4px);
    }
    .project-index {
        color: var(--orange);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .project-card-content {
        padding-top: 40px;
    }
    .project-card-content > span {
        color: var(--muted);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.13em;
        text-transform: uppercase;
        display: block;
        margin-bottom: 12px;
    }
    .project-card h3 {
        margin: 0 0 14px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2rem, 3.2vw, 2.6rem);
        line-height: 1.05;
        letter-spacing: -0.05em;
        font-weight: 600;
        color: var(--paper);
    }
    .project-card p {
        color: var(--muted);
        line-height: 1.6;
        font-size: 14px;
        margin-bottom: 25px;
    }

    /* ========================================================================= */
    /* 5. QUOTE SECTION (Matching screenshot 1)                                  */
    /* ========================================================================= */
    .quote-section {
        width: min(930px, 100%);
        margin: auto;
        padding: 150px 22px 165px;
        border-bottom: 1px solid var(--line);
        text-align: left;
    }
    .quote-mark {
        color: var(--orange);
        font: 130px Georgia, serif;
        line-height: 0.4;
        user-select: none;
    }
    .quote-section blockquote {
        max-width: 900px;
        margin: 30px 0 48px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2rem, 4vw, 4.4rem);
        line-height: 1.03;
        letter-spacing: -0.06em;
        font-weight: 500;
        color: var(--paper);
        font-style: italic;
    }
    .quote-source {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .quote-line {
        width: 37px;
        height: 1px;
        background: var(--orange);
    }
    .quote-source strong,
    .quote-source small {
        display: block;
    }
    .quote-source strong {
        font-size: 13px;
        color: var(--paper);
    }
    .quote-source small {
        margin-top: 6px;
        color: var(--muted);
        font: 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    /* ========================================================================= */
    /* 6. FAQ SECTION & 7. DIRECT CHANNEL (Exact dark theme integration)         */
    /* ========================================================================= */
    .faq-wrapper {
        border-bottom: 1px solid var(--line);
        background: #080b0d;
        padding: 100px clamp(22px, 6vw, 92px);
    }
    .faq-inner {
        max-width: 960px;
        margin: 0 auto;
    }
    .faq-header {
        text-align: center;
        margin-bottom: 50px;
    }
    .faq-header h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.2rem, 3.8vw, 3.4rem);
        font-weight: 700;
        color: var(--paper);
        margin: 12px 0;
    }
    .faq-header h2 span {
        color: var(--orange);
    }
    .faq-header p {
        color: #8a949b;
        font-size: 15px;
        max-width: 580px;
        margin: 0 auto;
    }
    .faq-accordion-item {
        background: #101518;
        border: 1px solid var(--line);
        border-radius: 10px;
        margin-bottom: 12px;
        overflow: hidden;
        transition: border-color 0.2s ease;
    }
    .faq-accordion-item.active {
        border-color: var(--orange);
    }
    .faq-accordion-btn {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        background: none;
        border: none;
        text-align: left;
        cursor: pointer;
        color: var(--paper);
        font-family: 'Space Grotesk', sans-serif;
        font-size: 16px;
        font-weight: 700;
    }
    .faq-icon-chevron {
        transition: transform 0.25s ease, color 0.25s ease;
        color: #8a949b;
        flex-shrink: 0;
    }
    .faq-accordion-item.active .faq-icon-chevron {
        transform: rotate(180deg);
        color: var(--orange);
    }
    .faq-body {
        padding: 0 24px 22px;
        color: #b0b8be;
        font-size: 14px;
        line-height: 1.7;
        border-top: 1px solid rgba(255, 255, 255, 0.04);
        display: none;
    }
    .faq-accordion-item.active .faq-body {
        display: block;
    }

    .contact-wrapper {
        background: #080b0d;
        padding: 100px clamp(22px, 6vw, 92px);
    }
    .contact-card-box {
        max-width: 1120px;
        margin: 0 auto;
        background: #101518;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 48px clamp(24px, 4vw, 48px);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 48px;
    }
    .contact-info-col h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2rem, 3.2vw, 2.6rem);
        font-weight: 700;
        color: var(--paper);
        margin: 12px 0 16px;
    }
    .contact-info-col h2 span {
        color: var(--orange);
    }
    .contact-info-col p {
        color: #8a949b;
        font-size: 15px;
        line-height: 1.7;
        margin-bottom: 32px;
    }
    .contact-item {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }
    .contact-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        background: rgba(241, 90, 36, 0.1);
        display: grid;
        place-items: center;
        color: var(--orange);
        flex-shrink: 0;
    }
    .contact-item-title {
        font: 700 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #8a949b;
    }
    .contact-item-val {
        color: var(--paper);
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
    }
    .contact-item-val:hover {
        color: var(--orange);
    }

    .form-field-label {
        display: block;
        font: 700 11px 'IBM Plex Mono', monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #8a949b;
        margin-bottom: 6px;
    }
    .form-text-input {
        width: 100%;
        padding: 12px 14px;
        background: #080b0d;
        border: 1px solid var(--line);
        border-radius: 6px;
        color: var(--paper);
        font-family: inherit;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }
    .form-text-input:focus {
        outline: none;
        border-color: var(--orange);
    }
</style>

<!-- Subtle Ambient Blueprint Technical Grid Overlay -->
<div class="ambient-grid" aria-hidden="true"></div>

<!-- ========================================================================= -->
<!-- 1. HERO SECTION: AUTHENTIC CIRCULAR SOCIETY CREST & AMBIENT RADAR RINGS   -->
<!-- ========================================================================= -->
<header class="hero-section" id="home" style="background-image: url('assets/sentec-hero-texture.png');">
    <div class="hero-overlay"></div>
    
    <!-- Background Radial Glow behind Hero & Crest -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] sm:w-[900px] h-[600px] sm:h-[900px] bg-gradient-to-tr from-[#f15a24]/12 via-[#00d2ff]/4 to-transparent rounded-full blur-3xl pointer-events-none -z-10"></div>

    <div class="hero-content-wrap">
        <!-- Left Column: Copy, Metadata, CTAs -->
        <div class="hero-copy">
            <div class="eyebrow">
                <span class="eyebrow-line"></span>
                <span>Student engineering society</span>
                <span class="eyebrow-status">SYS.01 / ONLINE</span>
            </div>

            <h1>
                Make the<br>
                <em>future</em> tangible.
            </h1>

            <p class="hero-lede">
                SENTEC is where ambitious students turn questions into working systems — through research, projects, and the kind of practice that compounds.
            </p>

            <div class="hero-actions">
                <a class="button button-primary" href="#events">
                    <span>Explore the work</span>
                    <i class="fas fa-arrow-up-right text-xs"></i>
                </a>
                <a class="button button-quiet" href="#about">
                    <span>What we do</span>
                    <i class="fas fa-chevron-right text-xs text-[#f15a24]"></i>
                </a>
            </div>

            <a class="scroll-cue" href="#about">
                <span>Scroll to inspect</span>
                <i class="fas fa-arrow-down-right text-xs"></i>
            </a>

            <div class="hero-proof">
                <span>01</span>
                <p>
                    Built at NED University<br>
                    <strong>for curious engineers</strong>
                </p>
            </div>
        </div>

        <!-- Right Column: Authentic Multi-Discipline Circular SENTEC Society Crest -->
        <div class="hero-visual">
            <div class="mark-stage" aria-label="Authentic SENTEC Engineering Society Crest">
                
                <!-- Rotating Radar Rings and Counter-Rotating Tracks from Home.tsx -->
                <div class="mark-orbit mark-orbit-outer"></div>
                <div class="mark-orbit mark-orbit-mid"></div>
                <div class="mark-orbit mark-orbit-inner"></div>

                <!-- Precision Crosshair Ticks -->
                <div class="mark-crosshair crosshair-top"></div>
                <div class="mark-crosshair crosshair-bottom"></div>
                <div class="mark-crosshair crosshair-left"></div>
                <div class="mark-crosshair crosshair-right"></div>

                <!-- Authentic Multi-Discipline SENTEC Crest Asset Stack with Hover Animation Layers -->
                <div class="reference-logo-stack" aria-hidden="true">
                    <img class="reference-logo reference-logo-base" src="images/SENTECNEWWHITELOGO.webp" alt="SENTEC Society Crest" onerror="this.src='assets/SENTECNEWWHITELOGO.webp'">
                    <img class="reference-logo reference-logo-layer layer-scan" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer layer-frame" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer layer-upper" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer layer-core" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer layer-wordmark" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-bolt" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-bridge" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-tower" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-sensor" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-monitor" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-flask" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-gear" src="images/SENTECNEWWHITELOGO.webp" alt="">
                    <img class="reference-logo reference-logo-layer element-core" src="images/SENTECNEWWHITELOGO.webp" alt="">
                </div>

                <!-- Animated Sweep Scanline -->
                <div class="mark-sweep"></div>

                <!-- Technical Caption -->
                <div class="mark-caption">
                    <span>SENTEC / FIELD SYSTEM</span>
                    <span>ACTIVE 1997—2026</span>
                </div>

            </div>
        </div>
    </div>

    <!-- Technical Side Coordinates (vertical-rl) -->
    <div class="hero-side-label">24° 51′ 36″ N / 67° 00′ 36″ E</div>
</header>

<!-- ========================================================================= -->
<!-- 2. THE BRIEF / PHILOSOPHY SECTION (Exact visual match to screenshot 1)    -->
<!-- ========================================================================= -->
<section class="intro-strip" id="about">
    <div class="section-rail">
        <span>00</span>
        <span>THE BRIEF</span>
    </div>

    <div class="intro-content">
        <p class="section-kicker">A platform for applied curiosity</p>
        <h2>
            Move from <span>“what if?”</span><br>
            to <strong>“watch this.”</strong>
        </h2>
        <p class="intro-copy">
            For nearly three decades, SENTEC has brought together students who would rather test an idea than leave it in a notebook. We make room for disciplines to collide, skills to sharpen, and good work to become visible.
        </p>
        <a class="text-link" href="#domains">
            <span>Read the operating principles</span>
            <i class="fas fa-arrow-up-right text-xs"></i>
        </a>
    </div>

    <div class="intro-aside" style="background-image: url('assets/sentec-about-texture.png');">
        <span>EST. 1997</span>
        <strong>29</strong>
        <small>
            years of<br>
            shared practice
        </small>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 3. DISCIPLINE SHOWCASE: "DIFFERENT FIELDS. ONE SIGNAL."                   -->
<!-- ========================================================================= -->
<section class="domains-section" id="domains">
    <div class="section-heading-row">
        <div>
            <p class="section-kicker">01 / Where we work</p>
            <h2>
                Different fields.<br>
                <em>One signal.</em>
            </h2>
        </div>
        <p class="section-heading-note">
            The best solutions rarely stay inside one discipline. Our domains are open doors into the same system.
        </p>
    </div>

    <div class="domain-list">
        <!-- Domain 01 -->
        <article class="domain-row">
            <span class="domain-number">01</span>
            <div class="domain-icon">
                <i class="fas fa-microchip text-base"></i>
            </div>
            <div class="domain-copy">
                <h3>Robotics & Automation</h3>
                <p>Build machines that sense, decide, and move with intention.</p>
            </div>
            <i class="fas fa-arrow-up-right domain-arrow text-sm"></i>
        </article>

        <!-- Domain 02 -->
        <article class="domain-row">
            <span class="domain-number">02</span>
            <div class="domain-icon">
                <i class="fas fa-brain text-base"></i>
            </div>
            <div class="domain-copy">
                <h3>Artificial Intelligence</h3>
                <p>Turn data into better questions, clearer models, and useful predictions.</p>
            </div>
            <i class="fas fa-arrow-up-right domain-arrow text-sm"></i>
        </article>

        <!-- Domain 03 -->
        <article class="domain-row">
            <span class="domain-number">03</span>
            <div class="domain-icon">
                <i class="fas fa-tachometer-alt text-base"></i>
            </div>
            <div class="domain-copy">
                <h3>Embedded Systems</h3>
                <p>Connect the physical world through small, resilient, intelligent systems.</p>
            </div>
            <i class="fas fa-arrow-up-right domain-arrow text-sm"></i>
        </article>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 4. LIVE EVENTS & OLYMPIADS SHOWCASE                                       -->
<!-- ========================================================================= -->
<section class="projects-section" id="events" style="background-image: url('assets/sentec-project-texture.png');">
    <div class="projects-overlay"></div>
    <div class="projects-inner">
        <div class="section-heading-row projects-heading">
            <div>
                <p class="section-kicker">02 / UPCOMING EVENTS & COMPETITIONS</p>
                <h2>
                    Live Events &<br>
                    <em>Olympiads.</em>
                </h2>
            </div>
            <a class="button button-outline" href="engineers_code">
                <span>View All Events</span>
                <i class="fas fa-arrow-up-right text-xs"></i>
            </a>
        </div>

        <div class="project-grid">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $idx => $ev): 
                    $evTitle = htmlspecialchars($ev['title'] ?? 'Event');
                    $evDate = !empty($ev['event_date']) ? date('M d, Y', strtotime($ev['event_date'])) : 'EVENT 0'.($idx + 1);
                    $evCategory = htmlspecialchars($ev['category'] ?? 'Competition & Workshop');
                    $evDesc = htmlspecialchars($ev['description'] ?? 'Official technical event hosted by SENTEC at NED University.');
                    $regLink = !empty($ev['event_link']) ? htmlspecialchars($ev['event_link']) : 'event_registration?id='.($ev['id'] ?? '');
                ?>
                    <article class="project-card">
                        <div class="project-index"><?php echo $evDate; ?></div>
                        <div class="project-card-content">
                            <span><?php echo $evCategory; ?></span>
                            <h3><?php echo $evTitle; ?></h3>
                            <p><?php echo $evDesc; ?></p>
                            <a href="<?php echo $regLink; ?>" class="text-link">
                                <span>Register Now</span>
                                <i class="fas fa-arrow-up-right text-xs"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="project-card">
                    <div class="project-index">01 / SOON</div>
                    <div class="project-card-content">
                        <span>Upcoming Events</span>
                        <h3>Coming<br>Soon</h3>
                        <p>New event details will be published here when they are ready.</p>
                        <span class="text-link" aria-disabled="true" style="opacity: 0.7; pointer-events: none; cursor: default;">
                            <span>Coming Soon</span>
                        </span>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 5. QUOTE SECTION (Matching screenshot 1)                                  -->
<!-- ========================================================================= -->
<section class="quote-section">
    <div class="quote-mark">“</div>
    <p class="section-kicker">03 / The reason it works</p>
    <blockquote>
        “SENTEC gives students a place to develop the capabilities they will need as engineering graduates — not just the credentials.”
    </blockquote>
    <div class="quote-source">
        <span class="quote-line"></span>
        <div>
            <strong>Prof. Dr. Murtuza</strong>
            <small>Faculty Incharge, SENTEC</small>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 6. KNOWLEDGE BASE / INTERACTIVE FAQS                                      -->
<!-- ========================================================================= -->
<section class="faq-wrapper" id="faqs">
    <div class="faq-inner">
        <div class="faq-header">
            <p class="section-kicker">04 / KNOWLEDGE BASE</p>
            <h2>Frequently Asked <span>Questions.</span></h2>
            <p>Everything you need to know about participating in SENTEC events, registrations, and gate clearances.</p>
        </div>

        <div class="faq-accordion-list">
            <?php 
            $faqs = [
                [
                    "q" => "What is SENTEC NEDUET?",
                    "a" => "SENTEC (Society for the Promotion of Science, Engineering and Technology) is one of the premier student-led technical societies at NED University of Engineering & Technology, fostering innovation, engineering olympiads, and hands-on technical development since 1997."
                ],
                [
                    "q" => "Who can participate in SENTEC events & PROXION Olympiad?",
                    "a" => "Events are open to all students across Pakistan, including NED University students, external universities, and intermediate/A-Level colleges."
                ],
                [
                    "q" => "How do module registrations and payments work?",
                    "a" => "You can register your team online via the Register portal. After submitting leader and team member details, upload your payment receipt/screenshot through the Upload Payment portal to receive confirmation."
                ],
                [
                    "q" => "What is the SENTEC Social Night & Gala pass?",
                    "a" => "The Social Night is our flagship networking and celebration evening featuring esports, stage performances, and networking with alumni and industry leaders."
                ],
                [
                    "q" => "How do I get my official Entry Gatepass?",
                    "a" => "Once your team registration or social pass is approved, your unique Gatepass code will be available on your Participant Dashboard and sent to your registered email."
                ]
            ];

            foreach ($faqs as $i => $item): 
                $isActive = ($i === 0);
            ?>
                <div class="faq-accordion-item <?php echo $isActive ? 'active' : ''; ?>">
                    <button class="faq-accordion-btn" type="button" aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>">
                        <span><?php echo htmlspecialchars($item['q']); ?></span>
                        <i class="fas fa-chevron-down faq-icon-chevron"></i>
                    </button>
                    <div class="faq-body" style="<?php echo $isActive ? 'display: block;' : ''; ?>">
                        <?php echo htmlspecialchars($item['a']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 7. DIRECT CHANNEL / QUICK CONTACT FORM                                    -->
<!-- ========================================================================= -->
<section class="contact-wrapper" id="contact">
    <div class="contact-card-box">
        <!-- Left Column: Official Channels -->
        <div class="contact-info-col">
            <p class="section-kicker">05 / DIRECT CHANNEL</p>
            <h2>Get in Touch with <span>SENTEC.</span></h2>
            <p>Have questions about upcoming modules, sponsorship proposals, or technical inquiries? Reach out to our executive secretariat.</p>

            <div class="contact-item">
                <div class="contact-icon-box">
                    <i class="far fa-envelope text-lg"></i>
                </div>
                <div>
                    <div class="contact-item-title">Official Email</div>
                    <a href="mailto:info@sentecneduet.live" class="contact-item-val">info@sentecneduet.live</a>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon-box">
                    <i class="fas fa-map-marker-alt text-lg"></i>
                </div>
                <div>
                    <div class="contact-item-title">Base Location</div>
                    <div class="contact-item-val">Student Affairs Dept, NED University, Karachi</div>
                </div>
            </div>
        </div>

        <!-- Right Column: Interactive Dispatch Form -->
        <div>
            <?php if ($contactFeedback): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $contactFeedback['success'] ? 'bg-green-950/70 border border-green-500/50 text-green-300' : 'bg-red-950/70 border border-red-500/50 text-red-300'; ?> text-xs font-mono">
                    <?php echo htmlspecialchars($contactFeedback['message']); ?>
                </div>
            <?php endif; ?>

            <div id="ajaxContactAlert" class="hidden mb-4 p-4 rounded-md text-xs font-mono"></div>

            <form id="quickContactForm" method="POST" action="#contact" class="flex flex-col gap-3.5">
                <input type="hidden" name="action" value="quick_contact">

                <div>
                    <label class="form-field-label">Your Name</label>
                    <input type="text" name="name" id="contactName" required placeholder="e.g. Sohaib Waseem" class="form-text-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-field-label">Email</label>
                        <input type="email" name="email" id="contactEmail" required placeholder="name@domain.com" class="form-text-input">
                    </div>
                    <div>
                        <label class="form-field-label">Phone</label>
                        <input type="tel" name="phone" id="contactPhone" placeholder="0300-1234567" class="form-text-input">
                    </div>
                </div>

                <div>
                    <label class="form-field-label">Message</label>
                    <textarea name="message" id="contactMessage" rows="4" required placeholder="Write your question or request..." class="form-text-input resize-y"></textarea>
                </div>

                <button type="submit" id="contactSubmitBtn" class="button button-primary w-full justify-center mt-2 cursor-pointer">
                    <i class="far fa-paper-plane"></i>
                    <span>Dispatch Message</span>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- INTERACTIVE SCRIPTS: FAQ ACCORDION & ASYNC CONTACT FORM                  -->
<!-- ========================================================================= -->
<script>
    // FAQ Accordion
    document.querySelectorAll('.faq-accordion-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = this.closest('.faq-accordion-item');
            const isActive = item.classList.contains('active');
            
            // Close all
            document.querySelectorAll('.faq-accordion-item').forEach(other => {
                other.classList.remove('active');
                other.querySelector('.faq-accordion-btn').setAttribute('aria-expanded', 'false');
                other.querySelector('.faq-body').style.display = 'none';
            });

            // Toggle selected
            if (!isActive) {
                item.classList.add('active');
                this.setAttribute('aria-expanded', 'true');
                item.querySelector('.faq-body').style.display = 'block';
            }
        });
    });

    // Quick Contact AJAX Submission
    const contactForm = document.getElementById('quickContactForm');
    const alertBox = document.getElementById('ajaxContactAlert');
    const submitBtn = document.getElementById('contactSubmitBtn');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', '1');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Dispatching...</span>';

            fetch('index.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                alertBox.classList.remove('hidden', 'bg-red-950/70', 'border-red-500/50', 'text-red-300', 'bg-green-950/70', 'border-green-500/50', 'text-green-300');
                if (data.success) {
                    alertBox.className = 'mb-4 p-4 rounded-md bg-green-950/70 border border-green-500/50 text-green-300 text-xs font-mono';
                    alertBox.textContent = data.message;
                    contactForm.reset();
                } else {
                    alertBox.className = 'mb-4 p-4 rounded-md bg-red-950/70 border border-red-500/50 text-red-300 text-xs font-mono';
                    alertBox.textContent = data.message;
                }
            })
            .catch(() => {
                alertBox.className = 'mb-4 p-4 rounded-md bg-red-950/70 border border-red-500/50 text-red-300 text-xs font-mono';
                alertBox.textContent = 'Transmission error. Please verify your connection or email directly.';
                alertBox.classList.remove('hidden');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="far fa-paper-plane"></i> <span>Dispatch Message</span>';
            });
        });
    }
</script>

<?php 
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $conn->close();
}
include 'footer.php'; 
?>