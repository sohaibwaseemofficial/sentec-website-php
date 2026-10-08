<?php
/**
 * SENTEC Universal Gate Scanner PWA
 * Fast, offline-first mobile QR scanner supporting:
 * - 3-Tier Distributed Fallback (Hotspot 192.168.43.1 -> Cloud Direct -> Local IndexedDB)
 * - Persistent Station Login (PIN & QR Activation)
 * - Dual Event Verification (Engineer's Code & RUH-E-RAQS Social Night)
 * - Audio Chimes, Haptics, Screen WakeLock & Auto-Dismiss Scanning
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SENTEC Gate Scanner | Check-In Terminal</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="scanner_manifest.json">
    <meta name="theme-color" content="#06090c">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="images/favicon2.png" type="image/png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&family=IBM+Plex+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- HTML5 QR Code Scanner Library (CDN with fallback) -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <style>
        :root {
            --neon-green: #00FF94;
            --neon-green-dim: rgba(0, 255, 148, 0.15);
            --neon-green-glow: rgba(0, 255, 148, 0.45);
            --danger-red: #ff334b;
            --danger-red-dim: rgba(255, 51, 75, 0.15);
            --warning-amber: #ffaa00;
            --bg-deep: #06090c;
            --bg-surface: #0e1317;
            --card-bg: rgba(16, 22, 27, 0.9);
            --border-line: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        body {
            background-color: var(--bg-deep);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* SCREEN 1: ACTIVATION KEYPAD */
        #auth-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 24px;
            text-align: center;
            position: relative;
            z-index: 10;
            background: radial-gradient(circle at 50% 20%, rgba(0, 255, 148, 0.08) 0%, transparent 60%), #06090c;
        }

        .auth-brand {
            margin-bottom: 24px;
        }

        .auth-brand img {
            width: 64px;
            height: 64px;
            margin-bottom: 12px;
            border-radius: 50%;
            border: 2px solid var(--border-line);
            padding: 4px;
        }

        .auth-title {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #fff;
            margin-bottom: 4px;
        }

        .auth-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-family: 'IBM Plex Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .pin-display-box {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin: 24px 0 20px;
        }

        .pin-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.25);
            background: transparent;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pin-dot.filled {
            background: var(--neon-green);
            border-color: var(--neon-green);
            box-shadow: 0 0 12px var(--neon-green);
            transform: scale(1.15);
        }

        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            width: 100%;
            max-width: 280px;
            margin-bottom: 24px;
        }

        .key-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-line);
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            padding: 16px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .key-btn:active {
            background: var(--neon-green-dim);
            border-color: var(--neon-green);
            color: var(--neon-green);
            transform: scale(0.94);
        }

        .preset-pills {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            max-width: 320px;
            margin-top: 6px;
        }

        .preset-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-line);
            color: var(--text-muted);
            font-size: 0.72rem;
            font-family: 'IBM Plex Mono', monospace;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.2s;
        }

        .preset-pill:hover, .preset-pill:active {
            border-color: var(--neon-green);
            color: #fff;
        }

        /* SCREEN 2: SCANNER TERMINAL */
        #scanner-screen {
            display: none;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            position: relative;
            background: #000;
        }

        /* TOP STATUS BAR */
        .scanner-topbar {
            background: rgba(6, 9, 12, 0.95);
            border-bottom: 1px solid var(--border-line);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 20;
            backdrop-filter: blur(16px);
        }

        .station-meta {
            display: flex;
            flex-direction: column;
        }

        .station-name-text {
            font-size: 0.95rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .station-role-badge {
            font-size: 0.68rem;
            font-family: 'IBM Plex Mono', monospace;
            color: var(--neon-green);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-line);
            color: #fff;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .icon-btn:active {
            transform: scale(0.92);
            background: var(--neon-green-dim);
            color: var(--neon-green);
        }

        .icon-btn.active {
            background: var(--neon-green);
            color: #000;
            box-shadow: 0 0 14px var(--neon-green-glow);
        }

        /* DAY SELECTOR TOGGLE (For Engineer's Code) */
        .day-toggle-bar {
            display: none;
            background: rgba(14, 19, 23, 0.9);
            border-bottom: 1px solid var(--border-line);
            padding: 6px 16px;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            z-index: 15;
        }

        .day-pills {
            display: flex;
            gap: 6px;
        }

        .day-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-line);
            color: var(--text-muted);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        .day-pill.active {
            background: var(--neon-green);
            color: #000;
            border-color: var(--neon-green);
        }

        /* VIEWFINDER & CAMERA */
        .camera-viewport-wrap {
            flex: 1;
            position: relative;
            background: #000;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #qr-reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
        }

        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        /* TARGET SIGHT RETICLE */
        .scanner-reticle {
            position: absolute;
            width: 250px;
            height: 250px;
            pointer-events: none;
            z-index: 10;
        }

        .scanner-reticle::before, .scanner-reticle::after,
        .scanner-reticle-c::before, .scanner-reticle-c::after {
            content: "";
            position: absolute;
            width: 32px;
            height: 32px;
            border-color: var(--neon-green);
            border-style: solid;
        }

        .scanner-reticle::before { top: 0; left: 0; border-width: 4px 0 0 4px; border-top-left-radius: 12px; }
        .scanner-reticle::after { top: 0; right: 0; border-width: 4px 4px 0 0; border-top-right-radius: 12px; }
        .scanner-reticle-c::before { bottom: 0; left: 0; border-width: 0 0 4px 4px; border-bottom-left-radius: 12px; }
        .scanner-reticle-c::after { bottom: 0; right: 0; border-width: 0 4px 4px 0; border-bottom-right-radius: 12px; }

        .reticle-laser {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--neon-green), transparent);
            box-shadow: 0 0 12px var(--neon-green);
            animation: laserSweep 2.2s infinite ease-in-out;
        }

        @keyframes laserSweep {
            0%, 100% { top: 5%; opacity: 0.3; }
            50% { top: 95%; opacity: 1; }
        }

        /* BOTTOM CONTROLS & STATS DOCK */
        .scanner-dock {
            background: rgba(6, 9, 12, 0.95);
            border-top: 1px solid var(--border-line);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 20;
            backdrop-filter: blur(16px);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-family: 'IBM Plex Mono', monospace;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-line);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--neon-green);
            box-shadow: 0 0 6px var(--neon-green);
        }

        .status-dot.offline {
            background: var(--warning-amber);
            box-shadow: 0 0 6px var(--warning-amber);
        }

        .stats-counter {
            font-size: 0.85rem;
            font-family: 'IBM Plex Mono', monospace;
            color: #fff;
        }

        .stats-counter strong {
            color: var(--neon-green);
            font-size: 1.05rem;
        }

        /* RESULT OVERLAY CARD */
        .result-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(12px);
            z-index: 50;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
        }

        .result-card {
            background: var(--card-bg);
            border: 2px solid var(--neon-green);
            border-radius: 24px;
            padding: 30px 24px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 0 50px rgba(0, 255, 148, 0.25);
            animation: cardPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .result-card.rejected {
            border-color: var(--danger-red);
            box-shadow: 0 0 50px rgba(255, 51, 75, 0.3);
        }

        .result-card.warning {
            border-color: var(--warning-amber);
            box-shadow: 0 0 50px rgba(255, 170, 0, 0.25);
        }

        @keyframes cardPop {
            0% { transform: scale(0.85); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .result-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--neon-green-dim);
            border: 2px solid var(--neon-green);
            color: var(--neon-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 16px;
        }

        .result-card.rejected .result-icon-wrap {
            background: var(--danger-red-dim);
            border-color: var(--danger-red);
            color: var(--danger-red);
        }

        .result-card.warning .result-icon-wrap {
            background: rgba(255, 170, 0, 0.15);
            border-color: var(--warning-amber);
            color: var(--warning-amber);
        }

        .result-title {
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 4px;
            color: #fff;
        }

        .result-attendee-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--neon-green);
            margin-bottom: 6px;
            word-break: break-word;
        }

        .result-card.rejected .result-attendee-name {
            color: #fff;
        }

        .result-meta {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .result-meta strong {
            color: #fff;
        }

        .result-timer-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: var(--neon-green);
            width: 100%;
            animation: timerDrain 1.6s linear forwards;
        }

        .result-card.rejected .result-timer-bar {
            background: var(--danger-red);
        }

        @keyframes timerDrain {
            0% { width: 100%; }
            100% { width: 0%; }
        }

        .btn-resume-scan {
            background: var(--neon-green);
            color: #000;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 0 20px var(--neon-green-glow);
            width: 100%;
        }

        .result-card.rejected .btn-resume-scan {
            background: var(--danger-red);
            color: #fff;
            box-shadow: 0 0 20px rgba(255, 51, 75, 0.4);
        }

        /* MANUAL ENTRY MODAL */
        #manual-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 60;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .manual-box {
            background: var(--bg-surface);
            border: 1px solid var(--border-line);
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            max-width: 340px;
        }

        .manual-input {
            width: 100%;
            padding: 14px;
            background: #06090c;
            border: 1px solid var(--border-line);
            border-radius: 12px;
            color: #fff;
            font-size: 1.05rem;
            margin: 14px 0;
            outline: none;
            text-align: center;
            font-family: 'IBM Plex Mono', monospace;
        }

        .manual-input:focus {
            border-color: var(--neon-green);
        }
    </style>
