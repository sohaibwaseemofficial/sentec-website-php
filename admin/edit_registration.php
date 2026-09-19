<?php
// Show errors to diagnose blank screen issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';
require_once __DIR__ . '/../event_attendees_helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo '<div class="alert alert-danger m-4">Invalid registration ID.</div>'; include 'footer.php'; exit; }

// Fetch existing row
$stmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) { echo '<div class="alert alert-danger m-4">Registration not found.</div>'; include 'footer.php'; exit; }

$hasUpdatedAt = false;
$colCheck = $conn->query("SHOW COLUMNS FROM event_registrations LIKE 'updated_at'");
if ($colCheck && $colCheck->num_rows > 0) { $hasUpdatedAt = true; }
if ($colCheck) { $colCheck->free(); }

$errors = [];
$success = false;

// Upload helpers
$targetDir = __DIR__ . '/../images/uploads/event_registrations/';
if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
$targetPublicPrefix = 'images/uploads/event_registrations/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Whitelisted fields we allow editing (add more as needed)
    $fields = [
        'team_name', 'institution_type', 'module_selection', 'status', 'brand_ambassador_code',
        'participant1_name','participant1_contact','participant1_email','participant1_cnic','participant1_roll_number',
        'participant2_name','participant2_contact','participant2_email','participant2_cnic','participant2_roll_number',
        'participant3_name','participant3_contact','participant3_email','participant3_cnic','participant3_roll_number',
        'participant4_name','participant4_contact','participant4_email','participant4_cnic','participant4_roll_number',
        'participant5_name','participant5_contact','participant5_email','participant5_cnic','participant5_roll_number',
        'participant6_name','participant6_contact','participant6_email','participant6_cnic','participant6_roll_number'
    ];

    $data = [];
    foreach ($fields as $f) {
        $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null;
    }

    // Basic validation
    if ($data['team_name'] === '') { $errors[] = 'Team name is required.'; }
    if (!in_array($data['status'], ['pending','approved','rejected'])) { $errors[] = 'Invalid status.'; }

    if (empty($errors)) {
        // Handle optional file uploads (only replace if a new file is uploaded)
        $fileCols = [
            'fees_screenshot',
            'participant1_face_image','participant1_id_card',
            'participant2_face_image','participant2_id_card',
            'participant3_face_image','participant3_id_card',
            'participant4_face_image','participant4_id_card',
            'participant5_face_image','participant5_id_card',
            'participant6_face_image','participant6_id_card'
        ];
        $fileUpdates = [];
        foreach ($fileCols as $fc) {
            if (!isset($_FILES[$fc]) || $_FILES[$fc]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            $uploadResult = save_image_as_webp($_FILES[$fc], $targetDir, $targetPublicPrefix);
            if ($uploadResult['success']) {
                $fileUpdates[$fc] = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['error'] ?? ('Failed to upload ' . $fc . '.');
            }
        }

        if (empty($errors)) {
            // Build dynamic update
            $setParts = [];
            $types = '';
            $values = [];
            foreach ($data as $col => $val) { $setParts[] = "$col = ?"; $types .= 's'; $values[] = $val; }
            foreach ($fileUpdates as $col => $val) { $setParts[] = "$col = ?"; $types .= 's'; $values[] = $val; }

            $types .= 'i';
            $values[] = $id;
            
            if (!empty($setParts)) {
                $setClause = implode(', ', $setParts);
                if ($hasUpdatedAt) {
                    $setClause .= ", updated_at = NOW()";
                }
                $sql = "UPDATE event_registrations SET " . $setClause . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                // mysqli::bind_param requires references; build ref array
                $bindParams = [];
                $bindParams[] = & $types;
                foreach ($values as $k => $v) { $bindParams[] = & $values[$k]; }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
                if ($stmt->execute()) {
                    $success = true;
                    // Refresh row
                    $stmt2 = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
                    $stmt2->bind_param('i', $id);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    $stmt2->close();

                    $syncParticipants = event_attendees_from_registration_row($row ?: []);
                    event_attendees_sync($conn, $id, $syncParticipants, $row['status'] ?? 'pending');
                    event_refresh_parent_attendance($conn, $id);
                } else {
                    $errors[] = 'Failed to update. DB Error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-edit me-2"></i> Edit Registration</h2>
        <p class="text-muted">Update team and participant details.</p>
    </div>
    <div>
        <a href="manage_registrations.php" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="glass-panel">
    <?php if ($success): ?>
        <div class="alert alert-success">Saved successfully.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Team Name</label>
            <input type="text" name="team_name" class="form-control" value="<?= htmlspecialchars($row['team_name']) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Institution Type</label>
            <select name="institution_type" class="form-select">
                <?php
                $opts = ['NED University Student','Non-NED University Student','College Student'];
                foreach ($opts as $o) {
                    $sel = ($row['institution_type'] === $o) ? 'selected' : '';
                    echo "<option $sel>" . htmlspecialchars($o) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Module</label>
            <input type="text" name="module_selection" class="form-control" value="<?= htmlspecialchars($row['module_selection']) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach (['pending','approved','rejected'] as $s): $sel = $row['status']===$s?'selected':''; ?>
                    <option value="<?= $s ?>" <?= $sel ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Ambassador Code (optional)</label>
            <input type="text" name="brand_ambassador_code" class="form-control" value="<?= htmlspecialchars($row['brand_ambassador_code'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Payment Proof (replace)</label>
            <?php if (!empty($row['fees_screenshot']) && $row['fees_screenshot'] !== 'Not Collected'): ?>
                <div class="mb-2"><a href="../<?= htmlspecialchars($row['fees_screenshot']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['fees_screenshot']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div>
            <?php endif; ?>
            <input type="file" name="fees_screenshot" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Leader (Participant 1)</h5>
        <div class="col-md-4"><label class="form-label">Name</label><input type="text" name="participant1_name" class="form-control" value="<?= htmlspecialchars($row['participant1_name']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Contact</label><input type="text" name="participant1_contact" class="form-control" value="<?= htmlspecialchars($row['participant1_contact']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="participant1_email" class="form-control" value="<?= htmlspecialchars($row['participant1_email']) ?>"></div>
        <div class="col-md-4"><label class="form-label">CNIC</label><input type="text" name="participant1_cnic" class="form-control" value="<?= htmlspecialchars($row['participant1_cnic']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Roll Number</label><input type="text" name="participant1_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant1_roll_number']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant1_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant1_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant1_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant1_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant1_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant1_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant1_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant1_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Participant 2</h5>
        <div class="col-md-4"><input placeholder="Name" name="participant2_name" class="form-control" value="<?= htmlspecialchars($row['participant2_name']) ?>"></div>
        <div class="col-md-4"><input placeholder="Contact" name="participant2_contact" class="form-control" value="<?= htmlspecialchars($row['participant2_contact']) ?>"></div>
        <div class="col-md-4"><input placeholder="Email" name="participant2_email" class="form-control" value="<?= htmlspecialchars($row['participant2_email']) ?>"></div>
        <div class="col-md-4"><input placeholder="CNIC" name="participant2_cnic" class="form-control" value="<?= htmlspecialchars($row['participant2_cnic']) ?>"></div>
        <div class="col-md-4"><input placeholder="Roll Number" name="participant2_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant2_roll_number']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant2_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant2_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant2_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant2_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant2_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant2_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant2_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant2_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Participant 3</h5>
        <div class="col-md-4"><input placeholder="Name" name="participant3_name" class="form-control" value="<?= htmlspecialchars($row['participant3_name']) ?>"></div>
        <div class="col-md-4"><input placeholder="Contact" name="participant3_contact" class="form-control" value="<?= htmlspecialchars($row['participant3_contact']) ?>"></div>
        <div class="col-md-4"><input placeholder="Email" name="participant3_email" class="form-control" value="<?= htmlspecialchars($row['participant3_email']) ?>"></div>
        <div class="col-md-4"><input placeholder="CNIC" name="participant3_cnic" class="form-control" value="<?= htmlspecialchars($row['participant3_cnic']) ?>"></div>
        <div class="col-md-4"><input placeholder="Roll Number" name="participant3_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant3_roll_number']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant3_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant3_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant3_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant3_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant3_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant3_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant3_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant3_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Participant 4</h5>
        <div class="col-md-4"><input placeholder="Name" name="participant4_name" class="form-control" value="<?= htmlspecialchars($row['participant4_name']) ?>"></div>
        <div class="col-md-4"><input placeholder="Contact" name="participant4_contact" class="form-control" value="<?= htmlspecialchars($row['participant4_contact']) ?>"></div>
        <div class="col-md-4"><input placeholder="Email" name="participant4_email" class="form-control" value="<?= htmlspecialchars($row['participant4_email']) ?>"></div>
        <div class="col-md-4"><input placeholder="CNIC" name="participant4_cnic" class="form-control" value="<?= htmlspecialchars($row['participant4_cnic']) ?>"></div>
        <div class="col-md-4"><input placeholder="Roll Number" name="participant4_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant4_roll_number']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant4_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant4_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant4_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant4_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant4_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant4_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant4_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant4_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Participant 5</h5>
        <div class="col-md-4"><input placeholder="Name" name="participant5_name" class="form-control" value="<?= htmlspecialchars($row['participant5_name'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Contact" name="participant5_contact" class="form-control" value="<?= htmlspecialchars($row['participant5_contact'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Email" name="participant5_email" class="form-control" value="<?= htmlspecialchars($row['participant5_email'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="CNIC" name="participant5_cnic" class="form-control" value="<?= htmlspecialchars($row['participant5_cnic'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Roll Number" name="participant5_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant5_roll_number'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant5_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant5_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant5_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant5_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant5_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant5_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant5_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant5_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <hr class="mt-4" style="border-color:#333;">
        <h5 class="text-white mt-2">Participant 6</h5>
        <div class="col-md-4"><input placeholder="Name" name="participant6_name" class="form-control" value="<?= htmlspecialchars($row['participant6_name'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Contact" name="participant6_contact" class="form-control" value="<?= htmlspecialchars($row['participant6_contact'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Email" name="participant6_email" class="form-control" value="<?= htmlspecialchars($row['participant6_email'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="CNIC" name="participant6_cnic" class="form-control" value="<?= htmlspecialchars($row['participant6_cnic'] ?? '') ?>"></div>
        <div class="col-md-4"><input placeholder="Roll Number" name="participant6_roll_number" class="form-control" value="<?= htmlspecialchars($row['participant6_roll_number'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Photo (replace)</label>
            <?php if (!empty($row['participant6_face_image'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant6_face_image']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant6_face_image']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant6_face_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-md-4"><label class="form-label">ID Card (replace)</label>
            <?php if (!empty($row['participant6_id_card'])): ?><div class="mb-2"><a href="../<?= htmlspecialchars($row['participant6_id_card']) ?>" target="_blank"><img src="../<?= htmlspecialchars($row['participant6_id_card']) ?>" style="height:40px; border:1px solid #333; border-radius:6px; object-fit:cover"></a></div><?php endif; ?>
            <input type="file" name="participant6_id_card" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="col-12 mt-3 d-flex justify-content-end gap-2">
            <a href="manage_registrations.php" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn-neon" type="submit">Save Changes</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
