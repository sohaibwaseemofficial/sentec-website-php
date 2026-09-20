<?php
require_once __DIR__ . '/cache_utils.php';

$partnersData = get_cached_data('public_partners_data', 600, function() {
    include __DIR__ . '/db_connection.php';
    $current = [];
    $past = [];
    if (isset($conn) && !$conn->connect_error) {
        $qCurrent = $conn->query("SELECT * FROM partners WHERE section = 'current'");
        if ($qCurrent && $qCurrent->num_rows > 0) {
            while ($row = $qCurrent->fetch_assoc()) {
                $current[] = $row;
            }
        }

        $qPast = $conn->query("SELECT * FROM partners WHERE section = 'past'");
        if ($qPast && $qPast->num_rows > 0) {
            while ($row = $qPast->fetch_assoc()) {
                $past[] = $row;
            }
        }
    }
    return ['current' => $current, 'past' => $past];
});

$currentPartners = $partnersData['current'] ?? [];
$pastPartners = $partnersData['past'] ?? [];
include 'header.php';
?>

<style>
    .partners-intro {
        max-width: 650px;
        margin-bottom: 50px;
    }
    .partners-intro p {
        margin: 18px 0 0;
        color: #9aa3a3;
        font-size: 16px;
        line-height: 1.7;
    }
    .partner-empty-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    @media (max-width: 768px) {
        .partner-empty-grid {
            grid-template-columns: 1fr;
        }
    }
    .partner-empty-card {
        min-height: 310px;
        padding: 32px;
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.65);
        position: relative;
    }
    .partner-empty-card-accent {
        background: linear-gradient(145deg, rgba(241, 90, 36, 0.14), rgba(15, 20, 22, 0.65) 60%);
    }
    .empty-icon {
        color: var(--orange);
    }
    .partner-empty-card > span {
        display: block;
        margin-top: 50px;
        color: #9aa3a3;
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.13em;
        text-transform: uppercase;
    }
    .partner-empty-card h2 {
        margin: 14px 0;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.2rem, 4vw, 3.8rem);
        line-height: 0.92;
        letter-spacing: -0.07em;
        font-weight: 500;
        color: #f4f1eb;
    }
    .partner-empty-card p {
        max-width: 320px;
        color: #9aa3a3;
        line-height: 1.6;
        font-size: 14px;
        margin: 0;
    }
    .empty-line {
        position: absolute;
        left: 32px;
        right: 32px;
        bottom: 26px;
        height: 1px;
        background: linear-gradient(90deg, var(--orange), transparent);
    }
    .partner-cta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-top: 24px;
        padding: 24px 0;
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .partner-cta > div {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #9aa3a3;
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .partner-cta > div svg {
        color: var(--orange);
    }
    .partner-cta a {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #f4f1eb;
        font-size: 14px;
        font-family: 'Space Grotesk', sans-serif;
        text-decoration: none;
        transition: color 0.2s;
    }
    .partner-cta a:hover {
        color: var(--orange);
    }
    .partner-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 48px;
    }
    .partner-card {
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.65);
        padding: 24px;
        display: flex;
        flex-direction: column;
        transition: border-color 0.2s;
    }
    .partner-card:hover {
        border-color: rgba(241, 90, 36, 0.5);
    }
    .partner-img-wrap {
        height: 140px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--line);
        display: grid;
        place-items: center;
        padding: 18px;
        margin-bottom: 18px;
    }
    .partner-img-wrap img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
    }
    .partner-card h3 {
        margin: 0 0 6px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 18px;
        color: #f4f1eb;
    }
    .partner-card p {
        margin: 0;
        color: #9aa3a3;
        font-size: 13px;
        line-height: 1.55;
    }
