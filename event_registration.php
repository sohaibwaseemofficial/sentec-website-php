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

    ob_start();
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Unknown error'];

    try {
        if (!file_exists('db_connection.php')) throw new Exception("Database file missing.");
        include 'db_connection.php';

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

        // Helper functions
        function processFile($fileArray, $inputName, $targetDir, $publicPrefix) {
            if (!isset($fileArray[$inputName]) || $fileArray[$inputName]['error'] !== UPLOAD_ERR_OK) return '';
            $result = save_image_as_webp($fileArray[$inputName], $targetDir, $publicPrefix);
            if ($result['success']) return $result['path'];
            throw new Exception($result['error'] ?? 'Failed to save file.');
        }

        function getVal($key) { return !empty($_POST[$key]) ? trim($_POST[$key]) : ''; }

        // ---------------------------------------------------------
        // B. CORE DATA COLLECTION (MUST HAPPEN BEFORE VALIDATION)
        // ---------------------------------------------------------
        $user_id     = (int)getVal('user_id');
        $institution = getVal('institutionType');
        $teamName    = getVal('teamName');
        $module      = getVal('moduleSelection');
        $brandCode   = getVal('brand_ambassador_code'); 
        $feesImg     = "Not Collected"; 

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
                'face'    => isset($_FILES["participant{$i}_face_image"]) ? processFile($_FILES, "participant{$i}_face_image", $targetDir, $publicPrefix) : '',
                'card'    => isset($_FILES["participant{$i}_id_card"]) ? processFile($_FILES, "participant{$i}_id_card", $targetDir, $publicPrefix) : ''
            ];
        }

        // ---------------------------------------------------------
        // C. DYNAMIC VALIDATION LOGIC
        // ---------------------------------------------------------
        $serverLimits = [
            "Code Rush" => ["min" => 3, "max" => 4],
            "Algo Masters" => ["min" => 3, "max" => 4],
            "Race with Code" => ["min" => 3, "max" => 4],
            "Design Sprint" => ["min" => 3, "max" => 4],
            "App Innovate" => ["min" => 3, "max" => 4],
            "Query Quest" => ["min" => 3, "max" => 4],
            "Maths Clash" => ["min" => 1, "max" => 1], // Usually solo
            "Bug Busters" => ["min" => 3, "max" => 4],
            "Web Wizards" => ["min" => 3, "max" => 4],
            "Blind Coding" => ["min" => 1, "max" => 1],
            "Valorant" => ["min" => 5, "max" => 5],
            "CS 2" => ["min" => 5, "max" => 5],
            "PUBG Mobile" => ["min" => 4, "max" => 4],
            "COD Mobile" => ["min" => 5, "max" => 5],
            "Pseudocode Builder" => ["min" => 3, "max" => 4],
            "Emoji Algorithm" => ["min" => 3, "max" => 4],
            "AI Energy Saver Dashboard" => ["min" => 2, "max" => 3],
            "AI Defect Finder (Basic Vision ML)" => ["min" => 2, "max" => 3],
            "AI Cyber Alert Classifier" => ["min" => 2, "max" => 3],
            "AI Disaster Aid Planner" => ["min" => 2, "max" => 3],
            "AI Home Energy Advisor" => ["min" => 2, "max" => 3],
            "AI Data Insight Tool" => ["min" => 2, "max" => 3],
            "AI City Planner Map" => ["min" => 2, "max" => 3],
            "AI Image Checker (Simple Classifier)" => ["min" => 2, "max" => 3]
        ];

        if (isset($serverLimits[$module])) {
            $minRequired = $serverLimits[$module]['min'];
            $maxAllowed  = $serverLimits[$module]['max'];

            // Validate required participants
            for ($i = 1; $i <= $minRequired; $i++) {
                if (empty($p[$i]['name'])) throw new Exception("Participant $i (Name) is required for $module.");
                if (empty($p[$i]['contact'])) throw new Exception("Participant $i (Contact) is required.");
                if (empty($p[$i]['email'])) throw new Exception("Participant $i (Email) is required.");
                if (empty($p[$i]['cnic'])) throw new Exception("Participant $i (CNIC) is required.");
                if (empty($p[$i]['roll'])) throw new Exception("Participant $i (Roll Number) is required.");
                if (empty($p[$i]['face'])) throw new Exception("Participant $i (Photo) is required.");
                if (empty($p[$i]['card'])) throw new Exception("Participant $i (ID Card) is required.");
            }

            // Validate maximum participants
            for ($i = $maxAllowed + 1; $i <= 4; $i++) {
                if (!empty($p[$i]['name'])) {
                    throw new Exception("The $module module only allows a maximum of $maxAllowed participants.");
                }
            }
        } else {
            if (empty($p[1]['name'])) throw new Exception("Team Leader name is required.");
        }

        // ---------------------------------------------------------
        // D. DUPLICATE CHECK
        // ---------------------------------------------------------
        $checkSql = "SELECT id FROM event_registrations WHERE user_id = ? AND module_selection = ? AND event_label = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("iss", $user_id, $module, $activeEventLabel);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("You are already registered for the " . $module . " module!");
        }
        $checkStmt->close();

        // ---------------------------------------------------------
        // E. DATABASE INSERTION
        // ---------------------------------------------------------
        $sql = "INSERT INTO event_registrations (
            user_id, institution_type, team_name, module_selection, brand_ambassador_code, fees_screenshot,
            participant1_name, participant1_contact, participant1_email, participant1_cnic, participant1_roll_number, participant1_face_image, participant1_id_card,
            participant2_name, participant2_contact, participant2_email, participant2_cnic, participant2_roll_number, participant2_face_image, participant2_id_card,
            participant3_name, participant3_contact, participant3_email, participant3_cnic, participant3_roll_number, participant3_face_image, participant3_id_card,
            participant4_name, participant4_contact, participant4_email, participant4_cnic, participant4_roll_number, participant4_face_image, participant4_id_card,
            participant5_name, participant5_contact, participant5_email, participant5_cnic, participant5_roll_number, participant5_face_image, participant5_id_card,
            participant6_name, participant6_contact, participant6_email, participant6_cnic, participant6_roll_number, participant6_face_image, participant6_id_card,
            event_label, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database Error: " . $conn->error);

        $stmt->bind_param("issssssssssssssssssssssssssssssssssssssssssssssss",
            $user_id, $institution, $teamName, $module, $brandCode, $feesImg,
            $p[1]['name'], $p[1]['contact'], $p[1]['email'], $p[1]['cnic'], $p[1]['roll'], $p[1]['face'], $p[1]['card'],
            $p[2]['name'], $p[2]['contact'], $p[2]['email'], $p[2]['cnic'], $p[2]['roll'], $p[2]['face'], $p[2]['card'],
            $p[3]['name'], $p[3]['contact'], $p[3]['email'], $p[3]['cnic'], $p[3]['roll'], $p[3]['face'], $p[3]['card'],
            $p[4]['name'], $p[4]['contact'], $p[4]['email'], $p[4]['cnic'], $p[4]['roll'], $p[4]['face'], $p[4]['card'],
            $p[5]['name'], $p[5]['contact'], $p[5]['email'], $p[5]['cnic'], $p[5]['roll'], $p[5]['face'], $p[5]['card'],
            $p[6]['name'], $p[6]['contact'], $p[6]['email'], $p[6]['cnic'], $p[6]['roll'], $p[6]['face'], $p[6]['card'],
            $activeEventLabel
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
            event_attendees_sync($conn, $registrationId, $syncParticipants, 'pending');
            $response['success'] = true;
            $response['message'] = 'Registration Submitted Successfully!';
        } else {
            throw new Exception("Database execution failed: " . $stmt->error);
        }
        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    ob_end_clean();
    echo json_encode($response);
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
    .inst-btn {
        width: 160px;
        height: 180px;          /* This controls the horizontal width you marked in red */
        margin: 10px;        /* Centers the button inside its column */
        padding: 20px 15px;    /* Vertical and horizontal inner spacing */
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        text-align: center;
        cursor: pointer;
        transition: 0.3s all ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }


    .inst-btn i {
        font-size: 3.5rem !important; /* Big icon */
        color: var(--accent) !important; /* Restores the bright green */
        margin-bottom: 15px !important;
    }

    .inst-btn h5 {
        border: none !important;      
        background: none !important;  
        transform: none !important;   
        color: #fff !important;
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
        padding: 0;
        font-family: 'Outfit', sans-serif;
        line-height: 1.3;
        white-space: normal; /* Allows text like "Non-NED Student" to wrap beautifully */

    }
    
    .inst-btn:hover {
        border-color: var(--accent);
        background: rgba(0, 255, 148, 0.08);
        box-shadow: 0 0 25px rgba(0, 255, 148, 0.15);
        transform: translateY(-5px);
    }


    .registration-section { padding-top: 140px; padding-bottom: 80px; }
    .glass-box {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        border-radius: 24px; padding: 40px; margin-bottom: 30px;
    }
    .form-label { color: #ccc; font-weight: 500; margin-bottom: 8px; }
    .text-danger { color: #ff4444 !important; }
    .form-select-dark {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        padding: 12px 16px;
    }
    .form-select-dark:focus {
        background-color: rgba(255, 255, 255, 0.08) !important;
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 255, 148, 0.15) !important;
        color: #fff !important;
    }
    .form-select-dark option {
        background-color: #1a1a1a;
        color: #fff;
    }
    .form-control-dark {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        padding: 12px 16px;
    }
    .form-control-dark:focus {
        background-color: rgba(255, 255, 255, 0.08) !important;
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 255, 148, 0.15) !important;
        color: #fff !important;
    }
    .form-control-dark::placeholder {
        color: #888 !important;
    }
    
