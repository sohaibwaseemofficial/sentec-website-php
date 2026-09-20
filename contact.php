<?php
include 'header.php';
include 'recaptcha_config.php';
?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<style>
    .contact-layout {
        display: grid;
        grid-template-columns: 310px minmax(0, 1fr);
        gap: clamp(40px, 8vw, 130px);
        align-items: start;
    }
    @media (max-width: 900px) {
        .contact-layout {
            grid-template-columns: 1fr;
            gap: 48px;
        }
    }
    .contact-aside h2 {
        margin: 18px 0 14px;
        font-size: clamp(2.2rem, 4vw, 3.8rem);
        font-family: 'Space Grotesk', sans-serif;
        line-height: 0.95;
        font-weight: 500;
        color: #f4f1eb;
        letter-spacing: -0.05em;
    }
    .contact-aside > p {
        color: #9aa3a3;
        font-size: 15px;
        line-height: 1.65;
        margin-bottom: 24px;
    }
    .contact-detail {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-top: 22px;
        color: #9aa3a3;
        font-size: 13px;
        line-height: 1.55;
    }
    .contact-detail svg {
        color: var(--orange);
        flex: 0 0 auto;
        margin-top: 2px;
    }
    .contact-detail a {
        color: #f4f1eb;
        text-decoration: none;
        transition: color 0.2s;
    }
    .contact-detail a:hover {
        color: var(--orange);
    }
    .contact-stamp {
        margin-top: 48px;
        padding-top: 15px;
        border-top: 1px solid var(--line);
        color: #5d696c;
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.12em;
        line-height: 1.7;
        text-transform: uppercase;
    }
    .contact-stamp strong {
        color: var(--orange);
        font-weight: 600;
    }
    .form-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 24px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .form-heading p {
        max-width: 280px;
        margin: 0;
        color: #9aa3a3;
        font-size: 13px;
        line-height: 1.6;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin: 24px 0;
    }
    @media (max-width: 600px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 20px;
        }
    }
    .form-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-top: 32px;
        padding-top: 20px;
        border-top: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .form-actions .spam-protection-badge {
        display: flex;
        gap: 7px;
        align-items: center;
        color: #5d696c;
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .signal-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        border: 0 !important;
        background: #f15a24 !important;
        color: #080b0d !important;
        padding: 14px 26px !important;
        font: 700 12px "IBM Plex Mono", monospace !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
    }
    .signal-btn span {
        color: #080b0d !important;
        font: 700 12px "IBM Plex Mono", monospace !important;
        letter-spacing: 0.08em !important;
    }
    .signal-btn svg {
        color: #080b0d !important;
        stroke: #080b0d !important;
    }
    .signal-btn:hover {
        background: #ff7a47 !important;
        transform: translateY(-1px);
    }
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    textarea:-webkit-autofill {
        -webkit-box-shadow: 0 0 0 1000px #151c20 inset !important;
        -webkit-text-fill-color: #f4f1eb !important;
        caret-color: #f4f1eb !important;
        transition: background-color 5000s ease-in-out 0s;
    }
</style>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching Contact.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        OPEN CHANNEL / CONTACT
                    </span>
                    <h1>
                        Send a<br>
                        <em>signal.</em>
                    </h1>
                    <p>
                        Questions, collaboration ideas, and ambitious problems belong here. Reach the SENTEC team and we will route your message to the right field.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>COMMUNICATION // DISPATCH</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Main Content Section: Two Column Industrial Layout -->
        <section class="secondary-section contact-layout motion-reveal is-visible">
            
            <!-- Left Console: Channel Status & Metadata -->
            <div class="contact-aside">
                <span class="section-kicker">CHANNEL STATUS</span>
                <h2>We are listening.</h2>
                <p>
                    The fastest route is the transmission form. For direct university correspondence, use the address below.
                </p>

                <div class="contact-detail">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <a href="mailto:info@sentecneduet.live">info@sentecneduet.live</a>
                </div>

                <div class="contact-detail">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>
                        Student Affairs Dept<br>
                        NED University, Karachi
                    </span>
                </div>

                <div class="contact-stamp">
                    RESPONSE WINDOW<br>
                    <strong>01—03 DAYS</strong>
                </div>

                <!-- Verified Social Channels -->
                <div style="margin-top: 32px; border-top: 1px solid var(--line); padding-top: 20px;">
                    <span style="font-size: 10px; font-family: 'IBM Plex Mono', monospace; color: #5d696c; display: block; margin-bottom: 12px; letter-spacing: 0.14em;">VERIFIED SOCIALS</span>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="https://www.linkedin.com/company/sentecneduet" target="_blank" rel="noopener noreferrer"
                           style="display: flex; align-items: center; gap: 10px; color: var(--orange); font-size: 13px; font-family: 'IBM Plex Mono', monospace; text-decoration: none;">
                            <i class="fab fa-linkedin-in text-xs"></i> <span>LINKEDIN ↗</span>
                        </a>
                        <a href="https://www.facebook.com/sentecneduet" target="_blank" rel="noopener noreferrer"
                           style="display: flex; align-items: center; gap: 10px; color: var(--orange); font-size: 13px; font-family: 'IBM Plex Mono', monospace; text-decoration: none;">
                            <i class="fab fa-facebook-f text-xs"></i> <span>FACEBOOK ↗</span>
                        </a>
                        <a href="https://www.instagram.com/sentecneduet/" target="_blank" rel="noopener noreferrer"
                           style="display: flex; align-items: center; gap: 10px; color: var(--orange); font-size: 13px; font-family: 'IBM Plex Mono', monospace; text-decoration: none;">
                            <i class="fab fa-instagram text-xs"></i> <span>INSTAGRAM ↗</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Console: Signal Transmission Form -->
            <form class="signal-form" action="contact_work.php" method="POST">
                <div class="form-heading">
                    <span class="section-kicker">TRANSMISSION FORM // 01</span>
                    <p>Tell us what you are building, exploring, or trying to solve.</p>
                </div>

                <!-- Honeypot + Timestamp -->
                <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">
                <input type="hidden" name="_ts" value="<?php echo time(); ?>">

                <div style="margin-bottom: 22px;">
                    <label for="fullName">FULL NAME</label>
                    <input type="text" id="fullName" name="fullname" required placeholder="Your full name" autofocus>
                </div>

                <div class="form-row">
                    <div>
                        <label for="contactEmail">EMAIL ADDRESS</label>
                        <input type="email" id="contactEmail" name="email" required placeholder="you@example.com">
                    </div>
                    <div>
                        <label for="contactPhone">PHONE NUMBER</label>
                        <input type="tel" id="contactPhone" name="phone" placeholder="Optional contact number">
                    </div>
                </div>

                <div style="margin-bottom: 22px;">
                    <label for="contactMessage">MESSAGE</label>
                    <textarea id="contactMessage" name="message" rows="5" required placeholder="Describe the signal or question..."></textarea>
                </div>

                <?php if (function_exists('recaptcha_enabled') && recaptcha_enabled()): ?>
                    <div style="margin: 20px 0;">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <span class="spam-protection-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--orange);">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                        LIGHTWEIGHT SPAM PROTECTION
                    </span>
                    <button type="submit" class="signal-btn">
                        <span>TRANSMIT MESSAGE</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </form>

        </section>
    </main>
</div>

<?php include 'footer.php'; ?>
