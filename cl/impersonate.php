<?php
session_start();
if (empty($_SESSION['client_authenticated'])) {
    die('Unauthorized');
}

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

$userId = intval($_GET['user_id'] ?? 0);
if ($userId <= 0) die('Invalid user.');

$stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die('User not found.');

$key = env('IMPERSONATE_KEY', 'default_change_me');
$expires = time() + 300; // 5 minutes
$payload = json_encode([
    'user_id' => $userId,
    'expires' => $expires,
    'nonce' => bin2hex(random_bytes(8))
]);

$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($payload, 'AES-256-CBC', $key, 0, $iv);
$token = base64_encode($iv . '::' . $encrypted);

$link = 'https://sentecneduet.live/login-as.php?token=' . urlencode($token);

// Log
$logMsg = date('Y-m-d H:i:s') . " | Client generated impersonation link for User ID {$userId} ({$user['email']})";
@file_put_contents(__DIR__ . '/../storage/impersonate.log', $logMsg . "\n", FILE_APPEND);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impersonation Link | SENTEC</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body.client-area {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-body);
            background-image: radial-gradient(circle at 50% 0%, #111a2e 0%, #030303 60%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            border-radius: 24px;
            padding: 40px;
            max-width: 600px;
            text-align: center;
            margin: 20px;
        }
    </style>
</head>
<body class="client-area">
<div class="glass-card">
    <h2 style="color: #00FF94; font-family:'Outfit';"><i class="fas fa-check-circle"></i> Link Generated</h2>
    <p style="color:#ccc;">Impersonation link for <strong style="color:#fff;"><?php echo htmlspecialchars($user['full_name']); ?></strong> (<?php echo htmlspecialchars($user['email']); ?>)</p>
    <div class="link-box" style="background:#000; border:1px dashed #00FF94; padding:15px; border-radius:10px; word-break:break-all; margin:20px 0;">
        <code style="color:#00FF94;"><?php echo $link; ?></code>
    </div>
    <div class="d-flex justify-content-center gap-3">
        <button class="btn-neon" onclick="navigator.clipboard.writeText('<?php echo addslashes($link); ?>')">Copy Link</button>
        <a href="<?php echo $link; ?>" target="_blank" class="btn-solid-green" style="text-decoration:none;">Open Now</a>
    </div>
    <p class="text-muted mt-3">Link expires in 5 minutes. You can generate a new one anytime.</p>
    <a href="index.php" class="btn-outline-light mt-2" style="display:inline-block;">← Back to Dashboard</a>
</div>
</body>
</html>