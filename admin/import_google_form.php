<?php
@set_time_limit(600);
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '256M');

include 'header.php';
include_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';
require_once __DIR__ . '/../image_utils.php';

$ignoredEmails = [
    'valomohid@gmail.com',
    'team.bestroyers@gmail.com'
];

function clean_phone_number(?string $str): string {
    if (!$str) return '';
    $str = preg_replace('/[\x{202a}\x{202c}\x{200e}\x{200f}\xa0]/u', ' ', $str);
    $str = trim($str);
    if (stripos($str, 'e') !== false) {
        $val = floatval($str);
        if ($val > 0) $str = number_format($val, 0, '', '');
    }
    $clean = preg_replace('/[\s\-+]/', '', $str);
    if (strpos($clean, '92') === 0) {
        $clean = '0' . substr($clean, 2);
    } elseif (strpos($clean, '0') !== 0 && strlen($clean) === 10) {
        $clean = '0' . $clean;
    }
    return $clean;
}

function clean_roll_number(?string $str): string {
    if (!$str) return 'NEDIAN';
    $str = trim($str);
    if (substr($str, -2) === '.0' && is_numeric(substr($str, 0, -2))) {
        $str = substr($str, 0, -2);
    }
    return !empty($str) ? $str : 'NEDIAN';
}

function extract_gdrive_id(?string $url): string {
    if (!$url) return '';
    if (preg_match('/(?:id=|\/d\/)([a-zA-Z0-9_\-]+)/', $url, $m)) {
        return $m[1];
    }
    return '';
}

