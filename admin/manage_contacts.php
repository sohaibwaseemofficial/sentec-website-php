<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../db_connection.php';

$msg = "";

// Delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    $delStmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    if ($delStmt) {
        $delStmt->bind_param("i", $delId);
        if ($delStmt->execute()) {
            $msg = "<div class='alert alert-success'>Message #$delId deleted successfully.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Failed to delete message: " . htmlspecialchars($conn->error) . "</div>";
        }
        $delStmt->close();
    }
}

// Stats
$totalCount = 0;
$todayCount = 0;
$cRes = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today FROM contact_messages");
if ($cRes && $row = $cRes->fetch_assoc()) {
    $totalCount = (int)$row['total'];
    $todayCount = (int)($row['today'] ?? 0);
}

// Search
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $searchWildcard = "%" . $conn->real_escape_string($search) . "%";
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ? ORDER BY created_at DESC");
    $stmt->bind_param("ssss", $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100");
}
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h2><i class="fas fa-envelope-open-text me-2"></i> Contact Inquiries</h2>
        <p class="text-muted">Review, search, and manage incoming messages submitted via the public Contact Us form.</p>
    </div>
    <div class="d-flex gap-3">
        <div class="stat-badge px-3 py-2" style="background: rgba(241, 90, 36, 0.1); border: 1px solid rgba(241, 90, 36, 0.35); border-radius: 6px;">
            <span class="small text-muted d-block">TOTAL INQUIRIES</span>
            <strong style="color: #f15a24; font-size: 1.25rem;"><?php echo $totalCount; ?></strong>
        </div>
        <div class="stat-badge px-3 py-2" style="background: rgba(0, 255, 148, 0.1); border: 1px solid rgba(0, 255, 148, 0.35); border-radius: 6px;">
            <span class="small text-muted d-block">RECEIVED TODAY</span>
            <strong style="color: #00ff94; font-size: 1.25rem;"><?php echo $todayCount; ?></strong>
        </div>
    </div>
</div>

<?php if ($msg) echo $msg; ?>

<div class="glass-panel p-4 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9 col-sm-8">
            <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search by name, email, phone, or keyword..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3 col-sm-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            <?php if (!empty($search)): ?>
                <a href="manage_contacts.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); font-family:'IBM Plex Mono', monospace; font-size: 0.75rem; text-transform: uppercase; color: #888;">
                    <th>ID</th>
                    <th>Sender Info</th>
                    <th>Phone</th>
                    <th style="min-width: 280px;">Message</th>
                    <th>Date & Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td class="font-monospace text-muted">#<?php echo $row['id']; ?></td>
                            <td>
                                <strong class="text-white"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="small text-decoration-none" style="color: #f15a24;">
                                    <?php echo htmlspecialchars($row['email']); ?>
                                </a>
                            </td>
                            <td class="font-monospace small text-muted">
                                <?php echo htmlspecialchars($row['phone'] ?: '—'); ?>
                            </td>
                            <td>
                                <div style="max-height: 90px; overflow-y: auto; font-size: 0.88rem; line-height: 1.5; color: #d1d5db;">
                                    <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                                </div>
                            </td>
                            <td class="small text-muted font-monospace" style="white-space: nowrap;">
                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?><br>
                                <span style="font-size: 0.72rem;"><?php echo date('g:i A', strtotime($row['created_at'])); ?></span>
                            </td>
                            <td>
                                <a href="manage_contacts.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete inquiry #<?php echo $row['id']; ?>?');" title="Delete Inquiry">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                            No contact inquiries found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