</style>

<section class="registration-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="color:#fff; font-family:'Outfit'; font-size:3rem;">Event Registration</h2>
            <p style="color:#888;">Register your team for the upcoming competition.</p>
        </div>

        

        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <div id="step1" class="glass-box" style="width: 100%; margin: 0 auto;">
                    <h3 class="text-center text-white mb-5">Select Institution Type</h3>
                    
                    <?php if ($visibleCount == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-lock mb-3" style="font-size:3rem; color:#ff4444;"></i>
                            <h4 class="text-danger">Registrations are currently paused</h4>
                            <p class="text-muted">Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-4">
                            
                            <?php if($instVis['ned']): ?>
                            
                                <div class="inst-btn" data-type="NED University Student">
                                    <i class="fas fa-university"></i><h5>NED Student</h5>
                                </div>
                            
                            <?php endif; ?>

                            <?php if($instVis['non_ned']): ?>
                            
                                <div class="inst-btn" data-type="Non-NED University Student">
                                    <i class="fas fa-graduation-cap"></i><h5>Non-NED Student</h5>
                                </div>
                            
                            <?php endif; ?>

                            <?php if($instVis['college']): ?>
                            
                                <div class="inst-btn" data-type="College Student">
                                    <i class="fas fa-school"></i><h5>College Student</h5>
                                </div>
                            
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,0.08);">
                        <p class="mb-2" style="color:#aaa; font-size: 0.95rem;">For Queries? WhatsApp:</p>
                        <strong style="color:var(--accent); font-size: 1rem; letter-spacing: 1.5px;">
                            <i class="fab fa-whatsapp me-2"></i>+92 313 2017551
                        </strong>
                    </div>
                </div>

                <div id="step2" class="glass-box" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                        <span class="text-white">Type: <strong style="color:var(--accent)" id="displayType"></strong></span>
                        <button type="button" id="changeTypeBtn" class="btn btn-sm btn-outline-secondary">Change</button>
                    </div>

                    <form id="regForm">
                        <input type="hidden" name="institutionType" id="inputType">
                        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">

                        <h4 class="text-white mb-3"><i class="fas fa-users text-primary me-2"></i>Team Details</h4>
                        <div class="row g-3 mb-5">
                            <div class="col-md-6"><label class="form-label">Team Name </label><input type="text" name="teamName" class="form-control form-control-dark" required></div>
                            <div class="col-md-6"><label class="form-label">Module </label>
                                <!-- MODIFIED: Empty by default, populated by JS -->
                                <select name="moduleSelection" id="moduleSelect" class="form-select form-select-dark" required>
                                    <option value="">Choose Module...</option>
                                </select>
                            </div>
                            <!-- NEW FIELD: BRAND AMBASSADOR CODE -->
                            <div class="col-12"><label class="form-label" style="color: #00ff94;">Brand Ambassador Code (Optional)</label>
                                <input type="text" name="brand_ambassador_code" class="form-control form-control-dark" placeholder="Enter code if applicable">
                            </div>
                        </div>

                        <h4 class="text-white mb-3"><i class="fas fa-user-astronaut text-warning me-2"></i>Team Leader</h4>
                        <div class="row g-3 mb-5">
                            <div class="col-md-6"><label class="form-label">Name </label><input type="text" name="participant1_name" class="form-control form-control-dark" required></div>
                            <div class="col-md-6"><label class="form-label">Email </label><input type="email" name="participant1_email" class="form-control form-control-dark" required></div>
                            <div class="col-md-6"><label class="form-label">Phone </label><input type="text" name="participant1_contact" class="form-control form-control-dark" required></div>
                            <div class="col-md-6"><label class="form-label">CNIC </label><input type="text" name="participant1_cnic" class="form-control form-control-dark" required></div>
                            <div class="col-12"><label class="form-label">Roll Number </label><input type="text" name="participant1_roll_number" class="form-control form-control-dark" required></div>
                            <div class="col-md-6"><label class="form-label">Photo </label><input type="file" name="participant1_face_image" class="form-control form-control-dark" accept="image/jpeg, image/png, image/webp" required></div>
                            <div class="col-md-6"><label class="form-label">Institute ID Card </label><input type="file" name="participant1_id_card" class="form-control form-control-dark" accept="image/jpeg, image/png, image/webp" required></div>
                        </div>

                        <?php 
                        // UPDATED LOOP: Participant 2 and 3 are Mandatory, 4 is Optional
                        for($i=2; $i<=6; $i++): 
                            $isMandatory = ($i <= 3); // True for 2 and 3
                            $labelStatus = $isMandatory ? "(Required)" : "(Optional)";
                            $reqAttr = $isMandatory ? "required" : "";
                            $titleColor = $isMandatory ? "#fff" : "#888";
                        ?>
                        <div class="participant-block mb-4 participant-group">
                            <h5 class="section-title" style="color:<?php echo $titleColor; ?>; border-bottom:1px solid #333; padding-bottom:10px;">
                                Participant <?php echo $i; ?> <?php echo $labelStatus; ?>
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6"><input type="text" name="participant<?php echo $i; ?>_name" class="form-control form-control-dark" placeholder="Name" <?php echo $reqAttr; ?>></div>
                                <div class="col-md-6"><input type="email" name="participant<?php echo $i; ?>_email" class="form-control form-control-dark" placeholder="Email" <?php echo $reqAttr; ?>></div>
                                <div class="col-md-6"><input type="text" name="participant<?php echo $i; ?>_contact" class="form-control form-control-dark" placeholder="Phone" <?php echo $reqAttr; ?>></div>
                                <div class="col-md-6"><input type="text" name="participant<?php echo $i; ?>_cnic" class="form-control form-control-dark" placeholder="CNIC" <?php echo $reqAttr; ?>></div>
                                <div class="col-12"><input type="text" name="participant<?php echo $i; ?>_roll_number" class="form-control form-control-dark" placeholder="Roll Number" <?php echo $reqAttr; ?>></div>
                                <div class="col-md-6"><label class="form-label small">Photo</label><input type="file" name="participant<?php echo $i; ?>_face_image" class="form-control form-control-dark" accept="image/jpeg, image/png, image/webp" <?php echo $reqAttr; ?>></div>
                                <div class="col-md-6"><label class="form-label small">Institute ID Card</label><input type="file" name="participant<?php echo $i; ?>_id_card" class="form-control form-control-dark" accept="image/jpeg, image/png, image/webp" <?php echo $reqAttr; ?>></div>
                            </div>
                        </div>
                        <?php endfor; ?>

                        <div class="text-center mt-5">
                            <button type="submit" class="btn-clear" id="submitBtn">Submit Registration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="resultModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-body text-center p-5">
        <div id="modalIcon" class="mb-3"></div>
        <h3 id="modalTitle" class="text-white"></h3>
        <!-- Removed class="text-muted" to ensure color visibility -->
        <p id="modalMsg" style="font-size: 1.1rem;"></p>
        <button type="button" class="btn btn-outline-light mt-3" data-bs-dismiss="modal" onclick="location.reload()">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. DATA: Corrected Object Syntax (using {} instead of [])
        const universityModules = {
            "Code Rush": { min: 3, max: 4 },
            "Algo Masters": { min: 3, max: 4 },
            "Race with Code": { min: 3, max: 4 },
            "Design Sprint": { min: 3, max: 4 },
            "App Innovate": { min: 3, max: 4 },
            "Query Quest": { min: 3, max: 4 },
            "Bug Busters": { min: 3, max: 4 },
            "Web Wizards": { min: 3, max: 4 },
            "Blind Coding": { min: 3, max: 4 },
            "Pseudocode Builder": { min: 3, max: 4 },
            "Emoji Algorithm": { min: 3, max: 4 },
            "Maths Clash": { min: 1, max: 1 },
            "Valorant": { min: 5, max: 5 },
            "CS 2": { min: 5, max: 5 },
            "COD Mobile": { min: 5, max: 5 },
            "PUBG Mobile": { min: 4, max: 4 }
        };

        const collegeModules = {
            "AI Energy Saver Dashboard": { min: 2, max: 3 },
            "AI Defect Finder (Basic Vision ML)": { min: 2, max: 3 },
            "AI Cyber Alert Classifier": { min: 2, max: 3 },
            "AI Disaster Aid Planner": { min: 2, max: 3 },
            "AI Home Energy Advisor": { min: 2, max: 3 },
            "AI Data Insight Tool": { min: 2, max: 3 },
            "AI City Planner Map": { min: 2, max: 3 },
            "AI Image Checker (Simple Classifier)": { min: 2, max: 3 }
        };

        // 2. Button Click Handlers: Select Institution Type
        const buttons = document.querySelectorAll('.inst-btn');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                
                // Update Hidden Input and UI Label
                document.getElementById('inputType').value = type;
                document.getElementById('displayType').innerText = type;

                // LOGIC: Populate Dropdown based on Type
                const moduleSelect = document.getElementById('moduleSelect');
                moduleSelect.innerHTML = '<option value="">Choose Module...</option>'; // Reset

                const listToUse = (type === 'College Student') ? collegeModules : universityModules;

                // Fixed: Use Object.keys to iterate over the module names
                Object.keys(listToUse).forEach(modName => {
                    const option = document.createElement('option');
                    option.value = modName;
                    option.textContent = modName;
                    moduleSelect.appendChild(option);
                });

                // Transition UI
                document.getElementById('step1').style.display = 'none';
                document.getElementById('step2').style.display = 'block';
                window.scrollTo(0,0);
            });
        });

        // 3. Change Button Logic
        document.getElementById('changeTypeBtn').addEventListener('click', function() {
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step1').style.display = 'block';
        });

        // 4. Dynamic Field Toggling (Min/Max Logic)
        function adjustParticipantFields() {
            const selectedModule = document.getElementById('moduleSelect').value;
            const type = document.getElementById('inputType').value;
            const moduleData = (type === 'College Student') ? collegeModules : universityModules;

            if (!selectedModule || !moduleData[selectedModule]) return;

            const limits = moduleData[selectedModule]; 
            const groups = document.querySelectorAll('.participant-group');
        
            groups.forEach((group, index) => {
                const participantNumber = index + 2; // Participant 2, 3, 4
                const inputs = group.querySelectorAll('input');
                const title = group.querySelector('.section-title');

                if (participantNumber <= limits.max) {
                    group.style.display = 'block'; // Show if within max
                    
                    const isRequired = (participantNumber <= limits.min);
                    inputs.forEach(input => {
                        input.required = isRequired;
                    });

                    // Set header color and text based on requirement
                    if (isRequired) {
                        title.style.color = '#fff';
                        title.innerHTML = `Participant ${participantNumber} (Required)`;
                    } else {
                        title.style.color = '#888';
                        title.innerHTML = `Participant ${participantNumber} (Optional)`;
                    }
                } else {
                    group.style.display = 'none'; // Hide if above max
                    inputs.forEach(input => {
                        input.required = false;
                        input.value = ''; // Clear data for hidden fields
                    });
                }
            });
        }

        // 5. Submission Logic
        document.getElementById('regForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerText;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(this);

            fetch('event_registration.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json()) 
            .then(data => {
                if(data.success) {
                    showModal('success', 'Success!', 'Your team has been registered.');
                    document.getElementById('regForm').reset();
                } else {
                    showModal('error', 'Error', data.message);
                }
            })
            .catch(err => {
                showModal('error', 'Upload Failed', 'Check your internet or file sizes.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = originalText;
            });
        });

        // Event Listeners for Dynamic Changes
        document.getElementById('moduleSelect').addEventListener('change', adjustParticipantFields);
    });

    // 6. Modal UI Function (Glow and Visual Effects)
    function showModal(type, title, msg) {
        const iconDiv = document.getElementById('modalIcon');
        const titleEl = document.getElementById('modalTitle');
        const msgEl = document.getElementById('modalMsg');
        const modalContent = document.querySelector('#resultModal .modal-content');
        const modal = new bootstrap.Modal(document.getElementById('resultModal'));
        
        msgEl.style.fontWeight = 'bold';

        if(type === 'success') {
            iconDiv.innerHTML = '<i class="fas fa-check-circle" style="font-size:4rem; color: #00ff94; text-shadow: 0 0 20px rgba(0,255,148, 0.6);"></i>';
            msgEl.style.color = '#00ff94'; 
            modalContent.style.border = '1px solid #00ff94';
            modalContent.style.boxShadow = '0 0 30px rgba(0, 255, 148, 0.3)';
        } else {
            iconDiv.innerHTML = '<i class="fas fa-exclamation-circle" style="font-size:4rem; color: #ff4444; text-shadow: 0 0 20px rgba(255,68,68, 0.6);"></i>';
            msgEl.style.color = '#ff4444'; 
            modalContent.style.border = '1px solid #ff4444';
            modalContent.style.boxShadow = '0 0 30px rgba(255, 68, 68, 0.3)';
        }

        titleEl.innerText = title;
        msgEl.innerText = msg;
        modal.show();
    }
</script>

<?php include 'footer.php'; ?>