function fetch_and_upload_drive_image(?string $urlOrId, string $folder = 'sentec_uploads/social_registrations'): ?string {
    $fileId = extract_gdrive_id($urlOrId);
    if (empty($fileId)) {
        // If it's already a direct or cloudinary URL, return as is
        if (!empty($urlOrId) && (strpos($urlOrId, 'http://') === 0 || strpos($urlOrId, 'https://') === 0)) {
            return $urlOrId;
        }
        return null;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'gdrive_import_');
    $downloadUrl = 'https://drive.google.com/uc?export=download&id=' . $fileId;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($data) && stripos(substr($data, 0, 150), '<html') === false) {
        file_put_contents($tmpPath, $data);
        $cloudinaryUrl = upload_to_cloudinary($tmpPath, $folder);
        @unlink($tmpPath);
        if ($cloudinaryUrl) {
            return $cloudinaryUrl;
        }
    }

    @unlink($tmpPath);
    return null;
}

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_google_form') {
    $rows = [];
    $sourceDesc = '';

    // Check if CSV/XLSX file was uploaded
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedTmp = $_FILES['csv_file']['tmp_name'];
        $uploadedName = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
        $sourceDesc = "Uploaded File: " . htmlspecialchars($uploadedName);

        if ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($uploadedTmp, 'r');
            if ($handle) {
                // Read lines with fgetcsv
                while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                    // Check if entire row was captured in first element (semicolon or tab separated)
                    if (count($data) === 1 && strpos($data[0], "\t") !== false) {
                        $data = explode("\t", $data[0]);
                    }
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } else {
            $report = [
                'success' => false,
                'message' => 'Please upload a CSV file (.csv) exported from Google Sheets. Tip: Open the Google Sheet -> File -> Download -> Comma-separated values (.csv).'
            ];
        }
    } elseif (!empty($_POST['csv_text'])) {
        $sourceDesc = "Pasted Google Sheets Data";
        $lines = preg_split('/\r\n|\r|\n/', trim($_POST['csv_text']));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            // Check if tab-separated (direct copy from Google Sheets)
            if (strpos($line, "\t") !== false) {
                $rows[] = explode("\t", $line);
            } else {
                $rows[] = str_getcsv($line);
            }
        }
    } else {
        $report = [
            'success' => false,
            'message' => 'No CSV file selected or pasted data provided.'
        ];
    }

    if (!empty($rows) && empty($report['message'])) {
        $importedCount = 0;
        $skippedCount  = 0;
        $errorCount    = 0;
        $details       = [];

        // Identify header columns if present
        $firstRow = $rows[0];
        $isHeader = false;
        $colMap = [
            'timestamp' => 0,
            'acc_email' => 1,
            'name'      => 2,
            'email'     => 3,
            'phone'     => 4,
            'pay'       => 5,
            'dept'      => 6,
            'roll'      => 7,
            'uni'       => 8,
            'face'      => 9,
            'card'      => 10
        ];

        foreach ($firstRow as $idx => $cell) {
            $c = strtolower(trim($cell));
            if (strpos($c, 'timestamp') !== false) { $colMap['timestamp'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'username') !== false || strpos($c, 'account email') !== false) { $colMap['acc_email'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'full name') !== false || ($c === 'name')) { $colMap['name'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'email') !== false && !isset($seenEmail)) { $colMap['email'] = $idx; $seenEmail = true; $isHeader = true; }
            elseif (strpos($c, 'phone') !== false || strpos($c, 'whatsapp') !== false || strpos($c, 'contact') !== false) { $colMap['phone'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'payment') !== false || strpos($c, 'screenshot') !== false || strpos($c, 'receipt') !== false || strpos($c, 'proof') !== false) { $colMap['pay'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'department') !== false || strpos($c, 'dept') !== false) { $colMap['dept'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'roll') !== false || strpos($c, 'cnic') !== false) { $colMap['roll'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'face') !== false || strpos($c, 'passport') !== false || strpos($c, 'picture') !== false) { $colMap['face'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'card') !== false || strpos($c, 'student id') !== false) { $colMap['card'] = $idx; $isHeader = true; }
            elseif (strpos($c, 'university') !== false || strpos($c, 'institute') !== false) { $colMap['uni'] = $idx; $isHeader = true; }
        }

        $startIndex = $isHeader ? 1 : 0;

        // Prepare statements
        $checkStmt = $conn->prepare("SELECT id, full_name, email FROM social_registrations WHERE email = ? LIMIT 1");
        $userStmt  = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        
        $insertSql = "INSERT INTO social_registrations 
            (user_id, full_name, email, phone, cnic, face_image, id_card_image, payment_proof, payment_status, 
             registration_type, ambassador_code, total_amount,
             participant2_name, participant2_email, participant2_phone, participant2_cnic, participant2_face, participant2_card,
             participant3_name, participant3_email, participant3_phone, participant3_cnic, participant3_face, participant3_card,
             event_label) 
            VALUES (?,?,?,?,?,?,?,?, 'submitted', ?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?)";
        $insertStmt = $conn->prepare($insertSql);

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || count($row) < 3) continue;

            $nameVal     = trim($row[$colMap['name']] ?? '');
            $accEmailVal = trim($row[$colMap['acc_email']] ?? '');
            $formEmailVal= trim($row[$colMap['email']] ?? '');
            $phoneVal    = trim($row[$colMap['phone']] ?? '');
            $payVal      = trim($row[$colMap['pay']] ?? '');
            $deptVal     = trim($row[$colMap['dept']] ?? '');
            $rollVal     = trim($row[$colMap['roll']] ?? '');
            $uniVal      = trim($row[$colMap['uni']] ?? '');
            $faceVal     = trim($row[$colMap['face']] ?? '');
            $cardVal     = trim($row[$colMap['card']] ?? '');

            // Fallback for dynamic columns (if col 8 is a Drive URL, it is Face Photo!)
            if (empty($faceVal) && (strpos($uniVal, 'drive.google.com') !== false || strpos($uniVal, 'id=') !== false)) {
                $faceVal = $uniVal;
                $uniVal = 'NED';
            }

            // Determine email
            $email = $formEmailVal;
            if (empty($email) || strpos($email, '@') === false) {
                $email = $accEmailVal;
            }
            $email = strtolower(trim($email));
            if (substr($email, -4) === '.con') {
                $email = substr($email, 0, -4) . '.com';
            }

            if (empty($nameVal) || empty($email) || strpos($email, '@') === false) {
                continue; // Skip invalid or empty row
            }

            // Check if ignored email
            if (in_array($email, $ignoredEmails, true)) {
                $skippedCount++;
                $details[] = [
                    'row'    => $i + 1,
                    'name'   => $nameVal,
                    'email'  => $email,
                    'status' => 'SKIPPED',
                    'note'   => 'In Ignored / Excluded list'
                ];
                continue;
            }

            // Check if already in database
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            if ($existing) {
                $skippedCount++;
                $details[] = [
                    'row'    => $i + 1,
                    'name'   => $nameVal,
                    'email'  => $email,
                    'status' => 'SKIPPED',
                    'note'   => "Already exists in database (ID: #{$existing['id']})"
                ];
                continue;
            }

            // Clean fields
            $phone = clean_phone_number($phoneVal);
            $roll  = clean_roll_number($rollVal);

            // Fetch images and upload directly to Cloudinary CDN
            $payProofUrl = fetch_and_upload_drive_image($payVal, 'sentec_uploads/social_registrations');
            if (!$payProofUrl) {
                $payProofUrl = 'images/uploads/social/default_receipt.webp';
            }

            $faceUrl = !empty($faceVal) ? fetch_and_upload_drive_image($faceVal, 'sentec_uploads/social_registrations') : null;
            $cardUrl = !empty($cardVal) ? fetch_and_upload_drive_image($cardVal, 'sentec_uploads/social_registrations') : null;

            // Lookup user_id if registered
            $userId = null;
            $userStmt->bind_param('s', $email);
            $userStmt->execute();
            $uRow = $userStmt->get_result()->fetch_assoc();
            if ($uRow) {
                $userId = (int)$uRow['id'];
            }

            $regType = 'standard';
            $ambCode = 'GOOGLE_FORM';
            $amount  = 500;
            $eventLabel = 'RUH-E-RAQS';

            $p2_name = null; $p2_email = null; $p2_phone = null; $p2_cnic = null; $p2_face = null; $p2_card = null;
            $p3_name = null; $p3_email = null; $p3_phone = null; $p3_cnic = null; $p3_face = null; $p3_card = null;

            $types = 'isssssssssisssssssssssss';
            $insertStmt->bind_param(
                $types,
                $userId,
                $nameVal,
                $email,
                $phone,
                $roll,
                $faceUrl,
                $cardUrl,
                $payProofUrl,
                $regType,
                $ambCode,
                $amount,
                $p2_name, $p2_email, $p2_phone, $p2_cnic, $p2_face, $p2_card,
                $p3_name, $p3_email, $p3_phone, $p3_cnic, $p3_face, $p3_card,
                $eventLabel
            );

            if ($insertStmt->execute()) {
                $newId = $conn->insert_id;
                // Sync attendees table
                $memberPayload = [
                    [
                        'label' => 'Person 1',
                        'name'  => $nameVal,
                        'email' => $email,
                        'phone' => $phone,
                        'cnic'  => $roll,
                        'face'  => $faceUrl,
                        'card'  => $cardUrl
                    ]
                ];
                social_attendees_sync($conn, $newId, $memberPayload, $payProofUrl, 'submitted');

                if (function_exists('log_admin_action')) {
                    log_admin_action('IMPORT_GOOGLE_FORM', "Imported Social Registration #$newId for $nameVal ($email) with Cloudinary images");
                }

                $importedCount++;
                $details[] = [
                    'row'       => $i + 1,
                    'name'      => $nameVal,
                    'email'     => $email,
                    'status'    => 'IMPORTED',
                    'id'        => $newId,
                    'pay_url'   => $payProofUrl,
                    'note'      => "Synced to Cloudinary & Attendee Pass Generated"
                ];
            } else {
                $errorCount++;
                $details[] = [
                    'row'    => $i + 1,
                    'name'   => $nameVal,
                    'email'  => $email,
                    'status' => 'ERROR',
                    'note'   => "DB Error: " . $insertStmt->error
                ];
            }
        }

        $report = [
            'success'   => true,
            'source'    => $sourceDesc,
            'total'     => count($rows) - $startIndex,
            'imported'  => $importedCount,
            'skipped'   => $skippedCount,
            'errors'    => $errorCount,
            'details'   => $details
        ];
    }
}
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="text-white fw-bold m-0">
                <i class="fas fa-file-import text-info me-2"></i> Import Google Form Registrations
            </h2>
            <p class="text-muted m-0">
                Export Google Form responses to CSV and sync them directly to Cloudinary and the live database. No Python, no libraries, no passwords required!
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_social.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i> Back to Social Management
            </a>
        </div>
    </div>

    <!-- Step-by-Step Instructions -->
    <div class="card bg-dark border-secondary mb-4 shadow">
        <div class="card-body">
            <h5 class="text-warning fw-bold mb-3">
                <i class="fas fa-info-circle me-2"></i> How your friend or team members can use this (Zero Setup):
            </h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(255,255,255,0.03); border-left: 3px solid #00E5FF;">
                        <span class="badge bg-info text-dark mb-2">Step 1</span>
                        <h6 class="text-white fw-bold">Open Google Sheet</h6>
                        <p class="text-muted small m-0">
                            Open the Google Sheet linked to the Google Form, click <strong>File</strong> &rarr; <strong>Download</strong> &rarr; <strong>Comma-separated values (.csv)</strong>.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(255,255,255,0.03); border-left: 3px solid #00FF94;">
                        <span class="badge bg-success mb-2">Step 2</span>
                        <h6 class="text-white fw-bold">Upload CSV Here</h6>
                        <p class="text-muted small m-0">
                            Select the downloaded <code>.csv</code> file below and click <strong>"Start Cloud Sync & Import"</strong>.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(255,255,255,0.03); border-left: 3px solid #FF9900;">
                        <span class="badge bg-warning text-dark mb-2">Step 3</span>
                        <h6 class="text-white fw-bold">Automatic Cloud Sync</h6>
                        <p class="text-muted small m-0">
                            The server downloads Google Drive photos, uploads them to <strong>Cloudinary CDN</strong>, creates QR gate passes, and skips duplicates automatically!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Report if action was performed -->
    <?php if ($report): ?>
        <?php if (!empty($report['success'])): ?>
            <div class="card bg-dark border-success mb-4 shadow-lg">
                <div class="card-header bg-success bg-opacity-25 border-success d-flex justify-content-between align-items-center">
                    <h5 class="text-success fw-bold m-0">
                        <i class="fas fa-check-circle me-2"></i> Import Completed Successfully!
                    </h5>
                    <span class="badge bg-success"><?php echo $report['imported']; ?> New Registered</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-4">
                        <div class="col-md-3">
                            <div class="p-3 rounded bg-secondary bg-opacity-25 border border-secondary">
                                <div class="text-muted small text-uppercase">Total Scanned</div>
                                <div class="fs-3 fw-bold text-white"><?php echo $report['total']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded bg-success bg-opacity-25 border border-success">
                                <div class="text-muted small text-uppercase">Newly Imported</div>
                                <div class="fs-3 fw-bold text-success"><?php echo $report['imported']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded bg-warning bg-opacity-25 border border-warning">
                                <div class="text-muted small text-uppercase">Skipped (Already In DB)</div>
                                <div class="fs-3 fw-bold text-warning"><?php echo $report['skipped']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded bg-danger bg-opacity-25 border border-danger">
                                <div class="text-muted small text-uppercase">Errors</div>
                                <div class="fs-3 fw-bold text-danger"><?php echo $report['errors']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <a href="manage_social.php" class="btn btn-primary fw-bold">
                            <i class="fas fa-users-cog me-1"></i> Open Social Management & Bulk Confirm
                        </a>
                    </div>

                    <?php if (!empty($report['details'])): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover table-bordered border-secondary align-middle small">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Row</th>
                                        <th>Participant</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Cloudinary Receipt</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['details'] as $item): ?>
                                        <tr>
                                            <td>#<?php echo $item['row']; ?></td>
                                            <td class="fw-bold text-white"><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['email']); ?></td>
                                            <td>
                                                <?php if ($item['status'] === 'IMPORTED'): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Imported (#<?php echo $item['id']; ?>)</span>
                                                <?php elseif ($item['status'] === 'SKIPPED'): ?>
                                                    <span class="badge bg-secondary">Skipped</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Error</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($item['pay_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($item['pay_url']); ?>" target="_blank" class="text-info text-decoration-none">
                                                        <i class="fas fa-external-link-alt me-1"></i>View Cloud CDN
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?php echo htmlspecialchars($item['note']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger border-danger">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($report['message'] ?? 'Error occurred during import.'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Importer Form -->
    <div class="row g-4">
        <!-- Option 1: File Upload -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary h-100 shadow">
                <div class="card-header bg-dark border-secondary">
                    <h5 class="text-white fw-bold m-0">
                        <i class="fas fa-file-csv text-success me-2"></i> Option A: Upload Google Sheets CSV
                    </h5>
                    <small class="text-muted">Recommended for whole spreadsheet downloads</small>
                </div>
                <div class="card-body">
                    <form action="import_google_form.php" method="POST" enctype="multipart/form-data" id="uploadCsvForm">
                        <input type="hidden" name="action" value="import_google_form">

                        <div class="mb-4">
                            <label class="form-label text-light fw-bold">Select CSV File (.csv)</label>
                            <input type="file" name="csv_file" class="form-control bg-dark text-light border-secondary" accept=".csv,text/csv,text/plain" required>
                            <small class="text-muted mt-1 d-block">
                                In Google Sheets: <code>File</code> &rarr; <code>Download</code> &rarr; <code>Comma-separated values (.csv)</code>
                            </small>
                        </div>

                        <div class="p-3 mb-4 rounded" style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.15);">
                            <div class="d-flex align-items-center gap-2 text-info small mb-1">
                                <i class="fas fa-shield-alt"></i> <strong>Safe & Deduplicated</strong>
                            </div>
                            <div class="text-muted small">
                                Existing emails are automatically detected and safely skipped. Only brand-new rows will be uploaded to Cloudinary CDN and inserted.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="submitFileBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Start Cloud Sync & Import
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Option 2: Copy-Paste Raw Data -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary h-100 shadow">
                <div class="card-header bg-dark border-secondary">
                    <h5 class="text-white fw-bold m-0">
                        <i class="fas fa-paste text-warning me-2"></i> Option B: Paste Google Sheets Rows Directly
                    </h5>
                    <small class="text-muted">Fastest for mobile / tablet or quick updates</small>
                </div>
                <div class="card-body">
                    <form action="import_google_form.php" method="POST" id="pasteCsvForm">
                        <input type="hidden" name="action" value="import_google_form">

                        <div class="mb-3">
                            <label class="form-label text-light fw-bold">Paste Copied Rows from Google Sheets</label>
                            <textarea name="csv_text" class="form-control bg-dark text-light border-secondary font-monospace" rows="6" placeholder="Select rows in Google Sheets, press Ctrl+C, and paste here..."></textarea>
                            <small class="text-muted mt-1 d-block">
                                Tab-separated clipboard text from Google Sheets is automatically parsed!
                            </small>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark" id="submitPasteBtn">
                            <i class="fas fa-sync-alt me-2"></i> Process & Sync Pasted Rows
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Fetching from Google Drive & Uploading to Cloudinary...';
        }
    });
});
</script>

<?php include 'footer.php'; ?>
