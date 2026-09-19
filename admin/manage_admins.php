<?php
include 'header.php';
include '../db_connection.php';

// Only super admins can manage accounts
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    die("Access denied. Only super admins can manage accounts.");
}

$msg = '';

// Handle add
if (isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // plain
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hash, $full_name, $role);
    if ($stmt->execute()) {
        $msg = '<div class="alert alert-success">Admin account created.</div>';
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Prevent deleting yourself
    if ($id == $_SESSION['admin_id']) {
        $msg = '<div class="alert alert-warning">You cannot delete your own account.</div>';
    } else {
        $conn->query("DELETE FROM admin_users WHERE id = $id");
        header("Location: manage_admins.php");
        exit;
    }
}

// Fetch all admins
$admins = $conn->query("SELECT * FROM admin_users ORDER BY created_at DESC");
?>

<style>
/* use existing admin styles */
</style>

<div class="page-header">
    <h2><i class="fas fa-user-cog me-2"></i> Manage Admin Accounts</h2>
</div>

<?php echo $msg; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="glass-panel">
            <h5 class="text-white mb-4">Add New Admin</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="text-white small">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small">Role</label>
                    <select name="role" class="form-select">
                        <option value="moderator">Moderator</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <button type="submit" name="add_admin" class="btn-neon w-100">Add Admin</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="glass-panel">
            <h5 class="text-white mb-4">Existing Admins</h5>
            <table class="table table-dark table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($a = $admins->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($a['username']); ?></td>
                        <td><?php echo $a['role']; ?></td>
                        <td><?php echo $a['last_login'] ? date('M j, Y H:i', strtotime($a['last_login'])) : 'Never'; ?></td>
                        <td>
                            <a href="?delete=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this admin?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>