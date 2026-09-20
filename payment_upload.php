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
    .payment-container {
        max-width: 680px;
        margin: 0 auto;
        padding: 40px 20px 80px;
        position: relative;
        z-index: 1;
    }
    .payment-box {
        background: #0d0d0d;
        border: 1px solid var(--border);
        padding: 36px;
        position: relative;
    }
    .payment-box::before {
        content: '';
        position: absolute;
        top: -1px;
        left: -1px;
        width: 14px;
        height: 14px;
        border-top: 2px solid #f15a24;
        border-left: 2px solid #f15a24;
    }
    .info-box {
        background: #080808;
        border: 1px solid var(--border);
        padding: 24px;
        margin-bottom: 28px;
        text-align: left;
    }
    .status-badge {
        padding: 4px 12px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 20px;
    }
    .status-submitted {
        background: rgba(241, 90, 36, 0.1);
        color: #f15a24;
        border: 1px solid rgba(241, 90, 36, 0.5);
    }
    .status-confirmed {
        background: rgba(52, 211, 153, 0.1);
        color: #34d399;
        border: 1px solid rgba(52, 211, 153, 0.5);
    }
    .status-rejected {
        background: rgba(248, 113, 113, 0.1);
        color: #f87171;
        border: 1px solid rgba(248, 113, 113, 0.5);
    }
    .status-pending {
        background: rgba(255, 255, 255, 0.05);
        color: #aaa;
        border: 1px solid var(--border);
    }
    .btn-submit-pay {
        background: #f15a24;
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.82rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 14px 28px;
        border: none;
        width: 100%;
        display: block;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-submit-pay:hover {
        background: #fff;
        color: #000;
    }
    .bank-field label {
        color: #888;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        display: block;
        margin-bottom: 4px;
    }
    .bank-field .bank-value {
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
    }
    .bank-field .bank-value-accent {
        color: #f15a24;
        font-size: 1.1rem;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
    }
    .bank-field .bank-value-mono {
        font-family: 'IBM Plex Mono', monospace;
        color: #eee;
    }
</style>

<div class="secondary-hero">
    <div class="secondary-hero-eyebrow">PORTAL // TRANSACTION VERIFICATION</div>
    <h1 class="secondary-hero-title">Upload <span class="accent-word">payment receipt.</span></h1>
    <p class="secondary-hero-sub">Submit your transaction proof for approved event registration verification.</p>
</div>

<div class="payment-container">
    <div class="payment-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span style="font-family:'IBM Plex Mono', monospace; font-size:0.75rem; color:var(--orange); text-transform:uppercase; letter-spacing:0.1em;">
                REF // REG-<?php echo str_pad($reg['id'], 5, '0', STR_PAD_LEFT); ?>
            </span>
            <span class="status-badge <?php echo 'status-' . ($reg['payment_status'] ?? 'pending'); ?>">
                STATUS: <?php echo strtoupper($reg['payment_status'] ?? 'PENDING'); ?>
            </span>
        </div>

        <div class="info-box">
            <div style="font-family:'IBM Plex Mono', monospace; font-size:0.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px;">
                TRANSFER DETAILS
            </div>
            <div class="row g-3">
                <div class="col-sm-6 bank-field">
                    <label>Bank Name</label>
                    <div class="bank-value">Meezan Bank Limited</div>
                </div>
                <div class="col-sm-6 bank-field">
                    <label>Account Title</label>
                    <div class="bank-value">SENTEC NEDUET</div>
                </div>
                <div class="col-sm-6 bank-field">
                    <label>Account Number</label>
                    <div class="bank-value-mono">01090105391234</div>
                </div>
                <div class="col-sm-6 bank-field">
                    <label>Fee Amount</label>
                    <div class="bank-value-accent">PKR <?php echo htmlspecialchars($reg['fee'] ?? '1500'); ?></div>
                </div>
                <div class="col-12 bank-field">
                    <label>IBAN</label>
                    <div class="bank-value-mono">PK73MPBL9972477140262131</div>
                </div>
                <div class="col-12 bank-field">
                    <label>Branch / Address</label>
                    <div class="bank-value">University Road Branch, NED University Campus</div>
                </div>
            </div>
        </div>

        <?php echo $msg; ?>

        <?php if (!empty($reg['payment_proof'])): ?>
            <div class="mb-4 text-center p-3" style="border:1px dashed var(--border); background:var(--ink-2);">
                <div style="font-family:'IBM Plex Mono', monospace; font-size:0.75rem; color:var(--muted); margin-bottom:8px;">PREVIOUS PROOF SUBMITTED</div>
                <a href="<?php echo htmlspecialchars($reg['payment_proof']); ?>" target="_blank" style="color:var(--orange); font-size:0.85rem; text-decoration:underline;">View Uploaded Screenshot &nearr;</a>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4 text-start">
                <label style="font-family:'IBM Plex Mono', monospace; font-size:0.75rem; color:var(--paper); text-transform:uppercase; letter-spacing:0.08em; display:block; margin-bottom:6px;">
                    Select Receipt / Screenshot (JPG, PNG, WEBP)
                </label>
                <input type="file" name="payment_proof" class="form-control" required accept="image/*" 
                       style="background:var(--ink-2); border:1px solid var(--border); color:var(--paper); font-size:0.85rem; border-radius:0; padding:10px;">
            </div>
            
            <button type="submit" class="btn-submit-pay">
                SUBMIT TRANSACTION PROOF &rarr;
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>

