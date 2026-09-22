<?php
require_once __DIR__ . '/cache_utils.php';

// Canonical team list from DB with caching
$teamMembers = get_cached_data('team_members', 3600, function() {
    require_once __DIR__ . '/db_connection.php';
    global $conn;
    $members = [];
    if (isset($conn) && !$conn->connect_error) {
        $query = "SELECT * FROM team_members";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $members[] = [
                    'name' => $row['name'],
                    'role' => $row['designation'],
                    'category' => $row['category'],
                    'image' => $row['image'],
                    'linkedin' => $row['linkedin'],
                    'sort_order' => (int)($row['sort_order'] ?? 10)
                ];
            }
            usort($members, function ($left, $right) {
                return ($left['sort_order'] <=> $right['sort_order'])
                    ?: strcasecmp($left['name'], $right['name']);
            });
        }
    }
    return $members;
});

include 'header.php';

// Fallback matching trewwws/client/src/pages/Team.tsx exactly
if (empty($teamMembers)) {
    $teamMembers = [
        ["name" => "Zainab Khan", "role" => "President", "category" => "Executive Committee", "image" => "img_699ad02b1fc581.39195815.webp", "linkedin" => "https://www.linkedin.com/in/zainab-khan05/"],
        ["name" => "Mohid Ahmer Khan", "role" => "Vice President", "category" => "Executive Committee", "image" => "img_699ad0b416ae07.91713360.webp", "linkedin" => "https://www.linkedin.com/in/mohid-ahmer-khan-464bb9282/"],
        ["name" => "Hurain Maria Qureshi", "role" => "Vice President", "category" => "Executive Committee", "image" => "img_699ad0b416ae07.91713360.webp", "linkedin" => "https://www.linkedin.com/in/hurain-maria-qureshi-10955b326/"],
        ["name" => "Abdul Rafay", "role" => "General Secretary", "category" => "Executive Committee", "image" => "img_699ad0fc987926.36537145.webp", "linkedin" => "https://www.linkedin.com/in/abdul-rafay-18bab5356"],
        ["name" => "Ubaid Raza", "role" => "Joint Secretary", "category" => "Executive Committee", "image" => "img_699ad131235627.04780835.webp", "linkedin" => ""],
        ["name" => "Syed Nabeel Hussain", "role" => "Social Media Manager", "category" => "Executive Committee", "image" => "img_699ad1fe1997b8.34742482.webp", "linkedin" => "https://www.linkedin.com/in/snabeel"],
        ["name" => "Adeen Amin", "role" => "Advisor to President", "category" => "Executive Committee", "image" => "img_699ad32eb5e359.81166017.webp", "linkedin" => "https://www.linkedin.com/in/adeen-amin-922a03340/"],
        ["name" => "Abdul Hadi", "role" => "Technical Advisor", "category" => "Executive Committee", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => ""],
        ["name" => "Muhammad Raahim Rizwan", "role" => "Technical Advisor", "category" => "Executive Committee", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => "https://linkedin.com/in/muhammad-raahim-rizwan"],
        ["name" => "Asma Khurram", "role" => "Director Event Management", "category" => "Directorate", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => "https://www.linkedin.com/in/asma-khurram-752975313?utm_source=share_via&utm_content=profile&utm_medium=member_android"],
        ["name" => "Muneeb Ali", "role" => "Co-Director Event Management", "category" => "Directorate", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => ""],
        ["name" => "Fizza Naqvi", "role" => "Director Promotions", "category" => "Directorate", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => "https://www.linkedin.com/in/fizza-naqvi-48b8a8266?utm_source=share_via&utm_content=profile&utm_medium=member_android"],
        ["name" => "Muhammad Amaan Khan", "role" => "Co-Director Promotions", "category" => "Directorate", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => ""],
        ["name" => "Aneeqa Kamran", "role" => "Ambassador Promotions", "category" => "Directorate", "image" => "img_699ad35f68a8b8.95437537.webp", "linkedin" => ""],
        ["name" => "Maaz Shahid", "role" => "Director Cyber Security", "category" => "Directorate", "image" => "img_699add53728bc4.65384935.webp", "linkedin" => "http://maazshahid.me/"],
        ["name" => "Muhammad Danish Ali", "role" => "Co-Director Artificial Intelligence", "category" => "Directorate", "image" => "img_699c0541efbee0.36151658.webp", "linkedin" => ""],
        ["name" => "S.M. Abdullah Abdulbadeeli", "role" => "Director Artificial Intelligence", "category" => "Directorate", "image" => "img_699c04f2269b16.28720706.webp", "linkedin" => "https://www.linkedin.com/in/smaasui/"],
        ["name" => "Humaria Razi", "role" => "Director Robotics", "category" => "Directorate", "image" => "img_699ae3e0e24c29.42568247.webp", "linkedin" => "https://pk.linkedin.com/in/humaira-razi7"],
        ["name" => "Javeria Iqbal", "role" => "Deputy Director Robotics", "category" => "Directorate", "image" => "img_699adc234f2055.82945924.webp", "linkedin" => "https://www.linkedin.com/in/jaweria-iqbal-248632350/"],
        ["name" => "Kiran Zehra", "role" => "Director Finance & Marketing", "category" => "Directorate", "image" => "img_699ade413d1921.61900042.webp", "linkedin" => "https://www.linkedin.com/in/kiranzehra-bme?utm_source=share_via&utm_content=profile&utm_medium=member_android"],
        ["name" => "Iraj Naveed", "role" => "Director Videography", "category" => "Directorate", "image" => "img_699adea98f3f08.35826763.webp", "linkedin" => "https://www.linkedin.com/in/iraj-naveed-181643319/"],
        ["name" => "Manal Kafeel", "role" => "Director Content Creation", "category" => "Directorate", "image" => "img_699adefa863834.07249728.webp", "linkedin" => "https://www.linkedin.com/in/manal-kafeel-19962b300/"],
        ["name" => "Mariam Ali", "role" => "Director Social Media", "category" => "Directorate", "image" => "img_699adefa863834.07249728.webp", "linkedin" => "https://www.linkedin.com/in/mariam-ali-a1892538b?utm_source=share_via&utm_content=profile&utm_medium=member_android"],
        ["name" => "Syed Haseeb Ahmed", "role" => "Director Creativity", "category" => "Directorate", "image" => "img_699ae0998e5767.60895474.webp", "linkedin" => ""],
        ["name" => "Minhal Yusuf", "role" => "Ceo of Discord", "category" => "Directorate", "image" => "img_699ae0998e5767.60895474.webp", "linkedin" => ""],
        ["name" => "Syeda Anzila Amin", "role" => "Director Graphics", "category" => "Directorate", "image" => "img_699ae0998e5767.60895474.webp", "linkedin" => ""],
        ["name" => "Maaz Ali", "role" => "Director Photography", "category" => "Directorate", "image" => "img_699c06dbb791d4.98158587.webp", "linkedin" => ""],
        ["name" => "Ayesha Aftab", "role" => "Deputy Director Photography", "category" => "Directorate", "image" => "img_699c07f20f00e3.62554718.webp", "linkedin" => ""]
    ];
}
?>

