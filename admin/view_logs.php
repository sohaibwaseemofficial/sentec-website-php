<?php
include 'header.php';
include '../db_connection.php';

if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    die("Only super admins can view logs.");
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