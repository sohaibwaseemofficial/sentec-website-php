<?php
/**
 * SENTEC Admin: Live Gate Operations & Terminal Monitor
 * Real-time monitoring of gate terminals, active stations, duplicate/fraud alerts, and check-in audit stream.
 */
include 'header.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../gate_api_helper.php';

// Handle Station Creation / Updates
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_station') {
        $stId = strtoupper(trim($_POST['station_id'] ?? ''));
        $stPin = trim($_POST['station_pin'] ?? '');
        $stName = trim($_POST['station_name'] ?? '');
        $stRole = trim($_POST['station_role'] ?? 'all');

        if (!empty($stId) && !empty($stPin) && !empty($stName)) {
            $stmt = $conn->prepare("INSERT INTO `gate_stations` (`station_id`, `station_pin`, `station_name`, `role`, `is_active`) 
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE `station_pin` = VALUES(`station_pin`), `station_name` = VALUES(`station_name`), `role` = VALUES(`role`)");
            $stmt->bind_param("ssss", $stId, $stPin, $stName, $stRole);
            if ($stmt->execute()) {
                $notice = "Station '{$stName}' saved successfully.";
            } else {
                $notice = "Error saving station: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'toggle_station') {
        $id = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 1);
        $conn->query("UPDATE `gate_stations` SET `is_active` = {$active} WHERE `id` = {$id}");
        $notice = "Station status updated.";
    }
}

// Initial Fetch of Stations
$stations = [];
$stRes = $conn->query("SELECT * FROM `gate_stations` ORDER BY id ASC");
if ($stRes) {
    while ($r = $stRes->fetch_assoc()) {
        $stations[] = $r;
    }
}

// Initial Counts
$totalSocial = 0;
$totalEngineer = 0;
$totalDuplicates = 0;
$todayAdmitted = 0;

