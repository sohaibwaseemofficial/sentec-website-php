<?php 
include 'header.php'; 
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/social_registration_settings.php';
$socialSettings = social_registrations_get_settings($conn);
?>

<script>
    document.title = "RUH-E-RAQS // روحِ رقص - SENTEC '26 Annual Sufi Evening | NED University";
</script>

<style>
    /* =========================================================
       RUH-E-RAQS // SOCIAL NIGHT REDESIGN STYLES
       Sufi Orange Fusion Theme
       ========================================================= */
    :root {
        --brand-orange: #f15a24;
        --brand-orange-hover: #ff8050;
        --brand-orange-dim: rgba(241, 90, 36, 0.15);
        --brand-orange-glow: rgba(241, 90, 36, 0.45);
        --neon-orange: #f15a24;
        --neon-orange-glow: rgba(241, 90, 36, 0.45);
        --neon-green: #f15a24;
        --neon-green-dim: rgba(241, 90, 36, 0.15);
        --neon-green-glow: rgba(241, 90, 36, 0.45);
        --bg-deep: #06090c;
        --bg-surface: #0e1317;
        --card-bg: rgba(16, 22, 27, 0.75);
        --glass-border: rgba(255, 255, 255, 0.08);
        --glass-border-hover: rgba(241, 90, 36, 0.35);
        --text-pure: #ffffff;
        --text-sub: #cbd5e1;
        --text-dim: #94a3b8;
    }

    /* Base resets for social event container */
    .social-page-wrap {
        background-color: var(--bg-deep);
        color: var(--text-sub);
        font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        overflow-x: hidden;
        position: relative;
    }

    /* Subtle background grid pattern */
    .social-page-wrap::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            radial-gradient(circle at 50% 15%, rgba(241, 90, 36, 0.09) 0%, transparent 60%),
            radial-gradient(circle at 85% 60%, rgba(241, 90, 36, 0.06) 0%, transparent 50%),
            linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 100% 100%, 100% 100%, 48px 48px, 48px 48px;
        pointer-events: none;
        z-index: 0;
    }

    .social-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        position: relative;
        z-index: 1;
    }

    /* =========================================================
       1. HERO SECTION
       ========================================================= */
    .social-hero-section {
        min-height: 88vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: clamp(90px, 12vh, 130px) 20px 60px;
        position: relative;
        overflow: hidden;
    }

    .hero-glow-orb {
        position: absolute;
        width: 320px;
        height: 320px;
        background: radial-gradient(circle, var(--brand-orange-glow) 0%, transparent 70%);
        border-radius: 50%;
        filter: blur(60px);
        top: 20%;
        left: 50%;
        transform: translate(-50%, -20%);
        pointer-events: none;
        z-index: 0;
        opacity: 0.6;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 900px;
        margin: 0 auto;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(241, 90, 36, 0.08);
        border: 1px solid rgba(241, 90, 36, 0.25);
        color: var(--brand-orange);
        padding: 6px 16px;
        border-radius: 9999px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 18px;
        backdrop-filter: blur(8px);
    }

    .hero-badge-pulse {
        width: 8px;
        height: 8px;
        background: var(--brand-orange);
        border-radius: 50%;
        box-shadow: 0 0 10px var(--brand-orange);
        animation: pulseDot 2s infinite ease-in-out;
    }

    @keyframes pulseDot {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.4); opacity: 0.4; }
    }

    .hero-urdu {
        font-family: 'Outfit', 'Noto Nastaliq Urdu', serif;
        font-size: clamp(2.2rem, 5.5vw, 3.8rem);
        font-weight: 700;
        color: var(--brand-orange);
        text-shadow: 0 0 25px var(--brand-orange-glow);
        margin: 0 0 4px 0;
        line-height: 1.2;
        letter-spacing: 2px;
    }

    .hero-title {
        font-family: 'Outfit', sans-serif;
        font-size: clamp(2.6rem, 7vw, 5.2rem);
        font-weight: 900;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: clamp(2px, 0.6vw, 6px);
        margin: 0 0 16px 0;
        line-height: 1.05;
        text-shadow: 0 0 40px rgba(241, 90, 36, 0.35);
    }

    .hero-lead {
        font-size: clamp(1rem, 2.2vw, 1.22rem);
        color: #cbd5e1;
        max-width: 720px;
        margin: 0 auto 32px;
        line-height: 1.6;
    }

    /* Key Details Ribbon */
    .hero-ribbon {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        max-width: 1120px;
        margin: 0 auto 36px;
        width: 100%;
    }

    .ribbon-pill {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 14px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        text-align: left;
        backdrop-filter: blur(12px);
        transition: all 0.25s ease;
        min-width: 0;
    }

    .ribbon-pill:hover {
        background: rgba(241, 90, 36, 0.05);
        border-color: rgba(241, 90, 36, 0.3);
        transform: translateY(-2px);
    }

    .ribbon-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        background: rgba(241, 90, 36, 0.12);
        border: 1px solid rgba(241, 90, 36, 0.25);
        border-radius: 10px;
        color: var(--brand-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .ribbon-meta {
        flex: 1;
        min-width: 0;
        overflow: visible;
    }

    .ribbon-label {
        font-size: 0.72rem;
        font-family: 'IBM Plex Mono', monospace;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 3px;
        line-height: 1.2;
    }

    .ribbon-value {
        font-size: 0.92rem;
        font-weight: 700;
        color: #ffffff;
        white-space: normal;
        word-break: normal;
        overflow-wrap: break-word;
        line-height: 1.35;
    }

    /* Buttons */
    .hero-cta-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 14px;
    }

    .btn-neon-solid {
        background: var(--brand-orange);
        color: #05080a;
        font-weight: 800;
        font-size: 0.96rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 14px 34px;
        border-radius: 9999px;
        text-decoration: none;
        box-shadow: 0 0 25px rgba(241, 90, 36, 0.45);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-neon-solid:hover {
        background: #ff8050;
        color: #000;
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 0 40px rgba(241, 90, 36, 0.75);
    }

    .btn-neon-outline {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
        font-weight: 700;
        font-size: 0.96rem;
        letter-spacing: 0.04em;
        padding: 14px 28px;
        border-radius: 9999px;
        text-decoration: none;
        backdrop-filter: blur(10px);
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-neon-outline:hover {
        border-color: var(--brand-orange);
        color: var(--brand-orange);
        background: rgba(241, 90, 36, 0.06);
        transform: translateY(-2px);
    }

    /* =========================================================
       2. SECTION HEADINGS
       ========================================================= */
    .section-head {
        text-align: center;
        margin-bottom: clamp(36px, 6vw, 56px);
        position: relative;
    }

    .section-tag {
        display: inline-block;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--brand-orange);
        letter-spacing: 0.15em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .section-title {
        font-family: 'Outfit', sans-serif;
        font-size: clamp(1.9rem, 4.2vw, 2.9rem);
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 12px;
        letter-spacing: -0.01em;
    }

    .section-desc {
        font-size: clamp(0.95rem, 1.8vw, 1.05rem);
        color: var(--text-dim);
        max-width: 620px;
        margin: 0 auto;
        line-height: 1.6;
    }

    /* =========================================================
       3. EVENT HIGHLIGHTS GRID
       ========================================================= */
    .highlights-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        margin-bottom: 30px;
    }

    @media (max-width: 991px) {
        .highlights-grid {
            grid-template-columns: 1fr;
        }
    }

    .highlight-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 28px 24px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(16px);
    }

    .highlight-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--brand-orange), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .highlight-card:hover {
        border-color: var(--glass-border-hover);
        transform: translateY(-6px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.4), 0 0 25px rgba(241, 90, 36, 0.1);
    }

    .highlight-card:hover::before {
        opacity: 1;
    }

    .highlight-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(241, 90, 36, 0.1);
        border: 1px solid rgba(241, 90, 36, 0.25);
        color: var(--brand-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        margin-bottom: 20px;
        transition: 0.3s;
    }

    .highlight-card:hover .highlight-icon-wrap {
        transform: scale(1.1);
        background: var(--brand-orange);
        color: #000;
        box-shadow: 0 0 20px var(--brand-orange-glow);
    }

    .highlight-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 10px;
    }

    .highlight-text {
        font-size: 0.92rem;
        color: var(--text-dim);
        line-height: 1.6;
        margin: 0;
    }

    /* =========================================================
       4. PROGRAM TIMELINE
       ========================================================= */
    .timeline-wrap {
        max-width: 850px;
        margin: 0 auto;
        position: relative;
    }

    .timeline-wrap::before {
        content: "";
        position: absolute;
        top: 20px;
        bottom: 20px;
        left: 27px;
        width: 2px;
        background: linear-gradient(to bottom, var(--brand-orange), rgba(241, 90, 36, 0.1));
    }

    .timeline-item {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 24px;
        position: relative;
    }

    .timeline-dot {
        width: 56px;
        height: 56px;
        min-width: 56px;
        border-radius: 50%;
        background: #090e12;
        border: 2px solid var(--brand-orange);
        color: var(--brand-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        box-shadow: 0 0 16px rgba(241, 90, 36, 0.3);
        z-index: 2;
    }

    .timeline-card {
        flex: 1;
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 20px 22px;
        backdrop-filter: blur(12px);
        transition: 0.25s ease;
    }

    .timeline-card:hover {
        border-color: rgba(241, 90, 36, 0.35);
        transform: translateX(4px);
    }

    .timeline-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 6px;
    }

    .timeline-time {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--brand-orange);
        background: rgba(241, 90, 36, 0.1);
        padding: 3px 10px;
        border-radius: 6px;
        letter-spacing: 0.05em;
    }

    .timeline-card-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        color: #ffffff;
        margin: 0;
    }

    .timeline-desc {
        font-size: 0.9rem;
        color: var(--text-dim);
        line-height: 1.5;
        margin: 6px 0 0 0;
    }

    /* =========================================================
       5. PASS PRICING SECTION
       ========================================================= */
    .passes-section {
        background: linear-gradient(180deg, rgba(14, 19, 23, 0.4) 0%, rgba(8, 12, 16, 0.8) 100%);
        border-top: 1px solid var(--glass-border);
        border-bottom: 1px solid var(--glass-border);
        padding: clamp(60px, 8vw, 90px) 0;
    }

    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
        gap: 24px;
        max-width: 1040px;
        margin: 0 auto;
        align-items: stretch;
    }

    .pricing-card {
        background: linear-gradient(160deg, rgba(20, 27, 33, 0.85) 0%, rgba(9, 13, 16, 0.95) 100%);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 36px 28px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.3s ease;
        backdrop-filter: blur(16px);
    }

    .pricing-card:hover {
        border-color: rgba(241, 90, 36, 0.35);
        transform: translateY(-6px);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6), 0 0 30px rgba(241, 90, 36, 0.1);
    }

    .pricing-card.featured {
        border-color: var(--brand-orange);
        box-shadow: 0 0 35px rgba(241, 90, 36, 0.2);
    }

    .badge-pill-top {
        position: absolute;
        top: -14px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--brand-orange);
        color: #05080a;
        font-family: 'IBM Plex Mono', monospace;
        font-weight: 800;
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 4px 18px;
        border-radius: 9999px;
        box-shadow: 0 0 15px rgba(241, 90, 36, 0.6);
        white-space: nowrap;
    }

    .tier-name {
        font-family: 'Outfit', sans-serif;
        font-size: 1.55rem;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 4px;
    }

    .tier-subtitle {
        font-size: 0.86rem;
        color: var(--text-dim);
        margin-bottom: 20px;
    }

    .price-box {
        margin-bottom: 24px;
        padding: 16px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .price-number {
        font-family: 'Outfit', sans-serif;
        font-size: clamp(2.4rem, 4.5vw, 3.2rem);
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
        display: flex;
        align-items: baseline;
        gap: 6px;
    }

    .price-currency {
        font-size: 1rem;
        font-weight: 700;
        color: var(--brand-orange);
        font-family: 'Space Grotesk', sans-serif;
    }

    .price-original {
        font-size: 1.15rem;
        color: #ef4444;
        text-decoration: line-through;
        opacity: 0.8;
        font-weight: 600;
        margin-bottom: 4px;
        display: inline-block;
    }

    .discount-pill {
        display: inline-block;
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        margin-left: 8px;
        vertical-align: middle;
    }

    .pricing-perks {
        list-style: none;
        padding: 0;
        margin: 0 0 28px 0;
    }

    .pricing-perks li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 0.92rem;
        color: #cbd5e1;
        margin-bottom: 12px;
    }

    .pricing-perks li i {
        color: var(--brand-orange);
        font-size: 0.95rem;
        margin-top: 3px;
        min-width: 16px;
    }

    .btn-card-action {
        width: 100%;
        padding: 14px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.95rem;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        transition: all 0.25s ease;
    }

    .btn-card-primary {
        background: var(--brand-orange);
        color: #05080a;
        box-shadow: 0 0 20px rgba(241, 90, 36, 0.35);
    }

    .btn-card-primary:hover {
        background: #ff8050;
        color: #000;
        box-shadow: 0 0 30px rgba(241, 90, 36, 0.6);
        transform: translateY(-2px);
    }

    .btn-card-outline {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    .btn-card-outline:hover {
        border-color: var(--brand-orange);
        color: var(--brand-orange);
        background: rgba(241, 90, 36, 0.05);
        transform: translateY(-2px);
    }

    /* =========================================================
       6. HOW TO REGISTER STEPS
       ========================================================= */
    .steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .step-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 30px 24px;
        position: relative;
        backdrop-filter: blur(12px);
        transition: 0.3s;
    }

    .step-card:hover {
        border-color: rgba(241, 90, 36, 0.3);
        transform: translateY(-4px);
    }

    .step-num-badge {
        font-family: 'Outfit', sans-serif;
        font-size: 2.8rem;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.08);
        line-height: 1;
        margin-bottom: 12px;
        letter-spacing: -1px;
    }

    .step-card-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.22rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 8px;
    }

    .step-card-text {
        font-size: 0.9rem;
        color: var(--text-dim);
        line-height: 1.6;
        margin: 0;
    }

    /* =========================================================
       7. POSTER & VENUE SECTION
       ========================================================= */
    .venue-showcase-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: center;
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        padding: clamp(24px, 5vw, 44px);
        margin-bottom: 60px;
        backdrop-filter: blur(16px);
    }

    .poster-container {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 35px rgba(241, 90, 36, 0.15);
        max-width: 420px;
        margin: 0 auto;
    }

    .poster-img {
        width: 100%;
        height: auto;
        display: block;
        transition: transform 0.5s ease;
    }

    .poster-container:hover .poster-img {
        transform: scale(1.03);
    }

    .venue-info-box {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .venue-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .venue-item-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;
        border-radius: 12px;
        background: rgba(241, 90, 36, 0.1);
        border: 1px solid rgba(241, 90, 36, 0.25);
        color: var(--brand-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .venue-item-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 4px;
    }

    .venue-item-desc {
        font-size: 0.9rem;
        color: var(--text-dim);
        line-height: 1.5;
        margin: 0;
    }

    /* =========================================================
       8. FAQ ACCORDION
       ========================================================= */
    .faq-container {
        max-width: 820px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .faq-item {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.25s ease;
    }

    .faq-item.active {
        border-color: rgba(241, 90, 36, 0.4);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    }

    .faq-question {
        width: 100%;
        text-align: left;
        background: transparent;
        border: none;
        padding: 18px 22px;
        color: #ffffff;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.02rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        cursor: pointer;
    }

    .faq-chevron {
        color: var(--brand-orange);
        transition: transform 0.3s ease;
        font-size: 0.9rem;
    }

    .faq-item.active .faq-chevron {
        transform: rotate(180deg);
    }

    .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.35s ease-out;
        padding: 0 22px;
        color: var(--text-dim);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .faq-item.active .faq-answer {
        max-height: 250px;
        padding-bottom: 18px;
    }

    /* =========================================================
       9. MOBILE RESPONSIVE TWEAKS & BOTTOM BAR
       ========================================================= */
    .mobile-float-cta {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(9, 13, 16, 0.92);
        border-top: 1px solid rgba(241, 90, 36, 0.25);
        backdrop-filter: blur(16px);
        padding: 12px 18px;
        z-index: 999;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.6);
    }

    @media (max-width: 1080px) {
        .hero-ribbon {
            grid-template-columns: repeat(2, 1fr);
            max-width: 680px;
            gap: 12px;
        }
    }

    @media (max-width: 991px) {
        .venue-showcase-grid {
            grid-template-columns: 1fr;
            text-align: left;
            gap: 32px;
        }
        .poster-container {
            max-width: 360px;
        }
    }

    @media (max-width: 768px) {
        .social-hero-section {
            padding: 85px 16px 45px;
            min-height: auto;
        }

        .hero-badge {
            font-size: 0.75rem;
            padding: 5px 14px;
        }

        .hero-urdu {
            font-size: 2.2rem;
            letter-spacing: 1px;
        }

        .hero-title {
            font-size: 2.5rem;
            letter-spacing: 2px;
        }

        .hero-lead {
            font-size: 0.98rem;
            line-height: 1.6;
            margin-bottom: 26px;
        }

        .hero-ribbon {
            grid-template-columns: 1fr;
            max-width: 100%;
            gap: 10px;
            margin-bottom: 28px;
        }

        .ribbon-pill {
            padding: 12px 14px;
            gap: 12px;
        }

        .ribbon-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            font-size: 1rem;
        }

        .ribbon-value {
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .hero-cta-group {
            flex-direction: column;
            width: 100%;
            gap: 12px;
        }

        .btn-neon-solid, .btn-neon-outline {
            width: 100%;
            justify-content: center;
            padding: 14px 20px;
            box-sizing: border-box;
        }

        .pricing-grid {
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .timeline-wrap::before {
            left: 20px;
        }

        .timeline-dot {
            width: 42px;
            height: 42px;
            min-width: 42px;
            font-size: 0.95rem;
        }

        .timeline-card {
            padding: 16px;
        }

        .mobile-float-cta {
            display: flex;
        }

        /* Provide clearance for mobile floating bar */
        .social-page-wrap {
            padding-bottom: 85px;
        }
    }

    @media (max-width: 480px) {
        .social-container {
            padding: 0 14px;
        }

        .hero-title {
            font-size: 2.15rem;
            letter-spacing: 1px;
        }

        .highlight-card {
            padding: 22px 18px;
        }

        .pricing-card {
            padding: 28px 18px;
        }

        .faq-question {
            padding: 16px 18px;
            font-size: 0.95rem;
        }

        .faq-answer {
            padding: 0 18px;
        }

        .faq-item.active .faq-answer {
            padding-bottom: 16px;
        }
    }
</style>

<div class="social-page-wrap">

    <!-- =========================================================
         HERO SECTION
         ========================================================= -->
    <section class="social-hero-section">
        <div class="hero-glow-orb"></div>
        <div class="hero-content">
            
            <div class="hero-badge">
                <span class="hero-badge-pulse"></span>
                SENTEC '26 OFFICIAL SUFI EVENING
            </div>

            <div class="hero-urdu">روحِ رقص</div>
            <h1 class="hero-title">RUH-E-RAQS</h1>
            
            <p class="hero-lead">
                An authentic, soul-stirring Sufi Qawwali evening. Experience the timeless rhythm of harmonium, tabla, and spiritual kalaam in the Syed Mahmood Alam Auditorium at NED University.
            </p>

            <!-- Key Info Ribbon -->
            <div class="hero-ribbon">
                <div class="ribbon-pill">
                    <div class="ribbon-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="ribbon-meta">
                        <div class="ribbon-label">Event Date</div>
                        <div class="ribbon-value">15th October, 2026</div>
                    </div>
                </div>

                <div class="ribbon-pill">
                    <div class="ribbon-icon"><i class="fas fa-clock"></i></div>
                    <div class="ribbon-meta">
                        <div class="ribbon-label">Gates & Timing</div>
                        <div class="ribbon-value">02:00 PM onwards</div>
                    </div>
                </div>

                <div class="ribbon-pill">
                    <div class="ribbon-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="ribbon-meta">
                        <div class="ribbon-label">Venue</div>
                        <div class="ribbon-value">Syed Mahmood Alam Auditorium</div>
                    </div>
                </div>

                <div class="ribbon-pill">
                    <div class="ribbon-icon"><i class="fas fa-id-badge"></i></div>
                    <div class="ribbon-meta">
                        <div class="ribbon-label">Eligibility</div>
                        <div class="ribbon-value">Exclusively for NEDians</div>
                    </div>
                </div>
            </div>

            <!-- CTA Actions -->
            <div class="hero-cta-group">
                <a href="#passes" class="btn-neon-solid">
                    <i class="fas fa-ticket-alt"></i> Get Your Pass Now
                </a>
                <a href="#lineup" class="btn-neon-outline">
                    <i class="fas fa-list-ul"></i> Explore Lineup & Highlights
                </a>
            </div>

        </div>
    </section>

    <!-- =========================================================
         EVENT HIGHLIGHTS SECTION
         ========================================================= -->
    <section id="lineup" style="padding: clamp(60px, 8vw, 90px) 0;">
        <div class="social-container">
            
            <div class="section-head">
                <span class="section-tag">WHAT TO EXPECT</span>
                <h2 class="section-title">An Authentic Sufi Musical Evening</h2>
                <p class="section-desc">
                    Ruh-e-Raqs brings the raw spiritual energy of classical Sufi Qawwali to NED University with live traditional instruments and passionate vocals.
                </p>
            </div>

            <div class="highlights-grid">
                
                <div class="highlight-card">
                    <div class="highlight-icon-wrap">
                        <i class="fas fa-microphone-alt"></i>
                    </div>
                    <h3 class="highlight-title">Live Sufi Qawwali</h3>
                    <p class="highlight-text">
                        Immerse yourself in legendary Sufi kalams, mystical poetry, and ecstatic vocal improvisations performed live by master Qawwals.
                    </p>
                </div>

                <div class="highlight-card">
                    <div class="highlight-icon-wrap">
                        <i class="fas fa-drum"></i>
                    </div>
                    <h3 class="highlight-title">Harmonium & Tabla Rhythms</h3>
                    <p class="highlight-text">
                        The authentic acoustic heartbeat of genuine Qawwali — soulful harmonium melodies, thunderous tabla beats, and synchronized rhythmic handclaps.
                    </p>
                </div>

                <div class="highlight-card">
                    <div class="highlight-icon-wrap">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3 class="highlight-title">Digital Fast-Track Pass</h3>
                    <p class="highlight-text">
                        Instant QR-enabled digital Gatepass sent straight to your email and portal dashboard for effortless security clearance at NED gates.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- =========================================================
         SCHEDULE & ITINERARY
         ========================================================= -->
    <section id="itinerary" style="padding: clamp(60px, 8vw, 90px) 0; background: rgba(255, 255, 255, 0.015);">
        <div class="social-container">
            
            <div class="section-head">
                <span class="section-tag">EVENT TIMELINE</span>
                <h2 class="section-title">Program Schedule & Timing</h2>
                <p class="section-desc">
                    Carefully curated from 02:00 PM onwards for an intense, soul-stirring spiritual musical experience.
                </p>
            </div>

            <div class="timeline-wrap">
                
                <div class="timeline-item">
                    <div class="timeline-dot"><i class="fas fa-door-open"></i></div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <h4 class="timeline-card-title">Gates Open & Digital Verification</h4>
                            <span class="timeline-time">02:00 PM</span>
                        </div>
                        <p class="timeline-desc">
                            Attendees arrive at NED University Gate 1 / 2. Fast-track QR Gatepass verification, security clearance, and auditorium seating.
                        </p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"><i class="fas fa-microphone-alt"></i></div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <h4 class="timeline-card-title">Welcoming Address & Opening Prelude</h4>
                            <span class="timeline-time">02:30 PM</span>
                        </div>
                        <p class="timeline-desc">
                            Brief welcoming address by the SENTEC Directorate followed by a traditional harmonium and tabla Hamd-o-Naat prelude.
                        </p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"><i class="fas fa-fire-alt"></i></div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <h4 class="timeline-card-title">The Grand Live Qawwali Session</h4>
                            <span class="timeline-time">03:00 PM</span>
                        </div>
                        <p class="timeline-desc">
                            The main headline Qawwali performance: authentic Sufi ensemble performing timeless kalams, high-energy choruses, and spiritual rhythms.
                        </p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"><i class="fas fa-award"></i></div>
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <h4 class="timeline-card-title">Concluding Kalaam & Wrap Up</h4>
                            <span class="timeline-time">04:00 PM onwards</span>
                        </div>
                        <p class="timeline-desc">
                            Soulful final verses, vote of thanks by SENTEC, and official wrap up of the evening.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- =========================================================
         PASS PRICING SECTION
         ========================================================= -->
    <section id="passes" class="passes-section">
        <div class="social-container">
            
            <div class="section-head">
                <span class="section-tag">RESERVE YOUR ENTRY</span>
                <h2 class="section-title">Select Your Entry Pass</h2>
                <p class="section-desc">
                    Limited seating capacity in the Main Auditorium. Passes are allocated strictly on a verified advance registration basis.
                </p>
            </div>

            <div class="pricing-grid">

                <!-- 1. PARTICIPANT TIER -->
                <?php if (!empty($socialSettings['enable_participant'])): 
                    $evPartOrig = (int)($socialSettings['participant_original_price'] ?? 0);
                    $evPartDisc = (int)($socialSettings['participant_price'] ?? 0);
                    $evPartActive = !empty($socialSettings['participant_discount_active']);
                    $evPartEff = social_tier_effective_price('participant', $socialSettings);
                ?>
                <div class="pricing-card">
                    <?php if ($evPartActive && $evPartOrig > $evPartDisc): ?>
                        <span class="badge-pill-top">COMPETITOR SUBSIDY</span>
                    <?php endif; ?>
                    <div>
                        <h3 class="tier-name">Competition Participant</h3>
                        <p class="tier-subtitle">Subsidized rate for registered SENTEC Olympiad teams</p>
                        
                        <div class="price-box">
                            <?php if ($evPartActive && $evPartOrig > $evPartDisc): ?>
                                <div>
                                    <span class="price-original"><?php echo $evPartOrig; ?> PKR</span>
                                    <span class="discount-pill">SAVE <?php echo ($evPartOrig - $evPartEff); ?> PKR</span>
                                </div>
                            <?php endif; ?>
                            <div class="price-number">
                                <?php echo $evPartEff; ?>
                                <span class="price-currency">PKR</span>
                            </div>
                        </div>

                        <ul class="pricing-perks">
                            <li><i class="fas fa-check-circle"></i> Single Entry for 1 Registered Participant</li>
                            <li><i class="fas fa-check-circle"></i> Guaranteed Main Auditorium Seating</li>
                            <li><i class="fas fa-check-circle"></i> Full Access to Live Qawwali Performance</li>
                            <li><i class="fas fa-check-circle"></i> Traditional Harmonium & Tabla Ensemble</li>
                            <li><i class="fas fa-check-circle"></i> Digital QR Gatepass for NED Entry</li>
                        </ul>
                    </div>

                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register' : 'login'; ?>" class="btn-card-action btn-card-outline">
                        <?php echo isset($_SESSION['user_id']) ? 'Register as Participant' : 'Login to Register'; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- 2. GROUP TIER -->
                <?php if (!empty($socialSettings['enable_group'])): 
                    $evGrpOrig = (int)($socialSettings['group_original_price'] ?? 1500);
                    $evGrpDisc = (int)($socialSettings['group_price'] ?? 1200);
                    $evGrpActive = !empty($socialSettings['group_discount_active']);
                    $evGrpEff = social_tier_effective_price('group', $socialSettings);
                ?>
                <div class="pricing-card featured">
                    <span class="badge-pill-top"><?php echo ($evGrpActive && $evGrpOrig > $evGrpDisc) ? 'GROUP DISCOUNT' : 'BEST VALUE'; ?></span>
                    
                    <div>
                        <h3 class="tier-name">Group Pass (Squad of 3)</h3>
                        <p class="tier-subtitle">Special bundle package for friends and delegations</p>
                        
                        <div class="price-box">
                            <?php if ($evGrpActive && $evGrpOrig > $evGrpDisc): ?>
                                <div>
                                    <span class="price-original"><?php echo $evGrpOrig; ?> PKR</span>
                                    <span class="discount-pill">SAVE <?php echo ($evGrpOrig - $evGrpEff); ?> PKR</span>
                                </div>
                            <?php endif; ?>
                            <div class="price-number">
                                <?php echo $evGrpEff; ?>
                                <span class="price-currency">PKR</span>
                            </div>
                        </div>

                        <ul class="pricing-perks">
                            <li><i class="fas fa-check-circle"></i> <strong>Full Access for 3 People (1 Bundle)</strong></li>
                            <li><i class="fas fa-check-circle"></i> Guaranteed Main Auditorium Seating</li>
                            <li><i class="fas fa-check-circle"></i> Full Access to Live Qawwali Performance</li>
                            <li><i class="fas fa-check-circle"></i> Traditional Harmonium & Tabla Ensemble</li>
                            <li><i class="fas fa-check-circle"></i> 3 Individual QR E-Gatepasses Generated</li>
                        </ul>
                    </div>

                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register' : 'login'; ?>" class="btn-card-action btn-card-primary">
                        <?php echo isset($_SESSION['user_id']) ? 'Get Group Pass' : 'Login to Register'; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- 3. INDIVIDUAL TIER -->
                <?php if (!empty($socialSettings['enable_individual']) || (empty($socialSettings['enable_participant']) && empty($socialSettings['enable_group']))): 
                    $evIndOrig = (int)($socialSettings['individual_original_price'] ?? 700);
                    $evIndDisc = (int)($socialSettings['individual_price'] ?? 500);
                    $evIndActive = !empty($socialSettings['early_bird_active']);
                    $evIndEff = social_tier_effective_price('standard', $socialSettings);
                    $isOnlyOneTier = empty($socialSettings['enable_participant']) && empty($socialSettings['enable_group']);
                ?>
                <div class="pricing-card <?php echo $isOnlyOneTier ? 'featured' : ''; ?>">
                    <?php if ($evIndActive && $evIndOrig > $evIndDisc): ?>
                        <span class="badge-pill-top">EARLY BIRD SPECIAL</span>
                    <?php elseif ($isOnlyOneTier): ?>
                        <span class="badge-pill-top">OFFICIAL PASS</span>
                    <?php endif; ?>
                    
                    <div>
                        <h3 class="tier-name">Individual Pass</h3>
                        <p class="tier-subtitle">Standard single entry pass for NED students</p>
                        
                        <div class="price-box">
                            <?php if ($evIndActive && $evIndOrig > $evIndDisc): ?>
                                <div>
                                    <span class="price-original"><?php echo $evIndOrig; ?> PKR</span>
                                    <span class="discount-pill">SAVE <?php echo ($evIndOrig - $evIndEff); ?> PKR</span>
                                </div>
                            <?php endif; ?>
                            <div class="price-number">
                                <?php echo $evIndEff; ?>
                                <span class="price-currency">PKR</span>
                            </div>
                        </div>

                        <ul class="pricing-perks">
                            <li><i class="fas fa-check-circle"></i> Single Entry Pass for 1 Person</li>
                            <li><i class="fas fa-check-circle"></i> Guaranteed Main Auditorium Seating</li>
                            <li><i class="fas fa-check-circle"></i> Full Access to Live Qawwali Performance</li>
                            <li><i class="fas fa-check-circle"></i> Traditional Harmonium & Tabla Ensemble</li>
                            <li><i class="fas fa-check-circle"></i> Instant Digital QR E-Gatepass</li>
                        </ul>
                    </div>

                    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register' : 'login'; ?>" class="btn-card-action btn-card-primary">
                        <?php echo isset($_SESSION['user_id']) ? 'Register for Pass' : 'Login to Register'; ?>
                    </a>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </section>

    <!-- =========================================================
         HOW REGISTRATION WORKS
         ========================================================= -->
    <section style="padding: clamp(60px, 8vw, 90px) 0;">
        <div class="social-container">
            
            <div class="section-head">
                <span class="section-tag">FAST & STREAMLINED</span>
                <h2 class="section-title">How to Get Your Pass in 3 Steps</h2>
                <p class="section-desc">
                    Our digital ticketing system ensures quick verification and frictionless gate entry on the event evening.
                </p>
            </div>

            <div class="steps-grid">
                
                <div class="step-card">
                    <div class="step-num-badge">01</div>
                    <h3 class="step-card-title">Sign In or Create Account</h3>
                    <p class="step-card-text">
                        Log in to your SENTEC portal account. If you don't have one yet, sign up in 30 seconds with your email and basic details.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-num-badge">02</div>
                    <h3 class="step-card-title">Submit Attendee Details</h3>
                    <p class="step-card-text">
                        Head to the Social Pass registration page, choose your pass tier, and provide attendee name(s) and contact info.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-num-badge">03</div>
                    <h3 class="step-card-title">Upload Proof & Get QR Pass</h3>
                    <p class="step-card-text">
                        Transfer the fee via Bank Transfer / EasyPaisa / JazzCash and upload the transaction screenshot. Once approved, your QR pass is sent instantly via email!
                    </p>
                </div>

            </div>

            <!-- Poster & Venue Showcase -->
            <div class="venue-showcase-grid">
                
                <div class="poster-container">
                    <img src="images/social-poster.webp?v=<?php echo @filemtime(__DIR__ . '/images/social-poster.webp') ?: time(); ?>" alt="Ruh-e-Raqs Social Event Official Poster" class="poster-img" loading="lazy">
                </div>

                <div class="venue-info-box">
                    <div>
                        <span class="section-tag">CAMPUS PROTOCOL & VENUE</span>
                        <h3 style="font-family:'Outfit'; font-size:1.8rem; color:#fff; margin-bottom:10px;">NED University Campus Guidelines</h3>
                        <p style="color:var(--text-dim); font-size:0.92rem; line-height:1.6;">
                            We ensure a secure, disciplined, and memorable family atmosphere. Please review the gate entry checklist below before arriving.
                        </p>
                    </div>

                    <div class="venue-item">
                        <div class="venue-item-icon"><i class="fas fa-university"></i></div>
                        <div>
                            <div class="venue-item-title">Syed Mahmood Alam Auditorium</div>
                            <div class="venue-item-desc">NED University of Engineering & Technology, Main University Road, Gulshan-e-Iqbal, Karachi.</div>
                        </div>
                    </div>

                    <div class="venue-item">
                        <div class="venue-item-icon"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="venue-item-title">Physical ID & E-Gatepass Check</div>
                            <div class="venue-item-desc">Every attendee must carry their Original CNIC or Valid Student ID Card along with their SENTEC QR E-Gatepass (on mobile screen).</div>
                        </div>
                    </div>

                    <div class="venue-item">
                        <div class="venue-item-icon"><i class="fas fa-user-graduate"></i></div>
                        <div>
                            <div class="venue-item-title">Exclusively for NEDians</div>
                            <div class="venue-item-desc">Entry is strictly restricted to enrolled NED University students. Valid NED Student ID card is required. Non-NEDians are strictly not allowed.</div>
                        </div>
                    </div>

                    <div class="venue-item">
                        <div class="venue-item-icon"><i class="fas fa-user-tie"></i></div>
                        <div>
                            <div class="venue-item-title">Dress Code</div>
                            <div class="venue-item-desc">Traditional / Cultural / Smart Formal attire is encouraged to honor the spirit of Ruh-e-Raqs.</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- =========================================================
         FREQUENTLY ASKED QUESTIONS
         ========================================================= -->
    <section style="padding: 0 0 clamp(70px, 9vw, 100px);">
        <div class="social-container">
            
            <div class="section-head">
                <span class="section-tag">COMMON QUESTIONS</span>
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-desc">
                    Everything you need to know about attendance, gate access, and passes.
                </p>
            </div>

            <div class="faq-container">
                
                <div class="faq-item active">
                    <button class="faq-question" type="button">
                        <span>Can students from other universities or non-NEDians attend?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        No, RUH-E-RAQS is strictly restricted to currently enrolled students of NED University. Non-NEDians are not permitted, and an original valid NED Student ID card is mandatory at the gate along with your digital QR E-Gatepass.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>How and when do I receive my QR Gatepass?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        After submitting your payment proof, our finance desk verifies the transaction. Upon approval, your unique QR Code E-Gatepass is emailed to you and becomes permanently accessible in your SENTEC student portal dashboard under the Social section.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What kind of performance is RUH-E-RAQS?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        RUH-E-RAQS is an authentic live Sufi Qawwali evening featuring traditional vocals, harmonium, and tabla rhythms, creating a powerful spiritual and cultural atmosphere in the NED Main Auditorium.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>Can I purchase passes on-spot at the gate?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        Due to strict auditorium capacity constraints and campus security protocols, on-spot registrations are strictly limited and may not be guaranteed. We strongly advise booking your pass online in advance before the allocation closes.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span>What payment options are available?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        We support standard online bank transfers (IBFT), EasyPaisa, and JazzCash. Complete account details and IBANs are shown directly inside the registration form after selecting your pass tier.
                    </div>
                </div>

            </div>

        </div>
    </section>

</div>

<!-- Mobile Bottom Floating Action Bar -->
<div class="mobile-float-cta">
    <div>
        <div style="font-size: 0.72rem; font-family:'IBM Plex Mono', monospace; color: var(--brand-orange); text-transform: uppercase;">
            RUH-E-RAQS 2026
        </div>
        <div style="font-size: 0.95rem; font-weight: 800; color: #fff;">
            Passes from <?php echo social_tier_effective_price('standard', $socialSettings); ?> PKR
        </div>
    </div>
    <a href="<?php echo isset($_SESSION['user_id']) ? 'social_register' : 'login'; ?>" class="btn-neon-solid" style="padding: 10px 20px; font-size: 0.88rem;">
        Get Pass <i class="fas fa-arrow-right"></i>
    </a>
</div>

<script>
    // FAQ Accordion Handler
    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', () => {
            const item = button.parentElement;
            const isActive = item.classList.contains('active');
            
            // Close all
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
            
            // Toggle clicked
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
