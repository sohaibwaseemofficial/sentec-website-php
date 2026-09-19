<?php
session_start();
require_once __DIR__ . '/../env_loader.php';

$CLIENT_ACCESS_CODE = env('CLIENT_ACCESS_CODE', 'changeme');
$isLoggedIn = $_SESSION['client_authenticated'] ?? false;
$error = '';

if (isset($_POST['client_passcode'])) {
    if ($_POST['client_passcode'] === $CLIENT_ACCESS_CODE) {
        $_SESSION['client_authenticated'] = true;
        $isLoggedIn = true;
    } else {
        $error = 'Invalid access code.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['client_authenticated']);
    header('Location: index.php');
    exit;
}

$users = [];
if ($isLoggedIn) {
    require_once __DIR__ . '/../db_connection.php';
    $result = $conn->query("SELECT id, full_name, email, phone FROM users ORDER BY full_name ASC");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | SENTEC</title>
    <!-- Same fonts and icons as main site -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Core SENTEC styles -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Additional override for client area only */
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
        .client-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 1000px;
            margin: 20px;
        }
        .btn-neon, .btn-solid-green, .btn-outline-light, .btn-outline-danger, .btn-outline-info {
            text-decoration: none !important;
        }
        /* Override table styles to match glass theme */
        .table-dark {
            --bs-table-bg: transparent;
            color: #ccc;
        }
        .table-dark thead th {
            border-bottom: 2px solid var(--accent);
            color: var(--accent);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            background: rgba(0,255,148,0.05);
        }
        .client-glass h2, .client-glass h4 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }
    </style>
</head>
<body class="client-area">
<?php if (!$isLoggedIn): ?>
    <div class="client-glass" style="max-width:400px; text-align:center;">
        <div style="font-family:'Outfit'; font-weight:800; font-size:2rem; color:#fff; margin-bottom:10px;">
            SENTEC<span style="color: #00FF94;">.</span>
        </div>
        <h2 style="color:#fff; margin-bottom:10px;">Client Access</h2>
        <p class="text-muted">Enter the access code provided by SENTEC.</p>
        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="client_passcode" class="form-control form-control-dark mb-3" placeholder="Access Code" required autofocus>
            <button type="submit" class="btn-neon w-100" style="padding:12px;">Unlock</button>
        </form>
    </div>
<?php else: ?>
    <div class="client-glass">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="color:#fff; margin:0;"><i class="fas fa-users-cog me-2"></i>Client Dashboard</h2>
                <p class="text-muted mb-0">Manage users and login as them.</p>
            </div>
            <a href="?logout=1" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="glass-panel p-4 mb-4" style="background: rgba(0,0,0,0.3); border-radius: 16px;">
            <h4 style="color: var(--accent);"><i class="fas fa-user-secret me-2"></i>Login As User (Impersonate)</h4>
            <p class="text-muted">Generate a secure temporary link to log in as any user without knowing their password.</p>
            <?php if (!empty($users)): ?>
                <form action="impersonate.php" method="GET" class="row g-3">
                    <div class="col-md-9">
                        <select name="user_id" class="form-select form-select-dark" required>
                            <option value="">— Select User —</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn-neon w-100" style="padding:12px;">Generate Link</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-muted">No users found.</p>
            <?php endif; ?>
        </div>

        <h4 style="color:#fff;"><i class="fas fa-list me-2"></i>Registered Users</h4>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                        <td>
                            <a href="impersonate.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-info">Impersonate</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</body>
</html>