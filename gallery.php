<?php
require_once __DIR__ . '/cache_utils.php';

// Fetch gallery records with high-performance cache
$galleryCache = get_cached_data('public_gallery_data', 600, function() {
    include __DIR__ . '/db_connection.php';
    $groups = [];
    $items = [];
    if (isset($conn) && !$conn->connect_error) {
        $gResult = $conn->query("SELECT DISTINCT group_title FROM gallery ORDER BY group_title ASC");
        if ($gResult && $gResult->num_rows > 0) {
            while ($gRow = $gResult->fetch_assoc()) {
                if (!empty($gRow['group_title'])) {
                    $groups[] = $gRow['group_title'];
                }
            }
        }

        $qResult = $conn->query("SELECT * FROM gallery ORDER BY id DESC");
        if ($qResult && $qResult->num_rows > 0) {
            while ($row = $qResult->fetch_assoc()) {
                $items[] = $row;
            }
        }
    }
    return ['groups' => $groups, 'items' => $items];
});

$groups = $galleryCache['groups'] ?? [];
$galleryItems = $galleryCache['items'] ?? [];
include 'header.php';

// Fallback curated gallery records from Gallery.tsx if DB is empty
if (empty($galleryItems)) {
    $fallbackRecords = [
        ["title" => "Systems in motion", "group_title" => "WORKSHOPS", "description" => "Hands-on robotics prototyping and circuit calibration."],
        ["title" => "The room becomes a lab", "group_title" => "EVENTS", "description" => "Hackathon arenas and engineering challenges."],
        ["title" => "From sketch to prototype", "group_title" => "PROJECTS", "description" => "CAD models translated into functional hardware."],
        ["title" => "People behind the systems", "group_title" => "COMMUNITY", "description" => "Mentors and students working across disciplines."],
        ["title" => "A new cohort arrives", "group_title" => "ORIENTATION", "description" => "Introducing fresh engineers to the SENTEC laboratory."],
        ["title" => "Making the invisible visible", "group_title" => "RESEARCH", "description" => "Signal analysis and machine learning test benches."]
    ];
    foreach ($fallbackRecords as $idx => $r) {
        $galleryItems[] = [
            'id' => $idx + 1,
            'group_title' => $r['group_title'],
            'main_image_url' => '',
            'additional_image_url' => '',
            'description' => $r['description']
        ];
        if (!in_array($r['group_title'], $groups)) {
            $groups[] = $r['group_title'];
        }
    }
}
?>