</head>
<body>

    <!-- =========================================================
         SCREEN 1: STATION ACTIVATION KEYPAD
         ========================================================= -->
    <div id="auth-screen">
        <div class="auth-brand">
            <img src="images/SENTECNEWWHITELOGO.webp" alt="SENTEC">
            <h1 class="auth-title">SENTEC GATE TERMINAL</h1>
            <div class="auth-subtitle">STATION ACTIVATION</div>
        </div>

        <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 6px;">
            Enter 4-Digit Station PIN
        </div>

        <div class="pin-display-box">
            <div class="pin-dot" id="dot-0"></div>
            <div class="pin-dot" id="dot-1"></div>
            <div class="pin-dot" id="dot-2"></div>
            <div class="pin-dot" id="dot-3"></div>
        </div>

        <div class="keypad-grid">
            <button class="key-btn" onclick="pressKey('1')">1</button>
            <button class="key-btn" onclick="pressKey('2')">2</button>
            <button class="key-btn" onclick="pressKey('3')">3</button>
            <button class="key-btn" onclick="pressKey('4')">4</button>
            <button class="key-btn" onclick="pressKey('5')">5</button>
            <button class="key-btn" onclick="pressKey('6')">6</button>
            <button class="key-btn" onclick="pressKey('7')">7</button>
            <button class="key-btn" onclick="pressKey('8')">8</button>
            <button class="key-btn" onclick="pressKey('9')">9</button>
            <button class="key-btn" style="font-size: 1rem; color: var(--text-muted);" onclick="clearPin()">CLEAR</button>
            <button class="key-btn" onclick="pressKey('0')">0</button>
            <button class="key-btn" onclick="backspacePin()"><i class="fas fa-backspace"></i></button>
        </div>

        <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 6px;">
            Quick Presets:
        </div>
        <div class="preset-pills">
            <span class="preset-pill" onclick="setPreset('1011')">1011 - Engineer G1</span>
            <span class="preset-pill" onclick="setPreset('2011')">2011 - Social G1</span>
            <span class="preset-pill" onclick="setPreset('9999')">9999 - Universal</span>
        </div>

        <div id="auth-error-msg" style="color: var(--danger-red); font-size: 0.85rem; margin-top: 14px; min-height: 20px;"></div>
    </div>

    <!-- =========================================================
         SCREEN 2: CONTINUOUS LIVE SCANNER
         ========================================================= -->
    <div id="scanner-screen">
        
        <!-- Top Status Bar -->
        <div class="scanner-topbar">
            <div class="station-meta">
                <div class="station-name-text">
                    <span id="st-name-display">Gate 1</span>
                    <i class="fas fa-check-circle" style="color: var(--neon-green); font-size: 0.75rem;"></i>
                </div>
                <div class="station-role-badge" id="st-role-display">ROLE: SOCIAL NIGHT</div>
            </div>

            <div class="topbar-actions">
                <button class="icon-btn" id="btn-torch" onclick="toggleTorch()" title="Flashlight">
                    <i class="fas fa-bolt"></i>
                </button>
                <button class="icon-btn" onclick="openManualModal()" title="Manual Entry">
                    <i class="fas fa-keyboard"></i>
                </button>
                <button class="icon-btn" onclick="logoutStation()" title="Exit Station" style="color: #ff4444;">
                    <i class="fas fa-power-off"></i>
                </button>
            </div>
        </div>

        <!-- Day Selector (visible only for engineer's code) -->
        <div class="day-toggle-bar" id="day-bar">
            <span style="color: var(--text-muted);">Competition Attendance:</span>
            <div class="day-pills">
                <button class="day-pill active" id="pill-day-1" onclick="setDay(1)">Day 1</button>
                <button class="day-pill" id="pill-day-2" onclick="setDay(2)">Day 2</button>
            </div>
        </div>

        <!-- Camera Viewport Area -->
        <div class="camera-viewport-wrap">
            <div id="qr-reader"></div>

            <!-- Reticle Sight Target -->
            <div class="scanner-reticle" id="reticle">
                <div class="scanner-reticle-c"></div>
                <div class="reticle-laser"></div>
            </div>

            <!-- Result Overlay Card -->
            <div class="result-overlay" id="result-overlay" onclick="resumeScanning()">
                <div class="result-card" id="result-card">
                    <div class="result-icon-wrap" id="result-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="result-title" id="result-title">ACCESS GRANTED</div>
                    <div class="result-attendee-name" id="result-name">Muhammad Ali</div>
                    <div class="result-meta" id="result-meta">
                        CNIC: 42101-1234567-1<br>
                        Pass: Individual Standard
                    </div>
                    <button class="btn-resume-scan" onclick="resumeScanning()">Next Attendee</button>
                    <div class="result-timer-bar" id="result-timer"></div>
                </div>
            </div>
        </div>

        <!-- Bottom Controls & Stats Dock -->
        <div class="scanner-dock">
            <div class="status-pill">
                <span class="status-dot" id="net-dot"></span>
                <span id="net-status-text">CLOUD ONLINE</span>
            </div>

            <div class="stats-counter">
                SCANS: <strong id="scan-count">0</strong>
            </div>
        </div>

    </div>

    <!-- Manual Code Modal -->
    <div id="manual-modal">
        <div class="manual-box">
            <h3 style="color:#fff; font-size:1.15rem; margin-bottom:4px;">Manual Ticket Entry</h3>
            <p style="color:var(--text-muted); font-size:0.8rem;">Enter Ticket ID or Attendee Number</p>
            <input type="text" class="manual-input" id="manual-code-input" placeholder="e.g. 45 or SOC-45" autocomplete="off">
            <div style="display:flex; gap:10px;">
                <button class="btn-resume-scan" style="background:#222; color:#fff;" onclick="closeManualModal()">Cancel</button>
                <button class="btn-resume-scan" onclick="submitManualCode()">Verify</button>
            </div>
        </div>
    </div>

    <script>
        /* =========================================================
           STATE & CONFIGURATION
           ========================================================= */
        let enteredPin = "";
        let currentAuth = null;
        let html5QrScanner = null;
        let isScanningActive = true;
        let autoResumeTimer = null;
        let isTorchOn = false;
        let currentDay = 1;
        let stationScanCount = 0;
        let wakeLock = null;
        let audioCtx = null;

        // Offline IndexedDB
        let db = null;
        const DB_NAME = "sentec_gate_db";
        const DB_VERSION = 1;

        /* =========================================================
           1. INITIALIZATION & PWA SERVICE WORKER
           ========================================================= */
        window.addEventListener('DOMContentLoaded', async () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('scanner_sw.js').catch(err => console.log('SW registration:', err));
            }

            initIndexedDB();
            initWakeLock();

            // Check if already authenticated in persistent localStorage
            const savedAuth = localStorage.getItem('sentec_gate_auth');
            if (savedAuth) {
                try {
                    currentAuth = JSON.parse(savedAuth);
                    launchScannerScreen();
                } catch(e) {
                    localStorage.removeItem('sentec_gate_auth');
                }
            }
        });

        /* =========================================================
           2. PIN KEYPAD LOGIC
           ========================================================= */
        function pressKey(num) {
            if (enteredPin.length < 4) {
                enteredPin += num;
                updatePinDots();
                if (enteredPin.length === 4) {
                    submitPinAuth();
                }
            }
        }

        function backspacePin() {
            if (enteredPin.length > 0) {
                enteredPin = enteredPin.slice(0, -1);
                updatePinDots();
            }
        }

        function clearPin() {
            enteredPin = "";
            updatePinDots();
            document.getElementById('auth-error-msg').innerText = "";
        }

        function setPreset(code) {
            enteredPin = code;
            updatePinDots();
            submitPinAuth();
        }

        function updatePinDots() {
            for (let i = 0; i < 4; i++) {
                const dot = document.getElementById(`dot-${i}`);
                if (i < enteredPin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            }
        }

        async function submitPinAuth() {
            const errEl = document.getElementById('auth-error-msg');
            errEl.innerText = "Authenticating Station...";

            try {
                const res = await fetch('api/gate/auth_pin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pin: enteredPin,
                        volunteer_name: 'Gate Lead',
                        device_id: 'Mobile_' + navigator.userAgent.slice(0, 20)
                    })
                });

                const data = await res.json();
                if (data.success && data.token) {
                    currentAuth = {
                        token: data.token,
                        station: data.station
                    };
                    localStorage.setItem('sentec_gate_auth', JSON.stringify(currentAuth));
                    launchScannerScreen();
                } else {
                    errEl.innerText = data.message || "Invalid Station PIN";
                    shakeKeypad();
                    clearPin();
                }
            } catch (err) {
                errEl.innerText = "Connection error. Retrying...";
                clearPin();
            }
        }

        function shakeKeypad() {
            const box = document.querySelector('.pin-display-box');
            box.style.transform = "translateX(-10px)";
            setTimeout(() => box.style.transform = "translateX(10px)", 70);
            setTimeout(() => box.style.transform = "translateX(-6px)", 140);
            setTimeout(() => box.style.transform = "translateX(0)", 210);
        }

        /* =========================================================
           3. LAUNCH SCANNER & HARDWARE INTEGRATION
           ========================================================= */
        function launchScannerScreen() {
            document.getElementById('auth-screen').style.display = 'none';
            document.getElementById('scanner-screen').style.display = 'flex';

            const st = currentAuth.station;
            document.getElementById('st-name-display').innerText = st.station_name || "Active Station";
            document.getElementById('st-role-display').innerText = `ROLE: ${st.role.toUpperCase()}`;

            if (st.role === 'engineer' || st.role === 'all') {
                document.getElementById('day-bar').style.display = 'flex';
            }

            startCameraScanner();
            seedLocalWhitelist();
            startBackgroundSync();
        }

        function logoutStation() {
            if (confirm("Disconnect and log out this station terminal?")) {
                stopCameraScanner();
                localStorage.removeItem('sentec_gate_auth');
                location.reload();
            }
        }

        function setDay(day) {
            currentDay = day;
            document.getElementById('pill-day-1').classList.toggle('active', day === 1);
            document.getElementById('pill-day-2').classList.toggle('active', day === 2);
        }

        /* Camera Scanner Initializer */
        function startCameraScanner() {
            if (html5QrScanner) {
                return;
            }

            html5QrScanner = new Html5Qrcode("qr-reader");
            const config = {
                fps: 20,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };

            html5QrScanner.start(
                { facingMode: "environment" },
                config,
                onQrCodeScanned,
                onQrCodeError
            ).catch(err => {
                console.warn("Camera start failed, retrying facingMode user:", err);
                html5QrScanner.start({ facingMode: "user" }, config, onQrCodeScanned, onQrCodeError);
            });
        }

        function stopCameraScanner() {
            if (html5QrScanner) {
                html5QrScanner.stop().catch(() => {}).finally(() => {
                    html5QrScanner = null;
                });
            }
        }

        function onQrCodeError(err) {
            // Silence repetitive scan errors in video frames
        }

        /* =========================================================
           4. CORE SCAN VERIFICATION & 3-TIER RESOLVER
           ========================================================= */
        async function onQrCodeScanned(decodedText) {
            if (!isScanningActive) return;
            isScanningActive = false; // Lock until card dismisses

            playFeedbackSound(true); // Immediate haptic click feedback

            const payload = {
                qr_code: decodedText,
                day: currentDay,
                device_timestamp: Date.now(),
                log_id: 'scan_' + Math.random().toString(36).substr(2, 9)
            };

            // Attempt Tier 1 / Tier 2: Cloud API check-in
            try {
                const response = await fetchWithTimeout('api/gate/scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${currentAuth.token}`
                    },
                    body: JSON.stringify(payload)
                }, 1200);

                const data = await response.json();
                handleScanResult(data);

            } catch (networkError) {
                // Tier 3 Fallback: Offline IndexedDB Check
                console.log("Network timeout. Executing Tier 3 Offline IndexedDB check...");
                handleOfflineFallback(decodedText, payload);
            }
        }

        function handleScanResult(data) {
            stationScanCount++;
            document.getElementById('scan-count').innerText = stationScanCount;

            const overlay = document.getElementById('result-overlay');
            const card = document.getElementById('result-card');
            const iconWrap = document.getElementById('result-icon');
            const titleEl = document.getElementById('result-title');
            const nameEl = document.getElementById('result-name');
            const metaEl = document.getElementById('result-meta');

            // Reset classes
            card.className = "result-card";

            if (data.status === 'APPROVED') {
                playFeedbackSound('success');
                vibrate([50]);
                iconWrap.innerHTML = '<i class="fas fa-check"></i>';
                titleEl.innerText = "ACCESS GRANTED";
                nameEl.innerText = data.attendee?.name || "Participant";
                metaEl.innerHTML = `
                    ${data.event ? `<strong>${data.event}</strong><br>` : ''}
                    ${data.attendee?.tier ? `Tier: ${data.attendee.tier}<br>` : ''}
                    ${data.attendee?.team ? `Team: ${data.attendee.team} (${data.attendee.module})<br>` : ''}
                    ${data.attendee?.cnic ? `ID: ${data.attendee.cnic}` : ''}
                `;
            } else if (data.status === 'DUPLICATE_REJECTED') {
                playFeedbackSound('error');
                vibrate([200, 100, 200]);
                card.classList.add('rejected');
                iconWrap.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.innerText = "ALREADY ADMITTED";
                nameEl.innerText = data.attendee?.name || "Duplicate Ticket";
                metaEl.innerHTML = `<strong>WARNING:</strong> ${data.message || 'Pass has already entered the venue.'}`;
            } else {
                playFeedbackSound('error');
                vibrate([150, 80, 150]);
                card.classList.add('warning');
                iconWrap.innerHTML = '<i class="fas fa-times"></i>';
                titleEl.innerText = "ENTRY RESTRICTED";
                nameEl.innerText = data.attendee?.name || "Invalid Pass";
                metaEl.innerHTML = data.message || "Entry not allowed at this gate.";
            }

            overlay.style.display = 'flex';

            // Auto-Resume Timer: Automatically reset viewfinder in 1.8 seconds
            clearTimeout(autoResumeTimer);
            autoResumeTimer = setTimeout(() => {
                resumeScanning();
            }, 1800);
        }

        function resumeScanning() {
            clearTimeout(autoResumeTimer);
            document.getElementById('result-overlay').style.display = 'none';
            setTimeout(() => {
                isScanningActive = true;
            }, 300);
        }

        /* =========================================================
           5. OFFLINE INDEXEDDB & OUTBOX ENGINE
           ========================================================= */
        function initIndexedDB() {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = (e) => {
                db = e.target.result;
                if (!db.objectStoreNames.contains('whitelist')) {
                    db.createObjectStore('whitelist', { keyPath: 't' });
                }
                if (!db.objectStoreNames.contains('outbox')) {
                    db.createObjectStore('outbox', { keyPath: 'log_id' });
                }
            };
            request.onsuccess = (e) => { db = e.target.result; };
        }

        async function seedLocalWhitelist() {
            try {
                const res = await fetch(`api/gate/seed.php?token=${encodeURIComponent(currentAuth.token)}`);
                const data = await res.json();
                if (data.success && Array.isArray(data.whitelist) && db) {
                    const tx = db.transaction('whitelist', 'readwrite');
                    const store = tx.objectStore('whitelist');
                    data.whitelist.forEach(item => store.put(item));
                    console.log(`✓ Seeded ${data.whitelist.length} tickets to local IndexedDB.`);
                }
            } catch(e) {
                console.log("Could not seed whitelist online, operating on existing cache.");
            }
        }

        function handleOfflineFallback(rawCode, payload) {
            updateNetworkStatus(false);

            if (!db) {
                handleScanResult({ status: 'ERROR', message: 'Offline database initializing.' });
                return;
            }

            // Extract pure ID
            const match = rawCode.match(/(?:attendee=|id=)(\d+)/) || rawCode.match(/(?:SOC|ENG)-(\d+)/i) || rawCode.match(/^(\d+)$/);
            const numId = match ? match[1] : null;
            const prefix = currentAuth.station.role === 'engineer' ? 'ENG-' : 'SOC-';
            const lookupKey = numId ? (prefix + numId) : rawCode;

            const tx = db.transaction(['whitelist', 'outbox'], 'readwrite');
            const wStore = tx.objectStore('whitelist');
            const oStore = tx.objectStore('outbox');

            const req = wStore.get(lookupKey);
            req.onsuccess = () => {
                const item = req.result;
                if (item) {
                    if (item.u === 1) {
                        handleScanResult({
                            status: 'DUPLICATE_REJECTED',
                            attendee: { name: item.n },
                            message: 'Already admitted (Checked in previously)'
                        });
                    } else {
                        item.u = 1;
                        wStore.put(item);
                        // Save to outbox
                        oStore.put({
                            log_id: payload.log_id,
                            t: lookupKey,
                            v: currentAuth.station.volunteer_name,
                            r: currentAuth.station.role,
                            s: 'APPROVED',
                            ts: payload.device_timestamp,
                            day: currentDay
                        });
                        handleScanResult({
                            status: 'APPROVED',
                            event: 'Offline Verified Entry',
                            attendee: { name: item.n, tier: item.m || 'Verified Pass' }
                        });
                    }
                } else {
                    // Not in whitelist, queue for sync
                    oStore.put({
                        log_id: payload.log_id,
                        t: rawCode,
                        v: currentAuth.station.volunteer_name,
                        r: currentAuth.station.role,
                        s: 'APPROVED_UNKNOWN',
                        ts: payload.device_timestamp,
                        day: currentDay
                    });
                    handleScanResult({
                        status: 'APPROVED',
                        event: 'Queued Offline',
                        attendee: { name: 'Attendee (Offline Pass)' }
                    });
                }
            };
        }

        /* Background Outbox Sync Worker */
        function startBackgroundSync() {
            setInterval(async () => {
                if (!db || !currentAuth) return;
                try {
                    const tx = db.transaction('outbox', 'readonly');
                    const store = tx.objectStore('outbox');
                    const getAllReq = store.getAll();
                    getAllReq.onsuccess = async () => {
                        const logs = getAllReq.result;
                        if (logs && logs.length > 0) {
                            console.log(`Pushing ${logs.length} outbox logs to cloud...`);
                            const pushRes = await fetch('api/gate/sync_push.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': `Bearer ${currentAuth.token}`
                                },
                                body: JSON.stringify({ logs })
                            });
                            const pushData = await pushRes.json();
                            if (pushData.success && Array.isArray(pushData.ack_ids)) {
                                const delTx = db.transaction('outbox', 'readwrite');
                                const dStore = delTx.objectStore('outbox');
                                pushData.ack_ids.forEach(id => dStore.delete(id));
                                updateNetworkStatus(true);
                            }
                        } else {
                            updateNetworkStatus(true);
                        }
                    };
                } catch(e) {
                    updateNetworkStatus(false);
                }
            }, 18000);
        }

        function updateNetworkStatus(isOnline) {
            const dot = document.getElementById('net-dot');
            const txt = document.getElementById('net-status-text');
            if (isOnline) {
                dot.className = "status-dot";
                txt.innerText = "CLOUD ONLINE";
            } else {
                dot.className = "status-dot offline";
                txt.innerText = "OFFLINE MODE";
            }
        }

        /* =========================================================
           6. AUDIO SYNTHESIS & HARDWARE WAKELOCK
           ========================================================= */
        function playFeedbackSound(type) {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);

                if (type === 'success') {
                    // Crisp 880Hz -> 1100Hz two-tone chime
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                    osc.frequency.setValueAtTime(1174, audioCtx.currentTime + 0.08);
                    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.22);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.23);
                } else if (type === 'error') {
                    // 200Hz harsh sawtooth buzz
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(180, audioCtx.currentTime);
                    gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.36);
                }
            } catch(e) {}
        }

        function vibrate(pattern) {
            if ('vibrate' in navigator) {
                try { navigator.vibrate(pattern); } catch(e) {}
            }
        }

        async function initWakeLock() {
            if ('wakeLock' in navigator) {
                try {
                    wakeLock = await navigator.wakeLock.request('screen');
                    document.addEventListener('visibilitychange', async () => {
                        if (wakeLock !== null && document.visibilityState === 'visible') {
                            wakeLock = await navigator.wakeLock.request('screen');
                        }
                    });
                } catch(e) {}
            }
        }

        async function toggleTorch() {
            if (!html5QrScanner) return;
            isTorchOn = !isTorchOn;
            try {
                await html5QrScanner.applyVideoConstraints({
                    advanced: [{ torch: isTorchOn }]
                });
                document.getElementById('btn-torch').classList.toggle('active', isTorchOn);
            } catch(e) {
                console.warn("Torch not supported on this device/camera.");
            }
        }

        /* =========================================================
           7. MANUAL CODE SEARCH MODAL
           ========================================================= */
        function openManualModal() {
            document.getElementById('manual-modal').style.display = 'flex';
            document.getElementById('manual-code-input').focus();
        }

        function closeManualModal() {
            document.getElementById('manual-modal').style.display = 'none';
            document.getElementById('manual-code-input').value = "";
        }

        function submitManualCode() {
            const code = document.getElementById('manual-code-input').value.trim();
            if (code) {
                closeManualModal();
                onQrCodeScanned(code);
            }
        }

        /* Helper: Fetch with timeout */
        function fetchWithTimeout(url, options, timeout = 1200) {
            return Promise.race([
                fetch(url, options),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), timeout))
            ]);
        }
    </script>
</body>
</html>
