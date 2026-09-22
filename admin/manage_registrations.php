<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../event_registration_settings.php'; // Add this line

// 1. FETCH STATS
$total = $conn->query("SELECT COUNT(*) as count FROM event_registrations")->fetch_assoc()['count'];
$pending = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'pending'")->fetch_assoc()['count'];
$approved = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'rejected'")->fetch_assoc()['count'];
?>

<style>
    /* PAGE SPECIFIC STYLES */
    .badge-status {
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-shadow: 0 0 8px rgba(0, 0, 0, 0.6);
    }
    .badge-pending {
        color: #ffbb33;
        border: 1px solid rgba(255, 187, 51, 0.7);
        background: radial-gradient(circle, rgba(255, 187, 51, 0.25) 0%, rgba(10, 10, 10, 0.8) 80%);
        box-shadow: 0 0 12px rgba(255, 187, 51, 0.35);
    }
    .badge-approved {
        color: #00ffd1;
        border: 1px solid rgba(0, 255, 148, 0.7);
        background: radial-gradient(circle, rgba(0, 255, 148, 0.25) 0%, rgba(9, 24, 32, 0.9) 80%);
        box-shadow: 0 0 14px rgba(0, 255, 148, 0.45);
    }
    .badge-rejected {
        color: #ff6a6a;
        border: 1px solid rgba(255, 68, 68, 0.7);
        background: radial-gradient(circle, rgba(255, 68, 68, 0.25) 0%, rgba(26, 0, 8, 0.85) 80%);
        box-shadow: 0 0 14px rgba(255, 68, 68, 0.35);
    }

    .badge-payment { font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; margin-left: 10px; }
    .pay-pending {
        background: rgba(255, 255, 255, 0.05);
        color: #cbd5f5;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .pay-submitted {
        background: rgba(255, 187, 51, 0.15);
        color: #ffbb33;
        border: 1px solid rgba(255, 187, 51, 0.6);
        box-shadow: 0 0 12px rgba(255, 187, 51, 0.3);
    }
    .pay-confirmed {
        background: rgba(0, 255, 148, 0.15);
        color: #00ffd1;
        border: 1px solid rgba(0, 255, 148, 0.7);
        box-shadow: 0 0 15px rgba(0, 255, 148, 0.35);
    }

    .badge-amb {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 8px;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(0, 195, 255, 0.12);
        border: 1px solid rgba(0, 195, 255, 0.4);
        color: #87f2ff;
        box-shadow: 0 0 12px rgba(0, 195, 255, 0.2);
    }

    /* REGISTRATION CARD (The "Full Detail" Box) */
    .reg-card {
        background: linear-gradient(135deg, rgba(10, 18, 36, 0.9) 0%, rgba(4, 8, 20, 0.95) 100%);
        backdrop-filter: blur(24px);
        border: 1px solid rgba(0, 255, 148, 0.05);
        border-radius: 18px;
        padding: 28px;
        margin-bottom: 28px;
        position: relative;
        transition: 0.35s ease;
        box-shadow: 0 25px 45px rgba(0, 0, 0, 0.55);
    }
    .reg-card:hover {
        border-color: rgba(0, 255, 148, 0.35);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6), 0 0 25px rgba(0, 255, 148, 0.2);
    }
    
    /* Status Border Indicator */
    .reg-card.approved { border-left: 5px solid #00FF94; }
    .reg-card.rejected { border-left: 5px solid #ff4444; }
    .reg-card.pending { border-left: 5px solid #ffbb33; }

    /* Participant Info Box */
    .participant-box {
        background: rgba(3, 10, 26, 0.75);
        border: 1px solid rgba(0, 255, 148, 0.08);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        box-shadow: inset 0 0 25px rgba(0, 0, 0, 0.35);
    }
    .info-label { color: var(--accent); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
    .info-val { color: #ddd; font-size: 0.95rem; word-break: break-word; }
    
    /* Images */
    .proof-img { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(0, 255, 148, 0.4); box-shadow: 0 0 18px rgba(0, 255, 148, 0.25); }
        /* Payment Proof Hover Effect */
    .payment-proof-link {
        position: relative;
        display: block;
        overflow: hidden;
        border-radius: 10px;
    }
    
    .payment-proof-link:hover .proof-img {
        transform: scale(1.02);
        box-shadow: 0 0 30px rgba(0, 255, 148, 0.5) !important;
        border-color: #00FF94 !important;
    }
    
    .payment-proof-link:hover .proof-overlay {
        opacity: 1 !important;
    }
        .pay-rejected {
        background: rgba(255, 68, 68, 0.15);
        color: #ff6a6a;
        border: 1px solid rgba(255, 68, 68, 0.6);
    }
    .face-img { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 2px solid var(--accent); }
    .id-img { width: 80px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #555; }

    /* Buttons */
    .btn-solid-green, .btn-solid-red {
        position: relative;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        border-radius: 999px;
        padding: 11px 26px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid transparent;
        transition: 0.35s ease;
        overflow: hidden;
    }
    .btn-solid-green {
        background: rgba(0, 255, 148, 0.18);
        border-color: rgba(0, 255, 148, 0.65);
        color: #00ffd1;
        box-shadow: 0 0 22px rgba(0, 255, 148, 0.35);
    }
    .btn-solid-green:hover {
        background: rgba(0, 255, 148, 0.28);
        color: #051612;
        box-shadow: 0 0 35px rgba(0, 255, 148, 0.6);
    }
    .btn-solid-red {
        background: rgba(255, 68, 68, 0.18);
        border-color: rgba(255, 68, 68, 0.65);
        color: #ff8080;
        box-shadow: 0 0 22px rgba(255, 68, 68, 0.35);
    }
    .btn-solid-red:hover {
        background: rgba(255, 68, 68, 0.28);
        color: #1b0505;
        box-shadow: 0 0 35px rgba(255, 68, 68, 0.6);
    }

    .btn-solid-green.btn-sm, .btn-solid-red.btn-sm {
        padding: 8px 18px;
        font-size: 0.75rem;
    }
    
    /* Checkbox */
    .reg-select { width: 20px; height: 20px; background-color: #050d1a; border-color: #3d4c61; cursor: pointer; border-radius: 6px; }
    .reg-select:checked { background-color: var(--accent); border-color: var(--accent); box-shadow: 0 0 10px rgba(0, 255, 148, 0.6); }

    #searchBox {
        background: rgba(5, 12, 28, 0.9);
        border: 1px solid rgba(0, 255, 148, 0.15) !important;
        color: #e5f8ff;
        padding: 12px 22px;
        border-radius: 40px;
        transition: 0.3s ease;
        min-width: 260px;
    }
    #searchBox:focus {
        border-color: rgba(0, 255, 148, 0.5) !important;
        box-shadow: 0 0 25px rgba(0, 255, 148, 0.2);
        outline: none;
    }

    #statusFilter .nav-link {
        border-radius: 999px;
        padding: 8px 18px;
        margin-right: 8px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid transparent;
        transition: 0.3s ease;
    }
    #statusFilter .nav-link:hover {
        border-color: rgba(0, 255, 148, 0.2);
        color: #00ffd1;
    }
    #statusFilter .nav-link.active {
        background: rgba(0, 255, 148, 0.18);
        border-color: rgba(0, 255, 148, 0.45);
        color: #011511;
        box-shadow: 0 0 25px rgba(0, 255, 148, 0.35);
    }

    .action-btn i { margin-right: 6px; }

    .btn-outline-info {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-color: rgba(0, 195, 255, 0.45);
        color: #4cd9ff;
        border-radius: 999px;
        padding: 10px 20px;
        transition: 0.3s ease;
        background: rgba(0, 195, 255, 0.08);
    }
    .btn-outline-info:hover {
        border-color: rgba(0, 195, 255, 0.75);
        color: #02131e;
        box-shadow: 0 0 22px rgba(0, 195, 255, 0.35);
        background: rgba(0, 195, 255, 0.2);
    }

    .btn-outline-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-color: rgba(255, 255, 255, 0.25);
        color: #b7c4d6;
        border-radius: 999px;
        padding: 10px 20px;
        transition: 0.3s ease;
        background: rgba(255, 255, 255, 0.02);
    }
    .btn-outline-secondary:hover {
        border-color: rgba(255, 68, 68, 0.5);
        color: #ff7d7d;
        box-shadow: 0 0 18px rgba(255, 68, 68, 0.25);
    }

    .reg-card .border-secondary { border-color: rgba(255, 255, 255, 0.08) !important; }

    @media (max-width: 1199px) {
        .d-flex.justify-content-between.align-items-start.mb-3 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 18px;
        }
    }

    @media (max-width: 991px) {
        .reg-card { padding: 22px; }
        .col-lg-4.text-center {
            border-left: none !important;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 24px;
            padding-left: 0 !important;
            padding-top: 24px;
        }
        .participant-box { flex-direction: column; text-align: center; }
        .participant-box img { margin-bottom: 12px; }
    }

    @media (max-width: 768px) {
        .page-header { text-align: center; }
        .d-flex.gap-3.align-items-center { flex-wrap: wrap; }
        .d-flex.gap-2 { width: 100%; justify-content: center; }
        #searchBox { width: 100%; min-width: 0; }
        .reg-card { padding: 20px; }
        .badge-status { font-size: 0.75rem; }
        .participant-box { padding: 12px; }
    }
