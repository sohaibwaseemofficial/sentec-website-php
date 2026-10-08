<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';
require_once __DIR__ . '/../social_registration_settings.php';
require_once __DIR__ . '/../image_utils.php';

$socialOpen = social_registrations_open($conn);
$socialLimit = social_registrations_limit();
$socialCount = social_registrations_count($conn);
$tierSettings = social_registrations_get_settings($conn);
?>

<style>
    /* PASS TIER SWITCHBOARD STYLES */
    .tier-control-panel {
        background: linear-gradient(135deg, rgba(17, 25, 40, 0.95), rgba(8, 14, 24, 0.98));
        border: 1px solid rgba(255, 106, 0, 0.35);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    }
    .tier-control-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        padding-bottom: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .tier-control-card {
        background: rgba(4, 9, 20, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 12px;
        padding: 20px;
        height: 100%;
        transition: all 0.25s ease;
        position: relative;
    }
    .tier-control-card.active {
        border-color: #00FF94;
        box-shadow: 0 0 15px rgba(0, 255, 148, 0.15);
    }
    .tier-control-card.disabled-tier {
        border-color: rgba(255, 255, 255, 0.06);
        opacity: 0.65;
    }
    .tier-switch-wrap {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .form-check-input:checked {
        background-color: #00FF94 !important;
        border-color: #00FF94 !important;
    }
    .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(0, 255, 148, 0.25) !important;
    }

    /* NEON THEME STYLES */
    .glass-panel {
        background: rgba(17, 25, 40, 0.75);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.125);
        border-radius: 12px;
        padding: 20px;
    }

    .glass-panel table {
        border-collapse: separate;
        border-spacing: 0 18px;
    }

    .glass-panel thead th {
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        color: #9da6c2;
    }

    .glass-panel tbody tr {
        background: rgba(4, 9, 20, 0.78);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
    }

    .glass-panel tbody tr td {
        border-top: none;
        border-bottom: none;
        padding-top: 18px;
        padding-bottom: 18px;
    }

    .glass-panel tbody tr td:first-child {
        border-top-left-radius: 18px;
        border-bottom-left-radius: 18px;
    }

    .glass-panel tbody tr td:last-child {
        border-top-right-radius: 18px;
        border-bottom-right-radius: 18px;
    }

    /* ACTION BUTTONS */
    .btn-icon { 
        width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; 
        border-radius: 8px; border: 1px solid transparent; transition: 0.3s; cursor: pointer;
    }
    
    /* Approve (Green) */
    .btn-approve { background: rgba(0, 255, 148, 0.15); color: #00FF94; border-color: #00FF94; }
    .btn-approve:hover { background: #00FF94; color: #000; box-shadow: 0 0 15px rgba(0,255,148,0.4); }

    /* Reject (Red) */
    .btn-reject { background: rgba(255, 68, 68, 0.15); color: #ff4444; border-color: #ff4444; }
    .btn-reject:hover { background: #ff4444; color: #fff; box-shadow: 0 0 15px rgba(255,68,68,0.4); }

    /* Pay Confirm (Yellow) */
    .btn-pay { background: rgba(255, 187, 51, 0.15); color: #ffbb33; border-color: #ffbb33; }
    .btn-pay:hover { background: #ffbb33; color: #000; box-shadow: 0 0 15px rgba(255,187,51,0.4); }

    /* Delete (Grey to Red) */
    .btn-delete { background: rgba(255, 255, 255, 0.05); color: #888; border-color: #444; }
    .btn-delete:hover { background: #ff4444; color: #fff; border-color: #ff4444; }

    /* STATUS BADGES */
    .badge-status { padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-approved { background: rgba(0, 255, 148, 0.1); color: #00FF94; border: 1px solid #00FF94; }
    .badge-rejected { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: 1px solid #ff4444; }
    .badge-pending  { background: rgba(255, 187, 51, 0.1); color: #ffbb33; border: 1px solid #ffbb33; }

    /* PAYMENT BADGES */
    .pay-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-top: 5px; letter-spacing: 0.5px; }
    .pay-submitted { background: #ffbb33; color: #000; font-weight: bold; }
    .pay-confirmed { background: #00FF94; color: #000; font-weight: bold; }
    .pay-pending   { background: #333; color: #888; }
    
    .proof-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #444; transition: 0.2s; }
    .proof-thumb:hover { transform: scale(1.5); border-color: #00FF94; z-index: 10; position: relative; }

    .participant-block { border-left: 2px solid rgba(255,255,255,0.08); padding-left: 12px; margin-bottom: 12px; }
    .participant-title { font-size: 0.78rem; letter-spacing: 1px; text-transform: uppercase; color: #00FF94; margin-bottom: 4px; }
    .participant-meta { font-size: 0.82rem; color: #aaa; line-height: 1.4; }
    .participant-photo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 10px; }
    .participant-photo { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 10px; background: rgba(2, 7, 16, 0.7); }
    .participant-photo span { font-size: 0.7rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
    .member-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 6px; }
    .member-pill.approved { background: rgba(0,255,148,0.15); color: #00FF94; }
    .member-pill.pending { background: rgba(255,187,51,0.15); color: #ffbb33; }
    .member-pill.rejected { background: rgba(255,68,68,0.15); color: #ff4444; }
    .participant-actions { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
    .btn-mini { padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: #eee; cursor: pointer; transition: 0.2s; }
    .btn-mini.approve { border-color: #00FF94; color: #00FF94; }
    .btn-mini.reject { border-color: #ff4444; color: #ff4444; }
    .btn-mini:hover { background: rgba(255,255,255,0.1); }

    @media (max-width: 1200px) {
        .glass-panel table { border-spacing: 0 12px; }
        .participant-block { padding-left: 10px; }
    }

    @media (max-width: 992px) {
        .glass-panel table,
        .glass-panel thead,
        .glass-panel tbody,
        .glass-panel th,
        .glass-panel tr,
        .glass-panel td { display: block; width: 100%; }

        .glass-panel thead { display: none; }

        .glass-panel tbody tr {
            border-radius: 18px;
            padding: 18px;
        }

        .glass-panel tbody tr td {
            padding: 10px 0;
            position: relative;
        }

        .glass-panel tbody tr td::before {
            content: attr(data-label);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: #7f8ea3;
            text-transform: uppercase;
            display: block;
            margin-bottom: 6px;
        }

        .participant-photo-grid { grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); }
        .participant-block { border-left: none; padding-left: 0; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 10px; }
        .participant-block:first-child { border-top: none; padding-top: 0; }
        .participant-meta { font-size: 0.9rem; }
        .glass-panel .text-end { text-align: left !important; }
        .btn-icon { width: 42px; height: 42px; margin-right: 6px; }
    }

    @media (max-width: 576px) {
        .glass-panel { padding: 16px; }
        .participant-title { font-size: 0.7rem; }
        .participant-meta { font-size: 0.8rem; }
        .participant-photo span { font-size: 0.6rem; }
        .btn-icon { width: 36px; height: 36px; }
    }
</style>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h2><i class="fas fa-glass-cheers me-2"></i> Social Evening Management</h2>
        <p class="text-muted">Manage guests, verify payments, and visibility.</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php $socialVisible = social_registrations_visible($conn); ?>

        <span class="badge <?php echo $socialOpen ? 'bg-success' : 'bg-danger'; ?>" id="social-status-badge">
            <?php echo $socialOpen ? 'Registrations Open' : 'Registrations Closed'; ?>
        </span>

        <button class="btn btn-outline-warning" id="toggle-social-btn" data-open="<?php echo $socialOpen ? '1' : '0'; ?>">
            <i class="fas fa-power-off me-1"></i><?php echo $socialOpen ? 'Close Registrations' : 'Open Registrations'; ?>
        </button>

        <button class="btn <?php echo $socialVisible ? 'btn-outline-danger' : 'btn-success'; ?>" id="vanish-social-btn" data-visible="<?php echo $socialVisible ? '1' : '0'; ?>">
            <i class="fas <?php echo $socialVisible ? 'fa-eye-slash' : 'fa-eye'; ?> me-1"></i> 
            <?php echo $socialVisible ? 'Vanish from Dashboard' : 'Show on Dashboard'; ?>
        </button>

        <div class="input-group" style="width: 220px;">
            <span class="input-group-text">Limit</span>
            <input type="number" min="0" class="form-control" id="social-limit-input" value="<?php echo $socialLimit ?? ''; ?>" placeholder="e.g., 350">
            <button class="btn btn-outline-info" type="button" id="save-social-limit" data-current="<?php echo $socialLimit ?? ''; ?>">Save</button>
        </div>
        <span class="text-muted small">Current: <?php echo (int) $socialCount; ?> registrations</span>
        <a href="download_social_registrations_csv" class="btn btn-outline-success">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </a>
    </div>
</div>

<!-- PASS TIER & EARLY BIRD PRICING SWITCHBOARD -->
<div class="tier-control-panel mb-4">
    <div class="tier-control-header">
        <div>
            <h4 class="m-0 text-white fw-bold"><i class="fas fa-sliders-h text-warning me-2"></i> Pass Tiers & Early Bird Pricing Switchboard</h4>
            <small class="text-muted">Turn pass types ON/OFF to hide or display them smoothly on the public registration form. Control early bird slashed prices in real time.</small>
        </div>
        <div>
            <button type="button" class="btn btn-success px-4 fw-bold" id="btn-save-tier-settings">
                <i class="fas fa-save me-1"></i> Save All Tier & Price Settings
            </button>
        </div>
    </div>

    <form id="tierSettingsForm">
        <div class="row g-3">
            <!-- Tier 1: Individual -->
            <div class="col-lg-4 col-md-12">
                <div class="tier-control-card <?php echo !empty($tierSettings['enable_individual']) ? 'active' : 'disabled-tier'; ?>" id="card-tier-individual">
                    <div class="tier-switch-wrap">
                        <div>
                            <span class="badge bg-primary mb-1">Pass Tier 1</span>
                            <h5 class="m-0 text-white fw-bold">Individual Pass</h5>
                        </div>
                        <div class="form-check form-switch fs-4 m-0">
                            <input class="form-check-input tier-switch" type="checkbox" id="switch_enable_individual" name="enable_individual" value="1" <?php echo !empty($tierSettings['enable_individual']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <p class="small text-muted mb-3">Single attendee registration form with portrait and ID card upload.</p>
                    
                    <div class="p-3 rounded bg-dark border border-secondary mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="switch_early_bird_active" name="early_bird_active" value="1" <?php echo !empty($tierSettings['early_bird_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label text-warning fw-bold small ms-1" for="switch_early_bird_active">
                                <i class="fas fa-bolt text-warning me-1"></i> Early Bird Discount Active
                            </label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted mb-1" for="input_ind_orig">Regular Price (Cut)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-black text-muted border-secondary">PKR</span>
                                    <input type="number" min="0" step="50" class="form-control bg-black text-white border-secondary" id="input_ind_orig" name="individual_original_price" value="<?php echo (int)$tierSettings['individual_original_price']; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="small text-warning fw-bold mb-1" for="input_ind_price">Discounted / Active</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-black text-warning border-secondary">PKR</span>
                                    <input type="number" min="0" step="50" class="form-control bg-black text-warning fw-bold border-secondary" id="input_ind_price" name="individual_price" value="<?php echo (int)$tierSettings['individual_price']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tier-live-preview">
                        <small class="text-muted d-block mb-1">Live Registration Preview:</small>
                        <div class="p-2 rounded bg-black text-center border border-secondary" id="preview-ind-price">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tier 2: Event Participant -->
            <div class="col-lg-4 col-md-12">
                <div class="tier-control-card <?php echo !empty($tierSettings['enable_participant']) ? 'active' : 'disabled-tier'; ?>" id="card-tier-participant">
                    <div class="tier-switch-wrap">
                        <div>
                            <span class="badge bg-secondary mb-1">Pass Tier 2</span>
                            <h5 class="m-0 text-white fw-bold">Event Participant</h5>
                        </div>
                        <div class="form-check form-switch fs-4 m-0">
                            <input class="form-check-input tier-switch" type="checkbox" id="switch_enable_participant" name="enable_participant" value="1" <?php echo !empty($tierSettings['enable_participant']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <p class="small text-muted mb-3">Subsidized / arena attendees pass for registered competition participants.</p>
                    
                    <div class="p-3 rounded bg-dark border border-secondary mb-3">
                        <label class="small text-muted mb-1" for="input_participant_price">Participant Fee (PKR)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-black text-muted border-secondary">PKR</span>
                            <input type="number" min="0" step="50" class="form-control bg-black text-white border-secondary" id="input_participant_price" name="participant_price" value="<?php echo (int)$tierSettings['participant_price']; ?>">
                        </div>
                        <small class="text-muted mt-1 d-block">Set to 0 for 100% free passes (skips payment proof requirement).</small>
                    </div>

                    <div class="tier-live-preview">
                        <small class="text-muted d-block mb-1">Live Status:</small>
                        <div class="p-2 rounded bg-black text-center border border-secondary">
                            <span class="badge <?php echo !empty($tierSettings['enable_participant']) ? 'bg-success' : 'bg-secondary'; ?>" id="badge-participant-status">
                                <?php echo !empty($tierSettings['enable_participant']) ? 'Visible on Form (PKR ' . (int)$tierSettings['participant_price'] . ')' : 'Hidden (Single Entry Mode)'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tier 3: Group Pass -->
            <div class="col-lg-4 col-md-12">
                <div class="tier-control-card <?php echo !empty($tierSettings['enable_group']) ? 'active' : 'disabled-tier'; ?>" id="card-tier-group">
                    <div class="tier-switch-wrap">
                        <div>
                            <span class="badge bg-info text-dark mb-1">Pass Tier 3</span>
                            <h5 class="m-0 text-white fw-bold">Group Pass (3 People)</h5>
                        </div>
                        <div class="form-check form-switch fs-4 m-0">
                            <input class="form-check-input tier-switch" type="checkbox" id="switch_enable_group" name="enable_group" value="1" <?php echo !empty($tierSettings['enable_group']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <p class="small text-muted mb-3">Bundle pass requiring attendee 1, 2, and 3 names, CNICs, portraits & ID cards.</p>
                    
                    <div class="p-3 rounded bg-dark border border-secondary mb-3">
                        <label class="small text-muted mb-1" for="input_group_price">Group Package Price (PKR)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-black text-muted border-secondary">PKR</span>
                            <input type="number" min="0" step="100" class="form-control bg-black text-white border-secondary" id="input_group_price" name="group_price" value="<?php echo (int)$tierSettings['group_price']; ?>">
                        </div>
                        <small class="text-muted mt-1 d-block">Total bundle price for all 3 members.</small>
                    </div>

                    <div class="tier-live-preview">
                        <small class="text-muted d-block mb-1">Live Status:</small>
                        <div class="p-2 rounded bg-black text-center border border-secondary">
                            <span class="badge <?php echo !empty($tierSettings['enable_group']) ? 'bg-success' : 'bg-secondary'; ?>" id="badge-group-status">
                                <?php echo !empty($tierSettings['enable_group']) ? 'Visible on Form (PKR ' . (int)$tierSettings['group_price'] . ')' : 'Hidden (Single Entry Mode)'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table table-hover text-white align-middle">
            <thead>
                <tr style="border-bottom: 2px solid #00FF94;">
                    <th>ID</th>
                    <th>Form Type</th>
                    <th>Ambassador Code</th>
                    <th>Participants</th>
                    <th>Photos / IDs</th>
                    <th>Payment Proof</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Check if table exists to prevent crash
                $check = $conn->query("SHOW TABLES LIKE 'social_registrations'");
                if ($check->num_rows == 0) {
                    echo "<tr><td colspan='6' class='text-center text-danger p-5'><h5>Table 'social_registrations' missing!</h5><p>Please run the SQL command provided.</p></td></tr>";
                } else {
                    $attendeeSupport = social_attendees_table_exists($conn);
                    $groups = [];
                    $typeLabels = [
                        'standard' => 'Individual',
                        'participant' => 'Event Participant',
                        'group' => 'Group (3 People)'
                    ];

                    if ($attendeeSupport) {
                        $sql = "SELECT sr.id, sr.payment_proof, sr.payment_status, sr.status, sr.created_at,
                                       sr.registration_type, sr.ambassador_code, sr.total_amount,
                                       sa.id AS attendee_id, sa.person_index, sa.label AS attendee_label,
                                       sa.full_name AS attendee_name, sa.email AS attendee_email, sa.phone AS attendee_phone,
                                       sa.cnic AS attendee_cnic, sa.face_image AS attendee_face, sa.id_card_image AS attendee_card,
                                       sa.status AS attendee_status
                                FROM social_registrations sr
                                JOIN social_attendees sa ON sa.registration_id = sr.id
                                ORDER BY sr.created_at DESC, sa.person_index ASC";
                        $res = $conn->query($sql);
                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $regId = (int) $row['id'];
                                if (!isset($groups[$regId])) {
                                    $groups[$regId] = [
                                        'registration' => [
                                            'id' => $row['id'],
                                            'payment_proof' => $row['payment_proof'],
                                            'payment_status' => $row['payment_status'],
                                            'status' => $row['status'],
                                            'registration_type' => $row['registration_type'] ?? null,
                                            'ambassador_code' => $row['ambassador_code'] ?? null,
                                            'total_amount' => $row['total_amount'] ?? null
                                        ],
                                        'attendees' => []
                                    ];
                                }
                                $groups[$regId]['attendees'][] = [
                                    'id' => (int) $row['attendee_id'],
                                    'label' => $row['attendee_label'] ?: ('Person ' . $row['person_index']),
                                    'name' => $row['attendee_name'],
                                    'email' => $row['attendee_email'],
                                    'phone' => $row['attendee_phone'],
                                    'cnic' => $row['attendee_cnic'],
                                    'face' => $row['attendee_face'],
                                    'card' => $row['attendee_card'],
                                    'status' => $row['attendee_status'] ?? $row['status']
                                ];
                            }
                        }
                    } else {
                        $res = $conn->query("SELECT * FROM social_registrations ORDER BY created_at DESC");
                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $participants = [[
                                    'id' => null,
                                    'label' => 'Primary',
                                    'name' => $row['full_name'],
                                    'email' => $row['email'],
                                    'phone' => $row['phone'],
                                    'cnic' => $row['cnic'],
                                    'face' => $row['face_image'],
                                    'card' => $row['id_card_image'],
                                    'status' => $row['status']
                                ]];

                                if (!empty($row['participant2_name'])) {
                                    $participants[] = [
                                        'id' => null,
                                        'label' => 'Person 2',
                                        'name' => $row['participant2_name'],
                                        'email' => $row['participant2_email'],
                                        'phone' => $row['participant2_phone'],
                                        'cnic' => $row['participant2_cnic'],
                                        'face' => $row['participant2_face'],
                                        'card' => $row['participant2_card'],
                                        'status' => $row['status']
                                    ];
                                }

                                if (!empty($row['participant3_name'])) {
                                    $participants[] = [
                                        'id' => null,
                                        'label' => 'Person 3',
                                        'name' => $row['participant3_name'],
                                        'email' => $row['participant3_email'],
                                        'phone' => $row['participant3_phone'],
                                        'cnic' => $row['participant3_cnic'],
                                        'face' => $row['participant3_face'],
                                        'card' => $row['participant3_card'],
                                        'status' => $row['status']
                                    ];
                                }

                                $groups[$row['id']] = [
                                    'registration' => [
                                        'id' => $row['id'],
                                        'payment_proof' => $row['payment_proof'],
                                        'payment_status' => $row['payment_status'],
                                        'status' => $row['status'],
                                        'registration_type' => $row['registration_type'] ?? null,
                                        'ambassador_code' => $row['ambassador_code'] ?? null,
                                        'total_amount' => $row['total_amount'] ?? null
                                    ],
                                    'attendees' => $participants
                                ];
                            }
                        }
                    }

                    if (empty($groups)) {
                        echo "<tr><td colspan='6' class='text-center text-muted p-5'>No registrations found yet.</td></tr>";
                    } else {
                        foreach ($groups as $group) {
                            $row = $group['registration'];
                            $attendees = $group['attendees'];
                            $payStatus = $row['payment_status'] ?? 'pending';
                            ?>
                            <tr>
                                <td data-label="ID">#<?php echo $row['id']; ?></td>
                                <td data-label="Form Type">
                                    <?php
                                        $typeCode = $row['registration_type'] ?? '';
                                        $typeLabel = $typeLabels[$typeCode] ?? ($typeCode ? ucfirst($typeCode) : 'N/A');
                                        echo htmlspecialchars($typeLabel);
                                        $amountMap = [
                                            'standard' => 500,
                                            'participant' => 0,
                                            'group' => 1200
                                        ];
                                        $displayAmount = $amountMap[$typeCode] ?? ($row['total_amount'] ?? null);
                                        if ($displayAmount !== null && $displayAmount !== '') {
                                            echo '<div class="text-muted small">PKR ' . number_format((float) $displayAmount) . '</div>';
                                        }
                                    ?>
                                </td>
                                <td data-label="Ambassador Code">
                                    <?php if (!empty($row['ambassador_code'])): ?>
                                        <span class="badge-status" style="border-color:#4dabf7;color:#4dabf7;background:rgba(77,171,247,0.1);">
                                            <?php echo htmlspecialchars($row['ambassador_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not provided</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Participants">
                                    <?php foreach ($attendees as $person): 
                                        $personStatus = $person['status'] ?? $row['status'] ?? 'pending';
                                        ?>
                                        <div class="participant-block">
                                            <div class="participant-title"><?php echo htmlspecialchars($person['label']); ?></div>
                                            <div class="participant-meta">
                                                <strong style="color:#fff; font-size:0.95rem;">
                                                    <?php echo htmlspecialchars($person['name'] ?? ''); ?>
                                                </strong><br>
                                                <?php if (!empty($person['cnic'])): ?>
                                                    <span><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($person['cnic']); ?></span><br>
                                                <?php endif; ?>
                                                <?php if (!empty($person['phone'])): ?>
                                                    <span><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($person['phone']); ?></span><br>
                                                <?php endif; ?>
                                                <?php if (!empty($person['email'])): ?>
                                                    <span><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($person['email']); ?></span>
                                                <?php endif; ?>
                                                <span class="member-pill <?php echo $personStatus; ?>"><?php echo ucfirst($personStatus); ?></span>
                                            </div>
                                            <?php if ($attendeeSupport): ?>
                                                <div class="participant-actions">
                                                    <button class="btn-mini approve action-btn" data-id="<?php echo $row['id']; ?>" data-attendee="<?php echo $person['id']; ?>" data-status="approved">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn-mini reject action-btn" data-id="<?php echo $row['id']; ?>" data-attendee="<?php echo $person['id']; ?>" data-status="rejected">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td data-label="Photos / IDs">
                                    <div class="participant-photo-grid">
                                        <?php foreach ($attendees as $person): ?>
                                            <?php if (!empty($person['face'])): 
                                                $faceImgUrl = resolve_image_url($person['face'], '../');
                                            ?>
                                                <div class="participant-photo" title="<?php echo htmlspecialchars($person['name']); ?> - Photo">
                                                    <a href="<?php echo htmlspecialchars($faceImgUrl); ?>" target="_blank">
                                                        <img src="<?php echo htmlspecialchars($faceImgUrl); ?>" class="proof-thumb" style="border-radius:50%; border:2px solid #00FF94;">
                                                    </a>
                                                    <span><?php echo $person['label']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($person['card'])): 
                                                $cardImgUrl = resolve_image_url($person['card'], '../');
                                            ?>
                                                <div class="participant-photo" title="<?php echo htmlspecialchars($person['name']); ?> - ID Card">
                                                    <a href="<?php echo htmlspecialchars($cardImgUrl); ?>" target="_blank">
                                                        <img src="<?php echo htmlspecialchars($cardImgUrl); ?>" class="proof-thumb">
                                                    </a>
                                                    <span>ID</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td data-label="Payment Proof">
                                    <?php if (!empty($row['payment_proof'])): 
                                        $proofImgUrl = resolve_image_url($row['payment_proof'], '../');
                                    ?>
                                        <a href="<?php echo htmlspecialchars($proofImgUrl); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($proofImgUrl); ?>" class="proof-thumb">
                                        </a>
                                        <br>
                                        <span class="pay-badge pay-<?php echo $payStatus; ?>"><?php echo ucfirst($payStatus); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="badge-status badge-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end" data-label="Actions">
                                    <a href="edit_social_registration.php?id=<?php echo $row['id']; ?>" class="btn-icon" title="Edit registration" style="border:1px solid #4dabf7; color:#4dabf7;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($payStatus == 'submitted'): ?>
                                        <button class="btn-icon btn-pay confirm-pay-btn" data-id="<?php echo $row['id']; ?>" title="Confirm Payment">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn-icon btn-approve action-btn" data-id="<?php echo $row['id']; ?>" data-status="approved" title="Approve &amp; Send E-Pass">
                                        <i class="fas fa-check"></i>
                                    </button>

                                    <button class="btn-icon btn-reject action-btn" data-id="<?php echo $row['id']; ?>" data-status="rejected" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>

                                    <button class="btn-icon btn-delete delete-btn" data-id="<?php echo $row['id']; ?>" title="Delete Permanently">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // 0. TOGGLE REGISTRATION OPEN/CLOSE
    $('#toggle-social-btn').click(function() {
        var btn = $(this);
        var currentlyOpen = btn.data('open') === 1 || btn.data('open') === '1';
        var nextVal = currentlyOpen ? 0 : 1;
        var confirmMsg = currentlyOpen ? "Close social registrations?" : "Open social registrations?";
        if(!confirm(confirmMsg)) return;

        btn.prop('disabled', true).css('opacity', '0.6');
        $.post('toggle_social_registrations.php', { open: nextVal }, function(res) {
            alert(res.message);
            if(res.success) {
                location.reload();
            } else {
                btn.prop('disabled', false).css('opacity', '1');
            }
        }, 'json').fail(function(xhr) {
            alert('Error: ' + xhr.responseText);
            btn.prop('disabled', false).css('opacity', '1');
        });
    });

    // 0b. SAVE LIMIT
    $('#save-social-limit').click(function() {
        var btn = $(this);
        var limitVal = $('#social-limit-input').val();
        btn.prop('disabled', true).css('opacity', '0.6');
        $.post('toggle_social_registrations.php', { limit: limitVal }, function(res) {
            alert(res.message);
            if(res.success) {
                location.reload();
            } else {
                btn.prop('disabled', false).css('opacity', '1');
            }
        }, 'json').fail(function(xhr) {
            alert('Error: ' + xhr.responseText);
            btn.prop('disabled', false).css('opacity', '1');
        });
    });
    
    // 1. APPROVE / REJECT
    $('.action-btn').click(function() {
        var btn = $(this);
        var id = btn.data('id');
        var attendee = btn.data('attendee');
        var status = btn.data('status');
        
        if(!confirm("Confirm " + status.toUpperCase() + "? Email will be sent.")) return;

        // Disable button to prevent double click
        btn.prop('disabled', true).css('opacity', '0.5');

        var payload = { status: status };
        if (attendee) {
            payload.attendee_id = attendee;
        } else {
            payload.id = id;
        }

        $.post('update_social_status.php', payload, function(res) {
            alert(res.message);
            if(res.success) location.reload();
            else btn.prop('disabled', false).css('opacity', '1');
        }, 'json')
        .fail(function(xhr) {
            alert("Error: " + xhr.responseText);
            btn.prop('disabled', false).css('opacity', '1');
        });
    });

    // 2. CONFIRM PAYMENT
    $('.confirm-pay-btn').click(function() {
        var btn = $(this);
        if(!confirm("Mark this payment as CONFIRMED?")) return;

        $.post('update_social_payment.php', { id: btn.data('id') }, function(res) {
            if(res.success) {
                alert("Payment Confirmed!");
                location.reload();
            } else {
                alert("Error: " + res.message);
            }
        }, 'json');
    });

    // 3. DELETE
    $('.delete-btn').click(function() {
        if(!confirm("⚠️ WARNING: Delete this guest permanently?\nThis cannot be undone.")) return;
        
        $.post('delete_social_registration.php', { id: $(this).data('id') }, function(res) {
            if(res.success) {
                alert("Guest Deleted.");
                location.reload();
            } else {
                alert("Error: " + res.message);
            }
        }, 'json');
    });

    $('#vanish-social-btn').click(function() {
        var current = $(this).data('visible');
        $.post('toggle_social_registrations.php', { visible: current == 1 ? 0 : 1 }, function(res) {
            alert(res.message); location.reload();
        }, 'json');
    });

    // 4. TIER SWITCHBOARD LOGIC
    function updateIndPreview() {
        var ebOn = $('#switch_early_bird_active').is(':checked');
        var orig = parseInt($('#input_ind_orig').val()) || 0;
        var active = parseInt($('#input_ind_price').val()) || 0;

        if (ebOn && orig > active) {
            $('#preview-ind-price').html(
                '<span style="text-decoration: line-through; opacity: 0.6; color: #ff6a6a; margin-right: 8px; font-weight: 600;">PKR ' + orig.toLocaleString() + '</span>' +
                '<strong style="color: #00FF94; font-size: 1.15rem;">PKR ' + active.toLocaleString() + '</strong> ' +
                '<span class="badge bg-warning text-dark ms-2 fw-bold"><i class="fas fa-bolt me-1"></i>EARLY BIRD</span>'
            );
        } else {
            var finalPrice = ebOn ? active : orig;
            $('#preview-ind-price').html(
                '<strong style="color: #00FF94; font-size: 1.15rem;">PKR ' + finalPrice.toLocaleString() + '</strong> ' +
                '<span class="badge bg-secondary ms-2">STANDARD</span>'
            );
        }
    }

    // Toggle card styling on switch changes
    $('#switch_enable_individual').change(function() {
        $('#card-tier-individual').toggleClass('active', this.checked).toggleClass('disabled-tier', !this.checked);
    });

    $('#switch_enable_participant').change(function() {
        var price = parseInt($('#input_participant_price').val()) || 0;
        $('#card-tier-participant').toggleClass('active', this.checked).toggleClass('disabled-tier', !this.checked);
        $('#badge-participant-status').text(this.checked ? ('Visible on Form (PKR ' + price.toLocaleString() + ')') : 'Hidden (Single Entry Mode)')
            .toggleClass('bg-success', this.checked).toggleClass('bg-secondary', !this.checked);
    });

    $('#switch_enable_group').change(function() {
        var price = parseInt($('#input_group_price').val()) || 0;
        $('#card-tier-group').toggleClass('active', this.checked).toggleClass('disabled-tier', !this.checked);
        $('#badge-group-status').text(this.checked ? ('Visible on Form (PKR ' + price.toLocaleString() + ')') : 'Hidden (Single Entry Mode)')
            .toggleClass('bg-success', this.checked).toggleClass('bg-secondary', !this.checked);
    });

    $('#switch_early_bird_active, #input_ind_orig, #input_ind_price').on('input change', updateIndPreview);
    updateIndPreview();

    // Save All Tier & Pricing Settings
    $('#btn-save-tier-settings').click(function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        var payload = {
            save_all_tiers: 1,
            enable_individual: $('#switch_enable_individual').is(':checked') ? 1 : 0,
            enable_participant: $('#switch_enable_participant').is(':checked') ? 1 : 0,
            enable_group: $('#switch_enable_group').is(':checked') ? 1 : 0,
            early_bird_active: $('#switch_early_bird_active').is(':checked') ? 1 : 0,
            individual_original_price: $('#input_ind_orig').val(),
            individual_price: $('#input_ind_price').val(),
            participant_price: $('#input_participant_price').val(),
            group_price: $('#input_group_price').val()
        };

        $.post('toggle_social_registrations.php', payload, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save All Tier & Price Settings');
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json').fail(function(xhr) {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save All Tier & Price Settings');
            alert('Server error: ' + xhr.responseText);
        });
    });

});
</script>
<?php include 'footer.php'; ?>
