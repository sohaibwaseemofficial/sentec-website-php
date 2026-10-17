<?php
include 'header.php';
include '../db_connection.php';

if (($_SESSION['admin_role'] ?? '') !== 'super_super_admin') {
    echo '<div class="container py-5 text-center">';
    echo '  <div class="glass-panel mx-auto p-5" style="max-width: 600px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(15, 23, 42, 0.85);">';
    echo '    <div class="mb-3"><i class="fas fa-lock text-danger" style="font-size: 3.5rem;"></i></div>';
    echo '    <h3 class="text-white fw-bold">403 - Restricted Access</h3>';
    echo '    <p class="text-muted mt-2 mb-4">Admin Activity Logs are strictly reserved for <strong>Super Super Admin</strong> clearance level.</p>';
    echo '    <a href="index.php" class="btn btn-outline-info px-4 py-2"><i class="fas fa-arrow-left me-2"></i>Return to Dashboard</a>';
    echo '  </div>';
    echo '</div>';
    include 'footer.php';
    exit();
}

$logs = $conn->query("
    SELECT al.*, au.full_name, au.username 
    FROM admin_logs al 
    JOIN admin_users au ON al.admin_id = au.id 
    ORDER BY al.created_at DESC 
    LIMIT 200
");
?>

<div class="page-header">
    <h2><i class="fas fa-history me-2"></i> Admin Activity Logs</h2>
</div>

<div class="glass-panel">
    <table class="table table-dark table-hover">
        <thead>
            <tr>
                <th>Time</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php while($log = $logs->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                <td><strong><?php echo htmlspecialchars($log['full_name']); ?></strong> (<?php echo $log['username']; ?>)</td>
                <td><?php echo $log['action']; ?></td>
                <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                <td><?php echo $log['ip_address']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>