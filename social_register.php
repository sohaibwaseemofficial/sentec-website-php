<?php
// 1. START SESSION (Must be the very first line)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/image_utils.php';
require_once __DIR__ . '/social_attendees_helper.php';
require_once __DIR__ . '/social_registration_settings.php';

$socialOpen = social_registrations_open($conn);
$user_id = (int)$_SESSION['user_id'];

// Fetch active event label
$activeEventLabel = 'proxion_2026'; // fallback
$eventRes = $conn->query("SELECT title FROM events WHERE status = 'upcoming' ORDER BY event_date DESC LIMIT 1");
if ($eventRes && $eventRes->num_rows > 0) {
    $activeEventLabel = $eventRes->fetch_assoc()['title'];
}

// 2b. REGISTRATION WINDOW CHECK
if (!$socialOpen) {
    ?>
    <section style="padding-top: 140px; padding-bottom: 80px;">
        <div class="container">
            <div class="glass-panel p-4 p-md-5 text-center">
                <i class="fas fa-lock" style="font-size:3rem; color:#ff6a6a;"></i>
                <h2 class="text-white mt-3">We’re currently full</h2>
                <p class="text-muted mb-4">Thanks for the overwhelming interest! All available spots are booked right now. Please check back later for any openings.</p>
                <a href="dashboard" class="btn btn-outline-light">Back to Dashboard</a>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

// 3. CHECK EXISTING REGISTRATION FOR ACTIVE EVENT
$checkStmt = $conn->prepare("SELECT id FROM social_registrations WHERE user_id = ? AND event_label = ? LIMIT 1");
$checkStmt->bind_param("is", $user_id, $activeEventLabel);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if ($checkRes && $checkRes->num_rows > 0) {
    $checkStmt->close();
    echo "<script>alert('You have already registered for Social Night!'); window.location.href='dashboard';</script>";
    exit;
}
$checkStmt->close();

// 4. HANDLE FORM SUBMISSION
$msg = "";
if (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST') {
    $settings = social_registrations_get_settings($conn);
    $allowedTypes = [];
    if (!empty($settings['enable_individual'])) $allowedTypes[] = 'standard';
    if (!empty($settings['enable_participant'])) $allowedTypes[] = 'participant';
    if (!empty($settings['enable_group'])) $allowedTypes[] = 'group';
    if (empty($allowedTypes)) $allowedTypes = ['standard'];

    $requestedType = in_array($_POST['reg_type'] ?? '', ['standard', 'participant', 'group'], true) ? $_POST['reg_type'] : 'standard';
    $reg_type = in_array($requestedType, $allowedTypes, true) ? $requestedType : $allowedTypes[0];
    $amb_code = !empty($_POST['ambassador_code']) ? trim($_POST['ambassador_code']) : NULL;
    
    // Dynamic Effective Price from Admin Settings (honors discount active state)
    $amount = social_tier_effective_price($reg_type, $settings);

    // --- Cloudinary / WebP Upload Helper ---
    if (!function_exists('uploadSocialFile')) {
        function uploadSocialFile($file, $prefix = 'social', $uid = 0) {
            if (!isset($file['name']) || empty($file['name']) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return null;
            }

            $targetDir = __DIR__ . '/images/uploads/social_registrations/';
            $publicPrefix = 'images/uploads/social_registrations/';

            $res = save_image_as_webp($file, $targetDir, $publicPrefix);
            if (!empty($res['success']) && !empty($res['path'])) {
                return $res['path'];
            }
            return false;
        }
    }

    // Capture & Sanitize Basic Info
    $p1_name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
    $p1_cnic = mb_substr(trim($_POST['cnic'] ?? ''), 0, 50);
    $p1_email = mb_substr(trim($_POST['email'] ?? ''), 0, 100);
    $p1_phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
    
    // Upload Images via Cloudinary
    $p1_face = uploadSocialFile($_FILES['face_image'] ?? [], "face1", $user_id);
    $p1_card = uploadSocialFile($_FILES['id_card'] ?? [], "card1", $user_id);
    $pay_proof = isset($_FILES['payment_proof']) ? uploadSocialFile($_FILES['payment_proof'], "pay", $user_id) : null;

    // Group Member Variables
    $groupValid = true;
    if ($reg_type === 'group') {
        $p2_name = !empty($_POST['p2_name']) ? mb_substr(trim($_POST['p2_name']), 0, 100) : null;
        $p2_cnic = !empty($_POST['p2_cnic']) ? mb_substr(trim($_POST['p2_cnic']), 0, 50) : null;
        $p2_email = !empty($_POST['p2_email']) ? mb_substr(trim($_POST['p2_email']), 0, 100) : null;
        $p2_phone = !empty($_POST['p2_phone']) ? mb_substr(trim($_POST['p2_phone']), 0, 50) : null;
        $p2_face = isset($_FILES['p2_face']) ? uploadSocialFile($_FILES['p2_face'], "face2", $user_id) : null;
        $p2_card = isset($_FILES['p2_card']) ? uploadSocialFile($_FILES['p2_card'], "card2", $user_id) : null;

        $p3_name = !empty($_POST['p3_name']) ? mb_substr(trim($_POST['p3_name']), 0, 100) : null;
        $p3_cnic = !empty($_POST['p3_cnic']) ? mb_substr(trim($_POST['p3_cnic']), 0, 50) : null;
        $p3_email = !empty($_POST['p3_email']) ? mb_substr(trim($_POST['p3_email']), 0, 100) : null;
        $p3_phone = !empty($_POST['p3_phone']) ? mb_substr(trim($_POST['p3_phone']), 0, 50) : null;
        $p3_face = isset($_FILES['p3_face']) ? uploadSocialFile($_FILES['p3_face'], "face3", $user_id) : null;
        $p3_card = isset($_FILES['p3_card']) ? uploadSocialFile($_FILES['p3_card'], "card3", $user_id) : null;

        if (empty($p2_name) || empty($p2_cnic) || empty($p2_email) || empty($p2_phone) || !$p2_face || !$p2_card ||
            empty($p3_name) || empty($p3_cnic) || empty($p3_email) || empty($p3_phone) || !$p3_face || !$p3_card) {
            $groupValid = false;
            $msg = "<div class='alert alert-danger'>Group passes require complete information and photo uploads (face portrait & student ID/CNIC) for all 3 members.</div>";
        }
    } else {
        $p2_name = $p2_cnic = $p2_email = $p2_phone = $p2_face = $p2_card = null;
        $p3_name = $p3_cnic = $p3_email = $p3_phone = $p3_face = $p3_card = null;
    }

    // Build participant payload
    $memberPayload = [[
        'label' => 'Primary',
        'name' => $p1_name,
        'email' => $p1_email,
        'phone' => $p1_phone,
        'cnic' => $p1_cnic,
        'face' => $p1_face,
        'card' => $p1_card
    ]];
    if ($reg_type === 'group' && $groupValid) {
        if (!empty($p2_name)) {
            $memberPayload[] = [
                'label' => 'Person 2',
                'name' => $p2_name,
                'email' => $p2_email,
                'phone' => $p2_phone,
                'cnic' => $p2_cnic,
                'face' => $p2_face,
                'card' => $p2_card
            ];
        }
        if (!empty($p3_name)) {
            $memberPayload[] = [
                'label' => 'Person 3',
                'name' => $p3_name,
                'email' => $p3_email,
                'phone' => $p3_phone,
                'cnic' => $p3_cnic,
                'face' => $p3_face,
                'card' => $p3_card
            ];
        }
    }

    // Validate Required Files (Payment proof only mandatory if amount > 0)
    $requiresPayment = ($amount > 0);
    $payProofOk = $requiresPayment ? (!empty($pay_proof) && $pay_proof !== false) : true;
    if (!$requiresPayment && empty($pay_proof)) {
        $pay_proof = 'free_participant_pass';
    }

    if ($p1_face && $p1_card && $payProofOk && $groupValid && empty($msg)) {
        // Insert into DB
        $sql = "INSERT INTO social_registrations 
        (user_id, full_name, email, phone, cnic, face_image, id_card_image, payment_proof, payment_status, 
         registration_type, ambassador_code, total_amount,
         participant2_name, participant2_email, participant2_phone, participant2_cnic, participant2_face, participant2_card,
         participant3_name, participant3_email, participant3_phone, participant3_cnic, participant3_face, participant3_card,
         event_label) 
        VALUES (?,?,?,?,?,?,?,?, 'submitted', ?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?)";

        $stmt = $conn->prepare($sql);
        $types = 'i' . str_repeat('s', 9) . 'i' . str_repeat('s', 13); // 2 integers, 22 strings
        $stmt->bind_param($types, 
            $user_id, $p1_name, $p1_email, $p1_phone, $p1_cnic, $p1_face, $p1_card, $pay_proof, 
            $reg_type, $amb_code, $amount,
            $p2_name, $p2_email, $p2_phone, $p2_cnic, $p2_face, $p2_card,
            $p3_name, $p3_email, $p3_phone, $p3_cnic, $p3_face, $p3_card,
            $activeEventLabel
        );

        if ($stmt->execute()) {
            $registrationId = $conn->insert_id;
            if (!empty($memberPayload)) {
                social_attendees_sync($conn, $registrationId, $memberPayload, $pay_proof, 'submitted');
            }
            echo "<script>alert('Registration Submitted Successfully!'); window.location.href='dashboard';</script>";
            exit;
        } else {
            $msg = "<div class='alert alert-danger'>DB Error: " . $conn->error . "</div>";
        }
    } else if (empty($msg)) {
        $msg = "<div class='alert alert-danger'>Upload failed. Please ensure face photo, ID card, and " . ($requiresPayment ? "payment receipt (JPG, PNG, or PDF)" : "required photos") . " are provided in valid formats.</div>";
    }
}
?>

<?php
$p1_prefill_name = $_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? '';
$p1_prefill_email = $_SESSION['user']['email'] ?? '';
$p1_prefill_phone = $_SESSION['user']['phone'] ?? '';
?>

<style>
    .social-reg-wrapper {
        padding: 40px 20px 80px;
        max-width: 900px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }
    .reg-wizard-card {
        background: rgba(15, 20, 22, 0.75);
        border: 1px solid var(--line);
        padding: 40px 36px;
        position: relative;
        box-shadow: 0 20px 80px rgba(0, 0, 0, 0.5);
    }
    .reg-wizard-card::before {
        content: '';
        position: absolute;
        top: -1px;
        left: -1px;
        width: 14px;
        height: 14px;
        border-top: 2px solid var(--orange);
        border-left: 2px solid var(--orange);
    }
    .tier-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }
    @media (max-width: 768px) {
        .tier-card-grid {
            grid-template-columns: 1fr;
        }
        .reg-wizard-card {
            padding: 24px 18px;
        }
    }
    .tier-track-card {
        padding: 22px 20px;
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.6);
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        position: relative;
    }
    .tier-track-card:hover {
        border-color: rgba(241, 90, 36, 0.5);
        background: rgba(241, 90, 36, 0.04);
    }
    .tier-track-card.selected {
        border-color: var(--orange) !important;
        background: rgba(241, 90, 36, 0.1) !important;
        box-shadow: 0 0 25px rgba(241, 90, 36, 0.12);
    }
    .tier-track-card .tier-title {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--paper);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .tier-track-card.selected .tier-title {
        color: var(--orange);
    }
    .tier-track-card .tier-price {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 18px;
        font-weight: 700;
        color: var(--paper);
        display: flex;
        align-items: center;
    }
    .tier-track-card.selected .tier-price {
        color: var(--orange);
    }
    .tier-price-strike {
        text-decoration: line-through;
        opacity: 0.55;
        font-size: 0.8em;
        margin-right: 8px;
        color: #ff5555;
        font-weight: 500;
    }
    .tier-price-current {
        font-weight: 700;
    }
    .early-bird-tag {
        display: inline-block;
        background: rgba(241, 90, 36, 0.18);
        border: 1px solid var(--orange);
        color: var(--orange);
        font-size: 9px;
        font-family: 'IBM Plex Mono', monospace;
        font-weight: 700;
        letter-spacing: 0.08em;
        padding: 2px 6px;
        border-radius: 3px;
        text-transform: uppercase;
        vertical-align: middle;
    }
    .tier-track-card .tier-badge {
        font-size: 11px;
        color: var(--muted);
        margin-top: 4px;
        font-family: 'Space Grotesk', sans-serif;
    }
    .signal-label {
        display: block;
        color: var(--muted);
        font: 600 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.14em;
        margin-bottom: 6px;
        text-transform: uppercase;
    }
    .signal-input {
        width: 100%;
        border: 0 !important;
        border-bottom: 1px solid var(--line) !important;
        background: transparent !important;
        color: var(--paper) !important;
        padding: 12px 0 !important;
        font-family: 'Space Grotesk', sans-serif !important;
        font-size: 15px !important;
        outline: none !important;
        border-radius: 0 !important;
        transition: border-color 0.2s ease;
        box-shadow: none !important;
    }
    .signal-input:focus {
        border-bottom-color: var(--orange) !important;
        background: transparent !important;
        color: #fff !important;
        box-shadow: none !important;
    }
    .signal-input::placeholder {
        color: rgba(255, 255, 255, 0.25) !important;
    }
    .participant-section-card {
        background: rgba(15, 20, 22, 0.5);
        border: 1px solid var(--line);
        border-radius: 4px;
        padding: 24px;
        margin-bottom: 24px;
        position: relative;
    }
    .participant-section-card::before {
        content: '';
        position: absolute;
        top: -1px;
        left: -1px;
        width: 12px;
        height: 12px;
        border-top: 2px solid var(--orange);
        border-left: 2px solid var(--orange);
    }
    .participant-title {
        color: var(--orange);
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--line);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .file-upload-block {
        background: #0a0e11;
        border: 1px dashed var(--line);
        padding: 14px 16px;
        border-radius: 4px;
        transition: border-color 0.2s ease;
    }
    .file-upload-block:hover {
        border-color: rgba(241, 90, 36, 0.5);
    }
    .file-upload-block input[type="file"] {
        background: transparent;
        color: var(--muted);
        font-size: 12px;
        font-family: 'IBM Plex Mono', monospace;
        width: 100%;
        outline: none;
    }
    .file-upload-block input[type="file"]::-webkit-file-upload-button {
        background: #151d21;
        color: var(--paper);
        border: 1px solid var(--line);
        padding: 6px 14px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-right: 12px;
    }
    .file-upload-block input[type="file"]::-webkit-file-upload-button:hover {
        border-color: var(--orange);
        color: var(--orange);
    }
    .payment-details-card {
        background: rgba(15, 20, 22, 0.85);
        border: 1px solid var(--orange);
        border-radius: 6px;
        padding: 24px;
        margin-bottom: 28px;
    }
    .payment-details-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }
    .payment-details-header i {
        color: var(--orange);
        font-size: 18px;
    }
    .payment-details-header h4 {
        margin: 0;
        font-size: 17px;
        color: var(--paper);
        font-family: 'Space Grotesk', sans-serif;
    }
    .payment-meta-grid {
        background: #080b0d;
        border: 1px solid var(--line);
        padding: 18px;
        border-radius: 4px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 12px;
        line-height: 2;
        color: var(--paper);
    }
    .btn-submit-pass {
        background: var(--orange);
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        padding: 16px 28px;
        border: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        transition: opacity 0.2s ease;
    }
    .btn-submit-pass:hover {
        opacity: 0.9;
    }
