<?php
include 'header.php';
include '../db_connection.php';

$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-users me-2"></i> Portal Signups</h2>
    <p class="text-muted">All user accounts created on the SENTEC portal.</p>
</div>

<div class="glass-panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <h4 class="text-white m-0">Total Signups: <?php echo count($users); ?></h4>
        <a href="download_signups_csv" class="btn-neon btn-sm">
            <i class="fas fa-file-csv"></i> Download CSV
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover text-white align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Institute</th>
                    <th>Created At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted p-4">No signups found.</td>
                    </tr>
                <?php else: ?>
                    <?php $sr = 1; foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['institution'] ?? ''); ?></td>
                            <td><?php echo isset($user['created_at']) ? date('d M Y H:i', strtotime($user['created_at'])) : '-'; ?></td>
                            <td>
                                <?php
                                    $isVerified = isset($user['is_verified']) ? (int)$user['is_verified'] : 0;
                                    if ($isVerified) {
                                        echo '<span class="badge bg-success">Verified</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Pending</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
