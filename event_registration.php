<?php
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. BACKEND LOGIC
require_once __DIR__ . '/image_utils.php';
require_once __DIR__ . '/event_attendees_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------------------------------------------------------
    // A. INITIAL SERVER SETTINGS
    // ---------------------------------------------------------
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('memory_limit', '256M');
    ini_set('max_execution_time', 300);

    // Clean any prior output buffer to ensure pure JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => 'error', 'success' => false, 'message' => 'Unknown error'];

    // Prevent PHP from failing silently when post_max_size is exceeded
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Total file upload size exceeded the server limit. Please ensure each image is under 800KB and try again.'
        ]);
        exit;
    }

    try {
        if (!file_exists('db_connection.php')) throw new Exception("Database file missing.");
        include 'db_connection.php';

        // Helper functions
        if (!function_exists('processFile')) {
            function processFile($fileArray, $inputName, $targetDir, $publicPrefix) {
                if (!isset($fileArray[$inputName]) || $fileArray[$inputName]['error'] !== UPLOAD_ERR_OK) return '';
                $result = save_image_as_webp($fileArray[$inputName], $targetDir, $publicPrefix);
                if ($result['success']) return $result['path'];
                throw new Exception($result['error'] ?? 'Failed to save file.');
            }
        }

        if (!function_exists('getVal')) {
            function getVal($key) { return !empty($_POST[$key]) ? trim($_POST[$key]) : ''; }
        }

        // Fetch active event label
        $activeEventLabel = '';
        $eventRes = $conn->query("SELECT title FROM events WHERE status = 'upcoming' ORDER BY event_date DESC LIMIT 1");
        if ($eventRes && $eventRes->num_rows > 0) {
            $activeEventLabel = $eventRes->fetch_assoc()['title'];
        } else {
            $activeEventLabel = 'proxion_2026'; // fallback
        }

        // Setup upload directories
        $targetDir = __DIR__ . '/images/uploads/event_registrations/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $publicPrefix = 'images/uploads/event_registrations/';

        // ---------------------------------------------------------
        // B. CORE DATA COLLECTION (MUST HAPPEN BEFORE VALIDATION)
        // ---------------------------------------------------------
        $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
        $user_id     = (int)getVal('user_id');
        if ($user_id <= 0 && $sessionUserId > 0) {
            $user_id = $sessionUserId;
        }
        if ($user_id <= 0) {
            throw new Exception("Authentication Error: Missing or invalid user ID. Please log in again.");
        }
        
        $institution = getVal('institutionType');
        $teamName    = getVal('teamName');
        $module      = getVal('moduleSelection');
        $brandCode   = getVal('brand_ambassador_code');
        
        // Process fees screenshot if provided
        $feesImg = isset($_FILES['fees_screenshot']) ? processFile($_FILES, 'fees_screenshot', $targetDir, $publicPrefix) : '';
        if (empty($feesImg)) {
            $feesImg = null;
        }
        $paymentStatus = !empty($feesImg) ? 'submitted' : 'pending';

        // Populate participant array
        $p = [];
        for ($i = 1; $i <= 6; $i++) {
            $p[$i] = [
                'name'    => getVal("participant{$i}_name"),
                'contact' => getVal("participant{$i}_contact"),
                'email'   => getVal("participant{$i}_email"),
                'cnic'    => getVal("participant{$i}_cnic"),
                'roll'    => getVal("participant{$i}_roll_number"),
                // Only attempt to process files if they exist in the request
                'face'    => isset($_FILES["participant{$i}_face_image"]) ? processFile($_FILES, "participant{$i}_face_image", $targetDir, $publicPrefix) : null,
                'card'    => isset($_FILES["participant{$i}_id_card"]) ? processFile($_FILES, "participant{$i}_id_card", $targetDir, $publicPrefix) : null
            ];
        }

        // ---------------------------------------------------------
        // C. DYNAMIC VALIDATION LOGIC
        // ---------------------------------------------------------
        $serverLimits = [
            "Line Following Robot (LFR)" => ["min" => 2, "max" => 4, "price" => "PKR 1,200"],
            "Circuit Designing Competition" => ["min" => 2, "max" => 4, "price" => "PKR 1,200"],
            "CYBER WAR ROOM" => ["min" => 3, "max" => 4, "price" => "PKR 1,400"],
            "RAG CHATBOT BUILDER" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "AGENT SPRINT: LIVE GMAIL AUTOMATION" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "AI COURT: FAKE OR REAL" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "BREAK THE RULES" => ["min" => 1, "max" => 1, "price" => "PKR 1,000"],
            "PitchFest" => ["min" => 1, "max" => 4, "price" => "PKR 500"],
            "AI DEBATE COLOSSEUM" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "Web Forces" => ["min" => 3, "max" => 4, "price" => "PKR 1,400"],
            "Reactor Zero" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "Fault Line" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],

            // Legacy backward compatibility entries
            "Code Rush" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Algo Masters" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Race with Code" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Design Sprint" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "App Innovate" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Query Quest" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Maths Clash" => ["min" => 1, "max" => 1, "price" => "PKR 500"],
            "Bug Busters" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Web Wizards" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Blind Coding" => ["min" => 1, "max" => 1, "price" => "PKR 500"],
            "Valorant" => ["min" => 5, "max" => 5, "price" => "PKR 2,000"],
            "CS 2" => ["min" => 5, "max" => 5, "price" => "PKR 2,000"],
            "PUBG Mobile" => ["min" => 4, "max" => 4, "price" => "PKR 1,500"],
            "COD Mobile" => ["min" => 5, "max" => 5, "price" => "PKR 2,000"],
            "Pseudocode Builder" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "Emoji Algorithm" => ["min" => 3, "max" => 4, "price" => "PKR 1,200"],
            "AI Disaster Aid Planner" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"],
            "AI Energy Saver Dashboard" => ["min" => 2, "max" => 3, "price" => "PKR 1,200"]
        ];

        // Require payment screenshot
        if (empty($feesImg)) {
            throw new Exception("Please upload the registration fee payment receipt / transaction screenshot.");
        }

        if (isset($serverLimits[$module])) {
            $minRequired = $serverLimits[$module]['min'];
            $maxAllowed  = $serverLimits[$module]['max'];

            // Validate required participants
            for ($i = 1; $i <= $minRequired; $i++) {
                $label = ($minRequired === 1 && $maxAllowed === 1) ? 'Solo Participant' : ($i === 1 ? 'Team Leader' : 'Participant 0' . $i);
                if (empty($p[$i]['name'])) throw new Exception("$label (Full Name) is required for $module.");
                if (empty($p[$i]['contact'])) throw new Exception("$label (Contact) is required.");
                if (empty($p[$i]['email'])) throw new Exception("$label (Email) is required.");
                if (empty($p[$i]['cnic'])) throw new Exception("$label (CNIC) is required.");
                if (empty($p[$i]['roll'])) throw new Exception("$label (Roll Number) is required.");
                if (empty($p[$i]['face'])) throw new Exception("Face Photograph is required for $label.");
                if (empty($p[$i]['card'])) throw new Exception("Student ID Card Photo is required for $label.");
            }

            // Validate optional participants: if any field is filled, all fields and photos must be complete
            for ($i = $minRequired + 1; $i <= $maxAllowed; $i++) {
                $hasAny = !empty($p[$i]['name']) || !empty($p[$i]['contact']) || !empty($p[$i]['email']) || 
                          !empty($p[$i]['cnic']) || !empty($p[$i]['roll']) || !empty($p[$i]['face']) || !empty($p[$i]['card']);
                if ($hasAny) {
                    $label = 'Participant 0' . $i;
                    if (empty($p[$i]['name'])) throw new Exception("$label (Full Name) is required (or clear all fields for this member).");
                    if (empty($p[$i]['contact'])) throw new Exception("$label (Contact) is required.");
                    if (empty($p[$i]['email'])) throw new Exception("$label (Email) is required.");
                    if (empty($p[$i]['cnic'])) throw new Exception("$label (CNIC) is required.");
                    if (empty($p[$i]['roll'])) throw new Exception("$label (Roll Number) is required.");
                    if (empty($p[$i]['face'])) throw new Exception("Face Photograph is required for $label.");
                    if (empty($p[$i]['card'])) throw new Exception("Student ID Card Photo is required for $label.");
                }
            }

            // Validate maximum participants
            for ($i = $maxAllowed + 1; $i <= 6; $i++) {
                if (!empty($p[$i]['name'])) {
                    throw new Exception("The $module module only allows a maximum of $maxAllowed participant" . ($maxAllowed > 1 ? 's' : '') . ".");
                }
            }
        } else {
            if (empty($p[1]['name'])) throw new Exception("Team Leader name is required.");
        }

        // ---------------------------------------------------------
        // D. DUPLICATE CHECK (Allow re-registration if rejected)
        // ---------------------------------------------------------
        $checkSql = "SELECT id FROM event_registrations WHERE user_id = ? AND module_selection = ? AND event_label = ? AND status != 'rejected'";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("iss", $user_id, $module, $activeEventLabel);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("You are already registered for the " . $module . " module!");
        }
        $checkStmt->close();

        // Sanitize string lengths to prevent MySQL strict mode truncation errors
        for ($i = 1; $i <= 6; $i++) {
            $p[$i]['name']    = mb_substr((string)$p[$i]['name'], 0, 100);
            $p[$i]['contact'] = mb_substr((string)$p[$i]['contact'], 0, 50);
            $p[$i]['email']   = mb_substr((string)$p[$i]['email'], 0, 100);
            $p[$i]['cnic']    = mb_substr((string)$p[$i]['cnic'], 0, 50);
            $p[$i]['roll']    = mb_substr((string)$p[$i]['roll'], 0, 50);
        }
        $teamName    = mb_substr($teamName, 0, 100);
        $institution = mb_substr($institution, 0, 100);
        $module      = mb_substr($module, 0, 100);
        $brandCode   = !empty($brandCode) ? mb_substr($brandCode, 0, 50) : null;

        // ---------------------------------------------------------
        // E. DATABASE INSERTION (STRICT MODE COMPLIANT)
        // ---------------------------------------------------------
        // Explicitly set allowed ENUM('pending','approved','rejected') status
        $registrationStatus = 'pending';

        $sql = "INSERT INTO event_registrations (
            user_id, institution_type, team_name, module_selection, brand_ambassador_code, fees_screenshot, payment_proof, payment_status,
            participant1_name, participant1_contact, participant1_email, participant1_cnic, participant1_roll_number, participant1_face_image, participant1_id_card,
            participant2_name, participant2_contact, participant2_email, participant2_cnic, participant2_roll_number, participant2_face_image, participant2_id_card,
            participant3_name, participant3_contact, participant3_email, participant3_cnic, participant3_roll_number, participant3_face_image, participant3_id_card,
            participant4_name, participant4_contact, participant4_email, participant4_cnic, participant4_roll_number, participant4_face_image, participant4_id_card,
            participant5_name, participant5_contact, participant5_email, participant5_cnic, participant5_roll_number, participant5_face_image, participant5_id_card,
            participant6_name, participant6_contact, participant6_email, participant6_cnic, participant6_roll_number, participant6_face_image, participant6_id_card,
            event_label, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?
        )";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);

        $bindTypes = "i" . str_repeat("s", 51);
        $stmt->bind_param($bindTypes,
            $user_id, $institution, $teamName, $module, $brandCode, $feesImg, $feesImg, $paymentStatus,
            $p[1]['name'], $p[1]['contact'], $p[1]['email'], $p[1]['cnic'], $p[1]['roll'], $p[1]['face'], $p[1]['card'],
            $p[2]['name'], $p[2]['contact'], $p[2]['email'], $p[2]['cnic'], $p[2]['roll'], $p[2]['face'], $p[2]['card'],
            $p[3]['name'], $p[3]['contact'], $p[3]['email'], $p[3]['cnic'], $p[3]['roll'], $p[3]['face'], $p[3]['card'],
            $p[4]['name'], $p[4]['contact'], $p[4]['email'], $p[4]['cnic'], $p[4]['roll'], $p[4]['face'], $p[4]['card'],
            $p[5]['name'], $p[5]['contact'], $p[5]['email'], $p[5]['cnic'], $p[5]['roll'], $p[5]['face'], $p[5]['card'],
            $p[6]['name'], $p[6]['contact'], $p[6]['email'], $p[6]['cnic'], $p[6]['roll'], $p[6]['face'], $p[6]['card'],
            $activeEventLabel, $registrationStatus
        );

        if ($stmt->execute()) {
            $registrationId = (int) $conn->insert_id;
            $syncParticipants = [];
            for ($i = 1; $i <= 6; $i++) {
                // Only sync if the participant actually has a name
                if (!empty($p[$i]['name'])) {
                    $syncParticipants[] = [
                        'label' => ($i === 1) ? 'Leader' : 'Member ' . $i,
                        'name' => $p[$i]['name'],
                        'email' => $p[$i]['email'],
                        'phone' => $p[$i]['contact'],
                        'cnic' => $p[$i]['cnic'],
                        'roll' => $p[$i]['roll'],
                        'face' => $p[$i]['face'],
                        'card' => $p[$i]['card'],
                        'person_index' => $i
                    ];
                }
            }
            event_attendees_sync($conn, $registrationId, $syncParticipants, $registrationStatus);
            $response = [
                'status' => 'success',
                'success' => true,
                'message' => 'Registration Submitted Successfully!',
                'registrationId' => $registrationId
            ];
        } else {
            throw new Exception("Database execution failed: " . $stmt->error);
        }
        $stmt->close();
        $conn->close();

    } catch (Throwable $e) {
        $response = [
            'status' => 'error',
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/// 3. FRONTEND VIEW
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'header.php'; 
include 'db_connection.php'; // THIS FIXES THE BLANK SCREEN
require_once __DIR__ . '/event_registration_settings.php';

$instVis = event_inst_visibility($conn);
$visibleCount = $instVis['ned'] + $instVis['non_ned'] + $instVis['college'];

// Smart Balancing Logic
$colClass = 'col-md-4'; // Default (3 boxes)
if ($visibleCount == 2) { $colClass = 'col-md-5'; } // Wider, centered for 2 boxes
if ($visibleCount == 1) { $colClass = 'col-md-6'; } // Widest, centered for 1 box
?>

<style>
    .step-progress-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 860px;
        margin: 0 auto 36px auto;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
        gap: 12px;
    }
    .step-indicator-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
        transition: color 0.2s ease;
    }
    .step-indicator-item.active {
        color: var(--orange);
    }
    .step-indicator-item.completed {
        color: #00ff94;
    }
    .step-circle {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid var(--line);
        background: transparent;
        transition: all 0.2s ease;
    }
    .step-indicator-item.active .step-circle {
        border-color: var(--orange);
        background: rgba(241, 90, 36, 0.15);
        color: var(--orange);
    }
    .step-indicator-item.completed .step-circle {
        border-color: #00ff94;
        color: #00ff94;
    }
    .reg-wizard-card {
        background: rgba(15, 20, 22, 0.75);
        border: 1px solid var(--line);
        padding: 40px 36px;
        max-width: 860px;
        margin: 0 auto 80px auto;
        box-shadow: 0 20px 80px rgba(0, 0, 0, 0.5);
    }
    .inst-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
        margin: 28px 0;
    }
    .inst-track-card {
        padding: 24px;
        border: 1px solid var(--line);
        background: rgba(15, 20, 22, 0.6);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
    }
    .inst-track-card:hover {
        border-color: rgba(241, 90, 36, 0.5);
        background: rgba(241, 90, 36, 0.04);
    }
    .inst-track-card.selected {
        border-color: var(--orange) !important;
        background: rgba(241, 90, 36, 0.1) !important;
        box-shadow: 0 0 25px rgba(241, 90, 36, 0.12);
    }
    .inst-track-card h3 {
        margin: 14px 0 8px;
        font-size: 16px;
        font-weight: 600;
        color: var(--paper);
        font-family: 'Space Grotesk', sans-serif;
    }
    .inst-track-card p {
        margin: 0;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.5;
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
    }
    .signal-input:focus {
        border-bottom-color: var(--orange) !important;
    }
    .signal-input::placeholder {
        color: rgba(255, 255, 255, 0.25) !important;
    }
    .signal-label {
        display: block;
        color: var(--muted);
        font: 600 10px 'IBM Plex Mono', monospace;
        letter-spacing: 0.14em;
        margin-bottom: 6px;
        text-transform: uppercase;
    }
    .signal-select {
        width: 100%;
        margin-top: 8px;
        padding: 14px 16px;
        background: #101518;
        color: #f4f1eb;
        border: 1px solid var(--line);
        border-radius: 4px;
        font-size: 14px;
        font-family: 'IBM Plex Mono', monospace;
        outline: none;
    }
    .signal-select:focus {
        border-color: var(--orange);
    }
    .signal-select option {
        background: #101518;
        color: #f4f1eb;
    }
    .participant-card {
        background: rgba(15, 20, 22, 0.5);
        border: 1px solid rgba(241, 90, 36, 0.35);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .participant-card.optional {
        border-color: var(--line);
    }
    .btn-step-next {
        background: var(--orange);
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 14px 28px;
        border: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: opacity 0.2s ease;
    }
    .btn-step-next:hover {
        opacity: 0.9;
    }
    .btn-step-back {
        background: transparent;
        color: var(--muted);
        font-weight: 600;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 14px 22px;
        border: 1px solid var(--line);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .btn-step-back:hover {
        border-color: var(--orange);
        color: var(--orange);
    }
</style>

<div class="secondary-page">
    <main>
        <!-- Top Hero matching SiteChrome.tsx -->
        <header class="secondary-hero motion-reveal is-visible">
            <div class="secondary-hero-grid">
                <div>
                    <span class="eyebrow">
                        <i></i>
                        PROXION '26 // REGISTRATION CONSOLE
                    </span>
                    <h1>
                        Register your<br>
                        <em style="color: var(--orange) !important;">team.</em>
                    </h1>
                    <p>
                        Complete the official registration for PROXION '26. Dynamic team configuration and automatic validation enabled.
                    </p>
                </div>
                <div class="secondary-hero-index">
                    <span>SYS.02</span>
                    <strong>REGISTRATION // CONSOLE</strong>
                    <small>
                        43°42'18" N<br>
                        67°08'07" E
                    </small>
                </div>
            </div>
        </header>

        <section class="secondary-section" style="max-width: 920px; margin: 0 auto; padding-top: 40px;">


            <!-- Hanging L-Bracket Above Form matching Screenshots -->
            <div style="width: 48px; height: 48px; border-left: 1.5px solid var(--orange); border-bottom: 1.5px solid var(--orange); margin: 0 auto 30px auto;"></div>

            <!-- 4-Step Progress Indicator -->
            <div class="step-progress-nav">
                <div class="step-indicator-item active" id="stepPill1">
                    <span class="step-circle" id="stepCircle1">1</span>
                    <span>Institution</span>
                </div>
                <div class="step-indicator-item" id="stepPill2">
                    <span class="step-circle" id="stepCircle2">2</span>
                    <span>Arena & Team</span>
                </div>
                <div class="step-indicator-item" id="stepPill3">
                    <span class="step-circle" id="stepCircle3">3</span>
                    <span>Participants</span>
                </div>
                <div class="step-indicator-item" id="stepPill4">
                    <span class="step-circle" id="stepCircle4">4</span>
                    <span>Payment & Code</span>
                </div>
            </div>

            <form id="multiStepRegForm" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo (int)$_SESSION['user_id']; ?>">
                <input type="hidden" name="institutionType" id="hiddenInstitution" value="NED University Student">

                <div class="reg-wizard-card">
                    
                    <!-- STEP 1: Institution Track -->
                    <div id="stepSection1">
                        <span style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block; margin-bottom: 8px;">
                            STEP 01 // INSTITUTION TYPE
                        </span>
                        <h2 style="margin: 0 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                            Select your institution track
                        </h2>

                        <div class="inst-card-grid">
                            <div class="inst-track-card selected" onclick="selectTrack('NED University Student', this)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                    <path d="M9 22v-4h6v4"></path>
                                    <path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"></path>
                                </svg>
                                <h3>NED University Student</h3>
                                <p>Current enrolled student of NEDUET Karachi</p>
                            </div>

                            <div class="inst-track-card" onclick="selectTrack('Non-NED University Student', this)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                    <path d="M9 22v-4h6v4"></path>
                                    <path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"></path>
                                </svg>
                                <h3>Non-NED University Student</h3>
                                <p>Students from FAST, IBA, NUST, GIKI, SSUET, etc.</p>
                            </div>

                            <div class="inst-track-card" onclick="selectTrack('College Student', this)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                    <path d="M9 22v-4h6v4"></path>
                                    <path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"></path>
                                </svg>
                                <h3>College / Intermediate Student</h3>
                                <p>Intermediate / A-Level / High School students</p>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn-step-next" onclick="goToStep(2)">
                                <span>PROCEED TO TEAM INFO</span>
                                <span>&rarr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Arena & Team -->
                    <div id="stepSection2" style="display: none;">
                        <span style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block; margin-bottom: 8px;">
                            STEP 02 // ARENA & TEAM
                        </span>
                        <h2 style="margin: 0 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                            Choose your competition track
                        </h2>

                        <div style="margin-bottom: 28px;">
                            <label class="signal-label" for="teamNameInput">TEAM NAME</label>
                            <input type="text" id="teamNameInput" name="teamName" required class="signal-input" placeholder="e.g. ByteForce, NeuralCraft, CyberShield">
                        </div>

                        <div style="margin-bottom: 24px;">
                            <label class="signal-label" for="moduleSelectInput">MODULE SELECTION</label>
                            <select id="moduleSelectInput" name="moduleSelection" class="signal-select" onchange="onModuleChange()">
                                <option value="Line Following Robot (LFR)">Line Following Robot (LFR) (Robotics) — Team of 2-4</option>
                                <option value="Circuit Designing Competition">Circuit Designing Competition (Hardware) — Team of 2-4</option>
                                <option value="CYBER WAR ROOM">CYBER WAR ROOM (Cybersecurity) — Team of 3-4</option>
                                <option value="RAG CHATBOT BUILDER">RAG CHATBOT BUILDER (AI/ML) — Team of 2-3</option>
                                <option value="AGENT SPRINT: LIVE GMAIL AUTOMATION">AGENT SPRINT: LIVE GMAIL AUTOMATION (AI/ML) — Team of 2-3</option>
                                <option value="AI COURT: FAKE OR REAL">AI COURT: FAKE OR REAL (AI/ML) — Team of 2-3</option>
                                <option value="DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE">DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE (Data) — Team of 2-3</option>
                                <option value="BREAK THE RULES">BREAK THE RULES (AI/Security) — Solo (1 Member)</option>
                                <option value="PitchFest">PitchFest (Innovation) — Team of 1-4</option>
                                <option value="AI DEBATE COLOSSEUM">AI DEBATE COLOSSEUM (AI/ML) — Team of 2-3</option>
                                <option value="Web Forces">Web Forces (Web Dev) — Team of 3-4</option>
                                <option value="Reactor Zero">Reactor Zero (Engineering) — Team of 2-3</option>
                                <option value="Fault Line">Fault Line (ML/Materials) — Team of 2-3</option>
                            </select>
                        </div>

                        <!-- Module Constraints Box -->
                        <div id="moduleConstraintBox" style="background: rgba(241, 90, 36, 0.08); border: 1px solid rgba(241, 90, 36, 0.35); border-radius: 6px; padding: 16px; margin: 24px 0;">
                            <div style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;" id="constraintTitle">
                                Team Constraint: Min 3 to Max 4 Members
                            </div>
                            <div style="color: var(--muted); font-size: 13px; line-height: 1.5;" id="constraintDesc">
                                Speed programming and algorithmic problem solving under pressure.
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn-step-back" onclick="goToStep(1)">
                                <span>&larr;</span>
                                <span>BACK</span>
                            </button>
                            <button type="button" class="btn-step-next" onclick="goToStep(3)">
                                <span>PROCEED TO PARTICIPANTS</span>
                                <span>&rarr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Participant Details -->
                    <div id="stepSection3" style="display: none;">
                        <span style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block; margin-bottom: 8px;">
                            STEP 03 // PARTICIPANT DETAILS
                        </span>
                        <h2 style="margin: 0 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;" id="rosterHeading">
                            Team Roster (3 Required, Up to 4)
                        </h2>

                        <div id="participantCardsContainer">
                            <!-- Injected dynamically based on module constraints -->
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn-step-back" onclick="goToStep(2)">
                                <span>&larr;</span>
                                <span>BACK</span>
                            </button>
                            <button type="button" class="btn-step-next" onclick="goToStep(4)">
                                <span>PROCEED TO PAYMENT</span>
                                <span>&rarr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4: Brand Ambassador Code & Payment Upload -->
                    <div id="stepSection4" style="display: none;">
                        <span style="color: var(--orange); font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; display: block; margin-bottom: 8px;">
                            STEP 04 // AMBASSADOR & PAYMENT
                        </span>
                        <h2 style="margin: 0 0 24px; font-size: 26px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                            Finalize Registration
                        </h2>

                        <div style="margin-bottom: 28px;">
                            <label class="signal-label" for="ambCodeInput">CAMPUS AMBASSADOR REFERRAL CODE (OPTIONAL)</label>
                            <input type="text" id="ambCodeInput" name="brand_ambassador_code" class="signal-input" placeholder="e.g. SNTC-AMB-101" style="text-transform: uppercase;">
                        </div>

                        <!-- Bank Details Card -->
                        <div style="background: rgba(15, 20, 22, 0.85); border: 1px solid var(--orange); border-radius: 8px; padding: 24px; margin-bottom: 28px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                                    <line x1="2" x2="22" y1="10" y2="10"></line>
                                </svg>
                                <h3 style="margin: 0; font-size: 18px; color: var(--paper); font-family: 'Space Grotesk', sans-serif;">
                                    Registration Fee Submission
                                </h3>
                            </div>
                            <p style="color: var(--muted); font-size: 13px; line-height: 1.6; margin: 0 0 16px;">
                                Transfer module entry fee to the official SENTEC account and upload the transaction screenshot below.
                            </p>
                            <div style="background: #080b0d; border: 1px solid var(--line); padding: 16px; border-radius: 6px; font-family: 'IBM Plex Mono', monospace; font-size: 12px; line-height: 1.8; color: var(--paper);">
                                <div><span style="color: var(--muted);">BANK:</span> Habib Metropolitan Bank Limited</div>
                                <div><span style="color: var(--muted);">TITLE:</span> NEDUET CONTROLLER STUDENT AFFAIRS</div>
                                <div><span style="color: var(--muted);">ACCOUNT:</span> 6-99-72-29314-714-262131</div>
                                <div><span style="color: var(--muted);">IBAN:</span> PK73MPBL9972477140262131</div>
                                <div><span style="color: var(--muted);">AMOUNT:</span> <strong style="color: var(--orange);" id="moduleFeeDisplay">PKR 1,200 / Team</strong></div>
                            </div>
                        </div>

                        <div style="margin-bottom: 28px;">
                            <label class="signal-label" for="receiptInput">SELECT RECEIPT / SCREENSHOT (JPG, PNG, WEBP, PDF) *</label>
                            <input type="file" id="receiptInput" name="fees_screenshot" accept="image/*,application/pdf" class="form-control" style="background: #101518; border: 1px solid var(--line); color: var(--paper); border-radius: 0; padding: 10px; font-size: 13px;">
                        </div>

                        <div id="submitAlertContainer" style="display: none; margin-bottom: 20px;"></div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line);">
                            <button type="button" class="btn-step-back" onclick="goToStep(3)">
                                <span>&larr;</span>
                                <span>BACK</span>
                            </button>
                            <button type="submit" id="finalSubmitBtn" class="btn-step-next">
                                <span id="submitBtnText">SUBMIT REGISTRATION</span>
                                <span>&rarr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 5: Success Screen -->
                    <div id="stepSection5" style="display: none; text-align: center; padding: 40px 10px;">
                        <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(0, 255, 148, 0.1); border: 1.5px solid #00ff94; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto; color: #00ff94; font-size: 32px;">
                            ✓
                        </div>
                        <span style="color: #00ff94; font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: 0.14em; font-weight: 600; text-transform: uppercase;">
                            DISPATCH COMPLETE // CLEARANCE ISSUED
                        </span>
                        <h2 style="margin: 14px 0 16px; font-size: 30px; font-weight: 500; color: var(--paper); letter-spacing: -0.03em; font-family: 'Space Grotesk', sans-serif;">
                            Registration Submitted!
                        </h2>
                        <p style="color: var(--muted); font-size: 14px; max-width: 500px; margin: 0 auto 30px auto; line-height: 1.6;">
                            Your team registration has been recorded into the SENTEC database. You can track validation status directly from your participant console.
                        </p>
                        <a href="dashboard" class="btn-step-next" style="text-decoration: none; display: inline-flex;">
                            <span>RETURN TO DASHBOARD</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                </div>
            </form>

        </section>
    </main>
</div>

<!-- Local Bulletproof Image Compressor (Zero External CDN Dependency) -->
<script type="text/javascript" src="js/sentec-compressor.js"></script>
<script>
    // Module Rules Database matching 13 official competition modules
    const MODULE_RULES = {
        "Line Following Robot (LFR)": {
            min: 2,
            max: 4,
            price: "PKR 1,200",
            category: "Robotics",
            desc: "A practical robotics competition in which autonomous robots follow a predefined track using sensors and control logic."
        },
        "Circuit Designing Competition": {
            min: 2,
            max: 4,
            price: "PKR 1,200",
            category: "Hardware",
            desc: "An electronics and digital-logic focused competition involving circuit design, problem solving, and circuit debugging through simulation."
        },
        "CYBER WAR ROOM": {
            min: 3,
            max: 4,
            price: "PKR 1,400",
            category: "Cybersecurity",
            desc: "Cyber War Room is a direct Attack & Defense Web Security Competition. Teams must first build and secure their own functional web application, package it using Docker, and submit it to the organizers. The application is then randomly assigned to another team."
        },
        "RAG CHATBOT BUILDER": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "AI/ML",
            desc: "Build a Retrieval-Augmented Generation chatbot from a provided PDF that answers accurately and stays polite under a live adversarial roleplay."
        },
        "AGENT SPRINT: LIVE GMAIL AUTOMATION": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "AI/ML",
            desc: "Build an agent that reads real emails from a provided Gmail account, classifies them, drafts policy-based replies, and displays live status on a dashboard."
        },
        "AI COURT: FAKE OR REAL": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "AI/ML",
            desc: "Classify six curated items as real or AI-generated and defend the verdict before a judging panel."
        },
        "DATA DETECTIVE: SINGLE HARDCOPY CHALLENGE": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "Data",
            desc: "Digitize and clean a single messy hardcopy dataset, build a dashboard, and catch a live injected anomaly."
        },
        "BREAK THE RULES": {
            min: 1,
            max: 1,
            price: "PKR 1,000",
            category: "AI/Security",
            desc: "Break a locked chatbot's hidden behavioral rules through conversation alone, across a minimum of three rule categories."
        },
        "PitchFest": {
            min: 1,
            max: 4,
            price: "PKR 500",
            category: "Innovation",
            desc: "Students can come up with their ideas and projects, then present them to a panel of evaluators who assess innovation, feasibility, and impact. The module rewards bold thinking, clear communication, and the ability to turn an idea into a compelling solution."
        },
        "AI DEBATE COLOSSEUM": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "AI/ML",
            desc: "Build a competing AI debate persona and face another team's persona live, with a live-updating public transcript and an AI judge deciding the winner."
        },
        "Web Forces": {
            min: 3,
            max: 4,
            price: "PKR 1,400",
            category: "Web Dev",
            desc: "Teams ship a working full stack app against a live spec that is only revealed at the start of the module. Partway through, a twist is dropped in (a broken API, a new requirement) to test how well the team adapts, not just how fast they can build."
        },
        "Reactor Zero": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "Engineering",
            desc: "A high-stakes competition where teams must design and build a reactor from scratch, facing real-world challenges and constraints."
        },
        "Fault Line": {
            min: 2,
            max: 3,
            price: "PKR 1,200",
            category: "ML/Materials",
            desc: "Teams get mixed data, concrete stress tests and fabric tensile tests, and must build one classifier pipeline that generalizes across material types."
        }
    };

    let currentStep = 1;
    let selectedTrack = "NED University Student";
    const loggedInUser = {
        name: "<?php echo addslashes($_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? ''); ?>",
        email: "<?php echo addslashes($_SESSION['user']['email'] ?? ''); ?>"
    };

    function selectTrack(trackName, element) {
        selectedTrack = trackName;
        document.getElementById('hiddenInstitution').value = trackName;
        document.querySelectorAll('.inst-track-card').forEach(c => {
            c.classList.remove('selected');
            const svg = c.querySelector('svg');
            if (svg) svg.setAttribute('stroke', 'var(--muted)');
        });
        element.classList.add('selected');
        const activeSvg = element.querySelector('svg');
        if (activeSvg) activeSvg.setAttribute('stroke', 'var(--orange)');
    }

    function onModuleChange() {
        const mod = document.getElementById('moduleSelectInput').value;
        const rule = MODULE_RULES[mod] || { min: 2, max: 4, price: "PKR 1,200", desc: "General competition track rules apply." };
        const minCount = rule.min;
        const maxCount = rule.max;

        let constraintText = '';
        if (minCount === 1 && maxCount === 1) {
            constraintText = 'Solo Participant (1 Member Required)';
        } else if (minCount === maxCount) {
            constraintText = `Team Constraint: Exactly ${minCount} Members Required`;
        } else {
            constraintText = `Team Constraint: Min ${minCount} to Max ${maxCount} Members`;
        }

        document.getElementById('constraintTitle').textContent = constraintText;
        document.getElementById('constraintDesc').textContent = rule.desc;

        let rosterText = '';
        if (minCount === 1 && maxCount === 1) {
            rosterText = 'Participant Details (1 Required)';
        } else if (minCount === maxCount) {
            rosterText = `Team Roster (${minCount} Members Required)`;
        } else {
            rosterText = `Team Roster (${minCount} Required, Up to ${maxCount})`;
        }
        document.getElementById('rosterHeading').textContent = rosterText;

        const feeEl = document.getElementById('moduleFeeDisplay');
        if (feeEl) {
            feeEl.textContent = (rule.price || 'PKR 1,200') + (minCount === 1 && maxCount === 1 ? ' / Person' : ' / Team');
        }

        renderParticipantCards(minCount, maxCount);
    }

    function renderParticipantCards(minCount, maxCount) {
        const container = document.getElementById('participantCardsContainer');
        // Cache existing field values so changing modules does not erase filled inputs
        const cached = {};
        container.querySelectorAll('input').forEach(inp => {
            if (inp.name && inp.type !== 'file') {
                cached[inp.name] = inp.value;
            }
        });

        container.innerHTML = '';

        for (let i = 1; i <= maxCount; i++) {
            const isLeader = (i === 1);
            const isRequired = (i <= minCount);

            const card = document.createElement('div');
            card.className = `participant-card ${isRequired ? '' : 'optional'}`;
            
            let cardTitle = '';
            if (minCount === 1 && maxCount === 1) {
                cardTitle = 'PARTICIPANT (SOLO) *';
            } else if (isLeader) {
                cardTitle = 'PARTICIPANT 01 (TEAM LEADER) *';
            } else {
                cardTitle = 'PARTICIPANT 0' + i + (isRequired ? ' *' : ' (OPTIONAL)');
            }

            const valName = cached[`participant${i}_name`] !== undefined ? cached[`participant${i}_name`] : (isLeader ? loggedInUser.name : '');
            const valContact = cached[`participant${i}_contact`] || '';
            const valEmail = cached[`participant${i}_email`] !== undefined ? cached[`participant${i}_email`] : (isLeader ? loggedInUser.email : '');
            const valCnic = cached[`participant${i}_cnic`] || '';
            const valRoll = cached[`participant${i}_roll_number`] || '';

            card.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--line); padding-bottom: 10px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: ${isRequired ? 'var(--orange)' : 'var(--muted)'}; font-family: 'Space Grotesk', sans-serif;">
                        ${cardTitle}
                    </h3>
                    <span style="font-family: 'IBM Plex Mono', monospace; font-size: 10px; color: ${isRequired ? 'var(--orange)' : 'var(--muted)'}; letter-spacing: 0.1em;">
                        ${isRequired ? 'REQUIRED' : 'OPTIONAL'}
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="signal-label">FULL NAME ${isRequired ? '*' : ''}</label>
                        <input type="text" name="participant${i}_name" class="signal-input" ${isRequired ? 'required' : ''} placeholder="Full Name" value="${valName}">
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label">PHONE / WHATSAPP NUMBER ${isRequired ? '*' : ''}</label>
                        <input type="tel" name="participant${i}_contact" class="signal-input" ${isRequired ? 'required' : ''} placeholder="+92 300 1234567" value="${valContact}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="signal-label">EMAIL ADDRESS ${isRequired ? '*' : ''}</label>
                        <input type="email" name="participant${i}_email" class="signal-input" ${isRequired ? 'required' : ''} placeholder="email@example.com" value="${valEmail}">
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label">CNIC / B-FORM NUMBER ${isRequired ? '*' : ''}</label>
                        <input type="text" name="participant${i}_cnic" class="signal-input" ${isRequired ? 'required' : ''} placeholder="42101-1234567-1" value="${valCnic}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="signal-label">STUDENT ROLL NUMBER / STUDENT ID ${isRequired ? '*' : ''}</label>
                    <input type="text" name="participant${i}_roll_number" class="signal-input" ${isRequired ? 'required' : ''} placeholder="e.g. CS-2024-042 or College Roll No" value="${valRoll}">
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="signal-label">FACE PHOTOGRAPH ${isRequired ? '*' : '(IF ADDING MEMBER) *'}</label>
                        <input type="file" name="participant${i}_face_image" accept="image/*" class="form-control" style="background:#101518; border:1px solid var(--line); color:var(--muted); font-size:11px; border-radius:0; padding:8px;" onchange="handleFileSelected(this)">
                        <span style="font-size:10px; color:var(--muted); display:block; margin-top:2px;">Clear photo for participant pass badge</span>
                        <div class="file-name-preview" style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#00ff94; margin-top:5px; display:none;"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="signal-label">STUDENT ID CARD PHOTO ${isRequired ? '*' : '(IF ADDING MEMBER) *'}</label>
                        <input type="file" name="participant${i}_id_card" accept="image/*" class="form-control" style="background:#101518; border:1px solid var(--line); color:var(--muted); font-size:11px; border-radius:0; padding:8px;" onchange="handleFileSelected(this)">
                        <span style="font-size:10px; color:var(--muted); display:block; margin-top:2px;">Front side of University/College ID card</span>
                        <div class="file-name-preview" style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#00ff94; margin-top:5px; display:none;"></div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        }

        // Attach input listeners to clear red borders on typing
        container.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = 'var(--line)';
                this.style.boxShadow = 'none';
            });
        });
    }

    function handleFileSelected(input) {
        input.style.borderColor = 'var(--line)';
        input.style.boxShadow = 'none';
        const preview = input.parentElement.querySelector('.file-name-preview');
        if (preview) {
            if (input.files && input.files[0]) {
                const f = input.files[0];
                const kb = Math.round(f.size / 1024);
                preview.textContent = `✓ Selected: ${f.name} (${kb} KB)`;
                preview.style.display = 'block';
            } else {
                preview.textContent = '';
                preview.style.display = 'none';
            }
        }
    }

    function highlightErrorInput(el, msg) {
        alert(msg);
        if (el) {
            el.style.borderColor = '#f87171';
            el.style.boxShadow = '0 0 12px rgba(248, 113, 113, 0.45)';
            el.focus();
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function goToStep(stepNumber) {
        // Validation when going forward
        if (stepNumber === 2 && currentStep === 1) {
            if (!selectedTrack) {
                alert("Please select your institution category.");
                return;
            }
        }
        if (stepNumber === 3 && currentStep === 2) {
            const teamNameInput = document.getElementById('teamNameInput');
            const teamName = teamNameInput.value.trim();
            if (!teamName) {
                highlightErrorInput(teamNameInput, "Please enter a Team Name.");
                return;
            }
        }
        if (stepNumber === 4 && currentStep === 3) {
            const mod = document.getElementById('moduleSelectInput').value;
            const rule = MODULE_RULES[mod] || { min: 2, max: 4 };

            // 1. Validate required participants (text AND both images required)
            for (let i = 1; i <= rule.min; i++) {
                const label = (rule.min === 1 && rule.max === 1) ? 'Solo Participant' : (i === 1 ? 'Team Leader' : 'Participant 0' + i);
                const nameEl = document.querySelector(`[name="participant${i}_name"]`);
                const contactEl = document.querySelector(`[name="participant${i}_contact"]`);
                const emailEl = document.querySelector(`[name="participant${i}_email"]`);
                const cnicEl = document.querySelector(`[name="participant${i}_cnic"]`);
                const rollEl = document.querySelector(`[name="participant${i}_roll_number"]`);
                const faceEl = document.querySelector(`[name="participant${i}_face_image"]`);
                const cardEl = document.querySelector(`[name="participant${i}_id_card"]`);

                if (!nameEl?.value.trim()) { highlightErrorInput(nameEl, `Please enter Full Name for ${label}.`); return; }
                if (!contactEl?.value.trim()) { highlightErrorInput(contactEl, `Please enter Phone / WhatsApp Number for ${label}.`); return; }
                if (!emailEl?.value.trim()) { highlightErrorInput(emailEl, `Please enter Email Address for ${label}.`); return; }
                if (!cnicEl?.value.trim()) { highlightErrorInput(cnicEl, `Please enter CNIC / B-Form Number for ${label}.`); return; }
                if (!rollEl?.value.trim()) { highlightErrorInput(rollEl, `Please enter Student Roll Number / ID for ${label}.`); return; }
                if (!faceEl?.files || faceEl.files.length === 0) { highlightErrorInput(faceEl, `Please upload Face Photograph for ${label}.`); return; }
                if (!cardEl?.files || cardEl.files.length === 0) { highlightErrorInput(cardEl, `Please upload Student ID Card Photo for ${label}.`); return; }
            }

            // 2. Validate optional participants: if someone edited/filled any field, all fields + images are required
            for (let i = rule.min + 1; i <= rule.max; i++) {
                const label = 'Participant 0' + i;
                const nameEl = document.querySelector(`[name="participant${i}_name"]`);
                const contactEl = document.querySelector(`[name="participant${i}_contact"]`);
                const emailEl = document.querySelector(`[name="participant${i}_email"]`);
                const cnicEl = document.querySelector(`[name="participant${i}_cnic"]`);
                const rollEl = document.querySelector(`[name="participant${i}_roll_number"]`);
                const faceEl = document.querySelector(`[name="participant${i}_face_image"]`);
                const cardEl = document.querySelector(`[name="participant${i}_id_card"]`);

                const hasName = !!nameEl?.value.trim();
                const hasContact = !!contactEl?.value.trim();
                const hasEmail = !!emailEl?.value.trim();
                const hasCnic = !!cnicEl?.value.trim();
                const hasRoll = !!rollEl?.value.trim();
                const hasFace = !!(faceEl?.files && faceEl.files.length > 0);
                const hasCard = !!(cardEl?.files && cardEl.files.length > 0);

                const isEditing = hasName || hasContact || hasEmail || hasCnic || hasRoll || hasFace || hasCard;
                if (isEditing) {
                    if (!hasName) { highlightErrorInput(nameEl, `Please enter Full Name for ${label} (or clear all fields if not adding this member).`); return; }
                    if (!hasContact) { highlightErrorInput(contactEl, `Please enter Phone / WhatsApp Number for ${label}.`); return; }
                    if (!hasEmail) { highlightErrorInput(emailEl, `Please enter Email Address for ${label}.`); return; }
                    if (!hasCnic) { highlightErrorInput(cnicEl, `Please enter CNIC / B-Form Number for ${label}.`); return; }
                    if (!hasRoll) { highlightErrorInput(rollEl, `Please enter Student Roll Number for ${label}.`); return; }
                    if (!hasFace) { highlightErrorInput(faceEl, `Please upload Face Photograph for ${label}.`); return; }
                    if (!hasCard) { highlightErrorInput(cardEl, `Please upload Student ID Card Photo for ${label}.`); return; }
                }
            }
        }

        // Hide all step sections
        for (let i = 1; i <= 5; i++) {
            const sec = document.getElementById('stepSection' + i);
            if (sec) sec.style.display = 'none';
        }

        // Show target step
        const target = document.getElementById('stepSection' + stepNumber);
        if (target) target.style.display = 'block';

        // Update indicators
        for (let i = 1; i <= 4; i++) {
            const pill = document.getElementById('stepPill' + i);
            const circle = document.getElementById('stepCircle' + i);
            if (pill && circle) {
                pill.classList.remove('active', 'completed');
                if (i === stepNumber) {
                    pill.classList.add('active');
                    circle.textContent = i;
                } else if (i < stepNumber) {
                    pill.classList.add('completed');
                    circle.textContent = '✓';
                } else {
                    circle.textContent = i;
                }
            }
        }

        currentStep = stepNumber;
        window.scrollTo({ top: 180, behavior: 'smooth' });
    }

    // Initialize on document ready
    document.addEventListener('DOMContentLoaded', function() {
        // Pre-select module from URL parameter if passed (e.g. from engineers_code.php card click)
        const urlParams = new URLSearchParams(window.location.search);
        const preselectedModule = urlParams.get('module');
        if (preselectedModule && MODULE_RULES[preselectedModule]) {
            const selectEl = document.getElementById('moduleSelectInput');
            if (selectEl) {
                selectEl.value = preselectedModule;
            }
        }

        onModuleChange();

        // Also add receipt change listener
        const receiptInput = document.getElementById('receiptInput');
        if (receiptInput) {
            receiptInput.addEventListener('change', function() {
                this.style.borderColor = 'var(--line)';
                this.style.boxShadow = 'none';
                let preview = this.parentElement.querySelector('.file-name-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'file-name-preview';
                    preview.style.cssText = "font-family:'IBM Plex Mono',monospace; font-size:11px; color:#00ff94; margin-top:6px;";
                    this.parentElement.appendChild(preview);
                }
                if (this.files && this.files[0]) {
                    const f = this.files[0];
                    const kb = Math.round(f.size / 1024);
                    preview.textContent = `✓ Selected: ${f.name} (${kb} KB)`;
                    preview.style.display = 'block';
                } else {
                    preview.textContent = '';
                    preview.style.display = 'none';
                }
            });
        }

        document.getElementById('multiStepRegForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('finalSubmitBtn');
            const btnText = document.getElementById('submitBtnText');
            const alertDiv = document.getElementById('submitAlertContainer');

            // VALIDATE PAYMENT SCREENSHOT BEFORE PROCEEDING
            const receiptInput = document.getElementById('receiptInput');
            if (!receiptInput || !receiptInput.files || receiptInput.files.length === 0) {
                alertDiv.style.display = 'block';
                alertDiv.innerHTML = `
                    <div style="background: rgba(248,113,113,0.15); border: 1px solid rgba(248,113,113,0.5); color: #f87171; padding: 14px; font-family: 'IBM Plex Mono', monospace; font-size: 12px; margin-bottom: 12px; border-radius: 4px;">
                        ⚠️ PAYMENT RECEIPT REQUIRED: You must select and attach your payment receipt / transaction screenshot before submitting registration.
                    </div>
                `;
                highlightErrorInput(receiptInput, "Please upload your payment receipt / transaction screenshot before submitting.");
                return;
            }

            btn.disabled = true;
            alertDiv.style.display = 'none';
            btnText.textContent = "PREPARING FILES...";

            const rawFormData = new FormData(this);
            const finalFormData = new FormData();
            const fileEntries = [];

            for (const [key, value] of rawFormData.entries()) {
                if (value instanceof File && value.name !== '') {
                    fileEntries.push({ key, file: value });
                } else if (!(value instanceof File)) {
                    finalFormData.append(key, value);
                }
            }

            try {
                for (let i = 0; i < fileEntries.length; i++) {
                    const item = fileEntries[i];
                    btnText.textContent = `PREPARING IMAGE ${i + 1} OF ${fileEntries.length}...`;

                    let readyFile = item.file;
                    // Only compress if the file is not already WebP and is larger than 150KB
                    const isAlreadyWebp = readyFile.type === 'image/webp' || (readyFile.name && readyFile.name.toLowerCase().endsWith('.webp'));
                    if (window.sentecCompressFile && !isAlreadyWebp && readyFile.size > 150 * 1024) {
                        try {
                            btnText.textContent = `OPTIMIZING IMAGE ${i + 1} OF ${fileEntries.length}...`;
                            readyFile = await window.sentecCompressFile(item.file, {
                                maxWidthOrHeight: 1600,
                                quality: 0.82
                            });
                        } catch (compErr) {
                            console.warn("Client compression fallback for", item.file.name, compErr);
                            readyFile = item.file;
                        }
                    }
                    const safeFileName = readyFile.name || (item.file && item.file.name) || 'upload.webp';
                    finalFormData.append(item.key, readyFile, safeFileName);
                }

                btnText.textContent = "TRANSMITTING REGISTRATION...";

                const targetUrl = window.location.href;
                const res = await fetch(targetUrl, {
                    method: 'POST',
                    body: finalFormData,
                    credentials: 'same-origin'
                });

                // Safely read response text and parse JSON
                const rawText = await res.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error("Failed to parse server response as JSON. Raw response:", rawText);
                    throw new Error("SERVER_ERROR_NON_JSON");
                }

                if (!res.ok && (!data || (!data.message && !data.error))) {
                    throw new Error("SERVER_STATUS_" + res.status);
                }

                const isSuccess = data && (data.status === 'success' || data.success === true);
                if (isSuccess) {
                    goToStep(5);
                } else {
                    const errorMsg = (data && (data.message || data.error)) ? String(data.message || data.error) : 'Error occurred during registration.';
                    alertDiv.style.display = 'block';
                    alertDiv.textContent = '';
                    const errBox = document.createElement('div');
                    errBox.style.cssText = "background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.4); color: #f87171; padding: 14px; font-family: 'IBM Plex Mono', monospace; font-size: 12px;";
                    errBox.textContent = errorMsg;
                    alertDiv.appendChild(errBox);

                    btn.disabled = false;
                    btnText.textContent = "SUBMIT REGISTRATION";
                }
            } catch (err) {
                console.error("Registration submission error:", err);

                let friendlyMessage = "A network error occurred. Please check your connection and try again.";
                const errStr = (err && (err.message || String(err))) || '';
                if (errStr.includes("Failed to fetch") || errStr.includes("NetworkError") || errStr.includes("Load failed") || errStr.includes("Network request failed")) {
                    friendlyMessage = "Upload connection was interrupted or timed out. Please check your mobile data / Wi-Fi connection and tap Submit Registration again.";
                } else if (err && (err.message === "SERVER_ERROR_NON_JSON" || (typeof err.message === "string" && err.message.startsWith("SERVER_STATUS_")))) {
                    friendlyMessage = "Server error occurred while processing registration. Please try again.";
                } else if (err && err.message && !err.message.includes("<") && err.message.length < 200) {
                    friendlyMessage = err.message;
                } else {
                    friendlyMessage = "Server error occurred. Please try again.";
                }

                alertDiv.style.display = 'block';
                alertDiv.textContent = '';
                const errBox = document.createElement('div');
                errBox.style.cssText = "background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.4); color: #f87171; padding: 14px; font-family: 'IBM Plex Mono', monospace; font-size: 12px;";
                errBox.textContent = friendlyMessage;
                alertDiv.appendChild(errBox);

                btn.disabled = false;
                btnText.textContent = "SUBMIT REGISTRATION";
            }
        });

        // Session Keep-Alive Heartbeat: Pings server every 4 minutes while user is filling registration
        setInterval(function() {
            fetch('session_ping.php', { credentials: 'same-origin' }).catch(function() {});
        }, 4 * 60 * 1000);
    });

</script>

<?php include 'footer.php'; ?>
