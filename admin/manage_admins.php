<?php
include 'header.php';
include '../db_connection.php';

// Check role: Only super_admin or super_super_admin can manage staff accounts
$currentRole = $_SESSION['admin_role'] ?? 'moderator';
if (!in_array($currentRole, ['super_admin', 'super_super_admin'], true)) {
    echo '<div class="container py-5 text-center">';
    echo '  <div class="glass-panel mx-auto p-5" style="max-width: 600px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(15, 23, 42, 0.85);">';
    echo '    <div class="mb-3"><i class="fas fa-shield-alt text-danger" style="font-size: 3.5rem;"></i></div>';
    echo '    <h3 class="text-white fw-bold">403 - Restricted Access</h3>';
    echo '    <p class="text-muted mt-2 mb-4">You do not have administrative clearance to manage staff accounts.</p>';
    echo '    <a href="index.php" class="btn btn-outline-info px-4 py-2"><i class="fas fa-arrow-left me-2"></i>Return to Dashboard</a>';
    echo '  </div>';
    echo '</div>';
    include 'footer.php';
    exit();
}

$isSuperSuperAdmin = ($currentRole === 'super_super_admin');
$msg = '';

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Admin account successfully deleted.</div>';
}

// Handle Change / Reset Password (Super Super Admin only)
if (isset($_POST['change_password']) && $isSuperSuperAdmin) {
    $targetId = intval($_POST['target_id'] ?? 0);
    $newPass = trim($_POST['new_password'] ?? '');
    if (!empty($newPass) && $targetId > 0) {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upStmt = $conn->prepare("UPDATE admin_users SET password = ?, plain_password = ? WHERE id = ?");
        $upStmt->bind_param("ssi", $newHash, $newPass, $targetId);
        if ($upStmt->execute()) {
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Password updated successfully for account #' . $targetId . '.</div>';
        } else {
            $msg = '<div class="alert alert-danger">Error updating password: ' . htmlspecialchars($upStmt->error) . '</div>';
        }
        $upStmt->close();
    } else {
        $msg = '<div class="alert alert-warning">Please enter a valid new password.</div>';
    }
}

