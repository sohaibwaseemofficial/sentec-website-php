<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';

$registrationId = intval($_GET['id'] ?? 0);
if ($registrationId <= 0) {
    echo '<div class="alert alert-danger m-4">Invalid registration ID.</div>';
    include 'footer.php';
    exit;
}

// Fetch registration
$stmt = $conn->prepare('SELECT * FROM social_registrations WHERE id = ?');
$stmt->bind_param('i', $registrationId);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registration) {
    echo '<div class="alert alert-danger m-4">Registration not found.</div>';
    include 'footer.php';
    exit;
}

$attendeeMode = social_attendees_table_exists($conn);
$attendees = $attendeeMode ? social_attendees_fetch_group($conn, $registrationId) : [];

$typeOptions = [
    'standard' => 'Individual',
    'participant' => 'Event Participant',
    'group' => 'Group (3 People)'
];

function save_upload(?array $file, string $prefix, int $id)
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return false;
    }
    $dir = realpath(__DIR__ . '/../images/uploads/social');
    if ($dir === false) {
        $dir = __DIR__ . '/../images/uploads/social';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $filename = $prefix . '_' . $id . '_' . uniqid('', true) . '.' . $ext;
    $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return 'images/uploads/social/' . $filename;
    }
    return false;
}

$statusMessage = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regType = $_POST['registration_type'] ?? $registration['registration_type'];
    if (!array_key_exists($regType, $typeOptions)) {
        $regType = $registration['registration_type'];
    }
    $ambCode = trim($_POST['ambassador_code'] ?? '');
    $ambCode = $ambCode === '' ? null : $ambCode;
    $amountInput = $_POST['total_amount'] ?? '';
    $amountVal = ($amountInput === '') ? ($registration['total_amount'] ?? 0) : (float)$amountInput;

    $paymentProofPath = $registration['payment_proof'];
    $newProof = save_upload($_FILES['payment_proof'] ?? null, 'pay_edit', $registrationId);
    if ($newProof === false) {
        $errors[] = 'Payment proof must be an image (jpg/jpeg/png/webp/heic).';
    } elseif ($newProof) {
        $paymentProofPath = $newProof;
    }

    if (!empty($errors)) {
        $statusMessage = '<div class="alert alert-danger">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
    } else {
        try {
            $conn->begin_transaction();

        if ($attendeeMode) {
            $updateParent = $conn->prepare('UPDATE social_registrations SET registration_type = ?, ambassador_code = ?, total_amount = ?, payment_proof = ? WHERE id = ?');
            $updateParent->bind_param('ssdsi', $regType, $ambCode, $amountVal, $paymentProofPath, $registrationId);
            if (!$updateParent->execute()) {
                throw new Exception('Failed to update registration: ' . $conn->error);
            }
            $updateParent->close();

            $updateAttendee = $conn->prepare('UPDATE social_attendees SET full_name = ?, email = ?, phone = ?, cnic = ?, label = ?, face_image = ?, id_card_image = ? WHERE id = ?');

            foreach ($attendees as $att) {
                $aid = (int)$att['id'];
                $data = $_POST['attendees'][$aid] ?? [];
                $name = trim($data['name'] ?? $att['full_name']);
                $email = trim($data['email'] ?? $att['email']);
                $phone = trim($data['phone'] ?? $att['phone']);
                $cnic = trim($data['cnic'] ?? $att['cnic']);
                $label = trim($data['label'] ?? $att['label']);

                $facePath = $att['face_image'];
                $cardPath = $att['id_card_image'];

                $newFace = save_upload($_FILES['face_' . $aid] ?? null, 'face' . $aid, $registrationId);
                if ($newFace === false) {
                    throw new Exception('Invalid face image for attendee #' . $aid . '.');
                } elseif ($newFace) {
                    $facePath = $newFace;
                }

                $newCard = save_upload($_FILES['card_' . $aid] ?? null, 'card' . $aid, $registrationId);
                if ($newCard === false) {
                    throw new Exception('Invalid ID card for attendee #' . $aid . '.');
                } elseif ($newCard) {
                    $cardPath = $newCard;
                }

                $updateAttendee->bind_param('sssssssi', $name, $email, $phone, $cnic, $label, $facePath, $cardPath, $aid);
                if (!$updateAttendee->execute()) {
                    throw new Exception('Failed updating attendee #' . $aid . ': ' . $conn->error);
                }
            }
            $updateAttendee->close();
        } else {
            // Legacy structure without social_attendees table
            $p1_name = trim($_POST['p1_name'] ?? $registration['full_name']);
            $p1_email = trim($_POST['p1_email'] ?? $registration['email']);
            $p1_phone = trim($_POST['p1_phone'] ?? $registration['phone']);
            $p1_cnic = trim($_POST['p1_cnic'] ?? $registration['cnic']);

            $p2_name = trim($_POST['p2_name'] ?? $registration['participant2_name']);
            $p2_email = trim($_POST['p2_email'] ?? $registration['participant2_email']);
            $p2_phone = trim($_POST['p2_phone'] ?? $registration['participant2_phone']);
            $p2_cnic = trim($_POST['p2_cnic'] ?? $registration['participant2_cnic']);

            $p3_name = trim($_POST['p3_name'] ?? $registration['participant3_name']);
            $p3_email = trim($_POST['p3_email'] ?? $registration['participant3_email']);
            $p3_phone = trim($_POST['p3_phone'] ?? $registration['participant3_phone']);
            $p3_cnic = trim($_POST['p3_cnic'] ?? $registration['participant3_cnic']);

            $face1 = $registration['face_image'];
            $card1 = $registration['id_card_image'];
            $face2 = $registration['participant2_face'];
            $card2 = $registration['participant2_card'];
            $face3 = $registration['participant3_face'];
            $card3 = $registration['participant3_card'];

            $nf1 = save_upload($_FILES['face1'] ?? null, 'face1', $registrationId);
            if ($nf1 === false) throw new Exception('Invalid face image for Person 1.');
            elseif ($nf1) $face1 = $nf1;

            $nc1 = save_upload($_FILES['card1'] ?? null, 'card1', $registrationId);
            if ($nc1 === false) throw new Exception('Invalid ID card for Person 1.');
            elseif ($nc1) $card1 = $nc1;

            $nf2 = save_upload($_FILES['face2'] ?? null, 'face2', $registrationId);
            if ($nf2 === false) throw new Exception('Invalid face image for Person 2.');
            elseif ($nf2) $face2 = $nf2;

            $nc2 = save_upload($_FILES['card2'] ?? null, 'card2', $registrationId);
            if ($nc2 === false) throw new Exception('Invalid ID card for Person 2.');
            elseif ($nc2) $card2 = $nc2;

            $nf3 = save_upload($_FILES['face3'] ?? null, 'face3', $registrationId);
            if ($nf3 === false) throw new Exception('Invalid face image for Person 3.');
            elseif ($nf3) $face3 = $nf3;

            $nc3 = save_upload($_FILES['card3'] ?? null, 'card3', $registrationId);
            if ($nc3 === false) throw new Exception('Invalid ID card for Person 3.');
            elseif ($nc3) $card3 = $nc3;

            $sql = 'UPDATE social_registrations SET full_name=?, email=?, phone=?, cnic=?, face_image=?, id_card_image=?, registration_type=?, ambassador_code=?, total_amount=?, payment_proof=?, participant2_name=?, participant2_email=?, participant2_phone=?, participant2_cnic=?, participant2_face=?, participant2_card=?, participant3_name=?, participant3_email=?, participant3_phone=?, participant3_cnic=?, participant3_face=?, participant3_card=? WHERE id=?';
            $update = $conn->prepare($sql);
            $update->bind_param(
                'ssssssssds' . str_repeat('s', 12) . 'i',
                $p1_name, $p1_email, $p1_phone, $p1_cnic, $face1, $card1, $regType, $ambCode, $amountVal, $paymentProofPath,
                $p2_name, $p2_email, $p2_phone, $p2_cnic, $face2, $card2,
                $p3_name, $p3_email, $p3_phone, $p3_cnic, $face3, $card3,
                $registrationId
            );
            if (!$update->execute()) {
                throw new Exception('Failed to update registration: ' . $conn->error);
            }
            $update->close();
        }

            $conn->commit();
            $statusMessage = '<div class="alert alert-success">Registration updated successfully.</div>';

            // Refresh data for display
            $stmt = $conn->prepare('SELECT * FROM social_registrations WHERE id = ?');
            $stmt->bind_param('i', $registrationId);
            $stmt->execute();
            $registration = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $attendees = $attendeeMode ? social_attendees_fetch_group($conn, $registrationId) : $attendees;
        } catch (Exception $e) {
            $conn->rollback();
            $statusMessage = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<style>
    .glass-panel { background: rgba(10, 15, 30, 0.75); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 20px; }
    .section-title { color: #00FF94; text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem; }
    .thumb { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid #333; }
    .form-label { color: #fff; font-weight: 600; }
    .form-control, .form-select { background: #0b1120; color: #fff; border: 1px solid #333; }
    .form-control:focus, .form-select:focus { border-color: #00FF94; box-shadow: 0 0 0 0.1rem rgba(0,255,148,0.25); }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-edit me-2"></i>Edit Social Registration #<?php echo htmlspecialchars($registrationId); ?></h2>
        <p class="text-muted mb-0">Update guest details, registration type, and uploaded documents.</p>
    </div>
    <a href="manage_social.php" class="btn btn-outline-secondary">Back to List</a>
</div>

<?php echo $statusMessage; ?>

<div class="glass-panel mt-3">
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Registration Type</label>
                <select name="registration_type" class="form-select" required>
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($registration['registration_type'] === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Ambassador Code</label>
                <input type="text" name="ambassador_code" class="form-control" value="<?php echo htmlspecialchars($registration['ambassador_code'] ?? ''); ?>" placeholder="Optional">
            </div>
            <div class="col-md-4">
                <label class="form-label">Total Amount (PKR)</label>
                <input type="number" step="1" min="0" name="total_amount" class="form-control" value="<?php echo htmlspecialchars($registration['total_amount'] ?? 0); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Payment Proof (replace)</label>
                <input type="file" name="payment_proof" class="form-control" accept="image/*">
                <?php if (!empty($registration['payment_proof'])): ?>
                    <div class="mt-2">
                        <a href="../<?php echo $registration['payment_proof']; ?>" target="_blank"><img src="../<?php echo $registration['payment_proof']; ?>" class="thumb"></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <?php if ($attendeeMode): ?>
            <h5 class="section-title mb-3">Attendees</h5>
            <?php foreach ($attendees as $att): ?>
                <div class="border rounded p-3 mb-3" style="border-color: rgba(255,255,255,0.1);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong style="color:#fff;">Person <?php echo (int)$att['person_index']; ?> (<?php echo htmlspecialchars($att['label'] ?? ''); ?>)</strong>
                        <span class="badge bg-secondary">ID <?php echo (int)$att['id']; ?></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Name</label><input type="text" name="attendees[<?php echo $att['id']; ?>][name]" class="form-control" value="<?php echo htmlspecialchars($att['full_name']); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="attendees[<?php echo $att['id']; ?>][email]" class="form-control" value="<?php echo htmlspecialchars($att['email']); ?>"></div>
                        <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="attendees[<?php echo $att['id']; ?>][phone]" class="form-control" value="<?php echo htmlspecialchars($att['phone']); ?>"></div>
                        <div class="col-md-4"><label class="form-label">CNIC</label><input type="text" name="attendees[<?php echo $att['id']; ?>][cnic]" class="form-control" value="<?php echo htmlspecialchars($att['cnic']); ?>"></div>
                        <div class="col-md-4"><label class="form-label">Label</label><input type="text" name="attendees[<?php echo $att['id']; ?>][label]" class="form-control" value="<?php echo htmlspecialchars($att['label']); ?>"></div>
                        <div class="col-md-4">
                            <label class="form-label">Face Image (replace)</label>
                            <input type="file" name="face_<?php echo $att['id']; ?>" class="form-control" accept="image/*">
                            <?php if (!empty($att['face_image'])): ?>
                                <div class="mt-2"><a href="../<?php echo $att['face_image']; ?>" target="_blank"><img src="../<?php echo $att['face_image']; ?>" class="thumb"></a></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ID Card (replace)</label>
                            <input type="file" name="card_<?php echo $att['id']; ?>" class="form-control" accept="image/*">
                            <?php if (!empty($att['id_card_image'])): ?>
                                <div class="mt-2"><a href="../<?php echo $att['id_card_image']; ?>" target="_blank"><img src="../<?php echo $att['id_card_image']; ?>" class="thumb"></a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <h5 class="section-title mb-3">Person 1 (Primary)</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="p1_name" class="form-control" value="<?php echo htmlspecialchars($registration['full_name']); ?>" required></div>
                <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="p1_cnic" class="form-control" value="<?php echo htmlspecialchars($registration['cnic']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="p1_email" class="form-control" value="<?php echo htmlspecialchars($registration['email']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="p1_phone" class="form-control" value="<?php echo htmlspecialchars($registration['phone']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Face Image</label><input type="file" name="face1" class="form-control" accept="image/*"><?php if (!empty($registration['face_image'])): ?><div class="mt-2"><a href="../<?php echo $registration['face_image']; ?>" target="_blank"><img src="../<?php echo $registration['face_image']; ?>" class="thumb"></a></div><?php endif; ?></div>
                <div class="col-md-6"><label class="form-label">ID Card</label><input type="file" name="card1" class="form-control" accept="image/*"><?php if (!empty($registration['id_card_image'])): ?><div class="mt-2"><a href="../<?php echo $registration['id_card_image']; ?>" target="_blank"><img src="../<?php echo $registration['id_card_image']; ?>" class="thumb"></a></div><?php endif; ?></div>
            </div>

            <h5 class="section-title mb-3">Person 2</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="p2_name" class="form-control" value="<?php echo htmlspecialchars($registration['participant2_name']); ?>"></div>
                <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="p2_cnic" class="form-control" value="<?php echo htmlspecialchars($registration['participant2_cnic']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="p2_email" class="form-control" value="<?php echo htmlspecialchars($registration['participant2_email']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="p2_phone" class="form-control" value="<?php echo htmlspecialchars($registration['participant2_phone']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Face Image</label><input type="file" name="face2" class="form-control" accept="image/*"><?php if (!empty($registration['participant2_face'])): ?><div class="mt-2"><a href="../<?php echo $registration['participant2_face']; ?>" target="_blank"><img src="../<?php echo $registration['participant2_face']; ?>" class="thumb"></a></div><?php endif; ?></div>
                <div class="col-md-6"><label class="form-label">ID Card</label><input type="file" name="card2" class="form-control" accept="image/*"><?php if (!empty($registration['participant2_card'])): ?><div class="mt-2"><a href="../<?php echo $registration['participant2_card']; ?>" target="_blank"><img src="../<?php echo $registration['participant2_card']; ?>" class="thumb"></a></div><?php endif; ?></div>
            </div>

            <h5 class="section-title mb-3">Person 3</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="p3_name" class="form-control" value="<?php echo htmlspecialchars($registration['participant3_name']); ?>"></div>
                <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="p3_cnic" class="form-control" value="<?php echo htmlspecialchars($registration['participant3_cnic']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="p3_email" class="form-control" value="<?php echo htmlspecialchars($registration['participant3_email']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="p3_phone" class="form-control" value="<?php echo htmlspecialchars($registration['participant3_phone']); ?>"></div>
                <div class="col-md-6"><label class="form-label">Face Image</label><input type="file" name="face3" class="form-control" accept="image/*"><?php if (!empty($registration['participant3_face'])): ?><div class="mt-2"><a href="../<?php echo $registration['participant3_face']; ?>" target="_blank"><img src="../<?php echo $registration['participant3_face']; ?>" class="thumb"></a></div><?php endif; ?></div>
                <div class="col-md-6"><label class="form-label">ID Card</label><input type="file" name="card3" class="form-control" accept="image/*"><?php if (!empty($registration['participant3_card'])): ?><div class="mt-2"><a href="../<?php echo $registration['participant3_card']; ?>" target="_blank"><img src="../<?php echo $registration['participant3_card']; ?>" class="thumb"></a></div><?php endif; ?></div>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Changes</button>
            <a href="manage_social.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
