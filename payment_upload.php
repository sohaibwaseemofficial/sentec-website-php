<?php
include 'header.php';
include 'db_connection.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. GET REGISTRATION DATA (Updated for multiple registrations)
$user_id = $_SESSION['user_id'];
$reg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reg_id > 0) {
    // Fetch the SPECIFIC registration the user clicked on
    $query = "SELECT * FROM event_registrations WHERE id = ? AND user_id = ? AND status = 'approved' LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $reg_id, $user_id);
} else {
    // Fallback: Fetch the most recent approved registration if no ID is provided
    $query = "SELECT * FROM event_registrations WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();

if (!$reg) {
    echo "<script>alert('Please select an approved registration to pay for.'); window.location='dashboard.php';</script>";
    exit;
}

// 3. HANDLE UPLOAD
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['payment_proof'])) {
    
    $targetDir = __DIR__ . '/images/uploads/payments/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $file = $_FILES['payment_proof'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = "<div class='alert alert-danger'>Upload Error. Try again.</div>";
    } elseif (!in_array($ext, $allowed)) {
        $msg = "<div class='alert alert-danger'>Only JPG, PNG, WEBP allowed.</div>";
    } else {
        $filename = "pay_" . $reg['id'] . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            // Update DB
            $dbPath = "images/uploads/payments/" . $filename;
            $update = $conn->prepare("UPDATE event_registrations SET payment_proof = ?, payment_status = 'submitted' WHERE id = ?");
            $update->bind_param("si", $dbPath, $reg['id']);
            $update->execute();
            
            // Refresh
            echo "<script>window.location.href='payment_upload.php';</script>";
        } else {
            $msg = "<div class='alert alert-danger'>Failed to save file.</div>";
        }
    }
}
?>