// Handle Add Admin
if (isset($_POST['add_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $requestedRole = trim($_POST['role'] ?? 'moderator');

    // Define permitted assignable roles based on who is logged in
    $allowedRoles = ['moderator', 'super_admin'];
    if ($isSuperSuperAdmin) {
        $allowedRoles[] = 'super_super_admin';
    }

    if (empty($username) || empty($password) || empty($full_name)) {
        $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>Please fill in all required fields.</div>';
    } elseif (!in_array($requestedRole, $allowedRoles, true)) {
        $msg = '<div class="alert alert-danger"><i class="fas fa-shield-alt me-2"></i>Access Denied: Only Super Super Admins can create or grant Super Super Admin accounts.</div>';
    } else {
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Username "' . htmlspecialchars($username) . '" already exists. Please choose a different username.</div>';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users (username, password, plain_password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hash, $password, $full_name, $requestedRole);
            
            if ($stmt->execute()) {
                $roleLabel = ($requestedRole === 'super_super_admin') ? 'Super Super Admin' : (($requestedRole === 'super_admin') ? 'Super Admin' : 'Moderator');
                $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Admin account created successfully as <strong>' . htmlspecialchars($roleLabel) . '</strong>.</div>';
            } else {
                $msg = '<div class="alert alert-danger">Error creating user: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}

// Handle Delete Admin
if (isset($_GET['delete'])) {
    $targetId = intval($_GET['delete']);
    $currentAdminId = intval($_SESSION['admin_id'] ?? 0);

    if ($targetId === $currentAdminId) {
        $msg = '<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>You cannot delete your own active account.</div>';
    } else {
        $targetQ = $conn->query("SELECT role, username FROM admin_users WHERE id = $targetId LIMIT 1");
        if ($targetQ && $targetUser = $targetQ->fetch_assoc()) {
            // If target is super_super_admin, only a super_super_admin can delete it
            if ($targetUser['role'] === 'super_super_admin' && !$isSuperSuperAdmin) {
                $msg = '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Access Denied: Super Super Admin accounts are protected and can only be deleted by a Super Super Admin.</div>';
            } else {
                $delStmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
                $delStmt->bind_param("i", $targetId);
                $delStmt->execute();
                $delStmt->close();
                header("Location: manage_admins.php?deleted=1");
                exit;
            }
        } else {
            $msg = '<div class="alert alert-danger">Account not found.</div>';
        }
    }
}

// Fetch all admins ordered by hierarchy and creation date
$admins = $conn->query("SELECT * FROM admin_users ORDER BY 
    CASE role 
        WHEN 'super_super_admin' THEN 1 
        WHEN 'super_admin' THEN 2 
        ELSE 3 
    END ASC, created_at DESC");
?>

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="text-white mb-1"><i class="fas fa-user-shield me-2 text-primary"></i> Staff & Access Management</h2>
        <p class="text-muted small mb-0">Manage portal administrative credentials, passwords, and role hierarchies.</p>
    </div>
    <div>
        <?php if ($isSuperSuperAdmin): ?>
            <span class="badge px-3 py-2" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); font-size: 11px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 0 12px rgba(139,92,246,0.4);">
                <i class="fas fa-crown me-1 text-warning"></i> Root Super Super Admin
            </span>
        <?php else: ?>
            <span class="badge bg-warning text-dark px-3 py-2" style="font-size: 11px;">
                <i class="fas fa-shield-alt me-1"></i> Super Admin Clearance
            </span>
        <?php endif; ?>
    </div>
</div>

<?php echo $msg; ?>

<div class="row">
    <!-- Add New Admin Panel -->
    <div class="col-lg-4 mb-4">
        <div class="glass-panel p-4" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px;">
            <h5 class="text-white mb-3 d-flex align-items-center gap-2">
                <i class="fas fa-user-plus text-info"></i> Create Admin Account
            </h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="text-muted small mb-1">Full Name</label>
                    <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. John Doe" required>
                </div>
                <div class="mb-3">
                    <label class="text-muted small mb-1">Username</label>
                    <input type="text" name="username" class="form-control bg-dark text-white border-secondary" placeholder="e.g. jdoe_admin" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="text-muted small mb-1">Password</label>
                    <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="••••••••" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="text-muted small mb-1">Role Clearance</label>
                    <select name="role" class="form-select bg-dark text-white border-secondary" required>
                        <option value="moderator">Moderator (Standard Staff)</option>
                        <option value="super_admin">Super Admin (Portal Manager)</option>
                        <?php if ($isSuperSuperAdmin): ?>
                            <option value="super_super_admin">⚡ Super Super Admin (Full Root & Reconciler)</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$isSuperSuperAdmin): ?>
                        <div class="form-text text-muted mt-1" style="font-size: 11px;">
                            <i class="fas fa-info-circle me-1 text-warning"></i>Only Super Super Admins can create Super Super Admin accounts.
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" name="add_admin" class="btn btn-primary w-100 py-2 mt-2" style="background: linear-gradient(135deg, #0ea5e9, #6366f1); border: none; font-weight: 600;">
                    <i class="fas fa-plus-circle me-1"></i> Register Admin
                </button>
            </form>
        </div>
    </div>

    <!-- Existing Admins Table -->
    <div class="col-lg-8">
        <div class="glass-panel p-4" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-users-cog text-warning"></i> Existing Administrators
                </h5>
                <span class="text-muted small font-mono">Total: <?php echo $admins->num_rows; ?></span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="border-color: rgba(255,255,255,0.06);">
                    <thead>
                        <tr class="text-muted small text-uppercase" style="letter-spacing: 0.05em; font-family: var(--font-mono);">
                            <th>Name / Identity</th>
                            <th>Username</th>
                            <th>Role</th>
                            <?php if ($isSuperSuperAdmin): ?>
                                <th>Password Clearance</th>
                            <?php endif; ?>
                            <th>Last Active</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($a = $admins->fetch_assoc()): 
                            $isRowSelf = ($a['id'] == ($_SESSION['admin_id'] ?? 0));
                            $isRowSuperSuper = ($a['role'] === 'super_super_admin');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?php echo htmlspecialchars($a['full_name'] ?? ''); ?></div>
                                <div class="text-muted" style="font-size: 11px;">ID: #<?php echo $a['id']; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary font-mono text-light">
                                    <?php echo htmlspecialchars($a['username']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['role'] === 'super_super_admin'): ?>
                                    <span class="badge font-mono" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 0 8px rgba(139,92,246,0.35);">
                                        <i class="fas fa-crown me-1 text-warning"></i>Super Super
                                    </span>
                                <?php elseif ($a['role'] === 'super_admin'): ?>
                                    <span class="badge bg-warning text-dark font-mono">
                                        <i class="fas fa-shield-alt me-1"></i>Super Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary font-mono text-light">
                                        <i class="fas fa-user me-1"></i>Moderator
                                    </span>
                                <?php endif; ?>
                            </td>

                            <?php if ($isSuperSuperAdmin): ?>
                            <td>
                                <?php if (!empty($a['plain_password'])): ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span id="pass_dots_<?php echo $a['id']; ?>" class="font-mono text-muted" style="letter-spacing: 0.1em;">••••••••</span>
                                        <span id="pass_text_<?php echo $a['id']; ?>" class="font-mono text-warning fw-bold d-none user-select-all" style="font-size: 12px; letter-spacing: 0.05em; background: rgba(0,0,0,0.5); padding: 2px 7px; border-radius: 4px; border: 1px solid rgba(234,179,8,0.35);">
                                            <?php echo htmlspecialchars($a['plain_password']); ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1.5" onclick="togglePass(<?php echo $a['id']; ?>)" title="Show/Hide Password">
                                            <i class="fas fa-eye" id="pass_icon_<?php echo $a['id']; ?>" style="font-size: 11px;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-1.5" onclick="copyPass('<?php echo htmlspecialchars(addslashes($a['plain_password'])); ?>', this)" title="Copy Password">
                                            <i class="fas fa-copy" style="font-size: 11px;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1.5" onclick="openResetModal(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars(addslashes($a['username'])); ?>')" title="Change Password">
                                            <i class="fas fa-key" style="font-size: 11px;"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="badge bg-dark border border-secondary font-mono text-muted" style="font-size: 10px;" title="Legacy one-way hash (pre-migration). Click 'Set Pass' to assign one or wait until they next log in.">
                                            <i class="fas fa-lock me-1 text-secondary"></i>Legacy Hash
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" onclick="openResetModal(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars(addslashes($a['username'])); ?>')" title="Set plain password now so it is viewable">
                                            <i class="fas fa-key me-1" style="font-size: 10px;"></i>Set Pass
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <td class="text-muted small font-mono">
                                <?php echo $a['last_login'] ? date('M j, Y H:i', strtotime($a['last_login'])) : '<span class="text-secondary">Never</span>'; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($isRowSelf): ?>
                                    <span class="badge bg-dark border border-info text-info small">You</span>
                                <?php elseif ($isRowSuperSuper && !$isSuperSuperAdmin): ?>
                                    <span class="badge bg-dark border border-secondary text-muted small" title="Super Super Admin accounts can only be managed by Super Super Admin">
                                        <i class="fas fa-lock me-1"></i>Protected
                                    </span>
                                <?php else: ?>
                                    <a href="?delete=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this administrator account (<?php echo htmlspecialchars($a['username']); ?>)?')" title="Delete Account">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isSuperSuperAdmin): ?>
<!-- Password Reset Modal for Super Super Admin -->
<div class="modal fade" id="resetPassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary" style="border-radius: 12px; background: #0f172a !important; border: 1px solid rgba(255,255,255,0.15);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="fas fa-key text-warning"></i> Set / Change Administrator Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="target_id" id="modalTargetId" value="">
                    <p class="text-muted small mb-3">
                        Setting a new password for account: <strong id="modalTargetUser" class="text-warning font-mono"></strong>.<br>
                        This will update their login credential and make it immediately viewable to you as Super Super Admin.
                    </p>
                    <div class="mb-3">
                        <label class="text-muted small mb-1">New Password</label>
                        <div class="input-group">
                            <input type="text" name="new_password" id="modalNewPass" class="form-control bg-black text-white border-secondary font-mono" placeholder="Enter new password" required autocomplete="off">
                            <button class="btn btn-outline-info" type="button" onclick="generateRandomPass()"><i class="fas fa-dice me-1"></i> Generate</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-sm btn-warning fw-bold px-3">
                        <i class="fas fa-save me-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePass(id) {
    const dots = document.getElementById('pass_dots_' + id);
    const text = document.getElementById('pass_text_' + id);
    const icon = document.getElementById('pass_icon_' + id);
    if (!text || !dots || !icon) return;
    
    if (text.classList.contains('d-none')) {
        text.classList.remove('d-none');
        dots.classList.add('d-none');
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        text.classList.add('d-none');
        dots.classList.remove('d-none');
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function openResetModal(id, username) {
    document.getElementById('modalTargetId').value = id;
    document.getElementById('modalTargetUser').textContent = username;
    document.getElementById('modalNewPass').value = '';
    const modalEl = document.getElementById('resetPassModal');
    if (window.bootstrap && bootstrap.Modal) {
        const myModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        myModal.show();
    } else {
        $(modalEl).modal('show');
    }
}

function generateRandomPass() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('modalNewPass').value = pass;
}

function copyPass(pass, btn) {
    navigator.clipboard.writeText(pass).then(function() {
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success" style="font-size: 11px;"></i>';
        setTimeout(() => { btn.innerHTML = origHtml; }, 1500);
    }).catch(function() {
        prompt("Copy password:", pass);
    });
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>