</style>

<?php
$eventOpen = event_registrations_open($conn);
$eventLimit = event_registrations_limit($conn);
$eventVisible = event_registrations_visible($conn); // NEW: Check visibility
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h2><i class="fas fa-users-cog me-2"></i> Manage Registrations</h2>
        <p class="text-muted">Review applications and toggle registration status.</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="badge <?php echo $eventOpen ? 'bg-success' : 'bg-danger'; ?>" id="event-status-badge">
            <?php echo $eventOpen ? 'Open' : 'Closed'; ?>
        </span>

        <button class="btn btn-outline-warning btn-sm" id="toggle-event-btn" data-open="<?php echo $eventOpen ? '1' : '0'; ?>">
            <i class="fas fa-power-off me-1"></i> Toggle Registration
        </button>

        <button class="btn <?php echo $eventVisible ? 'btn-outline-danger' : 'btn-success'; ?> btn-sm" id="vanish-event-btn" data-visible="<?php echo $eventVisible ? '1' : '0'; ?>">
            <i class="fas <?php echo $eventVisible ? 'fa-eye-slash' : 'fa-eye'; ?> me-1"></i> 
            <?php echo $eventVisible ? 'Vanish from Dashboard' : 'Show on Dashboard'; ?>
        </button>

        <div class="input-group input-group-sm" style="width: 180px;">
            <input type="number" class="form-control bg-dark text-white border-secondary" id="event-limit-input" value="<?php echo $eventLimit; ?>">
            <button class="btn btn-outline-info" type="button" id="save-event-limit">Save Limit</button>
        </div>
    </div>
