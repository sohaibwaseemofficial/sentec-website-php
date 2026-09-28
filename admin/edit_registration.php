<?php
// Show errors to diagnose blank screen issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';
require_once __DIR__ . '/../event_attendees_helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    echo '<div class="alert alert-danger m-4">Invalid registration ID.</div>';
    include 'footer.php';
    exit;
}

// Fetch existing row
$stmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo '<div class="alert alert-danger m-4">Registration not found.</div>';
    include 'footer.php';
    exit;
}

$hasUpdatedAt = false;
$colCheck = $conn->query("SHOW COLUMNS FROM event_registrations LIKE 'updated_at'");
if ($colCheck && $colCheck->num_rows > 0) { $hasUpdatedAt = true; }
if ($colCheck) { $colCheck->free(); }

$errors = [];
$success = false;

// Upload helpers - ensure clean normalized forward-slash paths
$targetDir = realpath(__DIR__ . '/../images/uploads/event_registrations');
if ($targetDir === false) {
    $targetDir = __DIR__ . '/../images/uploads/event_registrations';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $targetDir = realpath($targetDir) ?: $targetDir;
}
$targetDir = rtrim(str_replace('\\', '/', $targetDir), '/') . '/';
$targetPublicPrefix = 'images/uploads/event_registrations/';

// Check if POST was received but emptied because post_max_size was exceeded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $errors[] = 'Total upload payload size exceeded the server limit. Please upload smaller files (under 2MB each).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Whitelisted text fields
    $fields = [
        'team_name', 'institution_type', 'module_selection', 'status', 'payment_status',
        'brand_ambassador_code', 'event_label',
        'attendance_status', 'attendance_day1_status', 'attendance_day2_status',
        'participant1_name','participant1_contact','participant1_email','participant1_cnic','participant1_roll_number',
        'participant2_name','participant2_contact','participant2_email','participant2_cnic','participant2_roll_number',
        'participant3_name','participant3_contact','participant3_email','participant3_cnic','participant3_roll_number',
        'participant4_name','participant4_contact','participant4_email','participant4_cnic','participant4_roll_number',
        'participant5_name','participant5_contact','participant5_email','participant5_cnic','participant5_roll_number',
        'participant6_name','participant6_contact','participant6_email','participant6_cnic','participant6_roll_number'
    ];

    $data = [];
    foreach ($fields as $f) {
        $data[$f] = isset($_POST[$f]) ? trim((string)$_POST[$f]) : ($row[$f] ?? '');
    }

    // Validation
    if ($data['team_name'] === '') {
        $errors[] = 'Team name is required.';
    }
    if (!in_array($data['status'], ['pending','approved','rejected'], true)) {
        $errors[] = 'Invalid registration status.';
    }
    if (!in_array($data['payment_status'], ['pending','submitted','confirmed','rejected'], true)) {
        $data['payment_status'] = 'pending';
    }
    if (!in_array($data['attendance_status'], ['absent','present'], true)) {
        $data['attendance_status'] = 'absent';
    }
    if (!in_array($data['attendance_day1_status'], ['pending','present'], true)) {
        $data['attendance_day1_status'] = 'pending';
    }
    if (!in_array($data['attendance_day2_status'], ['pending','present'], true)) {
        $data['attendance_day2_status'] = 'pending';
    }

    // File upload columns
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

    // Handle removal checkboxes
    foreach ($fileCols as $fc) {
        if (!empty($_POST['remove_' . $fc])) {
            $fileUpdates[$fc] = '';
            if ($fc === 'fees_screenshot') {
                $fileUpdates['payment_proof'] = '';
            }
        }
    }

    // Handle uploaded files
    foreach ($fileCols as $fc) {
        if (!isset($_FILES[$fc]) || $_FILES[$fc]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$fc]['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES[$fc]['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "The file for {$fc} exceeds the server's upload size limit (max 2MB).";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "The file for {$fc} was only partially uploaded. Please try again.";
                    break;
                default:
                    $errors[] = "Failed to upload {$fc} (Error code: " . $_FILES[$fc]['error'] . ").";
                    break;
            }
            continue;
        }

        $uploadResult = save_image_as_webp($_FILES[$fc], $targetDir, $targetPublicPrefix);
        if ($uploadResult['success']) {
            $fileUpdates[$fc] = $uploadResult['path'];
            // Synchronize payment proof in both columns
            if ($fc === 'fees_screenshot') {
                $fileUpdates['payment_proof'] = $uploadResult['path'];
            }
        } else {
            $errors[] = $uploadResult['error'] ?? ('Failed to upload and process ' . $fc . '.');
        }
    }

    if (empty($errors)) {
        // Build dynamic update query
        $setParts = [];
        $types = '';
        $values = [];

        foreach ($data as $col => $val) {
            $setParts[] = "`$col` = ?";
            $types .= 's';
            $values[] = $val;
        }
        foreach ($fileUpdates as $col => $val) {
            $setParts[] = "`$col` = ?";
            $types .= 's';
            $values[] = $val;
        }

        $types .= 'i';
        $values[] = $id;

        if (!empty($setParts)) {
            $setClause = implode(', ', $setParts);
            if ($hasUpdatedAt) {
                $setClause .= ", `updated_at` = NOW()";
            }
            $sql = "UPDATE event_registrations SET " . $setClause . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $success = true;

                    // Refresh updated row
                    $stmt2 = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
                    $stmt2->bind_param('i', $id);
                    $stmt2->execute();
                    $row = $stmt2->get_result()->fetch_assoc();
                    $stmt2->close();

                    // Sync attendees across all 6 participants
                    $syncParticipants = event_attendees_from_registration_row($row ?: []);
                    event_attendees_sync($conn, $id, $syncParticipants, $row['status'] ?? 'pending');
                    event_refresh_parent_attendance($conn, $id);
                } else {
                    $errors[] = 'Failed to update record in database: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database query preparation failed: ' . $conn->error;
            }
        }
    }
}

