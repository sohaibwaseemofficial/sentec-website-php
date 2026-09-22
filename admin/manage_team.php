<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';

// 1. HANDLE ADD MEMBER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['designation']);
    $cat = $_POST['category'];
    $linkedin = trim($_POST['linkedin']);
    $domain = trim($_POST['domain']);
    $order = (int)$_POST['sort_order']; // New Order Field
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "<div class='alert alert-danger'>Photo upload failed. Please try again.</div>";
    } else {
        $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/team/', 'images/uploads/team/');

        if (!$uploadResult['success']) {
            echo "<div class='alert alert-danger'>" . htmlspecialchars($uploadResult['error']) . "</div>";
        } else {
            $imgPath = $uploadResult['path'];

            $stmt = $conn->prepare("INSERT INTO team_members (name, designation, category, image, linkedin, domain, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $name, $role, $cat, $imgPath, $linkedin, $domain, $order);

            if($stmt->execute()) {
                echo "<script>window.location.href='manage_team';</script>";
            } else {
                echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
        }
    }
}

// 2. HANDLE UPDATE ORDER (Quick Edit)
if (isset($_POST['update_order'])) {
    $id = (int)$_POST['member_id'];
    $new_order = (int)$_POST['new_sort_order'];
    $conn->query("UPDATE team_members SET sort_order = $new_order WHERE id = $id");
    invalidate_cache('team_members');
    echo "<script>window.location.href='manage_team';</script>";
}

// 3. FETCH TEAM (Sorted by your Order)
$team = $conn->query("SELECT * FROM team_members ORDER BY sort_order ASC, id DESC");
?>

<div class="page-header">
    <h2>Team Management</h2>
    <p>Add members and control their display order. (Lower number = Shows first).</p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="glass-panel">
            <h4 class="text-white mb-4">Add Member</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_member" value="1">
                
                <div class="mb-3">
                    <label class="text-white small">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="text-white small">Role (e.g. President)</label>
                    <input type="text" name="designation" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="text-white small">Category</label>
                    <select name="category" class="form-control" style="background:#0b1120; color:#fff; border:1px solid #333;">
                        <option>Presiding Board</option>
                        <option>Executive Committee</option>
                        <option>Directorate</option>
                        <option>Member</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="text-white small">Domain (Optional)</label>
                    <input type="text" name="domain" class="form-control" placeholder="e.g. Web Dev">
                </div>

                <div class="mb-3">
                    <label class="text-white small">LinkedIn URL (Optional)</label>
                    <input type="url" name="linkedin" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="text-white small" style="color: var(--accent) !important;">Display Order (Priority)</label>
                    <input type="number" name="sort_order" class="form-control" value="10" style="border-color: var(--accent);">
                    <small class="text-muted">1 shows first, 10 shows later.</small>
                </div>

                <div class="mb-3">
                    <label class="text-white small">Photo</label>
                    <input type="file" name="image" class="form-control" required>
                </div>

                <button type="submit" class="btn-neon w-100">Add Member</button>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="glass-panel">
            <h4 class="text-white mb-4">Current Team</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Order</th>
                            <th>Photo</th>
                            <th>Name / Role</th>
                            <th>Category</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $team->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <form method="POST" style="display:flex; align-items:center; gap:5px;">
                                    <input type="hidden" name="update_order" value="1">
                                    <input type="hidden" name="member_id" value="<?php echo $row['id']; ?>">
                                    <input type="number" name="new_sort_order" value="<?php echo $row['sort_order']; ?>" 
                                           class="form-control form-control-sm" 
                                           style="width: 60px; padding: 5px; text-align: center; background:#000; border:1px solid #444; color:#fff;">
                                    <button type="submit" class="btn btn-sm btn-link text-success p-0" title="Save Order">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                            <td><img src="../<?php echo htmlspecialchars($row['image']); ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover;"></td>
                            <td>
                                <strong class="text-white"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <small style="color:#aaa;"><?php echo htmlspecialchars($row['designation']); ?></small>
                            </td>
                            <td><span class="badge bg-dark border border-secondary"><?php echo htmlspecialchars($row['category']); ?></span></td>
                            <td>
                                <a href="edit_team_member.php?id=<?php echo $row['id']; ?>" class="text-info me-2" title="Edit member">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_team.php?id=<?php echo $row['id']; ?>" class="text-danger" onclick="return confirm('Delete this member?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
