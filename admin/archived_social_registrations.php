<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';

$res = $conn->query("SELECT * FROM archived_social_registrations ORDER BY deleted_at DESC");
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2><i class="fas fa-trash-restore me-2 text-warning"></i> Deleted Social Registrations Archive</h2>
        <p class="text-muted">History of all deleted social event registrations with photos, payments, and admin tracking.</p>
    </div>
    <div>
        <a href="manage_social.php" class="btn btn-outline-info">
            <i class="fas fa-arrow-left me-1"></i> Back to Active Registrations
        </a>
    </div>
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table table-hover text-white align-middle">
            <thead>
                <tr style="border-bottom: 2px solid #ffaa00;">
                    <th>Original ID</th>
                    <th>Name / Contact</th>
                    <th>CNIC / Roll</th>
                    <th>Form Type</th>
                    <th>Payment Proof</th>
                    <th>Photos</th>
                    <th>Deleted When</th>
                    <th>Deleted By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$res || $res->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted p-5">
                            <i class="fas fa-archive fs-1 text-secondary mb-3 d-block"></i>
                            No deleted social registrations found in archive.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $res->fetch_assoc()): 
                        $proofUrl = resolve_image_url($row['payment_proof'], '../');
                        $faceUrl = resolve_image_url($row['face_image'], '../');
                        $cardUrl = resolve_image_url($row['id_card_image'], '../');
                    ?>
                    <tr>
                        <td><strong>#<?php echo (int)$row['original_id']; ?></strong></td>
                        <td>
                            <strong class="text-white"><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <small class="text-info"><?php echo htmlspecialchars($row['email']); ?></small><br>
                            <small class="text-muted"><?php echo htmlspecialchars($row['phone']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['cnic'] ?: 'N/A'); ?></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($row['registration_type'] ?: 'standard'); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($row['payment_proof'])): ?>
                                <a href="<?php echo htmlspecialchars($proofUrl); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($proofUrl); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #444;">
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (!empty($row['face_image'])): ?>
                                    <a href="<?php echo htmlspecialchars($faceUrl); ?>" target="_blank" title="Face Photo">
                                        <img src="<?php echo htmlspecialchars($faceUrl); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #00FF94;">
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($row['id_card_image'])): ?>
                                    <a href="<?php echo htmlspecialchars($cardUrl); ?>" target="_blank" title="ID Card">
                                        <img src="<?php echo htmlspecialchars($cardUrl); ?>" style="width:40px;height:40px;border-radius:4px;object-fit:cover;border:1px solid #4dabf7;">
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo date('M j, Y h:i A', strtotime($row['deleted_at'])); ?></td>
                        <td><span class="badge bg-dark border border-warning text-warning"><?php echo htmlspecialchars($row['deleted_by'] ?: 'Admin'); ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
