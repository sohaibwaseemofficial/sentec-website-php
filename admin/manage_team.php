<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../cache_utils.php';
require_once __DIR__ . '/../image_utils.php';

$feedback = '';
$feedbackType = 'success';

// 1. HANDLE ADD MEMBER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['designation']);
    $cat = $_POST['category'];
    $linkedin = trim($_POST['linkedin']);
    $domain = trim($_POST['domain']);
    $order = (int)$_POST['sort_order'];
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $feedback = "Photo upload failed. Please select a valid photo.";
        $feedbackType = "danger";
    } else {
        $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/team/', 'images/uploads/team/');

        if (!$uploadResult['success']) {
            $feedback = $uploadResult['error'] ?? "Failed to upload photo.";
            $feedbackType = "danger";
        } else {
            $imgPath = $uploadResult['path'];

            $stmt = $conn->prepare("INSERT INTO team_members (name, designation, category, image, linkedin, domain, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $name, $role, $cat, $imgPath, $linkedin, $domain, $order);

            if($stmt->execute()) {
                invalidate_cache('team_members');
                $feedback = "Team member '$name' added successfully!";
                $feedbackType = "success";
            } else {
                $feedback = "Error adding member: " . $stmt->error;
                $feedbackType = "danger";
            }
            $stmt->close();
        }
    }
}

// 2. HANDLE UPDATE ORDER (Quick Edit)
if (isset($_POST['update_order'])) {
    $id = (int)$_POST['member_id'];
    $new_order = (int)$_POST['new_sort_order'];
    $stmt = $conn->prepare("UPDATE team_members SET sort_order = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_order, $id);
    if ($stmt->execute()) {
        invalidate_cache('team_members');
        $feedback = "Display order updated.";
        $feedbackType = "success";
    }
    $stmt->close();
}

// Handle query message alerts (e.g. from delete or edit)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $feedback = "Team member removed successfully.";
        $feedbackType = "success";
    } elseif ($_GET['msg'] === 'updated') {
        $feedback = "Team member details updated.";
        $feedbackType = "success";
    }
}

// 3. FETCH ALL TEAM MEMBERS (Sorted by Priority)
$teamResult = $conn->query("SELECT * FROM team_members ORDER BY sort_order ASC, id ASC");
$allMembers = [];
if ($teamResult) {
    while ($row = $teamResult->fetch_assoc()) {
        $allMembers[] = $row;
    }
}

