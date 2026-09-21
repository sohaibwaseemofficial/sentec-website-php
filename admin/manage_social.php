<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';
require_once __DIR__ . '/../social_registration_settings.php';

$socialOpen = social_registrations_open($conn);
$socialLimit = social_registrations_limit();
$socialCount = social_registrations_count($conn);
?>

<style>
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
                                            <?php if (!empty($person['face'])): ?>
                                                <div class="participant-photo" title="<?php echo htmlspecialchars($person['name']); ?> - Photo">
                                                    <a href="../<?php echo $person['face']; ?>" target="_blank">
                                                        <img src="../<?php echo $person['face']; ?>" class="proof-thumb" style="border-radius:50%; border:2px solid #00FF94;">
                                                    </a>
                                                    <span><?php echo $person['label']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($person['card'])): ?>
                                                <div class="participant-photo" title="<?php echo htmlspecialchars($person['name']); ?> - ID Card">
                                                    <a href="../<?php echo $person['card']; ?>" target="_blank">
                                                        <img src="../<?php echo $person['card']; ?>" class="proof-thumb">
                                                    </a>
                                                    <span>ID</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td data-label="Payment Proof">
                                    <?php if (!empty($row['payment_proof'])): ?>
                                        <a href="../<?php echo $row['payment_proof']; ?>" target="_blank">
                                            <img src="../<?php echo $row['payment_proof']; ?>" class="proof-thumb">
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

});
</script>
<?php include 'footer.php'; ?>
