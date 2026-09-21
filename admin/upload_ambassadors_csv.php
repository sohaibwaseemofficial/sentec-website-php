<?php
// Increase max execution time for large CSV uploads
@set_time_limit(300);

include 'header.php';
include '../db_connection.php';

$message = '';
$created = 0; 
$updated = 0; 
$errors = [];

$validTypes = ['volunteer' => 'Volunteer Ambassadors', 'brand' => 'Brand Ambassadors'];
$type = strtolower($_GET['type'] ?? ($_POST['ambassador_type'] ?? ''));
if (!array_key_exists($type, $validTypes)) {
    $type = 'volunteer';
}
$typeLabel = $validTypes[$type];

// Check if optional columns exist in DB
$hasInitialPwd = false;
$hasTypeColumn = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'initial_password'");
    $hasInitialPwd = ($col && $col->num_rows > 0);
} catch (Exception $e) { $hasInitialPwd = false; }
try {
    $colType = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
    $hasTypeColumn = ($colType && $colType->num_rows > 0);
} catch (Exception $e) { $hasTypeColumn = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($tmpName, "r")) !== FALSE) {
            // Skip Header
            // FIX 1: Added escape parameter "\\" to silence deprecation warning
            fgetcsv($handle, 1000, ",", "\"", "\\"); 
            
            $rowNum = 1;
            while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
                $rowNum++;
                
                if (count($data) < 5) {
                    $errors[] = "Row $rowNum skipped: Not enough columns.";
                    continue;
                }

                $name = trim($data[0]);
                $email = trim($data[1]);
                $phone = trim($data[2] ?? '');
                $inst = trim($data[3] ?? '');
                $code = trim($data[4]);
                
                // FIX 2: SANITIZE STATUS (Prevent "Data Truncated" crash)
                $rawStatus = strtolower(trim($data[5] ?? 'active'));
                // Force valid enum value
                $status = ($rawStatus === 'inactive') ? 'inactive' : 'active'; 

                $pwd = trim($data[6] ?? '');

                if (!$name || !$email || !$code) {
                    $errors[] = "Row $rowNum skipped: Missing Name, Email, or Code.";
                    continue;
                }

                $hash = $pwd ? password_hash($pwd, PASSWORD_DEFAULT) : null;

                // CHECK IF EXISTS
                $check = $conn->prepare("SELECT id FROM brand_ambassadors WHERE code = ? OR email = ?");
                $check->bind_param("ss", $code, $email);
                $check->execute();
                $res = $check->get_result();

                if ($res->num_rows > 0) {
                    // UPDATE EXISTING
                    $id = $res->fetch_assoc()['id'];
                    if ($hasTypeColumn) {
                        if ($hasInitialPwd && $pwd) {
                            $stmt = $conn->prepare("UPDATE brand_ambassadors SET name=?, email=?, phone=?, institution=?, status=?, password_hash=?, initial_password=?, ambassador_type=? WHERE id=?");
                            $stmt->bind_param("ssssssssi", $name, $email, $phone, $inst, $status, $hash, $pwd, $type, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE brand_ambassadors SET name=?, email=?, phone=?, institution=?, status=?, ambassador_type=? WHERE id=?");
                            $stmt->bind_param("ssssssi", $name, $email, $phone, $inst, $status, $type, $id);
                        }
                    } else {
                        if ($hasInitialPwd && $pwd) {
                            $stmt = $conn->prepare("UPDATE brand_ambassadors SET name=?, email=?, phone=?, institution=?, status=?, password_hash=?, initial_password=? WHERE id=?");
                            $stmt->bind_param("sssssssi", $name, $email, $phone, $inst, $status, $hash, $pwd, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE brand_ambassadors SET name=?, email=?, phone=?, institution=?, status=? WHERE id=?");
                            $stmt->bind_param("sssssi", $name, $email, $phone, $inst, $status, $id);
                        }
                    }

                    if ($stmt->execute()) $updated++;
                    else $errors[] = "Row $rowNum Update Failed: " . $stmt->error;

                } else {
                    // INSERT NEW
                    if ($hasTypeColumn) {
                        if ($hasInitialPwd) {
                            $stmt = $conn->prepare("INSERT INTO brand_ambassadors (name, email, phone, institution, code, status, ambassador_type, password_hash, initial_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssssss", $name, $email, $phone, $inst, $code, $status, $type, $hash, $pwd);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO brand_ambassadors (name, email, phone, institution, code, status, ambassador_type, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssssss", $name, $email, $phone, $inst, $code, $status, $type, $hash);
                        }
                    } else {
                        if ($hasInitialPwd) {
                            $stmt = $conn->prepare("INSERT INTO brand_ambassadors (name, email, phone, institution, code, status, password_hash, initial_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssssss", $name, $email, $phone, $inst, $code, $status, $hash, $pwd);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO brand_ambassadors (name, email, phone, institution, code, status, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssss", $name, $email, $phone, $inst, $code, $status, $hash);
                        }
                    }

                    if ($stmt->execute()) $created++;
                    else $errors[] = "Row $rowNum Insert Failed: " . $stmt->error;
                }
            }
            fclose($handle);
            
            $msgClass = empty($errors) ? 'alert-success' : 'alert-warning';
            $message = "<div class='alert $msgClass'>Success! Created: <strong>$created</strong> | Updated: <strong>$updated</strong></div>";
        } else {
            $message = "<div class='alert alert-danger'>Could not open file.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Upload Error Code: " . $_FILES['csv_file']['error'] . "</div>";
    }
}
?>

<div class="page-header">
    <h2><i class="fas fa-file-csv me-2"></i> Import <?php echo htmlspecialchars($typeLabel); ?></h2>
    <p class="text-muted">Upload a CSV file to bulk add or update this ambassador group.</p>
</div>

<div class="glass-panel">
  <?php echo $message; ?>
    <?php if (!$hasTypeColumn): ?>
            <div class="alert alert-warning" style="background:rgba(255,193,7,0.1); border:1px solid #ffc107; color:#fff;">
                    <strong>Heads up:</strong> The `ambassador_type` column is missing from the database. CSV imports will not assign types correctly until the migration runs.
            </div>
    <?php endif; ?>
  
  <?php if (!empty($errors)): ?>
      <div class="alert alert-warning" style="background:rgba(255,193,7,0.1); border:1px solid #ffc107; color:#fff;">
          <strong>Some rows failed:</strong><br>
          <ul>
              <?php foreach($errors as $err): ?>
                  <li><?php echo htmlspecialchars($err); ?></li>
              <?php endforeach; ?>
          </ul>
      </div>
  <?php endif; ?>
  
  <div class="row">
      <div class="col-md-6">
          <h5 class="text-white mb-3">Upload File</h5>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
            <input type="file" name="csv_file" accept=".csv" class="form-control mb-4" required>
            <div class="d-flex gap-3">
                <button class="btn-neon w-100" type="submit">
                    <i class="fas fa-cloud-upload-alt me-2"></i> Upload & Import
                </button>
                                <a href="manage_ambassadors?type=<?php echo urlencode($type); ?>" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center" style="text-decoration:none; color:#ccc;">Cancel</a>
            </div>
          </form>
      </div>
      
      <div class="col-md-6">
          <h5 class="text-white mb-3">CSV Format Guide</h5>
          <div class="p-3 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
              <p class="small text-muted mb-2">Create a file with these columns (no header row needed, but preferred):</p>
              <code style="color:var(--accent); display:block; margin-bottom:15px;">name, email, phone, institution, code, status, password</code>
              
              <p class="small text-muted mb-2">Example:</p>
              <pre style="color:#ccc; font-size:0.85rem;">John Doe, john@email.com, 03001234567, NEDUET, AMB001, active, Pass123</pre>
          </div>
      </div>
  </div>
</div>

<?php include 'footer.php'; ?>
