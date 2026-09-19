<?php
// 1. START SESSION (Must be the very first line)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/social_attendees_helper.php';
require_once __DIR__ . '/social_registration_settings.php';

$socialOpen = social_registrations_open($conn);

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $_SESSION['user_id'];

// 2b. REGISTRATION WINDOW CHECK
if (!$socialOpen) {
    ?>
    <section style="padding-top: 140px; padding-bottom: 80px;">
        <div class="container">
            <div class="glass-panel p-4 p-md-5 text-center">
                <i class="fas fa-lock" style="font-size:3rem; color:#ff6a6a;"></i>
                <h2 class="text-white mt-3">We’re currently full</h2>
                <p class="text-muted mb-4">Thanks for the overwhelming interest! All available spots are booked right now. Please check back later for any openings.</p>
                <a href="dashboard.php" class="btn btn-outline-light">Back to Dashboard</a>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

// 3. CHECK EXISTING REGISTRATION
$check = $conn->query("SELECT id FROM social_registrations WHERE user_id = $user_id");
if ($check->num_rows > 0) {
    echo "<script>alert('You have already registered for Social Night!'); window.location.href='dashboard.php';</script>";
    exit;
}

// 4. HANDLE FORM SUBMISSION
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Fetch active event label
        $activeEventLabel = '';
        $eventRes = $conn->query("SELECT title FROM events WHERE status = 'upcoming' ORDER BY event_date DESC LIMIT 1");
        if ($eventRes && $eventRes->num_rows > 0) {
            $activeEventLabel = $eventRes->fetch_assoc()['title'];
        } else {
            $activeEventLabel = 'proxion_2026'; // fallback
        }
    
    $reg_type = $_POST['reg_type'];
    $amb_code = !empty($_POST['ambassador_code']) ? $_POST['ambassador_code'] : NULL;
    
    // Calculate Amount
    $amount = 500;
    if ($reg_type === 'participant') $amount = 0;
    if ($reg_type === 'group') $amount = 1200;

    // Create Directory
    $targetDir = "images/uploads/social/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    // --- OPTIMIZATION: Helper Function for Uploads ---
    function uploadFile($file, $prefix, $uid, $dir) {
        if (!isset($file['name']) || empty($file['name'])) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
        if (!in_array($ext, $allowed)) return false;
        
        $name = $prefix . "_" . $uid . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
            return $dir . $name;
        }
        return false;
    }

    // Capture Basic Info
    $p1_name = $_POST['name'];
    $p1_cnic = $_POST['cnic'];
    $p1_email = $_POST['email'];
    $p1_phone = $_POST['phone'];
    
    // Upload Images (Using the helper function)
    $p1_face = uploadFile($_FILES['face_image'], "face1", $user_id, $targetDir);
    $p1_card = uploadFile($_FILES['id_card'], "card1", $user_id, $targetDir);
    $pay_proof = uploadFile($_FILES['payment_proof'], "pay", $user_id, $targetDir);

    // Group Member Variables (Nullable)
    $p2_name = $_POST['p2_name'] ?? null; $p2_cnic = $_POST['p2_cnic'] ?? null;
    $p2_email = $_POST['p2_email'] ?? null; $p2_phone = $_POST['p2_phone'] ?? null;
    $p2_face = isset($_FILES['p2_face']) ? uploadFile($_FILES['p2_face'], "face2", $user_id, $targetDir) : null;
    $p2_card = isset($_FILES['p2_card']) ? uploadFile($_FILES['p2_card'], "card2", $user_id, $targetDir) : null;

    $p3_name = $_POST['p3_name'] ?? null; $p3_cnic = $_POST['p3_cnic'] ?? null;
    $p3_email = $_POST['p3_email'] ?? null; $p3_phone = $_POST['p3_phone'] ?? null;
    $p3_face = isset($_FILES['p3_face']) ? uploadFile($_FILES['p3_face'], "face3", $user_id, $targetDir) : null;
    $p3_card = isset($_FILES['p3_card']) ? uploadFile($_FILES['p3_card'], "card3", $user_id, $targetDir) : null;

    // Build participant payload (will also feed attendee table if available)
    $memberPayload = [[
        'label' => 'Primary',
        'name' => $p1_name,
        'email' => $p1_email,
        'phone' => $p1_phone,
        'cnic' => $p1_cnic,
        'face' => $p1_face,
        'card' => $p1_card
    ]];
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

    // Validate Required Files
    if ($p1_face && $p1_card && $pay_proof) {
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
            echo "<script>alert('Registration Submitted Successfully!'); window.location.href='dashboard.php';</script>";
        } else {
            $msg = "<div class='alert alert-danger'>DB Error: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>Upload failed. Please ensure images are valid (JPG/PNG).</div>";
    }
}
?>