// Current payment proof source
$currentPaymentProof = !empty($row['payment_proof']) ? $row['payment_proof'] : (!empty($row['fees_screenshot']) && $row['fees_screenshot'] !== 'Not Collected' ? $row['fees_screenshot'] : '');
$resolvedPaymentProof = resolve_image_url($currentPaymentProof, '../');
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-white"><i class="fas fa-edit me-2 text-info"></i> Edit Registration Details</h2>
        <p class="text-muted mb-0">Team: <strong class="text-white"><?= htmlspecialchars($row['team_name'] ?? '') ?></strong> (ID: #<?= $id ?>)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="manage_registrations" class="btn btn-outline-light"><i class="fas fa-arrow-left me-1"></i> Back to Registrations</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-check-circle fa-lg"></i>
        <div>
            <strong>Changes Saved Successfully!</strong> All team details and uploaded images have been updated.
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4">
        <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Please fix the following errors:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="edit_registration?id=<?= $id ?>" method="post" enctype="multipart/form-data" class="d-flex flex-column gap-4">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- SECTION 1: TEAM & MODULE INFO -->
    <div class="glass-panel p-4" style="background: rgba(18, 18, 24, 0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px;">
        <h5 class="text-white mb-3 border-bottom border-secondary pb-2"><i class="fas fa-users-cog me-2 text-primary"></i> Team & Module Information</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Team Name <span class="text-danger">*</span></label>
                <input type="text" name="team_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($row['team_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Institution Category</label>
                <select name="institution_type" class="form-select bg-dark text-white border-secondary">
                    <?php
                    $opts = ['NED University Student', 'Non-NED University Student', 'College Student'];
                    $currInst = $row['institution_type'] ?? '';
                    if (!in_array($currInst, $opts, true) && $currInst !== '') {
                        $opts[] = $currInst;
                    }
                    foreach ($opts as $o) {
                        $sel = ($currInst === $o) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($o) . "\" $sel>" . htmlspecialchars($o) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Competition Module</label>
                <input type="text" list="module_list" name="module_selection" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($row['module_selection'] ?? '') ?>" placeholder="Select or type module">
                <datalist id="module_list">
                    <option value="Line Following Robot (LFR)">
                    <option value="Circuit Designing Competition">
                    <option value="CYBER WAR ROOM">
                    <option value="RAG CHATBOT BUILDER">
                    <option value="AGENT SPRINT: LIVE GMAIL AUTOMATION">
                    <option value="AI COURT: FAKE OR REAL">
                    <option value="DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE">
                    <option value="BREAK THE RULES">
                    <option value="PitchFest">
                    <option value="AI DEBATE COLOSSEUM">
                    <option value="Web Forces">
                    <option value="Reactor Zero">
                    <option value="Fault Line">
                </datalist>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Event Label</label>
                <input type="text" name="event_label" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($row['event_label'] ?? '') ?>" placeholder="e.g. proxion_2026">
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Ambassador Code (optional)</label>
                <input type="text" name="brand_ambassador_code" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($row['brand_ambassador_code'] ?? '') ?>" placeholder="e.g. AMB-1234">
            </div>
        </div>
    </div>

    <!-- SECTION 2: STATUS & PAYMENT VERIFICATION -->
    <div class="glass-panel p-4" style="background: rgba(18, 18, 24, 0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px;">
        <h5 class="text-white mb-3 border-bottom border-secondary pb-2"><i class="fas fa-file-invoice-dollar me-2 text-warning"></i> Status & Payment Proof</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Team Registration Status</label>
                <select name="status" class="form-select bg-dark text-white border-secondary">
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= (($row['status'] ?? 'pending') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Payment Verification Status</label>
                <select name="payment_status" class="form-select bg-dark text-white border-secondary">
                    <?php foreach (['pending' => 'Pending', 'submitted' => 'Submitted', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= (($row['payment_status'] ?? 'pending') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Payment Screenshot / Proof</label>
                <?php if (!empty($currentPaymentProof) && $currentPaymentProof !== 'Not Collected'): ?>
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <a href="<?= htmlspecialchars($resolvedPaymentProof) ?>" target="_blank" class="d-inline-block">
                            <img src="<?= htmlspecialchars($resolvedPaymentProof) ?>" style="height: 48px; width: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #555;" alt="Payment Proof">
                        </a>
                        <div class="flex-grow-1 overflow-hidden">
                            <a href="<?= htmlspecialchars($resolvedPaymentProof) ?>" target="_blank" class="text-info text-decoration-none small d-block text-truncate fw-bold">
                                <i class="fas fa-external-link-alt me-1"></i> View Full Image
                            </a>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_fees_screenshot" id="remove_fees_screenshot" value="1">
                                <label class="form-check-label text-danger small" for="remove_fees_screenshot">Remove Proof</label>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i> No payment proof on file.</div>
                <?php endif; ?>
                <input type="file" name="fees_screenshot" class="form-control bg-dark text-white border-secondary" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">Select an image (JPG, PNG, WebP under 2MB) to upload/replace.</small>
            </div>
        </div>
    </div>

    <!-- SECTION 3: ATTENDANCE TRACKING -->
    <div class="glass-panel p-4" style="background: rgba(18, 18, 24, 0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px;">
        <h5 class="text-white mb-3 border-bottom border-secondary pb-2"><i class="fas fa-calendar-check me-2 text-success"></i> Attendance Management</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Overall Attendance Status</label>
                <select name="attendance_status" class="form-select bg-dark text-white border-secondary">
                    <option value="absent" <?= (($row['attendance_status'] ?? 'absent') === 'absent') ? 'selected' : '' ?>>Absent</option>
                    <option value="present" <?= (($row['attendance_status'] ?? '') === 'present') ? 'selected' : '' ?>>Present</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Day 1 Attendance</label>
                <select name="attendance_day1_status" class="form-select bg-dark text-white border-secondary">
                    <option value="pending" <?= (($row['attendance_day1_status'] ?? 'pending') === 'pending') ? 'selected' : '' ?>>Pending / Not Checked In</option>
                    <option value="present" <?= (($row['attendance_day1_status'] ?? '') === 'present') ? 'selected' : '' ?>>Present (Checked In)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-light fw-bold">Day 2 Attendance</label>
                <select name="attendance_day2_status" class="form-select bg-dark text-white border-secondary">
                    <option value="pending" <?= (($row['attendance_day2_status'] ?? 'pending') === 'pending') ? 'selected' : '' ?>>Pending / Not Checked In</option>
                    <option value="present" <?= (($row['attendance_day2_status'] ?? '') === 'present') ? 'selected' : '' ?>>Present (Checked In)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- SECTION 4: PARTICIPANTS 1 TO 6 -->
    <?php
    $participantRoles = [
        1 => 'Team Leader (Participant 1)',
        2 => 'Participant 2 (Member)',
        3 => 'Participant 3 (Member)',
        4 => 'Participant 4 (Member)',
        5 => 'Participant 5 (Member)',
        6 => 'Participant 6 (Member)'
    ];

    for ($i = 1; $i <= 6; $i++):
        $pName   = $row["participant{$i}_name"] ?? '';
        $pPhone  = $row["participant{$i}_contact"] ?? '';
        $pEmail  = $row["participant{$i}_email"] ?? '';
        $pCnic   = $row["participant{$i}_cnic"] ?? '';
        $pRoll   = $row["participant{$i}_roll_number"] ?? '';
        $pFace   = $row["participant{$i}_face_image"] ?? '';
        $pIdCard = $row["participant{$i}_id_card"] ?? '';

        $resolvedFace = !empty($pFace) ? resolve_image_url($pFace, '../') : '';
        $resolvedCard = !empty($pIdCard) ? resolve_image_url($pIdCard, '../') : '';
        $isLeader = ($i === 1);
    ?>
        <div class="glass-panel p-4" style="background: rgba(18, 18, 24, 0.7); border: 1px solid <?= $isLeader ? 'rgba(0, 255, 148, 0.25)' : 'rgba(255,255,255,0.08)' ?>; border-radius: 12px;">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-2">
                <h5 class="text-white mb-0">
                    <?php if ($isLeader): ?>
                        <i class="fas fa-crown text-warning me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-user text-info me-2"></i>
                    <?php endif; ?>
                    <?= $participantRoles[$i] ?>
                </h5>
                <?php if ($isLeader): ?>
                    <span class="badge bg-success text-dark fw-bold">Primary Contact</span>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-light small fw-bold">Full Name <?= $isLeader ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="text" name="participant<?= $i ?>_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars((string)$pName) ?>" placeholder="Full name">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-light small fw-bold">Contact / Phone <?= $isLeader ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="text" name="participant<?= $i ?>_contact" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars((string)$pPhone) ?>" placeholder="03xx-xxxxxxx">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-light small fw-bold">Email Address <?= $isLeader ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="email" name="participant<?= $i ?>_email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars((string)$pEmail) ?>" placeholder="email@example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-light small fw-bold">CNIC / B-Form <?= $isLeader ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="text" name="participant<?= $i ?>_cnic" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars((string)$pCnic) ?>" placeholder="42xxx-xxxxxxx-x">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-light small fw-bold">Roll / Student Number <?= $isLeader ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="text" name="participant<?= $i ?>_roll_number" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars((string)$pRoll) ?>" placeholder="e.g. CS-042">
                </div>

                <!-- Face Photo -->
                <div class="col-md-6 mt-3">
                    <label class="form-label text-light small fw-bold">Face Photo</label>
                    <?php if (!empty($resolvedFace)): ?>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                            <a href="<?= htmlspecialchars($resolvedFace) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($resolvedFace) ?>" style="height: 48px; width: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #555;" alt="Face Photo">
                            </a>
                            <div class="flex-grow-1 overflow-hidden">
                                <a href="<?= htmlspecialchars($resolvedFace) ?>" target="_blank" class="text-info text-decoration-none small d-block text-truncate fw-bold">
                                    <i class="fas fa-external-link-alt me-1"></i> View Face Image
                                </a>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_participant<?= $i ?>_face_image" id="remove_p<?= $i ?>_face" value="1">
                                    <label class="form-check-label text-danger small" for="remove_p<?= $i ?>_face">Remove Photo</label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="participant<?= $i ?>_face_image" class="form-control bg-dark text-white border-secondary" accept="image/jpeg,image/png,image/webp">
                </div>

                <!-- ID Card Image -->
                <div class="col-md-6 mt-3">
                    <label class="form-label text-light small fw-bold">Student ID Card</label>
                    <?php if (!empty($resolvedCard)): ?>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                            <a href="<?= htmlspecialchars($resolvedCard) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($resolvedCard) ?>" style="height: 48px; width: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #555;" alt="ID Card">
                            </a>
                            <div class="flex-grow-1 overflow-hidden">
                                <a href="<?= htmlspecialchars($resolvedCard) ?>" target="_blank" class="text-info text-decoration-none small d-block text-truncate fw-bold">
                                    <i class="fas fa-external-link-alt me-1"></i> View ID Card
                                </a>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_participant<?= $i ?>_id_card" id="remove_p<?= $i ?>_card" value="1">
                                    <label class="form-check-label text-danger small" for="remove_p<?= $i ?>_card">Remove Card</label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="participant<?= $i ?>_id_card" class="form-control bg-dark text-white border-secondary" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
        </div>
    <?php endfor; ?>

    <!-- SUBMIT BUTTONS -->
    <div class="d-flex justify-content-end gap-3 my-4">
        <a href="manage_registrations" class="btn btn-outline-secondary px-4 py-2">
            <i class="fas fa-times me-1"></i> Cancel
        </a>
        <button type="submit" class="btn-neon px-5 py-2" style="font-size: 1rem; font-weight: 600;">
            <i class="fas fa-save me-2"></i> Save Changes
        </button>
    </div>
</form>

<?php include 'footer.php'; ?>
