<?php
include 'db_connection.php'; // Ensure this connects correctly
include 'header.php'; // Include your nav
?>
<link rel="stylesheet" href="team_modern.css">

<section class="team-section">
    <div class="container">
        
        <div class="section-header text-center mb-5">
            <h2 style="text-align: center;">Meet The Team</h2>
            <p style="color: #888;">The minds behind SENTEC</p>
        </div>

        <div class="filter-container">
            <button class="filter-btn active" onclick="filterTeam('all')">All</button>
            <!-- <button class="filter-btn" onclick="filterTeam('Presiding Board')">Presiding Board</button> -->
            <button class="filter-btn" onclick="filterTeam('Executive Committee')">Executive Committee</button>
            <button class="filter-btn" onclick="filterTeam('Directorate')">Directorate</button>
            <button class="filter-btn" onclick="filterTeam('Member')">Members</button>
        </div>

        <div class="team-grid">
            <?php
            // UPDATED QUERY: Sort by 'sort_order' first, then by Category Hierarchy, then Name
            $query = "SELECT * FROM team_members 
                      ORDER BY sort_order ASC, 
                      FIELD(category, 'Presiding Board', 'Executive Committee', 'Directorate', 'Member', 'Alumni'), 
                      name ASC";
            
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Safe Data Handling
                    $name = htmlspecialchars($row['name']);
                    $role = htmlspecialchars($row['designation']);
                    $cat  = htmlspecialchars($row['category']);
                    $dom  = htmlspecialchars($row['domain']);
                    $img  = !empty($row['image']) ? $row['image'] : 'images/default_profile.png';
                    $li   = $row['linkedin'];
            ?>
                <div class="team-card" data-category="<?= $cat ?>">
                    <div class="img-wrapper">
                        <img src="<?= $img ?>" alt="<?= $name ?>">
                        <div class="social-overlay">
                            <?php if($li): ?>
                                <a href="<?= $li ?>" target="_blank" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="member-name"><?= $name ?></h3>
                        <p class="member-role"><?= $role ?></p>
                        <?php if(!empty($dom)): ?>
                            <span class="member-domain"><?= $dom ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo "<p class='text-center text-white'>No team members found.</p>";
            }
            ?>
        </div>
    </div>
</section>

<script>
function filterTeam(category) {
    const cards = document.querySelectorAll('.team-card');
    const btns = document.querySelectorAll('.filter-btn');

    // Button Styling
    btns.forEach(btn => {
        if(btn.innerText.includes(category) || (category === 'all' && btn.innerText === 'All')) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Filtering Logic
    cards.forEach(card => {
        if (category === 'all' || card.getAttribute('data-category') === category) {
            card.style.display = 'flex'; // Restore flex display
            setTimeout(() => card.style.opacity = '1', 10);
        } else {
            card.style.opacity = '0';
            setTimeout(() => card.style.display = 'none', 300); // Wait for fade out
        }
    });
}
</script>

<?php include 'footer.php'; ?>
