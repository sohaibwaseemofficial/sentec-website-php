<?php
session_start();
require_once __DIR__ . '/../env_loader.php';
$GATE_PASSWORD = env('EVENT_GATE_PASSCODE', 'sentec_event_day');

if (isset($_SESSION['is_event_gatekeeper'])) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['password'] ?? '';
    if ($input === $GATE_PASSWORD) {
        $_SESSION['is_event_gatekeeper'] = true;
        header("Location: index.php");
        exit;
    }
    $error = "Incorrect Passcode";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Gate | SENTEC</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { background: #050505; color: #fff; font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; text-align: center; width: 90%; max-width: 350px; }
        input { width: 100%; padding: 15px; background: #0b1120; border: 1px solid #333; color: #fff; border-radius: 50px; text-align: center; font-family: 'Outfit'; font-size: 1rem; box-sizing: border-box; margin-bottom: 20px; outline: none; }
        input:focus { border-color: #00FF94; }
        button { width: 100%; padding: 15px; background: #00FF94; color: #000; font-weight: bold; border: none; border-radius: 50px; font-size: 1rem; cursor: pointer; text-transform: uppercase; }
        .logo { font-size: 2rem; font-weight: bold; margin-bottom: 20px; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">SENTEC<span style="color:#00FF94">.</span></div>
        <p style="color:#888; margin-bottom: 30px;">Event Volunteer Portal</p>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter Event Passcode" required>
            <button type="submit">Unlock Device</button>
        </form>
        <?php if($error): ?>
            <p style="color:#ff4444; margin-top:20px;"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