</style>

<section class="secondary-hero">
    <div class="secondary-hero-grid">
        <div>
            <div class="eyebrow"><i></i> SOCIAL NIGHT // ACCESS PASS</div>
            <h1>Secure your <em>pass.</em></h1>
            <p class="secondary-hero-lead">Join the official SENTEC social night, networking reception, and evening banquet at NED University.</p>
        </div>
        <div class="secondary-hero-index">
            <div>PASS.GATE // ACTIVE</div>
            <div style="color:var(--text-dim); margin-top:4px;">ENTRY VERIFICATION REQUIRED</div>
        </div>
    </div>
</section>

<div class="social-reg-wrapper">
    <div class="reg-wizard-card">
        <?php echo $msg; ?>

        <form method="POST" enctype="multipart/form-data" id="socialForm" action="">
            <span style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block; margin-bottom: 8px;">
                PASS TIER SELECTION //
            </span>
            <h2 style="margin: 0 0 20px; font-size: 24px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                Choose your entrance pass
            </h2>

            <?php
            $settings = social_registrations_get_settings($conn);
            $enableInd = !empty($settings['enable_individual']);
            $enablePart = !empty($settings['enable_participant']);
            $enableGrp = !empty($settings['enable_group']);

            // Fallback so at least individual is available if all disabled
            if (!$enableInd && !$enablePart && !$enableGrp) {
                $enableInd = true;
            }

            $defaultType = $enableInd ? 'standard' : ($enablePart ? 'participant' : 'group');
            $defaultAmount = social_tier_effective_price($defaultType, $settings);

            $indOrig = (int)($settings['individual_original_price'] ?? 700);
            $indDisc = (int)($settings['individual_price'] ?? 500);
            $indActive = !empty($settings['early_bird_active']);

            $partOrig = (int)($settings['participant_original_price'] ?? 0);
            $partDisc = (int)($settings['participant_price'] ?? 0);
            $partActive = !empty($settings['participant_discount_active']);

            $grpOrig = (int)($settings['group_original_price'] ?? 1500);
            $grpDisc = (int)($settings['group_price'] ?? 1200);
            $grpActive = !empty($settings['group_discount_active']);
            ?>

            <div class="tier-card-grid">
                <?php if ($enableInd): ?>
                    <div class="tier-track-card <?php echo $defaultType === 'standard' ? 'selected' : ''; ?>" onclick="selectType('standard', this)" id="btn-standard">
                        <div class="tier-title">
                            Individual
                            <?php if ($indActive && $indOrig > $indDisc): ?>
                                <span class="early-bird-tag"><i class="fas fa-bolt"></i> EARLY BIRD</span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-price">
                            <?php if ($indActive && $indOrig > $indDisc): ?>
                                <span class="tier-price-strike">PKR <?php echo number_format($indOrig); ?></span>
                                <span class="tier-price-current">PKR <?php echo number_format($indDisc); ?></span>
                            <?php else: ?>
                                <span class="tier-price-current">PKR <?php echo number_format($indOrig > 0 ? $indOrig : $indDisc); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-badge">
                            <?php echo ($indActive && $indOrig > $indDisc) ? 'Early bird discount pass' : 'Single attendee entry pass'; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($enablePart): ?>
                    <div class="tier-track-card <?php echo $defaultType === 'participant' ? 'selected' : ''; ?>" onclick="selectType('participant', this)" id="btn-participant">
                        <div class="tier-title">
                            Event Participant
                            <?php if ($partActive && $partOrig > $partDisc): ?>
                                <span class="early-bird-tag"><i class="fas fa-tag"></i> DISCOUNT</span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-price">
                            <?php if ($partActive && $partOrig > $partDisc): ?>
                                <span class="tier-price-strike">PKR <?php echo number_format($partOrig); ?></span>
                                <span class="tier-price-current">PKR <?php echo number_format($partDisc); ?></span>
                            <?php else: ?>
                                <span class="tier-price-current">PKR <?php echo number_format($partOrig > 0 ? $partOrig : $partDisc); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-badge">Subsidized / arena attendees</div>
                    </div>
                <?php endif; ?>

                <?php if ($enableGrp): ?>
                    <div class="tier-track-card <?php echo $defaultType === 'group' ? 'selected' : ''; ?>" onclick="selectType('group', this)" id="btn-group">
                        <div class="tier-title">
                            Group (3 People)
                            <?php if ($grpActive && $grpOrig > $grpDisc): ?>
                                <span class="early-bird-tag"><i class="fas fa-tag"></i> DISCOUNT</span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-price">
                            <?php if ($grpActive && $grpOrig > $grpDisc): ?>
                                <span class="tier-price-strike">PKR <?php echo number_format($grpOrig); ?></span>
                                <span class="tier-price-current">PKR <?php echo number_format($grpDisc); ?></span>
                            <?php else: ?>
                                <span class="tier-price-current">PKR <?php echo number_format($grpOrig); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="tier-badge">Package bundle for 3 guests</div>
                    </div>
                <?php endif; ?>
            </div>

            <input type="hidden" name="reg_type" id="reg_type" value="<?php echo htmlspecialchars($defaultType); ?>">

            <div style="margin-bottom: 28px;">
                <label class="signal-label" for="ambCodeInput">BRAND AMBASSADOR CODE (OPTIONAL)</label>
                <input type="text" id="ambCodeInput" name="ambassador_code" class="signal-input" placeholder="Enter referral code if applicable" style="text-transform: uppercase;">
            </div>

            <!-- Person 1 (Primary Attendee) -->
            <div class="participant-section-card">
                <div class="participant-title">
                    <i class="fas fa-user"></i> Person 1 (Primary Attendee)
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="signal-label" for="p1_name">FULL NAME *</label>
                        <input type="text" id="p1_name" name="name" class="signal-input" required value="<?php echo htmlspecialchars($p1_prefill_name); ?>" placeholder="e.g. Sohaib Waseem">
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label" for="p1_cnic">CNIC / FORM-B (13 DIGITS) *</label>
                        <input type="text" id="p1_cnic" name="cnic" class="signal-input" required placeholder="42101-xxxxxxx-x">
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label" for="p1_email">EMAIL ADDRESS *</label>
                        <input type="email" id="p1_email" name="email" class="signal-input" required value="<?php echo htmlspecialchars($p1_prefill_email); ?>" placeholder="name@domain.com">
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label" for="p1_phone">PHONE / WHATSAPP *</label>
                        <input type="tel" id="p1_phone" name="phone" class="signal-input" required value="<?php echo htmlspecialchars($p1_prefill_phone); ?>" placeholder="03xxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <div class="file-upload-block">
                            <label class="signal-label" style="margin-bottom: 8px;">
                                <i class="fas fa-camera text-orange-500 me-1"></i> FACE PHOTO (PORTRAIT) *
                            </label>
                            <input type="file" name="face_image" accept="image/*" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="file-upload-block">
                            <label class="signal-label" style="margin-bottom: 8px;">
                                <i class="fas fa-id-card text-orange-500 me-1"></i> STUDENT ID / CNIC CARD *
                            </label>
                            <input type="file" name="id_card" accept="image/*" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Members (Person 2 & Person 3) -->
            <div id="group-fields" style="display:none;">
                <!-- Person 2 -->
                <div class="participant-section-card">
                    <div class="participant-title">
                        <i class="fas fa-user-friends"></i> Person 2 (Group Member)
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="signal-label">FULL NAME *</label>
                            <input type="text" name="p2_name" class="signal-input group-req" placeholder="Second attendee name">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">CNIC / FORM-B *</label>
                            <input type="text" name="p2_cnic" class="signal-input group-req" placeholder="42101-xxxxxxx-x">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">EMAIL ADDRESS *</label>
                            <input type="email" name="p2_email" class="signal-input group-req" placeholder="member2@domain.com">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">PHONE / WHATSAPP *</label>
                            <input type="tel" name="p2_phone" class="signal-input group-req" placeholder="03xxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <div class="file-upload-block">
                                <label class="signal-label" style="margin-bottom: 8px;">
                                    <i class="fas fa-camera text-orange-500 me-1"></i> FACE PHOTO *
                                </label>
                                <input type="file" name="p2_face" class="group-req" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="file-upload-block">
                                <label class="signal-label" style="margin-bottom: 8px;">
                                    <i class="fas fa-id-card text-orange-500 me-1"></i> STUDENT ID / CNIC *
                                </label>
                                <input type="file" name="p2_card" class="group-req" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Person 3 -->
                <div class="participant-section-card">
                    <div class="participant-title">
                        <i class="fas fa-user-friends"></i> Person 3 (Group Member)
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="signal-label">FULL NAME *</label>
                            <input type="text" name="p3_name" class="signal-input group-req" placeholder="Third attendee name">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">CNIC / FORM-B *</label>
                            <input type="text" name="p3_cnic" class="signal-input group-req" placeholder="42101-xxxxxxx-x">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">EMAIL ADDRESS *</label>
                            <input type="email" name="p3_email" class="signal-input group-req" placeholder="member3@domain.com">
                        </div>
                        <div class="col-md-6">
                            <label class="signal-label">PHONE / WHATSAPP *</label>
                            <input type="tel" name="p3_phone" class="signal-input group-req" placeholder="03xxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <div class="file-upload-block">
                                <label class="signal-label" style="margin-bottom: 8px;">
                                    <i class="fas fa-camera text-orange-500 me-1"></i> FACE PHOTO *
                                </label>
                                <input type="file" name="p3_face" class="group-req" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="file-upload-block">
                                <label class="signal-label" style="margin-bottom: 8px;">
                                    <i class="fas fa-id-card text-orange-500 me-1"></i> STUDENT ID / CNIC *
                                </label>
                                <input type="file" name="p3_card" class="group-req" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Details Card -->
            <div class="payment-details-card">
                <div class="payment-details-header">
                    <i class="fas fa-wallet"></i>
                    <h4>Payment Details</h4>
                </div>
                <div class="payment-meta-grid">
                    <div><span style="color: var(--muted);">BANK:</span> <strong style="color: #fff;">Habib Metropolitan Bank Limited</strong></div>
                    <div><span style="color: var(--muted);">TITLE:</span> <strong style="color: #fff;">NEDUET CONTROLLER STUDENT AFFAIRS</strong></div>
                    <div><span style="color: var(--muted);">ACCOUNT:</span> <strong style="color: #fff;">6-99-72-29314-714-262131</strong></div>
                    <div><span style="color: var(--muted);">IBAN:</span> <strong style="color: #ccc;">PK73MPBL9972477140262131</strong></div>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--muted); font-size: 11px;">TOTAL PAYABLE:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php
                            $defaultOrig = ($defaultType === 'standard') ? $indOrig : (($defaultType === 'participant') ? $partOrig : $grpOrig);
                            $defaultDiscActive = ($defaultType === 'standard') ? $indActive : (($defaultType === 'participant') ? $partActive : $grpActive);
                            $showDefaultStrike = ($defaultDiscActive && $defaultOrig > $defaultAmount);
                            ?>
                            <span id="display-amount-strike" class="tier-price-strike" style="font-size: 14px; <?php echo $showDefaultStrike ? '' : 'display: none;'; ?>">PKR <?php echo number_format($defaultOrig); ?></span>
                            <strong id="display-amount" style="color: var(--orange); font-size: 20px;">PKR <?php echo number_format($defaultAmount); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Payment Proof -->
            <div id="payment-proof-group" style="margin-bottom: 30px;">
                <label class="signal-label" id="payment-label">UPLOAD PAYMENT PROOF *</label>
                <div class="file-upload-block">
                    <input type="file" id="payment-input" name="payment_proof" accept="image/*,application/pdf" required>
                </div>
                <small style="color: var(--muted); font-size: 11px; font-family: 'IBM Plex Mono', monospace; display: block; margin-top: 6px;">SUPPORTED FORMATS: JPG, PNG, WEBP, PDF</small>
            </div>

            <button type="submit" id="socialSubmitBtn" class="btn-submit-pass">
                <span id="socialSubmitText">SUBMIT REGISTRATION // CONFIRM PASS</span>
                <span>&rarr;</span>
            </button>
        </form>
    </div>