<style>
    .payment-section {
        padding-top: 140px;
        padding-bottom: 80px;
        min-height: 100vh;
        background: radial-gradient(circle at top, rgba(10, 20, 40, 0.55), rgba(2, 5, 12, 0.95));
    }
    .glass-panel {
        background: linear-gradient(140deg, rgba(14, 24, 46, 0.9), rgba(5, 10, 24, 0.95));
        backdrop-filter: blur(24px);
        border: 1px solid rgba(0, 255, 148, 0.1);
        border-radius: 26px;
        padding: 46px;
        box-shadow: 0 30px 70px rgba(0, 0, 0, 0.55);
    }
    .info-box {
        background: rgba(0, 255, 148, 0.06);
        border: 1px dashed rgba(0, 255, 148, 0.5);
        padding: 28px;
        border-radius: 18px;
        margin-bottom: 32px;
        text-align: left;
        box-shadow: inset 0 0 25px rgba(0, 255, 148, 0.08);
    }
    .status-badge {
        padding: 9px 24px;
        border-radius: 999px;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.9rem;
        display: inline-block;
        margin-bottom: 24px;
        letter-spacing: 0.7px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.4);
    }
    .status-submitted {
        background: rgba(255, 187, 51, 0.18);
        color: #ffbb33;
        border: 1px solid rgba(255, 187, 51, 0.6);
        box-shadow: 0 0 18px rgba(255, 187, 51, 0.35);
    }
    .status-confirmed {
        background: rgba(0, 255, 148, 0.2);
        color: #04211a;
        border: 1px solid rgba(0, 255, 148, 0.7);
        box-shadow: 0 0 22px rgba(0, 255, 148, 0.4);
    }
    .status-rejected {
        background: rgba(255, 68, 68, 0.22);
        color: #ff8484;
        border: 1px solid rgba(255, 68, 68, 0.7);
        box-shadow: 0 0 20px rgba(255, 68, 68, 0.35);
    }
    .status-pending {
        background: rgba(255, 255, 255, 0.08);
        color: #d5e9ff;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    /* Glowing Button Style */
    .btn-glow {
        background: rgba(0, 255, 148, 0.16);
        border: 1px solid rgba(0, 255, 148, 0.6);
        color: #00ffd1;
        font-weight: 800;
        text-transform: uppercase;
        padding: 15px 30px;
        border-radius: 999px;
        transition: 0.3s;
        width: 100%;
        display: block;
        letter-spacing: 1px;
        box-shadow: 0 0 22px rgba(0, 255, 148, 0.25);
    }
    .btn-glow:hover {
        background: rgba(0, 255, 148, 0.27);
        color: #031610;
        box-shadow: 0 0 32px rgba(0, 255, 148, 0.45);
    }

    .btn-glow.secondary {
        background: rgba(255, 187, 51, 0.14);
        border-color: rgba(255, 187, 51, 0.55);
        color: #ffbb33;
        box-shadow: 0 0 20px rgba(255, 187, 51, 0.3);
    }

    .btn-glow.secondary:hover {
        background: rgba(255, 187, 51, 0.24);
        color: #120b02;
        box-shadow: 0 0 32px rgba(255, 187, 51, 0.45);
    }

    .bank-field label {
        color: #7da6c9;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.9px;
    }

    .bank-field .bank-value {
        color: #f4f8ff;
        font-size: 1.05rem;
        font-weight: 600;
    }

    .bank-field .bank-value-accent {
        color: #00ffd1;
        font-size: 1.1rem;
        letter-spacing: 0.5px;
    }

    .bank-field .bank-value-mono {
        font-family: monospace;
        letter-spacing: 1px;
        color: #d2fdf0;
    }

    @media (max-width: 991px) {
        .glass-panel { padding: 36px; }
    }

    @media (max-width: 767px) {
        .payment-section { padding-top: 120px; padding-bottom: 60px; }
        .glass-panel { padding: 28px; border-radius: 22px; }
        .status-badge { font-size: 0.8rem; margin-bottom: 20px; }
        .info-box { padding: 22px; }
    }

    @media (max-width: 575px) {
        .glass-panel { padding: 22px; }
        .status-badge { padding: 8px 18px; }
        .btn-glow { padding: 14px 22px; }
    }
</style>

<section class="payment-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="glass-panel text-center">
                    
                    <h2 style="color:#fff; font-family:'Outfit'; margin-bottom: 10px;">Payment Verification</h2>
                    <p style="color:#aaa; margin-bottom: 30px;">Complete your registration by submitting the fee.</p>

                    <?php
                    $pStatus = $reg['payment_status'] ?: 'pending';
                    echo "<div class='status-badge status-$pStatus'>Status: " . ucfirst($pStatus) . "</div>";
                    ?>

                    <?php if ($pStatus == 'confirmed'): ?>
                        
                        <div class="py-5">
                            <i class="fas fa-check-circle" style="font-size: 5rem; color: #00FF94; margin-bottom: 20px; text-shadow: 0 0 30px rgba(0,255,148,0.4);"></i>
                            <h3 class="text-white">Payment Confirmed!</h3>
                            <p class="text-muted">Your slot is secured. See you at the event!</p>
                            <a href="dashboard.php" class="btn-glow mt-3">Back to Dashboard</a>
                        </div>

                    <?php else: ?>

                        <div class="info-box">
                            <h4 style="color:#fff; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                                <i class="fas fa-wallet me-2" style="color:var(--accent);"></i> Bank Details
                            </h4>
                            
                            <div class="row g-3">
                                <div class="col-12 bank-field">
                                    <label>Account Title</label>
                                    <div class="bank-value">NEDUET CONTROLLER STUDENT AFFAIRS</div>
                                </div>
                                <div class="col-md-6 bank-field">
                                    <label>Bank Name</label>
                                    <div class="bank-value">Habib Metropolitan Bank Limited</div>
                                </div>
                                <div class="col-md-6 bank-field">
                                    <label>Branch Code</label>
                                    <div class="bank-value">50</div>
                                </div>
                                <div class="col-12 bank-field">
                                    <label>Account Number</label>
                                    <div class="bank-value-accent bank-value-mono">6-99-72-29314-714-262131</div>
                                </div>
                                <div class="col-12 bank-field">
                                    <label>IBAN</label>
                                    <div class="bank-value-mono">PK73MPBL9972477140262131</div>
                                </div>
                                <div class="col-12 bank-field">
                                    <label>Swift Code</label>
                                    <div class="bank-value">MPBLPKKA050</div>
                                </div>
                                <div class="col-12 bank-field">
                                    <label>Bank Address</label>
                                    <div class="bank-value">University Road Branch, Karachi</div>
                                </div>
                            </div>
                        </div>

                        <?php echo $msg; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4 text-start">
                                <label class="form-label" style="color: #fff !important; font-weight:600; font-size:1.1rem;">
                                    Upload Screenshot
                                </label>
                                <input type="file" name="payment_proof" class="form-control form-control-dark" required accept="image/*" style="border: 1px solid #444;">
                                <small style="color:#888;">Accepted: JPG, PNG, WEBP</small>
                            </div>
                            
                            <button type="submit" class="btn-glow">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Submit Proof
                            </button>
                        </form>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