</div>

<?php $instVis = event_inst_visibility($conn); ?>
<div class="d-flex justify-content-end align-items-center gap-2 mb-4 p-3" style="background: rgba(10, 18, 36, 0.6); border: 1px solid rgba(0, 255, 148, 0.1); border-radius: 16px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
    <span class="text-muted small fw-bold me-2" style="text-transform: uppercase; letter-spacing: 1px;">
        <i class="fas fa-sliders-h me-2 text-info"></i> Form Visibility Toggles:
    </span>
    
    <button class="btn <?php echo $instVis['ned'] ? 'btn-solid-green' : 'btn-solid-red'; ?> btn-sm" onclick="toggleInst('ned', <?php echo $instVis['ned'] ? '0' : '1'; ?>)" style="padding: 6px 16px;">
        NED <?php echo $instVis['ned'] ? '<i class="fas fa-eye ms-2"></i>' : '<i class="fas fa-eye-slash ms-2"></i>'; ?>
    </button>
    
    <button class="btn <?php echo $instVis['non_ned'] ? 'btn-solid-green' : 'btn-solid-red'; ?> btn-sm" onclick="toggleInst('non_ned', <?php echo $instVis['non_ned'] ? '0' : '1'; ?>)" style="padding: 6px 16px;">
        Non-NED <?php echo $instVis['non_ned'] ? '<i class="fas fa-eye ms-2"></i>' : '<i class="fas fa-eye-slash ms-2"></i>'; ?>
    </button>
    
    <button class="btn <?php echo $instVis['college'] ? 'btn-solid-green' : 'btn-solid-red'; ?> btn-sm" onclick="toggleInst('college', <?php echo $instVis['college'] ? '0' : '1'; ?>)" style="padding: 6px 16px;">
        College <?php echo $instVis['college'] ? '<i class="fas fa-eye ms-2"></i>' : '<i class="fas fa-eye-slash ms-2"></i>'; ?>
    </button>
</div>

<script>
function toggleInst(type, val) {
    if(!confirm("Are you sure you want to toggle this option on the public form?")) return;
    $.post('toggle_event_status.php', { toggle_inst: type, val: val }, function(res) {
        alert(res.message); location.reload();
    }, 'json');
}
</script>

<div class="row mb-4 g-3">
    <div class="col-md-3"><div class="stat-box"><h3><?php echo $total; ?></h3><p>Total</p></div></div>
    <div class="col-md-3"><div class="stat-box" style="border-color:#ffbb33;"><h3 style="color:#ffbb33;"><?php echo $pending; ?></h3><p>Pending</p></div></div>
    <div class="col-md-3"><div class="stat-box" style="border-color:#00FF94;"><h3 style="color:#00FF94;"><?php echo $approved; ?></h3><p>Approved</p></div></div>
    <div class="col-md-3"><div class="stat-box" style="border-color:#ff4444;"><h3 style="color:#ff4444;"><?php echo $rejected; ?></h3><p>Rejected</p></div></div>
</div>