</div>

<script src="js/sentec-compressor.js"></script>
<script>
    const tierDiscountActive = {
        standard: <?php echo !empty($settings['early_bird_active']) ? 'true' : 'false'; ?>,
        participant: <?php echo !empty($settings['participant_discount_active']) ? 'true' : 'false'; ?>,
        group: <?php echo !empty($settings['group_discount_active']) ? 'true' : 'false'; ?>
    };
    const tierOriginalPrices = {
        standard: <?php echo (int)($settings['individual_original_price'] ?? 700); ?>,
        participant: <?php echo (int)($settings['participant_original_price'] ?? 0); ?>,
        group: <?php echo (int)($settings['group_original_price'] ?? 1500); ?>
    };
    const tierDiscountPrices = {
        standard: <?php echo (int)($settings['individual_price'] ?? 500); ?>,
        participant: <?php echo (int)($settings['participant_price'] ?? 0); ?>,
        group: <?php echo (int)($settings['group_price'] ?? 1200); ?>
    };

    function getTierEffectivePrice(type) {
        const isDisc = tierDiscountActive[type];
        const orig = tierOriginalPrices[type];
        const disc = tierDiscountPrices[type];
        if (isDisc && orig > disc) {
            return disc;
        }
        return (orig > 0) ? orig : disc;
    }

    function selectType(type, element) {
        document.querySelectorAll('.tier-track-card').forEach(b => b.classList.remove('selected'));
        if (element) {
            element.classList.add('selected');
        } else {
            const el = document.getElementById('btn-' + type);
            if (el) el.classList.add('selected');
        }
        document.getElementById('reg_type').value = type;

        const amount = getTierEffectivePrice(type);
        document.getElementById('display-amount').innerHTML = 'PKR ' + amount;

        const strikeEl = document.getElementById('display-amount-strike');
        if (strikeEl) {
            const isDisc = tierDiscountActive[type];
            const orig = tierOriginalPrices[type];
            if (isDisc && orig > amount) {
                strikeEl.textContent = 'PKR ' + orig;
                strikeEl.style.display = 'inline';
            } else {
                strikeEl.style.display = 'none';
            }
        }

        const groupDiv = document.getElementById('group-fields');
        const groupInputs = document.querySelectorAll('.group-req');
        if (type === 'group') {
            if (groupDiv) groupDiv.style.display = 'block';
            groupInputs.forEach(i => i.setAttribute('required', 'true'));
        } else {
            if (groupDiv) groupDiv.style.display = 'none';
            groupInputs.forEach(i => i.removeAttribute('required'));
        }

        // Toggle payment proof requirement for free participant pass
        const payGroup = document.getElementById('payment-proof-group');
        const payInput = document.getElementById('payment-input');
        if (type === 'participant' && amount === 0) {
            if (payInput) payInput.removeAttribute('required');
            if (payGroup) payGroup.style.display = 'none';
        } else {
            if (payInput) payInput.setAttribute('required', 'true');
            if (payGroup) payGroup.style.display = 'block';
        }
    }

    // Initialize for active tier
    selectType('<?php echo $defaultType; ?>', document.getElementById('btn-<?php echo $defaultType; ?>'));

    document.getElementById('socialForm').addEventListener('submit', async function(e) {
        const btn = document.getElementById('socialSubmitBtn');
        const btnText = document.getElementById('socialSubmitText');
        if (btn.dataset.ready === 'true') return;

        e.preventDefault();
        btn.disabled = true;
        btnText.textContent = "OPTIMIZING IMAGES...";

        try {
            const fileInputs = this.querySelectorAll('input[type="file"]');
            for (let i = 0; i < fileInputs.length; i++) {
                const inp = fileInputs[i];
                if (inp.files && inp.files[0] && window.sentecCompressFile) {
                    const origFile = inp.files[0];
                    const isPdf = (origFile.type && origFile.type.toLowerCase().includes('pdf')) || (origFile.name && origFile.name.toLowerCase().endsWith('.pdf'));
                    if (isPdf) continue;

                    try {
                        const optimized = await window.sentecCompressFile(origFile, {
                            maxWidthOrHeight: 1600,
                            quality: 0.82
                        });
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(optimized);
                            inp.files = dt.files;
                        } catch (dtErr) {
                            // DataTransfer fallback for older browsers
                        }
                    } catch (err) {
                        console.warn('Compress fallback for', inp.name, err);
                    }
                }
            }

            btnText.textContent = "TRANSMITTING REGISTRATION...";
            btn.dataset.ready = 'true';
            btn.disabled = false;
            this.submit();
        } catch (submitErr) {
            console.error('Submission pre-process error:', submitErr);
            btn.dataset.ready = 'true';
            btn.disabled = false;
            btnText.textContent = "TRANSMITTING REGISTRATION...";
            this.submit();
        }
    });

    // Session keep-alive heartbeat every 4 minutes
    setInterval(function() {
        fetch('session_ping.php', { credentials: 'same-origin' }).catch(function() {});
    }, 4 * 60 * 1000);
</script>

<?php include 'footer.php'; ?>