$totalCount = count($allMembers);
$execCount = count(array_filter($allMembers, fn($m) => $m['category'] === 'Executive Committee'));
$dirCount = count(array_filter($allMembers, fn($m) => $m['category'] === 'Directorate'));
$presCount = count(array_filter($allMembers, fn($m) => $m['category'] === 'Presiding Board'));
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h2>Team Management</h2>
        <p class="mb-0">Manage all team members shown on the public site and control their display order.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge" style="background: rgba(255, 90, 0, 0.15); border: 1px solid rgba(255, 90, 0, 0.4); color: #ff8c42; padding: 8px 14px; font-size: 0.85rem;">
            <?= $totalCount ?> Total Members
        </span>
        <a href="../team.php" target="_blank" class="btn btn-sm btn-outline-light" style="display:inline-flex; align-items:center; gap:6px;">
            <i class="fas fa-external-link-alt"></i> View Public Page
        </a>
    </div>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?= $feedbackType ?> alert-dismissible fade show" role="alert" style="border-radius: 8px;">
        <?= htmlspecialchars($feedback) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- ADD MEMBER FORM -->
    <div class="col-lg-4">
        <div class="glass-panel" style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px;">
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fas fa-user-plus" style="color: var(--accent);"></i>
                <h4 class="text-white mb-0" style="font-size: 1.25rem;">Add Team Member</h4>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_member" value="1">
                
                <div class="mb-3">
                    <label class="text-white-50 small mb-1">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" style="background:#090d16; border-color:#2a3346; color:#fff;" required placeholder="e.g. John Doe">
                </div>
                
                <div class="mb-3">
                    <label class="text-white-50 small mb-1">Role / Designation <span class="text-danger">*</span></label>
                    <input type="text" name="designation" class="form-control" style="background:#090d16; border-color:#2a3346; color:#fff;" required placeholder="e.g. Director AI">
                </div>
                
                <div class="mb-3">
                    <label class="text-white-50 small mb-1">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" style="background:#090d16; border-color:#2a3346; color:#fff;">
                        <option value="Executive Committee">Executive Committee</option>
                        <option value="Directorate" selected>Directorate</option>
                        <option value="Presiding Board">Presiding Board</option>
                        <option value="Member">Member</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="text-white-50 small mb-1">Domain (Optional)</label>
                    <input type="text" name="domain" class="form-control" style="background:#090d16; border-color:#2a3346; color:#fff;" placeholder="e.g. AI / Web Dev / Robotics">
                </div>

                <div class="mb-3">
                    <label class="text-white-50 small mb-1">LinkedIn URL (Optional)</label>
                    <input type="url" name="linkedin" class="form-control" style="background:#090d16; border-color:#2a3346; color:#fff;" placeholder="https://linkedin.com/in/...">
                </div>

                <div class="mb-3">
                    <label class="text-white small mb-1" style="color: var(--accent) !important;">Display Priority Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $totalCount + 1 ?>" style="background:#090d16; border-color: var(--accent); color:#fff;">
                    <small class="text-muted d-block mt-1">Lower numbers appear first (e.g. 1 to 28).</small>
                </div>

                <div class="mb-4">
                    <label class="text-white-50 small mb-1">Photo <span class="text-danger">*</span></label>
                    <input type="file" name="image" class="form-control" style="background:#090d16; border-color:#2a3346; color:#fff;" accept="image/*" required>
                    <small class="text-muted">Auto-converted to optimized WebP format.</small>
                </div>

                <button type="submit" class="btn-neon w-100 py-2">
                    <i class="fas fa-plus me-1"></i> Add Member
                </button>
            </form>
        </div>
    </div>

    <!-- CURRENT TEAM LIST -->
    <div class="col-lg-8">
        <div class="glass-panel" style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="text-white mb-0" style="font-size: 1.25rem;">Active Roster</h4>
                    <span class="badge bg-secondary" id="visibleCountBadge"><?= $totalCount ?></span>
                </div>
                
                <!-- Quick Search Input -->
                <div style="min-width: 240px; max-width: 320px; width: 100%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" id="teamSearchInput" class="form-control bg-dark border-secondary text-white" placeholder="Search by name or role...">
                    </div>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-category active" data-filter="all" style="background: var(--accent); color: #000; font-weight: 600; border-radius: 6px; padding: 4px 12px;">
                    All (<?= $totalCount ?>)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-category text-white" data-filter="Executive Committee" style="border-radius: 6px; padding: 4px 12px;">
                    Exec Comm (<?= $execCount ?>)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-category text-white" data-filter="Directorate" style="border-radius: 6px; padding: 4px 12px;">
                    Directorate (<?= $dirCount ?>)
                </button>
                <?php if ($presCount > 0): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-category text-white" data-filter="Presiding Board" style="border-radius: 6px; padding: 4px 12px;">
                    Presiding Board (<?= $presCount ?>)
                </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0" id="teamTable" style="color: #cbd5e1;">
                    <thead style="position: sticky; top: 0; background: #0f172a; z-index: 2;">
                        <tr style="border-bottom: 2px solid rgba(255,255,255,0.1);">
                            <th style="width: 90px;">Order</th>
                            <th style="width: 60px;">Photo</th>
                            <th>Name / Designation</th>
                            <th>Category</th>
                            <th style="width: 90px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allMembers)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No team members found in database.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allMembers as $row): 
                                $cat = $row['category'];
                                $badgeStyle = "background: rgba(148, 163, 184, 0.12); border: 1px solid rgba(148, 163, 184, 0.3); color: #94a3b8;";
                                if ($cat === 'Executive Committee') {
                                    $badgeStyle = "background: rgba(255, 90, 0, 0.12); border: 1px solid rgba(255, 90, 0, 0.4); color: #ff8c42;";
                                } elseif ($cat === 'Directorate') {
                                    $badgeStyle = "background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.4); color: #38bdf8;";
                                } elseif ($cat === 'Presiding Board') {
                                    $badgeStyle = "background: rgba(250, 204, 21, 0.12); border: 1px solid rgba(250, 204, 21, 0.4); color: #facc15;";
                                }
                            ?>
                            <tr class="team-row" data-category="<?= htmlspecialchars($row['category']) ?>" data-search="<?= strtolower(htmlspecialchars($row['name'] . ' ' . $row['designation'] . ' ' . ($row['domain'] ?? ''))) ?>">
                                <td>
                                    <form method="POST" style="display:flex; align-items:center; gap:4px;" class="mb-0">
                                        <input type="hidden" name="update_order" value="1">
                                        <input type="hidden" name="member_id" value="<?= $row['id']; ?>">
                                        <input type="number" name="new_sort_order" value="<?= (int)$row['sort_order']; ?>" 
                                               class="form-control form-control-sm" 
                                               style="width: 52px; padding: 4px; text-align: center; background:#070b12; border:1px solid #334155; color:#fff; font-weight:600;">
                                        <button type="submit" class="btn btn-sm btn-link text-success p-1" title="Save Order">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <img src="../<?= htmlspecialchars($row['image']); ?>" 
                                         onerror="this.onerror=null;this.src='../images/logo.png';" 
                                         style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:1.5px solid rgba(255,255,255,0.15);" 
                                         alt="<?= htmlspecialchars($row['name']); ?>"
                                         loading="lazy">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong class="text-white"><?= htmlspecialchars($row['name']); ?></strong>
                                        <?php if (!empty($row['linkedin'])): ?>
                                            <a href="<?= htmlspecialchars($row['linkedin']); ?>" target="_blank" rel="noopener" class="text-muted" title="View LinkedIn" style="font-size:0.85rem;">
                                                <i class="fab fa-linkedin" style="color: #0ea5e9;"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small" style="color: #94a3b8;"><?= htmlspecialchars($row['designation']); ?></div>
                                    <?php if (!empty($row['domain'])): ?>
                                        <span class="badge bg-dark text-muted border border-secondary" style="font-size: 0.65rem; padding: 2px 6px;">
                                            <?= htmlspecialchars($row['domain']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="<?= $badgeStyle ?> font-weight:500; font-size:0.75rem; border-radius:4px; padding: 4px 8px;">
                                        <?= htmlspecialchars($row['category']); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="edit_team_member.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-info p-1 px-2 me-1" title="Edit Member">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_team.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete Member" onclick="return confirm('Are you sure you want to remove <?= addslashes(htmlspecialchars($row['name'])); ?>?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('teamSearchInput');
    const categoryBtns = document.querySelectorAll('.btn-category');
    const rows = document.querySelectorAll('.team-row');
    const badge = document.getElementById('visibleCountBadge');

    let currentFilter = 'all';

    function filterTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const cat = row.getAttribute('data-category');
            const searchData = row.getAttribute('data-search') || '';

            const matchesCategory = (currentFilter === 'all' || cat === currentFilter);
            const matchesQuery = query === '' || searchData.includes(query);

            if (matchesCategory && matchesQuery) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (badge) {
            badge.textContent = visibleCount;
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            categoryBtns.forEach(b => {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = '#fff';
            });
            this.classList.add('active');
            this.style.background = 'var(--accent)';
            this.style.color = '#000';

            currentFilter = this.getAttribute('data-filter');
            filterTable();
        });
    });
});
</script>

<?php include 'footer.php'; ?>
