<?php
include 'header.php';
include 'db_connection.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}

// Logic for update (same as before, just kept clean)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... [Your existing logic mostly goes here, ensure pathing is correct] ...
    // Note: I'm focusing on the UI here, assuming your logic works from previous file
    // Just ensure the update query handles the image correctly.
}
?>

<div class="container" style="padding-top: 120px; max-width: 600px;">
    <div class="glass-form p-4 rounded-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <h3 class="text-white mb-4">Edit Profile: <span style="color:var(--accent)"><?= htmlspecialchars($member['name']) ?></span></h3>
        
        <form action="edit_team_member.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $member['id'] ?>">
            
            <!-- Current Image Preview -->
            <div class="text-center mb-4">
                <img src="../<?= $member['image'] ?>" alt="Current" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent);">
                <p class="text-muted small mt-2">Current Photo</p>
            </div>

            <div class="mb-3">
                <label class="form-label text-white">Name</label>
                <input type="text" name="name" class="form-control form-control-dark" value="<?= htmlspecialchars($member['name']) ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white">Designation</label>
                    <input type="text" name="designation" class="form-control form-control-dark" value="<?= htmlspecialchars($member['designation']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white">Category</label>
                    <select name="category" class="form-select form-select-dark">
                        <option value="Presiding Board" <?= $member['category'] == 'Presiding Board' ? 'selected' : '' ?>>Presiding Board</option>
                        <option value="Directorate" <?= $member['category'] == 'Directorate' ? 'selected' : '' ?>>Directorate</option>
                        <option value="Executive Committee" <?= $member['category'] == 'Executive Committee' ? 'selected' : '' ?>>Executive Committee</option>
                        <option value="Member" <?= $member['category'] == 'Member' ? 'selected' : '' ?>>Member</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-white">Update Photo (Optional)</label>
                <input type="file" name="image" class="form-control form-control-dark">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="background: var(--accent); color: #000; border: none;">Save Changes</button>
                <a href="manage_team" class="btn btn-outline-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