$cntRes = $conn->query("SELECT 
    COUNT(CASE WHEN status = 'APPROVED' AND gate_type = 'social' THEN 1 END) AS social_admitted,
    COUNT(CASE WHEN status = 'APPROVED' AND gate_type = 'engineer' THEN 1 END) AS engineer_admitted,
    COUNT(CASE WHEN status = 'DUPLICATE_REJECTED' THEN 1 END) AS duplicates,
    COUNT(CASE WHEN status = 'APPROVED' AND DATE(created_at) = CURDATE() THEN 1 END) AS today_admitted
    FROM `scan_audit_logs`");

if ($cntRes) {
    $c = $cntRes->fetch_assoc();
    $totalSocial = (int)$c['social_admitted'];
    $totalEngineer = (int)$c['engineer_admitted'];
    $totalDuplicates = (int)$c['duplicates'];
    $todayAdmitted = (int)$c['today_admitted'];
}
?>

<style>
    .gate-stat-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 20px 22px;
        position: relative;
        overflow: hidden;
        transition: 0.25s ease;
    }
    .gate-stat-card:hover {
        border-color: rgba(0, 255, 148, 0.3);
        transform: translateY(-2px);
    }
    .gate-stat-num {
        font-family: 'Outfit', sans-serif;
        font-size: 2.2rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        margin: 6px 0 2px;
    }
    .gate-stat-label {
        font-size: 0.78rem;
        font-family: var(--font-mono);
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .stat-badge-accent {
        color: var(--neon-green, #00FF94);
    }
    .pulse-live-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #00FF94;
        box-shadow: 0 0 10px #00FF94;
        animation: pulseLive 1.8s infinite;
    }
    @keyframes pulseLive {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(1.3); }
    }
    .status-badge-approved { background: rgba(0,255,148,0.15); color: #00FF94; border: 1px solid rgba(0,255,148,0.3); }
    .status-badge-duplicate { background: rgba(255,51,75,0.2); color: #ff334b; border: 1px solid rgba(255,51,75,0.4); }
    .status-badge-warning { background: rgba(255,170,0,0.15); color: #ffaa00; border: 1px solid rgba(255,170,0,0.3); }
    
    .fraud-alert-banner {
        background: linear-gradient(90deg, rgba(255, 51, 75, 0.15) 0%, rgba(20, 26, 32, 0.9) 100%);
        border: 1px solid rgba(255, 51, 75, 0.4);
        border-radius: 14px;
        padding: 14px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
</style>

<div class="container-fluid p-0">
    
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="pulse-live-indicator"></span>
                <span style="font-family: var(--font-mono); font-size: 11px; color: var(--orange); letter-spacing: 0.12em; text-transform: uppercase;">
                    LIVE OPERATIONS // GATE RADAR
                </span>
            </div>
            <h2 class="mb-0 text-white" style="font-family:'Outfit'; font-weight:800;">Gate Operations Terminal</h2>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="../scanner" target="_blank" class="btn btn-sm text-dark fw-bold rounded-pill px-3 py-2" style="background: #00FF94; box-shadow: 0 0 15px rgba(0,255,148,0.3);">
                <i class="fas fa-camera me-1"></i> Launch Scanner Terminal <i class="fas fa-external-link-alt ms-1" style="font-size:10px;"></i>
            </a>
            <button class="btn btn-sm btn-outline-light rounded-pill px-3 py-2" data-bs-toggle="modal" data-bs-target="#newStationModal">
                <i class="fas fa-plus me-1"></i> New Gate Station
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 text-white" onclick="pollStats(true)">
                <i class="fas fa-sync-alt" id="refresh-icon"></i>
            </button>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($notice); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Real-time Duplicate Fraud Banner (Shown if duplicates detected) -->
    <div id="fraud-alert-container" style="<?php echo ($totalDuplicates > 0) ? 'display:flex;' : 'display:none;'; ?>" class="fraud-alert-banner">
        <div style="font-size: 1.8rem; color: #ff334b;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div style="flex: 1;">
            <div style="font-weight: 800; color: #fff; font-size: 1rem;">
                Duplicate Entry Flagged
            </div>
            <div style="font-size: 0.85rem; color: #cbd5e1;" id="fraud-alert-text">
                One or more ticket IDs were scanned multiple times at gate checkpoints. Check the audit logs below.
            </div>
        </div>
        <span class="badge bg-danger rounded-pill px-3 py-2" id="dup-badge-count"><?php echo $totalDuplicates; ?> Duplicates</span>
    </div>

    <!-- 1. METRICS GRID -->
    <div class="row g-3 mb-4">
        
        <div class="col-xl-3 col-sm-6">
            <div class="gate-stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="gate-stat-label">Total Admitted</span>
                    <i class="fas fa-user-check text-muted" style="font-size:1.2rem;"></i>
                </div>
                <div class="gate-stat-num stat-badge-accent" id="stat-total-admitted">
                    <?php echo ($totalSocial + $totalEngineer); ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Today: <strong class="text-white" id="stat-today-admitted"><?php echo $todayAdmitted; ?></strong> check-ins
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="gate-stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="gate-stat-label">Ruh-e-Raqs (Social)</span>
                    <i class="fas fa-ticket-alt text-muted" style="font-size:1.2rem;"></i>
                </div>
                <div class="gate-stat-num text-white" id="stat-social-admitted">
                    <?php echo $totalSocial; ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Auditorium verified passes
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="gate-stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="gate-stat-label">Engineer's Code</span>
                    <i class="fas fa-code text-muted" style="font-size:1.2rem;"></i>
                </div>
                <div class="gate-stat-num text-white" id="stat-engineer-admitted">
                    <?php echo $totalEngineer; ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Team competitor entries
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="gate-stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="gate-stat-label">Duplicate Alerts</span>
                    <i class="fas fa-shield-alt text-danger" style="font-size:1.2rem;"></i>
                </div>
                <div class="gate-stat-num <?php echo ($totalDuplicates > 0) ? 'text-danger' : 'text-white'; ?>" id="stat-duplicate-count">
                    <?php echo $totalDuplicates; ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Blocked secondary attempts
                </div>
            </div>
        </div>

    </div>

    <!-- 2. STATIONS DIRECTORY & CONTROL -->
    <div class="glass-panel p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="text-white mb-1" style="font-family:'Outfit'; font-weight:700;">
                    <i class="fas fa-broadcast-tower me-2 text-warning"></i> Registered Gate Stations
                </h4>
                <p class="text-muted small mb-0">Stations unlock volunteer mobile devices with 4-digit PINs or Setup QR.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="text-muted font-mono" style="font-size: 0.78rem; border-color: rgba(255,255,255,0.08);">
                    <tr>
                        <th>STATION ID</th>
                        <th>NAME & LOCATION</th>
                        <th>ROLE PERMISSION</th>
                        <th>4-DIGIT PIN</th>
                        <th>LAST HEARTBEAT</th>
                        <th>STATUS</th>
                        <th class="text-end">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="stations-tbody">
                    <?php foreach ($stations as $st): 
                        $roleBadge = ($st['role'] === 'engineer') 
                            ? 'bg-primary text-white' 
                            : (($st['role'] === 'social') ? 'bg-success text-dark' : 'bg-warning text-dark');
                        $isActive = !empty($st['is_active']);
                    ?>
                    <tr>
                        <td>
                            <strong class="font-mono text-white"><?php echo htmlspecialchars($st['station_id']); ?></strong>
                        </td>
                        <td>
                            <span class="text-white fw-bold"><?php echo htmlspecialchars($st['station_name']); ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $roleBadge; ?> font-mono px-2 py-1">
                                <?php echo strtoupper($st['role']); ?>
                            </span>
                        </td>
                        <td>
                            <code class="px-2 py-1 bg-dark border border-secondary rounded text-warning fw-bold font-mono">
                                <?php echo htmlspecialchars($st['station_pin']); ?>
                            </code>
                        </td>
                        <td class="text-muted small font-mono">
                            <?php echo $st['last_active_at'] ? date('h:i:s A (M d)', strtotime($st['last_active_at'])) : 'Idle'; ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success bg-opacity-25 text-success border border-success">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">DISABLED</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2 py-1" onclick="showStationQr('<?php echo $st['station_id']; ?>', '<?php echo $st['station_pin']; ?>', '<?php echo addslashes($st['station_name']); ?>', '<?php echo $st['role']; ?>')">
                                <i class="fas fa-qrcode"></i> Setup QR
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. REAL-TIME AUDIT STREAM -->
    <div class="glass-panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h4 class="text-white mb-1" style="font-family:'Outfit'; font-weight:700;">
                    <i class="fas fa-stream me-2 text-info"></i> Real-Time Check-In Stream
                </h4>
                <p class="text-muted small mb-0">Live feed auto-updates every 4 seconds. Tracks attendee admissions and volunteer actions.</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <input type="text" id="stream-filter-input" class="form-control form-control-sm bg-dark text-white border-secondary rounded-pill px-3" placeholder="Filter by Name or Ticket..." style="width: 220px;">
                <select id="stream-status-select" class="form-select form-select-sm bg-dark text-white border-secondary rounded-pill px-3" style="width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="APPROVED">Approved Only</option>
                    <option value="DUPLICATE_REJECTED">Duplicates Only</option>
                    <option value="social">Social Only</option>
                    <option value="engineer">Engineer Only</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="text-muted font-mono" style="font-size: 0.76rem; border-color: rgba(255,255,255,0.08);">
                    <tr>
                        <th>TIME</th>
                        <th>ATTENDEE</th>
                        <th>TICKET ID</th>
                        <th>EVENT TYPE</th>
                        <th>STATION & VOLUNTEER</th>
                        <th>STATUS</th>
                        <th>AUDIT NOTES</th>
                    </tr>
                </thead>
                <tbody id="audit-stream-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                            Loading real-time audit stream...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL: CREATE STATION -->
<div class="modal fade" id="newStationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-outfit fw-bold">Configure Gate Station</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_station">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted font-mono">STATION ID (e.g. GATE_ENG_03)</label>
                        <input type="text" name="station_id" class="form-control bg-black text-white border-secondary font-mono" placeholder="GATE_VIP_01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted font-mono">STATION NAME / LOCATION</label>
                        <input type="text" name="station_name" class="form-control bg-black text-white border-secondary" placeholder="VIP Entrance / Gate 3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted font-mono">4-DIGIT PIN</label>
                        <input type="text" name="station_pin" maxlength="6" class="form-control bg-black text-white border-secondary font-mono text-center fs-4" placeholder="3011" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted font-mono">ALLOWED ROLE</label>
                        <select name="station_role" class="form-select bg-black text-white border-secondary">
                            <option value="social">RUH-E-RAQS (Social Evening Only)</option>
                            <option value="engineer">Engineer's Code (Olympiad Only)</option>
                            <option value="all">Universal (All Events Allowed)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Save Station</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: STATION SETUP QR CODE -->
<div class="modal fade" id="stationQrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm text-center">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h6 class="modal-title font-mono" id="modal-qr-title">STATION QR</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="modal-qr-container" style="background:#fff; padding:12px; border-radius:16px; display:inline-block; margin-bottom:14px;">
                    <img id="station-qr-img" src="" alt="Setup QR" style="width:200px; height:200px; display:block;">
                </div>
                <div class="fw-bold fs-5 text-white mb-1" id="modal-qr-name">Gate 1</div>
                <div class="font-mono text-warning" id="modal-qr-pin">PIN: 1011</div>
                <p class="text-muted small mt-2 mb-0">Point volunteer mobile scanner at this QR code to unlock immediately.</p>
            </div>
        </div>
    </div>
</div>

<script>
    let pollInterval = null;
    let allLogs = [];

    // On Page Load: Fetch Stats and Start 4-Second Polling
    document.addEventListener('DOMContentLoaded', () => {
        pollStats(true);
        pollInterval = setInterval(() => pollStats(false), 4000);

        // Filter event listeners
        document.getElementById('stream-filter-input').addEventListener('input', renderLogsTable);
        document.getElementById('stream-status-select').addEventListener('change', renderLogsTable);
    });

    async function pollStats(showSpinner = false) {
        const refreshIcon = document.getElementById('refresh-icon');
        if (showSpinner && refreshIcon) refreshIcon.classList.add('fa-spin');

        try {
            // Provide super admin gate authorization header
            const res = await fetch('../api/gate/stats.php?token=<?php 
                echo gate_create_token([
                    'station_id' => 'ADMIN_SUPER',
                    'station_name' => 'Admin Live Monitor',
                    'role' => 'all',
                    'volunteer_name' => $adminUser,
                    'device_id' => 'AdminConsole'
                ]); 
            ?>');

            const data = await res.json();
            if (data.success) {
                // Update Top Stats
                const s = data.stats;
                document.getElementById('stat-total-admitted').innerText = s.total_admitted || 0;
                document.getElementById('stat-today-admitted').innerText = s.today_admitted || 0;
                document.getElementById('stat-social-admitted').innerText = s.social_admitted || 0;
                document.getElementById('stat-engineer-admitted').innerText = s.engineer_admitted || 0;
                
                const dupEl = document.getElementById('stat-duplicate-count');
                dupEl.innerText = s.duplicate_rejections || 0;
                dupEl.className = (s.duplicate_rejections > 0) ? "gate-stat-num text-danger" : "gate-stat-num text-white";

                // Update Fraud Banner
                const fraudBox = document.getElementById('fraud-alert-container');
                if (s.duplicate_rejections > 0) {
                    fraudBox.style.display = 'flex';
                    document.getElementById('dup-badge-count').innerText = `${s.duplicate_rejections} Duplicates`;
                }

                // Update Logs
                if (Array.isArray(data.recent_logs)) {
                    allLogs = data.recent_logs;
                    renderLogsTable();
                }
            }
        } catch (e) {
            console.warn("Poll failed, retrying in 4s:", e);
        } finally {
            if (showSpinner && refreshIcon) refreshIcon.classList.remove('fa-spin');
        }
    }

    function renderLogsTable() {
        const tbody = document.getElementById('audit-stream-tbody');
        const filterText = document.getElementById('stream-filter-input').value.toLowerCase().trim();
        const filterStatus = document.getElementById('stream-status-select').value;

        if (!allLogs || allLogs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No scan logs recorded yet today.</td></tr>';
            return;
        }

        const filtered = allLogs.filter(log => {
            const matchesText = !filterText || 
                (log.attendee_name && log.attendee_name.toLowerCase().includes(filterText)) ||
                (log.ticket_id && log.ticket_id.toLowerCase().includes(filterText)) ||
                (log.volunteer_id && log.volunteer_id.toLowerCase().includes(filterText));

            let matchesStatus = true;
            if (filterStatus === 'APPROVED') matchesStatus = (log.status === 'APPROVED');
            else if (filterStatus === 'DUPLICATE_REJECTED') matchesStatus = (log.status === 'DUPLICATE_REJECTED');
            else if (filterStatus === 'social') matchesStatus = (log.gate_type === 'social');
            else if (filterStatus === 'engineer') matchesStatus = (log.gate_type === 'engineer');

            return matchesText && matchesStatus;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No logs match the current filter.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(log => {
            let statusBadge = '';
            if (log.status === 'APPROVED') {
                statusBadge = '<span class="badge status-badge-approved font-mono">APPROVED</span>';
            } else if (log.status === 'DUPLICATE_REJECTED') {
                statusBadge = '<span class="badge status-badge-duplicate font-mono"><i class="fas fa-exclamation-circle me-1"></i>DUPLICATE</span>';
            } else {
                statusBadge = `<span class="badge status-badge-warning font-mono">${log.status}</span>`;
            }

            const timeStr = log.created_at ? log.created_at.split(' ')[1] : '--:--:--';
            const roleBadge = (log.gate_type === 'social') 
                ? '<span class="badge bg-success bg-opacity-25 text-success">Social</span>' 
                : '<span class="badge bg-primary bg-opacity-25 text-primary">Engineer</span>';

            return `
                <tr>
                    <td class="font-mono text-muted small">${timeStr}</td>
                    <td>
                        <strong class="text-white">${escapeHtml(log.attendee_name || 'Attendee')}</strong>
                    </td>
                    <td>
                        <code class="text-info font-mono">${escapeHtml(log.ticket_id || '—')}</code>
                    </td>
                    <td>${roleBadge}</td>
                    <td class="small">
                        <span class="text-white">${escapeHtml(log.station_id)}</span><br>
                        <span class="text-muted" style="font-size:11px;">By ${escapeHtml(log.volunteer_id)}</span>
                    </td>
                    <td>${statusBadge}</td>
                    <td class="small text-muted">${escapeHtml(log.notes || '—')}</td>
                </tr>
            `;
        }).join('');
    }

    function showStationQr(stationId, pin, name, role) {
        document.getElementById('modal-qr-title').innerText = stationId;
        document.getElementById('modal-qr-name').innerText = name;
        document.getElementById('modal-qr-pin').innerText = `STATION PIN: ${pin}`;

        const qrData = encodeURIComponent(`{"station_id":"${stationId}","pin":"${pin}","role":"${role}"}`);
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrData}`;
        document.getElementById('station-qr-img').src = qrUrl;

        const modal = new bootstrap.Modal(document.getElementById('stationQrModal'));
        modal.show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php include 'footer.php'; ?>