<style>
    /* NEON THEME & BUTTONS */
    .form-label { color: #fff; font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; }
    .form-control-dark { background: #0b1120; border: 1px solid #333; color: #fff; padding: 12px; }
    .form-control-dark:focus { background: #0b1120; border-color: var(--accent); color: #fff; box-shadow: 0 0 10px rgba(0, 255, 148, 0.2); }
    
    .btn-neon-green {
        background: #00FF94; /* BRIGHT NEON GREEN */
        color: #000;
        font-weight: 800;
        font-size: 1.1rem;
        padding: 16px 30px;
        border: none;
        border-radius: 50px;
        text-transform: uppercase;
        letter-spacing: 1px;
        width: 100%;
        display: block;
        transition: 0.3s;
        box-shadow: 0 0 20px rgba(0, 255, 148, 0.4);
        cursor: pointer;
    }
    .btn-neon-green:hover {
        background: #00cc7a;
        box-shadow: 0 0 40px rgba(0, 255, 148, 0.7);
        transform: translateY(-2px);
    }

    /* TABS */
    .reg-tabs { display: flex; gap: 10px; margin-bottom: 30px; justify-content: center; }
    .tab-btn {
        background: rgba(255,255,255,0.05); border: 1px solid #444; color: #aaa;
        padding: 15px 25px; border-radius: 12px; cursor: pointer; transition: 0.3s; flex: 1; text-align: center;
    }
    .tab-btn.active {
        background: rgba(0,255,148,0.1); border-color: var(--accent); color: var(--accent); font-weight: bold;
        box-shadow: 0 0 20px rgba(0, 255, 148, 0.2);
    }
    .tab-price { display: block; font-size: 1.2rem; margin-top: 5px; }

    /* SECTIONS */
    .member-section { background: rgba(255,255,255,0.03); border: 1px dashed #444; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    .section-title { color: var(--accent); border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
    .upload-box { border: 1px dashed #555; padding: 15px; border-radius: 8px; text-align: center; background: rgba(0,0,0,0.2); }
    
    /* PAYMENT CARD */
    .payment-card {
        background: rgba(2, 12, 10, 0.6);
        border: 1px dashed var(--accent);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
    }
    .payment-header { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .payment-header i { font-size: 1.5rem; color: var(--accent); }
    .payment-header h4 { margin: 0; color: #fff; font-weight: 700; font-family: 'Outfit', sans-serif; font-size: 1.3rem; }
    .detail-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #7c899f; margin-bottom: 6px; font-weight: 600; }
    .detail-value { font-size: 1.1rem; color: #fff; font-weight: 700; margin-bottom: 24px; font-family: 'Outfit', sans-serif; }
    .detail-value.highlight { color: var(--accent); font-size: 1.4rem; text-shadow: 0 0 10px rgba(0,255,148,0.3); }
</style>

<section style="padding-top: 140px; padding-bottom: 80px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="glass-panel p-4 p-md-5">
                    
                    <h2 class="text-center text-white mb-2" style="font-family:'Outfit'">Social Night Pass</h2>
                    <p class="text-center text-muted mb-4">Select your pass type below.</p>
                    <?php echo $msg; ?>

                    <form method="POST" enctype="multipart/form-data" id="socialForm" action="">
                        
                        <div class="reg-tabs">
                            <div class="tab-btn active" onclick="selectType('standard')" id="btn-standard">
                                <div>Individual</div>
                                <span class="tab-price">PKR 500</span>
                            </div>
                            <div class="tab-btn" onclick="selectType('participant')" id="btn-participant">
                                <div>Event Participant</div>
                                <span class="tab-price">PKR 0</span>
                            </div>
                            <div class="tab-btn" onclick="selectType('group')" id="btn-group">
                                <div>Group (3 People)</div>
                                <span class="tab-price">PKR 1200</span>
                            </div>
                        </div>

                        <input type="hidden" name="reg_type" id="reg_type" value="standard">

                        <div class="mb-4">
                            <label class="form-label">Brand Ambassador Code (Optional)</label>
                            <input type="text" name="ambassador_code" class="form-control form-control-dark" placeholder="Enter code if applicable">
                        </div>

                        <div class="member-section">
                            <h5 class="section-title">Person 1 (Primary)</h5>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control form-control-dark" required></div>
                                <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="cnic" class="form-control form-control-dark" required></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control form-control-dark" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control form-control-dark" required></div>
                                <div class="col-md-6"><div class="upload-box"><label class="form-label mb-2"><i class="fas fa-camera text-success"></i> Photo</label><input type="file" name="face_image" class="form-control form-control-dark" accept="image/*" required></div></div>
                                <div class="col-md-6"><div class="upload-box"><label class="form-label mb-2"><i class="fas fa-id-card text-info"></i> ID Card</label><input type="file" name="id_card" class="form-control form-control-dark" accept="image/*" required></div></div>
                            </div>
                        </div>

                        <div id="group-fields" style="display:none;">
                            <div class="member-section">
                                <h5 class="section-title">Person 2</h5>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="p2_name" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="p2_cnic" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="p2_email" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="p2_phone" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><div class="upload-box"><label class="form-label"><i class="fas fa-camera text-success"></i> Photo</label><input type="file" name="p2_face" class="form-control form-control-dark group-req" accept="image/*"></div></div>
                                    <div class="col-md-6"><div class="upload-box"><label class="form-label"><i class="fas fa-id-card text-info"></i> ID Card</label><input type="file" name="p2_card" class="form-control form-control-dark group-req" accept="image/*"></div></div>
                                </div>
                            </div>
                            <div class="member-section">
                                <h5 class="section-title">Person 3</h5>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="p3_name" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" name="p3_cnic" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="p3_email" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="p3_phone" class="form-control form-control-dark group-req"></div>
                                    <div class="col-md-6"><div class="upload-box"><label class="form-label"><i class="fas fa-camera text-success"></i> Photo</label><input type="file" name="p3_face" class="form-control form-control-dark group-req" accept="image/*"></div></div>
                                    <div class="col-md-6"><div class="upload-box"><label class="form-label"><i class="fas fa-id-card text-info"></i> ID Card</label><input type="file" name="p3_card" class="form-control form-control-dark group-req" accept="image/*"></div></div>
                                </div>
                            </div>
                        </div>

                        <div class="payment-card">
                            <div class="payment-header"><i class="fas fa-wallet"></i><h4>Bank Details</h4></div>
                            <div class="row g-3">
                                <div class="col-md-12"><div class="detail-label">Account Name</div><div class="detail-value">Sohaib Waseem</div></div>
                                <div class="col-md-6"><div class="detail-label">Bank / Wallet</div><div class="detail-value">NayaPay</div></div>
                                <div class="col-md-6"><div class="detail-label">Account Number</div><div class="detail-value" style="font-family: monospace;">03132017551</div></div>
                                <div class="col-12"><div class="detail-label">IBAN</div><div class="detail-value" style="font-family: monospace; color:#ccc;">PK98NAYA1234503132017551</div></div>
                                <div class="col-12 mt-3 pt-3" style="border-top: 1px dashed rgba(255,255,255,0.2);">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="detail-label mb-0" style="font-size: 1rem; color: #fff;">TOTAL PAYABLE</div>
                                        <div class="detail-value highlight mb-0" id="display-amount" style="font-size: 1.8rem;">PKR 500</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Upload Payment Proof</label>
                            <input type="file" name="payment_proof" class="form-control form-control-dark" accept="image/*" required>
                            <small class="text-muted">Please upload clear proof of transaction.</small>
                        </div>

                        <button type="submit" class="btn-neon-green">SUBMIT REGISTRATION</button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function selectType(type) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-' + type).classList.add('active');
        document.getElementById('reg_type').value = type;

        let amount = 500;
        if(type === 'participant') amount = 0;
        if(type === 'group') amount = 1200;
        document.getElementById('display-amount').innerHTML = 'PKR ' + amount;

        const groupDiv = document.getElementById('group-fields');
        const groupInputs = document.querySelectorAll('.group-req');
        if(type === 'group') {
            groupDiv.style.display = 'block';
            groupInputs.forEach(i => i.setAttribute('required', 'true'));
        } else {
            groupDiv.style.display = 'none';
            groupInputs.forEach(i => i.removeAttribute('required'));
        }
    }
</script>

<?php include 'footer.php'; ?>