<style>
    .gallery-section {
        padding-top: 64px;
    }
    .archive-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 30px;
        padding-bottom: 26px;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .archive-toolbar p {
        margin: 11px 0 0;
        color: #9aa3a3;
        font-size: 14px;
    }
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }
    .filter-bar button {
        padding: 9px 12px;
        border: 1px solid var(--line);
        background: transparent;
        color: #9aa3a3;
        font: 600 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.08em;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        border-radius: 0;
    }
    .filter-bar button:hover,
    .filter-bar button.is-active {
        border-color: var(--orange);
        color: #f4f1eb;
        background: rgba(241, 90, 36, 0.12);
    }
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-top: 32px;
    }
    @media (max-width: 992px) {
        .gallery-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 600px) {
        .gallery-grid {
            grid-template-columns: 1fr;
        }
    }
    .gallery-card {
        min-height: 340px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.75);
        position: relative;
        transition: border-color 0.25s ease;
    }
    .gallery-card:hover {
        border-color: rgba(241, 90, 36, 0.6);
    }
    .gallery-card.is-hidden {
        display: none !important;
    }
    .gallery-card-visual {
        min-height: 180px;
        display: grid;
        place-items: center;
        position: relative;
        color: rgba(244, 241, 235, 0.8);
        border-bottom: 1px solid rgba(244, 241, 235, 0.14);
        background: linear-gradient(145deg, rgba(241, 90, 36, 0.12), rgba(8, 11, 13, 0.9));
        overflow: hidden;
    }
    .gallery-card-visual img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        inset: 0;
        filter: grayscale(0.5) contrast(1.1);
        transition: filter 0.3s ease, transform 0.3s ease;
    }
    .gallery-card:hover .gallery-card-visual img {
        filter: grayscale(0) contrast(1.05);
        transform: scale(1.04);
    }
    .gallery-card-index {
        position: absolute;
        top: 12px;
        left: 12px;
        color: var(--orange);
        font: 700 10px "IBM Plex Mono", monospace;
        letter-spacing: 0.08em;
        z-index: 2;
    }
    .gallery-card-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 16px 0 8px;
        color: #5d696c;
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .gallery-card h2 {
        margin: 0 0 6px;
        font-size: 19px;
        line-height: 1.2;
        font-weight: 500;
        color: #f4f1eb;
        font-family: 'Space Grotesk', sans-serif;
        letter-spacing: -0.03em;
    }
    .gallery-card p {
        margin: 0;
        color: var(--orange);
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .gallery-card-desc {
        color: #9aa3a3 !important;
        font: 13px 'Space Grotesk', sans-serif !important;
        text-transform: none !important;
        margin-top: 8px !important;
        line-height: 1.5;
    }
</style>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching Gallery.tsx & SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        ARCHIVE / VISUAL FIELD
                    </span>
                    <h1>
                        The<br>
                        <em>gallery.</em>
                    </h1>
                    <p>
                        A growing visual record of workshops, prototypes, people, and the moments that make SENTEC a living engineering system.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>ARCHIVE // TELEMETRY</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- Secondary Gallery Section -->
        <section class="secondary-section gallery-section motion-reveal is-visible">
            <div class="archive-toolbar">
                <div>
                    <span class="section-kicker">
                        RECORDS / <span id="galleryCount"><?= str_pad(count($galleryItems), 2, '0', STR_PAD_LEFT) ?></span>
                    </span>
                    <p>Curated signals from the SENTEC field.</p>
                </div>

                <!-- Dynamic Filter Bar -->
                <div class="filter-bar" id="galleryFilterBar">
                    <button type="button" class="is-active" data-filter="ALL">ALL</button>
                    <?php foreach ($groups as $g): ?>
                        <button type="button" data-filter="<?= htmlspecialchars(strtoupper($g)) ?>">
                            <?= htmlspecialchars(strtoupper($g)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Gallery Grid -->
            <div class="gallery-grid" id="galleryGrid">
                <?php foreach ($galleryItems as $idx => $item): 
                    $title = !empty($item['title']) ? htmlspecialchars($item['title']) : htmlspecialchars($item['group_title']);
                    $cat   = htmlspecialchars($item['group_title']);
                    $desc  = !empty($item['description']) ? htmlspecialchars($item['description']) : '';
                    $img   = !empty($item['main_image_url']) ? str_replace('../', './', $item['main_image_url']) : '';
                ?>
                    <article class="gallery-card" data-group="<?= strtoupper($cat) ?>">
                        <div class="gallery-card-visual">
                            <span class="gallery-card-index"><?= sprintf('%02d', $idx + 1) ?></span>

                            <?php if (!empty($img)): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= $title ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>

                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--orange); z-index: 1;">
                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                                <circle cx="12" cy="13" r="3"></circle>
                            </svg>
                        </div>

                        <div class="gallery-card-meta">
                            <span>ARCHIVE // RECORD</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--orange);">
                                <line x1="7" y1="17" x2="17" y2="7"></line>
                                <polyline points="7 7 17 7 17 17"></polyline>
                            </svg>
                        </div>

                        <h2><?= $title ?></h2>
                        <p><?= $cat ?></p>
                        <?php if (!empty($desc)): ?>
                            <div class="gallery-card-desc"><?= $desc ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<!-- Instant Dynamic JavaScript Filtering without page reload -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterBtns = document.querySelectorAll('#galleryFilterBar button');
        const cards = document.querySelectorAll('#galleryGrid .gallery-card');
        const countEl = document.getElementById('galleryCount');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.getAttribute('data-filter').trim().toUpperCase();

                filterBtns.forEach(b => b.classList.remove('is-active'));
                this.classList.add('is-active');

                let count = 0;
                cards.forEach(card => {
                    const grp = (card.getAttribute('data-group') || '').trim().toUpperCase();
                    if (target === 'ALL' || grp === target) {
                        card.classList.remove('is-hidden');
                        count++;
                    } else {
                        card.classList.add('is-hidden');
                    }
                });

                if (countEl) {
                    countEl.textContent = String(count).padStart(2, '0');
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>