<div class="glass-panel">
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex gap-3 align-items-center">
            <input type="checkbox" id="selectAll" class="reg-select">
            <label for="selectAll" class="text-white fw-bold">Select All</label>
            
            <button id="bulkApprove" class="btn-solid-green btn-sm ms-3"><i class="fas fa-check-double"></i> Bulk Approve</button>
            <button id="bulkReject" class="btn-solid-red btn-sm"><i class="fas fa-ban"></i> Bulk Reject</button>
            <button id="sendAllGatePass" class="btn-outline-info btn-sm ms-2"><i class="fas fa-qrcode"></i> Send Gate Passes (Approved)</button>
        </div>
        
        <div class="d-flex gap-2">
            <input type="text" id="searchBox" placeholder="Search Team, Leader, CNIC..." style="background:#0b1120; border:1px solid #333; color:#fff; padding:10px 20px; border-radius:30px; width:300px;">
            <a href="download_registrations_csv" class="btn-neon btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="statusFilter">
        <li class="nav-item"><a class="nav-link active" data-status="all" href="#">All</a></li>
        <li class="nav-item"><a class="nav-link" data-status="pending" href="#">Pending</a></li>
        <li class="nav-item"><a class="nav-link" data-status="approved" href="#">Approved</a></li>
        <li class="nav-item"><a class="nav-link" data-status="rejected" href="#">Rejected</a></li>
    </ul>

    <div id="registrationsContainer">
        <?php
        // Ensure we are selecting everything so participant 5 & 6 are included
        $sql = "SELECT * FROM event_registrations ORDER BY created_at DESC";
        $result = $conn->query($sql);

        if (!$result) {
            echo '<div class="alert alert-danger">Unable to load registrations: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '</div>';
        } elseif ($result->num_rows > 0) {
            $rowNum = 0;
            while ($row = $result->fetch_assoc()) {
                $rowNum++;
                $paymentProof = (string)($row['payment_proof'] ?? '');
                $feeImg = "../" . htmlspecialchars($paymentProof, ENT_QUOTES, 'UTF-8');
                $payStatus = !empty($row['payment_status']) ? strtolower($row['payment_status']) : 'pending';

                // 1. DATA GATHERING: Updated for full 6-member support
                $teamMembers = [];
                $searchTerms = [$row['team_name'], $row['institution_type'], $row['module_selection']];
                
                for ($memberIndex = 1; $memberIndex <= 6; $memberIndex++) {
                    $nameKey = "participant{$memberIndex}_name";
                    $emailKey = "participant{$memberIndex}_email";
                    $nameVal = trim($row[$nameKey] ?? '');
                    $emailVal = trim($row[$emailKey] ?? '');
                    
                    if ($nameVal === '' && $emailVal === '') continue;

                    // Add member name to search terms so admin can find them
                    $searchTerms[] = $nameVal;

                    $teamMembers[] = [
                        'key' => "participant{$memberIndex}",
                        'name' => $nameVal !== '' ? $nameVal : "Participant {$memberIndex}",
                        'email' => $emailVal,
                        'role' => $memberIndex === 1 ? 'Leader' : 'Member'
                    ];
                }
                
                $teamMembersJson = htmlspecialchars(json_encode($teamMembers, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                $searchString = strtolower(implode(' ', $searchTerms));
                ?>
                
                <div class="reg-card <?php echo $row['status']; ?>" data-status="<?php echo $row['status']; ?>" data-search="<?php echo $searchString; ?>">
                    
                    <div class="d-flex justify-content-between align-items-start mb-3 border-bottom border-secondary pb-3">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="reg-select form-check-input" value="<?php echo $row['id']; ?>">
                            <div>
                                <h3 class="text-white m-0"><span class="badge bg-secondary me-2" style="font-size: 0.7rem; vertical-align: middle;">#<?php echo $rowNum; ?></span><?php echo htmlspecialchars($row['team_name']); ?></h3>
                                <small class="text-muted"><?php echo htmlspecialchars($row['institution_type']); ?></small>
                                <span class="badge bg-dark border border-secondary ms-2"><?php echo htmlspecialchars($row['module_selection']); ?></span>
                                <?php if (!empty($row['brand_ambassador_code'])): ?>
                                    <div class="badge-amb"><i class="fas fa-user-shield"></i> Code: <?php echo htmlspecialchars($row['brand_ambassador_code']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge-status badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                            <div class="mt-2">
                                <span class="badge-payment pay-<?php echo $payStatus; ?>">Pay: <?php echo ucfirst($payStatus); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <?php for($i=1; $i<=6; $i++): 
                                    if(!empty($row["participant{$i}_name"])): 
                                        // Safety check for image paths
                                        $faceImg = !empty($row["participant{$i}_face_image"]) ? "../".$row["participant{$i}_face_image"] : "../images/default-avatar.png";
                                        $idImg = !empty($row["participant{$i}_id_card"]) ? "../".$row["participant{$i}_id_card"] : null;
                                    ?>
                                    <div class="col-md-6">
                                        <div class="participant-box d-flex gap-3 align-items-center">
                                            <a href="<?php echo $faceImg; ?>" target="_blank">
                                                <img src="<?php echo $faceImg; ?>" class="face-img">
                                            </a>
                                            
                                            <div class="flex-grow-1">
                                                <h6 class="text-white m-0 mb-1">
                                                    <?php echo htmlspecialchars($row["participant{$i}_name"]); ?> 
                                                    <?php if($i==1) echo '<i class="fas fa-crown text-warning" title="Leader"></i>'; ?>
                                                </h6>
                                                <div class="info-label">Email</div>
                                                <div class="info-val small mb-1"><?php echo htmlspecialchars($row["participant{$i}_email"]); ?></div>
                                                <div class="d-flex justify-content-between">
                                                    <div><span class="info-label">Phone:</span> <span class="text-light small"><?php echo htmlspecialchars($row["participant{$i}_contact"]); ?></span></div>
                                                    <?php if($idImg): ?>
                                                        <a href="<?php echo $idImg; ?>" target="_blank" class="text-info small text-decoration-none"><i class="fas fa-id-card"></i> ID Card</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; endfor; ?>
                            </div>
                        </div>

                                                <div class="col-lg-4 text-center border-start border-secondary ps-4">
                            <?php if(!empty($row['payment_proof']) && $row['payment_proof'] !== 'Not Collected'): ?>
                                <div class="payment-verification-box" style="background: rgba(0,0,0,0.3); border-radius: 16px; padding: 20px 15px; margin-bottom: 15px;">
                                    <p class="info-label mb-3" style="font-size: 0.9rem;">
                                        <i class="fas fa-receipt me-2" style="color: var(--accent);"></i>Payment Verification
                                    </p>
                                    
                                    <a href="../<?php echo $row['payment_proof']; ?>" target="_blank" class="payment-proof-link" style="display: block; position: relative;">
                                        <img src="../<?php echo $row['payment_proof']; ?>" class="proof-img mb-2" style="border-color:var(--accent); cursor: pointer;">
                                        <div class="proof-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,255,148,0.1); opacity: 0; transition: 0.3s; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-search-plus" style="font-size: 2rem; color: #00FF94;"></i>
                                        </div>
                                    </a>
                                    
                                    <div class="payment-status-badge mb-3">
                                        <?php if($payStatus == 'confirmed'): ?>
                                            <span class="badge" style="background: #00FF94; color: #000; padding: 8px 16px; border-radius: 20px; font-weight: 700;">
                                                <i class="fas fa-check-circle me-1"></i> PAYMENT CONFIRMED
                                            </span>
                                        <?php elseif($payStatus == 'rejected'): ?>
                                            <span class="badge" style="background: #ff4444; color: #fff; padding: 8px 16px; border-radius: 20px; font-weight: 700;">
                                                <i class="fas fa-times-circle me-1"></i> PAYMENT REJECTED
                                            </span>
                                        <?php elseif($payStatus == 'submitted' || $payStatus == 'Submitted'): ?>
                                            <span class="badge" style="background: #ffbb33; color: #000; padding: 8px 16px; border-radius: 20px; font-weight: 700;">
                                                <i class="fas fa-clock me-1"></i> AWAITING VERIFICATION
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #666; color: #fff; padding: 8px 16px; border-radius: 20px;">
                                                <i class="fas fa-hourglass-start me-1"></i> PENDING
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                    $showConfirmButton = ($payStatus == 'submitted' || $payStatus == 'Submitted');
                                    if($showConfirmButton): ?>
                                        <button class="btn-solid-green w-100 confirm-pay-btn" data-id="<?php echo $row['id']; ?>" style="padding: 12px;">
                                            <i class="fas fa-check-circle me-2"></i> Confirm Payment
                                        </button>
                                        <button class="btn-solid-red w-100 mt-2 reject-pay-btn" data-id="<?php echo $row['id']; ?>" style="padding: 10px;">
                                            <i class="fas fa-times-circle me-2"></i> Reject Payment
                                        </button>
                                    <?php elseif($payStatus == 'confirmed'): ?>
                                        <button class="btn-outline-secondary w-100" disabled style="opacity: 0.5; padding: 12px;">
                                            <i class="fas fa-check-double me-2"></i> Already Confirmed
                                        </button>
                                    <?php elseif($payStatus == 'rejected'): ?>
                                        <button class="btn-outline-secondary w-100" disabled style="opacity: 0.5; padding: 12px;">
                                            <i class="fas fa-times me-2"></i> Payment Rejected
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="payment-verification-box" style="background: rgba(0,0,0,0.2); border-radius: 16px; padding: 20px 15px;">
                                    <p class="info-label mb-2">Payment Status</p>
                                    <i class="fas fa-receipt mb-3" style="font-size: 2rem; color: #555;"></i>
                                    <p class="text-muted small">No payment proof uploaded yet.</p>
                                    <span class="badge" style="background: #444; color: #aaa; padding: 8px 16px; border-radius: 20px;">
                                        <i class="fas fa-ban me-1"></i> NOT SUBMITTED
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            

                            <hr class="border-secondary">

                            <div class="d-grid gap-2">
                                <a href="edit_registration.php?id=<?php echo $row['id']; ?>" class="btn-outline-info btn-sm">
                                    <i class="fas fa-pen"></i> Edit Details
                                </a>
                                <button type="button" class="btn-outline-info btn-sm send-gatepass-btn" data-registration-id="<?php echo (int)$row['id']; ?>">
                                    <i class="fas fa-qrcode"></i> Send Gate Pass
                                </button>
                                <button type="button" class="btn-outline-info btn-sm email-team-btn"
                                    data-registration-id="<?php echo (int)$row['id']; ?>"
                                    data-team-name="<?php echo htmlspecialchars($row['team_name'], ENT_QUOTES); ?>"
                                    data-team-module="<?php echo htmlspecialchars($row['module_selection'], ENT_QUOTES); ?>"
                                    data-team-institution="<?php echo htmlspecialchars($row['institution_type'], ENT_QUOTES); ?>"
                                    data-members='<?php echo $teamMembersJson; ?>'>
                                    <i class="fas fa-envelope"></i> Email Team
                                </button>

                                <?php if ($row['status'] !== 'approved'): ?>
                                    <button class="btn-solid-green action-btn" data-id="<?php echo $row['id']; ?>" data-status="approved">Approve Team</button>
                                <?php endif; ?>
                                
                                <?php if ($row['status'] !== 'rejected'): ?>
                                    <button class="btn-solid-red action-btn" data-id="<?php echo $row['id']; ?>" data-status="rejected">Reject Team</button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-secondary btn-sm delete-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<div class="text-center p-5 text-muted"><h4>No registrations found.</h4></div>';
        }
        ?>
    </div>
</div>

    <div class="modal fade" id="teamEmailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
                <div class="modal-header border-secondary">
                    <div>
                        <h5 class="modal-title mb-0">Send Team Email</h5>
                        <small class="text-muted">Craft a custom message styled for SENTEC.</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="teamEmailForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="registration_id" id="teamEmailRegistrationId">
                        <div class="mb-3 p-3" style="background:rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.08); border-radius:10px;">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
                                <div>
                                    <div class="text-muted" style="font-size:0.75rem; letter-spacing:0.08em; text-transform:uppercase;">Team</div>
                                    <h5 class="text-white mb-0" id="teamEmailTeamName">Team</h5>
                                </div>
                                <div class="text-md-end">
                                    <span class="badge bg-dark border border-secondary" id="teamEmailModuleBadge" style="font-size:0.75rem;">Module</span>
                                    <div class="text-muted small mt-1" id="teamEmailInstitution">Institution</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Recipients</label>
                            <div id="teamEmailRecipientList" style="max-height:220px; overflow-y:auto; background:#000; border:1px solid #333; border-radius:10px; padding:14px;">
                                <div class="text-muted small">Select a team from the list to load members.</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-info" id="teamEmailSelectAll"><i class="fas fa-user-check me-1"></i> Toggle All</button>
                                <small class="text-muted" id="teamEmailSelectedCount" style="font-size:0.75rem;">No recipients selected.</small>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email Subject</label>
                                <input type="text" name="subject" class="form-control" placeholder="Subject" style="background:#000; border-color:#444; color:#fff;" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Header Badge</label>
                                <input type="text" name="title" class="form-control" value="Team Update" placeholder="Label above heading" style="background:#000; border-color:#444; color:#fff;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Headline</label>
                                <input type="text" name="heading" class="form-control" placeholder="e.g. Congratulations!" style="background:#000; border-color:#444; color:#fff;" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Greeting Line</label>
                                <input type="text" name="greeting" class="form-control" value="Hello {{participant_name}}," placeholder="Use placeholders like {{participant_name}}" style="background:#000; border-color:#444; color:#fff;">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Message Body</label>
                            <textarea name="body" class="form-control" rows="6" placeholder="Details for the team. Use placeholders such as {{team_name}} or {{module}}." style="background:#000; border-color:#444; color:#fff;" required></textarea>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">CTA Button Label <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                                <input type="text" name="cta_label" class="form-control" placeholder="e.g. View Schedule" style="background:#000; border-color:#444; color:#fff;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CTA Button URL <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                                <input type="url" name="cta_url" class="form-control" placeholder="https://" style="background:#000; border-color:#444; color:#fff;">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Footer Note <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                            <textarea name="footer_note" class="form-control" rows="2" placeholder="Override the default SENTEC footer if needed." style="background:#000; border-color:#444; color:#fff;"></textarea>
                        </div>

                        <small class="text-muted d-block mt-3" style="font-size:0.75rem;">Supported placeholders: <code>{{participant_name}}</code>, <code>{{team_name}}</code>, <code>{{module}}</code>, <code>{{institution}}</code>, <code>{{leader_name}}</code>.</small>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-neon" id="teamEmailSendBtn">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() { 
    // Add this logic to your existing script block
    $('#toggle-event-btn').click(function() {
        var current = $(this).data('open');
         if(!confirm("Change registration status?")) return;
         $.post('toggle_event_status.php', { open: current == 1 ? 0 : 1 }, function(res) {
            alert(res.message); location.reload();
    }, 'json');
    });

    $('#save-event-limit').click(function() {
        var limit = $('#event-limit-input').val();
        $.post('toggle_event_status.php', { limit: limit }, function(res) {
            alert(res.message); location.reload();
        }, 'json');
    });
    // 1. SEARCH
    $("#searchBox").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".reg-card").filter(function() {
            $(this).toggle($(this).data('search').indexOf(value) > -1)
        });
    });

    // 2. FILTER TABS
    $('#statusFilter .nav-link').on('click', function(e) {
        e.preventDefault();
        $('#statusFilter .nav-link').removeClass('active');
        $(this).addClass('active');
        var status = $(this).data('status');
        if(status === 'all') { $('.reg-card').fadeIn(); } 
        else { $('.reg-card').hide(); $('.reg-card[data-status="' + status + '"]').fadeIn(); }
    });

    // 3. BULK SELECT
    $('#selectAll').change(function() {
        $('.reg-card:visible .reg-select').prop('checked', this.checked);
    });

    // 4. BULK ACTIONS
    function bulkAction(status) {
        var ids = $('.reg-select:checked').map(function(){return $(this).val();}).get();
        if(ids.length === 0) { alert("Select at least one team."); return; }
        
        if(confirm("Confirm " + status + " for " + ids.length + " teams? Emails will be sent.")) {
            $.post('bulk_update_registration_status.php', { ids: ids, status: status }, function(res) {
                alert(res.message); location.reload();
            }, 'json');
        }
    }
    $('#bulkApprove').click(function() { bulkAction('approved'); });
    $('#bulkReject').click(function() { bulkAction('rejected'); });

    // 4b. BULK SEND GATE PASSES (all approved)
    $('#sendAllGatePass').click(function() {
        if(!confirm('Send gate pass QR emails to all approved teams?')) { return; }
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        $.post('send_event_gatepass_all.php', {}, function(res) {
            alert(res.message || ('Sent: ' + (res.sent || 0)));
        }, 'json').fail(function(err) {
            alert('Bulk gate pass failed.');
        }).always(function(){
            btn.prop('disabled', false).html('<i class="fas fa-qrcode"></i> Send Gate Passes (Approved)');
        });
    });

    // 5. SINGLE ACTION (Approve/Reject)
    $('.action-btn').click(function() {
        var btn = $(this);
        var id = btn.data('id');
        var status = btn.data('status');
        if(!confirm("Mark this team as " + status.toUpperCase() + "?")) return;

        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');

        $.post('update_registration_status.php', { id: id, status: status }, function(res) {
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert("Error: " + (res.message || "Failed to update status."));
                btn.prop('disabled', false).html(originalHtml);
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Failed to update status.';
            try {
                var json = JSON.parse(xhr.responseText);
                if (json && json.message) msg = json.message;
            } catch(e) {}
            alert("Error: " + msg);
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // 6a. CONFIRM PAYMENT
    $('.confirm-pay-btn').click(function() {
        var btn = $(this);
        if(!confirm("Confirm payment received? User will be notified.")) return;

        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Confirming...');

        $.post('update_payment_status.php', { id: btn.data('id') }, function(res) {
            alert(res.message);
            location.reload();
        }, 'json').fail(function(xhr) {
            alert("Error confirming payment. Please try again.");
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // 6b. REJECT PAYMENT
    $('.reject-pay-btn').click(function() {
        if(confirm("Reject this payment? The team will be notified to upload again.")) {
            const btn = $(this);
            const id = btn.data('id');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Rejecting...');
            
            $.post('reject_payment.php', { id: id }, function(res) {
                alert(res.message);
                location.reload();
            }, 'json').fail(function() {
                alert('Error rejecting payment');
                btn.prop('disabled', false).html('<i class="fas fa-times-circle me-2"></i> Reject Payment');
            });
        }
    });

    // 7. DELETE
    $('.delete-btn').click(function() {
        var btn = $(this);
        if(!confirm("Delete permanently? This removes all data and images.")) return;

        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

        $.post('delete_registration.php', { id: btn.data('id') }, function(res) {
            if (res.success) {
                alert("Registration deleted successfully.");
                location.reload();
            } else {
                alert("Error: " + (res.message || "Failed to delete registration."));
                btn.prop('disabled', false).html(originalHtml);
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Failed to delete registration.';
            try {
                var json = JSON.parse(xhr.responseText);
                if (json && json.message) msg = json.message;
            } catch(e) {}
            alert("Error: " + msg);
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // 8. TEAM EMAIL COMPOSER
    const teamEmailModalEl = document.getElementById('teamEmailModal');
    const teamEmailForm = document.getElementById('teamEmailForm');
    const teamEmailRecipientList = document.getElementById('teamEmailRecipientList');
    const teamEmailSelectAll = document.getElementById('teamEmailSelectAll');
    const teamEmailSelectedCount = document.getElementById('teamEmailSelectedCount');
    const teamEmailTeamName = document.getElementById('teamEmailTeamName');
    const teamEmailModuleBadge = document.getElementById('teamEmailModuleBadge');
    const teamEmailInstitution = document.getElementById('teamEmailInstitution');
    const teamEmailRegistrationId = document.getElementById('teamEmailRegistrationId');
    let teamEmailModalInstance = null;

    function updateTeamEmailSelectedCount() {
        if (!teamEmailRecipientList || !teamEmailSelectedCount) { return; }
        const checkboxes = teamEmailRecipientList.querySelectorAll('input[name="recipient_keys[]"]');
        let enabled = 0;
        let selected = 0;
        checkboxes.forEach(function(cb) {
            if (cb.disabled) { return; }
            enabled++;
            if (cb.checked) { selected++; }
        });
        if (enabled === 0) {
            teamEmailSelectedCount.textContent = 'No deliverable email addresses available.';
        } else {
            teamEmailSelectedCount.textContent = selected + ' of ' + enabled + ' recipients selected.';
        }
    }

    function resetTeamRecipientsPlaceholder() {
        if (!teamEmailRecipientList) { return; }
        teamEmailRecipientList.innerHTML = '<div class="text-muted small">Select a team from the list to load members.</div>';
        if (teamEmailSelectedCount) {
            teamEmailSelectedCount.textContent = 'No recipients selected.';
        }
    }

    function buildTeamEmailRecipientList(members) {
        if (!teamEmailRecipientList) { return; }
        teamEmailRecipientList.innerHTML = '';
        if (!Array.isArray(members) || members.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small';
            empty.textContent = 'No team members with contact details are available.';
            teamEmailRecipientList.appendChild(empty);
            updateTeamEmailSelectedCount();
            return;
        }
        members.forEach(function(member) {
            const wrapper = document.createElement('label');
            wrapper.className = 'd-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 team-email-option';
            wrapper.style.background = 'rgba(0,0,0,0.45)';
            wrapper.style.border = '1px solid rgba(255,255,255,0.08)';
            wrapper.style.borderRadius = '8px';
            wrapper.style.padding = '10px 12px';

            const left = document.createElement('div');
            left.className = 'd-flex align-items-center gap-2';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'recipient_keys[]';
            checkbox.value = member.key || '';
            checkbox.className = 'form-check-input team-email-recipient';
            if (member.email) {
                checkbox.checked = true;
            } else {
                checkbox.disabled = true;
            }

            const nameWrap = document.createElement('div');
            const nameStrong = document.createElement('strong');
            nameStrong.textContent = member.name || 'Member';
            nameWrap.appendChild(nameStrong);
            if (member.role) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark border border-secondary ms-2';
                badge.textContent = member.role;
                nameWrap.appendChild(badge);
            }

            left.appendChild(checkbox);
            left.appendChild(nameWrap);

            const emailDiv = document.createElement('div');
            emailDiv.className = 'small';
            if (member.email) {
                emailDiv.classList.add('text-muted');
                emailDiv.textContent = member.email;
            } else {
                emailDiv.classList.add('text-danger');
                emailDiv.textContent = 'No email on file';
            }

            wrapper.appendChild(left);
            wrapper.appendChild(emailDiv);
            teamEmailRecipientList.appendChild(wrapper);

            checkbox.addEventListener('change', updateTeamEmailSelectedCount);
        });
        updateTeamEmailSelectedCount();
    }

    if (teamEmailSelectAll) {
        teamEmailSelectAll.addEventListener('click', function(ev) {
            ev.preventDefault();
            if (!teamEmailRecipientList) { return; }
            const checkboxes = teamEmailRecipientList.querySelectorAll('input[name="recipient_keys[]"]');
            if (!checkboxes.length) { return; }
            const enabled = Array.from(checkboxes).filter(function(cb) { return !cb.disabled; });
            if (!enabled.length) { return; }
            const shouldCheck = enabled.some(function(cb) { return !cb.checked; });
            enabled.forEach(function(cb) { cb.checked = shouldCheck; });
            updateTeamEmailSelectedCount();
        });
    }

    document.querySelectorAll('.email-team-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!teamEmailForm || !teamEmailModalEl) { return; }
            teamEmailForm.reset();
            const regId = this.getAttribute('data-registration-id') || '';
            const teamName = this.getAttribute('data-team-name') || 'Team';
            const module = this.getAttribute('data-team-module') || '';
            const institution = this.getAttribute('data-team-institution') || '';
            let members = [];
            try {
                members = JSON.parse(this.getAttribute('data-members') || '[]') || [];
            } catch (err) {
                members = [];
            }
            if (teamEmailRegistrationId) {
                teamEmailRegistrationId.value = regId;
            }
            if (teamEmailTeamName) {
                teamEmailTeamName.textContent = teamName;
            }
            if (teamEmailModuleBadge) {
                teamEmailModuleBadge.classList.add('border', 'border-secondary');
                teamEmailModuleBadge.classList.remove('bg-secondary', 'text-dark');
                teamEmailModuleBadge.classList.add('bg-dark');
                if (module !== '') {
                    teamEmailModuleBadge.textContent = module;
                } else {
                    teamEmailModuleBadge.textContent = 'Module not provided';
                    teamEmailModuleBadge.classList.remove('bg-dark');
                    teamEmailModuleBadge.classList.add('bg-secondary', 'text-dark');
                }
            }
            if (teamEmailInstitution) {
                teamEmailInstitution.textContent = institution !== '' ? institution : 'Institution not provided';
            }
            buildTeamEmailRecipientList(Array.isArray(members) ? members : []);
            if (!teamEmailModalInstance && typeof bootstrap !== 'undefined') {
                teamEmailModalInstance = new bootstrap.Modal(teamEmailModalEl);
            }
            if (teamEmailModalInstance) {
                teamEmailModalInstance.show();
            } else if (window.jQuery) {
                $(teamEmailModalEl).modal('show');
            }
        });
    });

    if (teamEmailForm) {
        teamEmailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!teamEmailRecipientList) { return; }
            const selected = teamEmailRecipientList.querySelectorAll('input[name="recipient_keys[]"]:checked');
            if (!selected.length) {
                alert('Please select at least one team member.');
                return;
            }
            const submitBtn = document.getElementById('teamEmailSendBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }
            const formData = new FormData(teamEmailForm);
            fetch('custom_team_email.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                const sent = data && typeof data.sent !== 'undefined' ? data.sent : 0;
                const errors = data && Array.isArray(data.errors) && data.errors.length ? '\nErrors:\n' + data.errors.join('\n') : '';
                alert('Sent: ' + sent + ' email(s).' + errors);
                if (teamEmailModalInstance) {
                    teamEmailModalInstance.hide();
                } else if (window.jQuery) {
                    $(teamEmailModalEl).modal('hide');
                }
                teamEmailForm.reset();
                if (teamEmailRegistrationId) {
                    teamEmailRegistrationId.value = '';
                }
                resetTeamRecipientsPlaceholder();
            })
            .catch(function(err) {
                alert('Custom email failed: ' + err);
            })
            .finally(function() {
                updateTeamEmailSelectedCount();
                if (teamEmailSelectedCount && teamEmailRecipientList && !teamEmailRecipientList.querySelectorAll('input[name="recipient_keys[]"]').length) {
                    teamEmailSelectedCount.textContent = 'No recipients selected.';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Send Email';
                }
            });
        });
    }

    resetTeamRecipientsPlaceholder();

    // 9. SEND GATE PASS (single team)
    document.querySelectorAll('.send-gatepass-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var regId = this.getAttribute('data-registration-id') || '';
            if(!regId) { return; }
            if(!confirm('Send gate pass emails to this team?')) { return; }
            const original = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            fetch('send_event_gatepass.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'registration_id=' + encodeURIComponent(regId)
            }).then(function(res){ return res.json(); })
            .then(function(data){
                alert((data && data.message) ? data.message : 'Email triggered.');
            }).catch(function(){
                alert('Failed to send gate pass.');
            }).finally(() => {
                this.disabled = false;
                this.innerHTML = original;
            });
        });
    });

    $('#vanish-event-btn').click(function() {
        var current = $(this).data('visible');
        $.post('toggle_event_status.php', { visible: current == 1 ? 0 : 1 }, function(res) {
            alert(res.message); location.reload();
        }, 'json');
    });

});
</script>

<?php include 'footer.php'; ?>