<!-- 100% Exact Industrial CSS extracted from React index.css -->
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
        --ease-smooth: cubic-bezier(0.77, 0, 0.175, 1);
    }

    /* Ambient Technical Blueprint Grid Overlay */
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
        mask-image: linear-gradient(to bottom, black 0%, black 75%, transparent 92%);
        -webkit-mask-image: linear-gradient(to bottom, black 0%, black 75%, transparent 92%);
    }

    .secondary-page {
        position: relative;
        background: var(--ink);
        min-height: 100vh;
    }

    /* Secondary Hero matching SiteChrome.tsx and index.css lines 1964-2032 */
    .secondary-hero {
        padding: 118px clamp(22px, 7vw, 100px) 90px;
        border-bottom: 1px solid var(--line);
        position: relative;
        background: var(--ink);
    }
    .secondary-hero::after {
        content: "";
        position: absolute;
        pointer-events: none;
        opacity: 0.78;
        right: clamp(22px, 7vw, 100px);
        bottom: 26px;
        width: 138px;
        height: 30px;
        border-top: 1px solid var(--orange);
        border-right: 1px solid var(--orange);
        background: repeating-linear-gradient(
            90deg,
            transparent 0 11px,
            var(--orange) 12px 13px,
            transparent 14px 20px
        );
    }
    .secondary-hero-grid {
        width: min(1320px, 100%);
        margin: auto;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px;
        gap: 70px;
        align-items: end;
    }
    @media (max-width: 900px) {
        .secondary-hero-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }
    }

    .eyebrow,
    .section-kicker {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--orange);
        font: 10px "IBM Plex Mono", monospace;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }
    .eyebrow i {
        width: 35px;
        height: 1px;
        display: block;
        background: var(--orange);
    }

    .secondary-hero h1 {
        max-width: 760px;
        margin: 28px 0 25px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(4rem, 10vw, 9.5rem);
        line-height: 0.84;
        letter-spacing: -0.09em;
        font-weight: 500;
        color: var(--paper);
    }
    .secondary-hero h1 em {
        color: var(--orange);
        font-style: normal;
        display: block;
    }

    .secondary-hero p {
        max-width: 560px;
        color: var(--muted);
        font-size: 17px;
        line-height: 1.7;
        margin: 0;
    }

    .secondary-hero-index {
        align-self: stretch;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 10px 0 0 20px;
        min-height: 175px;
        border-left: 1px solid var(--orange);
        color: var(--muted);
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .secondary-hero-index span {
        color: var(--orange);
    }
    .secondary-hero-index strong {
        color: var(--paper);
        font-weight: 400;
    }
    .secondary-hero-index small {
        color: var(--muted-dark);
        font-size: 9px;
        line-height: 1.55;
        letter-spacing: 0.12em;
    }

    /* Secondary Section / Team Section matching index.css lines 2033-2037 & 3281-3288 */
    .secondary-section {
        width: min(1320px, 100%);
        margin: auto;
        padding: 92px clamp(22px, 7vw, 100px) 130px;
        position: relative;
    }
    .secondary-section::before {
        content: "";
        position: absolute;
        pointer-events: none;
        opacity: 0.78;
        left: max(22px, calc((100vw - 1320px) / 2 + 7vw));
        top: 34px;
        width: 42px;
        height: 42px;
        border-left: 1px solid var(--orange);
        border-bottom: 1px solid var(--orange);
    }
    .team-section {
        padding-top: 64px;
    }

    /* Toolbar matching index.css lines 2176-2210 */
    .team-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 30px;
        padding-bottom: 26px;
        border-bottom: 1px solid var(--line);
        position: relative;
        z-index: 2;
    }
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }
    .filter-bar button {
        padding: 9px 11px;
        border: 1px solid var(--line);
        background: transparent;
        color: var(--muted);
        font: 9px "IBM Plex Mono", monospace;
        letter-spacing: 0.08em;
        cursor: pointer;
        transition: all 0.2s ease;
        border-radius: 0;
        text-transform: uppercase;
    }
    .filter-bar button:hover,
    .filter-bar button.is-active {
        border-color: var(--orange);
        color: var(--paper);
        background: rgba(241, 90, 36, 0.1);
    }

    /* 4-Column Grid matching index.css lines 2406-2411 */
    .team-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-top: 28px;
        position: relative;
        z-index: 2;
    }
    @media (max-width: 1100px) {
        .team-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 768px) {
        .team-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 480px) {
        .team-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Team Card matching index.css lines 2412-2495 & 3298-3323 */
    .team-card {
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.65);
        display: flex;
        flex-direction: column;
        transition: border-color 0.25s ease;
    }
    .team-card:hover {
        border-color: rgba(241, 90, 36, 0.6);
    }
    .team-card.is-hidden {
        display: none !important;
    }

    .team-portrait {
        aspect-ratio: 1 / 0.98;
        position: relative;
        overflow: hidden;
        background: linear-gradient(
            145deg,
            rgba(241, 90, 36, 0.2),
            rgba(8, 11, 13, 0.85)
        );
    }
    .team-portrait::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
        background:
            linear-gradient(
                180deg,
                rgba(8, 11, 13, 0.02) 35%,
                rgba(8, 11, 13, 0.55) 100%
            ),
            linear-gradient(
                90deg,
                transparent 49%,
                rgba(241, 90, 36, 0.22) 50%,
                transparent 51%
            );
        mix-blend-mode: multiply;
    }
    .team-portrait img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: relative;
        z-index: 2;
        filter: grayscale(1) contrast(1.24) brightness(0.78);
        transition: filter 0.3s, transform 0.3s;
    }
    .team-card:hover .team-portrait img {
        filter: grayscale(0.15) contrast(1.14) brightness(0.92);
        transform: scale(1.03);
    }

    .portrait-fallback {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        color: var(--orange);
        z-index: 1;
    }

    .team-portrait > span {
        position: absolute;
        z-index: 4;
        left: 12px;
        top: 12px;
        color: var(--orange);
        font: 10px "IBM Plex Mono", monospace;
    }
    .team-portrait > a {
        position: absolute;
        z-index: 4;
        right: 12px;
        top: 12px;
        width: 28px;
        height: 28px;
        display: grid;
        place-items: center;
        background: rgba(8, 11, 13, 0.75);
        color: var(--paper);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: background 0.2s, color 0.2s, border-color 0.2s;
    }
    .team-portrait > a:hover {
        background: var(--orange);
        color: #080b0d;
        border-color: var(--orange);
    }

    .team-card-copy {
        padding: 16px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .team-card-copy > span {
        color: var(--orange);
        font: 8px "IBM Plex Mono", monospace;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .team-card-copy h2 {
        margin: 12px 0 5px;
        font-size: 20px;
        line-height: 1;
        letter-spacing: -0.04em;
        font-weight: 500;
        color: var(--paper);
        font-family: "Space Grotesk", sans-serif;
    }
    .team-card-copy p {
        margin: 0;
        color: var(--muted);
        font: 11px "IBM Plex Mono", monospace;
        line-height: 1.45;
    }
</style>

<!-- Subtle Ambient Blueprint Technical Grid Overlay -->
<div class="ambient-grid" aria-hidden="true"></div>

<div class="secondary-page">
    <main>
        <!-- 1. Exact Hero matching SiteChrome.tsx & Screenshot 1 -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        FIELD DIRECTORY / PEOPLE
                    </span>
                    <h1>
                        Meet the<br>
                        <em>team.</em>
                    </h1>
                    <p>
                        The people building the next signal at NED University—operators, mentors, directors, and curious engineers moving the system forward.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>FIELD / ACTIVE</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <!-- 2. Exact Secondary Team Section matching Team.tsx & Screenshot 2 -->
        <section class="secondary-section team-section motion-reveal is-visible">
            <div class="team-toolbar">
                <span class="section-kicker">
                    DIRECTORY / <span id="recordCount"><?= str_pad(count($teamMembers), 2, '0', STR_PAD_LEFT) ?></span> RECORDS
                </span>
                <div class="filter-bar" id="teamFilterBar">
                    <button type="button" class="is-active" data-filter="ALL">ALL</button>
                    <button type="button" data-filter="EXECUTIVE COMMITTEE">EXECUTIVE COMMITTEE</button>
                    <button type="button" data-filter="DIRECTORATE">DIRECTORATE</button>
                </div>
            </div>

            <!-- 4-Column Grid -->
            <div class="team-grid" id="teamGrid">
                <?php foreach ($teamMembers as $index => $member): 
                    $name = htmlspecialchars($member['name']);
                    $role = htmlspecialchars($member['role']);
                    $cat  = htmlspecialchars($member['category']);
                    $img  = !empty($member['image']) ? $member['image'] : '';
                    $li   = !empty($member['linkedin']) ? htmlspecialchars($member['linkedin']) : '';
                    
                    // Image URL resolution
                    $imgSrc = '';
                    if (!empty($img)) {
                        if (file_exists($img)) {
                            $imgSrc = $img;
                        } elseif (file_exists('images/' . basename($img))) {
                            $imgSrc = 'images/' . basename($img);
                        } elseif (filter_var($img, FILTER_VALIDATE_URL)) {
                            $imgSrc = $img;
                        } else {
                            $imgSrc = 'https://sentecneduet.live/images/uploads/team/' . basename($img);
                        }
                    }
                ?>
                    <article class="team-card" data-category="<?= strtoupper($cat) ?>">
                        <div class="team-portrait">
                            <?php if (!empty($imgSrc)): ?>
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= $name ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>

                            <!-- Centered Lucide UserRound SVG Icon Fallback -->
                            <div class="portrait-fallback">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round text-[#f15a24]">
                                    <circle cx="12" cy="8" r="5"></circle>
                                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                                </svg>
                            </div>

                            <!-- Numeric Index in Top Left (01, 02, 03...) -->
                            <span><?= sprintf('%02d', $index + 1) ?></span>

                            <!-- Floating LinkedIn Badge in Top Right -->
                            <?php if (!empty($li)): ?>
                                <a href="<?= $li ?>" target="_blank" rel="noreferrer" aria-label="<?= $name ?> on LinkedIn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                                        <rect width="4" height="12" x="2" y="9"></rect>
                                        <circle cx="4" cy="4" r="2"></circle>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Card Metadata Copy -->
                        <div class="team-card-copy">
                            <span><?= $cat ?></span>
                            <h2><?= $name ?></h2>
                            <p><?= $role ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<!-- Instant Filter Logic matching Team.tsx -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterBtns = document.querySelectorAll('#teamFilterBar button');
        const cards = document.querySelectorAll('#teamGrid .team-card');
        const counter = document.getElementById('recordCount');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const selected = this.getAttribute('data-filter').trim().toUpperCase();

                // Toggle active button
                filterBtns.forEach(b => b.classList.remove('is-active'));
                this.classList.add('is-active');

                let count = 0;
                cards.forEach(card => {
                    const cardCat = (card.getAttribute('data-category') || '').trim().toUpperCase();
                    if (selected === 'ALL' || cardCat === selected) {
                        card.classList.remove('is-hidden');
                        count++;
                    } else {
                        card.classList.add('is-hidden');
                    }
                });

                if (counter) {
                    counter.textContent = String(count).padStart(2, '0');
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>