</style>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching Partners.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        NETWORK / COLLABORATION
                    </span>
                    <h1>
                        Build the<br>
                        <em>network.</em>
                    </h1>
                    <p>
                        SENTEC works best in signal with others. This page is ready to become the public record of the institutions, teams, and people who help students go further.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>ECOSYSTEM // ALLIANCE</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Main Content Section -->
        <section class="secondary-section motion-reveal is-visible">
            
            <?php if (!empty($currentPartners) || !empty($pastPartners)): ?>
                
                <?php if (!empty($currentPartners)): ?>
                    <div class="partners-intro">
                        <span class="section-kicker">ACTIVE ALLIANCES // <?php echo str_pad(count($currentPartners), 2, '0', STR_PAD_LEFT); ?> ENTITIES</span>
                        <p>Active collaborations, sponsorships, and academic support fueling student initiatives.</p>
                    </div>
                    <div class="partner-card-grid">
                        <?php foreach ($currentPartners as $p): ?>
                            <article class="partner-card">
                                <div class="partner-img-wrap">
                                    <img src="<?= htmlspecialchars(str_replace('../', './', $p['image_url'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                </div>
                                <h3><?= htmlspecialchars($p['name']) ?></h3>
                                <p><?= htmlspecialchars($p['description']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pastPartners)): ?>
                    <div class="partners-intro" style="margin-top: 50px;">
                        <span class="section-kicker">HISTORICAL PARTNERSHIPS</span>
                        <p>Past contributors whose joint ventures compounded our engineering community.</p>
                    </div>
                    <div class="partner-card-grid">
                        <?php foreach ($pastPartners as $p): ?>
                            <article class="partner-card">
                                <div class="partner-img-wrap">
                                    <img src="<?= htmlspecialchars(str_replace('../', './', $p['image_url'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                </div>
                                <h3><?= htmlspecialchars($p['name']) ?></h3>
                                <p><?= htmlspecialchars($p['description']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <!-- Transparent Empty Registry matching Partners.tsx -->
                <div class="partners-intro">
                    <span class="section-kicker">PARTNER REGISTER // 00</span>
                    <p>
                        No current or past partner records have been published yet. When a collaboration is confirmed, it belongs here with context—not decoration.
                    </p>
                </div>

                <div class="partner-empty-grid">
                    <article class="partner-empty-card">
                        <div class="empty-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="2"></circle>
                                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                            </svg>
                        </div>
                        <span>CURRENT PARTNERS</span>
                        <h2>Open channel.</h2>
                        <p>
                            Reserved for active collaborations, sponsorships, and institutional support.
                        </p>
                        <div class="empty-line"></div>
                    </article>

                    <article class="partner-empty-card partner-empty-card-accent">
                        <div class="empty-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m11 17 2 2a1 1 0 0 0 1.4 0l4.6-4.6a1 1 0 0 0 0-1.4l-2-2"></path>
                                <path d="m18 10 1-1a2 2 0 0 0 0-3l-2-2a2 2 0 0 0-3 0l-1 1"></path>
                                <path d="m14 14-3-3"></path>
                                <path d="m7.8 7.8-2.6 2.6a1 1 0 0 0 0 1.4l2 2a1 1 0 0 0 1.4 0l4.6-4.6a1 1 0 0 0 0-1.4l-2-2a1 1 0 0 0-1.4 0"></path>
                            </svg>
                        </div>
                        <span>PAST PARTNERS</span>
                        <h2>Archive ready.</h2>
                        <p>
                            Reserved for previous collaborations and the work they helped make possible.
                        </p>
                        <div class="empty-line"></div>
                    </article>
                </div>

            <?php endif; ?>

            <!-- Partnership Callout -->
            <div class="partner-cta">
                <div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>ARE YOU BUILDING WITH US?</span>
                </div>
                <a href="mailto:info@sentecneduet.live">
                    <span>Start a partnership conversation</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--orange);">
                        <line x1="7" y1="17" x2="17" y2="7"></line>
                        <polyline points="7 7 17 7 17 17"></polyline>
                    </svg>
                </a>
            </div>

        </section>
    </main>
</div>

<?php include 'footer.php'; ?>
