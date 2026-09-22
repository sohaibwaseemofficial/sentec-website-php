<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../cache_utils.php';
require_once __DIR__ . '/../image_utils.php';

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM team_members WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    header('Location: manage_team.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $category = $_POST['category'] ?? '';
    $imagePath = $member['image'];

    if ($name === '' || $designation === '') {
        $error = 'Name and designation are required.';
    } elseif (!in_array($category, ['Presiding Board', 'Executive Committee', 'Directorate', 'Member'], true)) {
        $error = 'Invalid team category.';
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Photo upload failed. Please try again.';
            } else {
                $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/team/', 'images/uploads/team/');
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'] ?? 'Photo upload failed. Please try again.';
                } else {
                    $imagePath = $uploadResult['path'];
                }
            }
        }

        if (empty($error)) {
            $update = $conn->prepare('UPDATE team_members SET name = ?, designation = ?, category = ?, image = ? WHERE id = ?');
            $update->bind_param('ssssi', $name, $designation, $category, $imagePath, $id);
            if ($update->execute()) {
                if ($imagePath !== $member['image']) {
                    $oldImage = __DIR__ . '/../' . $member['image'];
                    if (is_file($oldImage)) {
                        unlink($oldImage);
                    }
                }
                invalidate_cache('team_members');
                header('Location: manage_team.php?msg=updated');
                exit();
            }
            $error = 'Unable to save team member changes.';
            $update->close();
        }
    }
}

include 'header.php';
?>

<div class="container" style="padding-top: 120px; max-width: 600px;">
    <div class="glass-form p-4 rounded-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <h3 class="text-white mb-4">Edit Profile: <span style="color:var(--accent)"><?= htmlspecialchars($member['name']) ?></span></h3>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
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
