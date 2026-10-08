# SENTEC Native Android Gate Scanner APK

High-performance, offline-resilient gate check-in mobile application designed for **SENTEC Ruh-e-Raqs (Social Night)** and **Engineer's Code Olympiad**.

---

## ⚡ Architectural Highlights

1. **3-Tier Check-In Resolution**:
   - **Tier 1 (Sub-15ms Local Hotspot)**: Point to Master Hub phone at `http://192.168.43.1:8080/api/scan`.
   - **Tier 2 (Cloud Fallback)**: Direct HTTPS call to `https://sentecneduet.live/api/gate/scan.php`.
   - **Tier 3 (Zero-Connectivity Offline SQLite)**: Validates against pre-downloaded Room SQLite whitelist (`attendees`), updates locally atomically, and appends to the Outbox Queue (`audit_logs`).

2. **Dual-Role Operation in a Single APK**:
   - **Scanner Mode**: CameraX + Google ML Kit for continuous hardware QR scanning (with auto-focus, torch toggle, and 1.8s auto-cooldown).
   - **Master Hub Mode**: Boots an embedded **NanoHTTPD** HTTP server on port `8080` as an Android Foreground Service with CPU `WakeLock`. Other scanner phones connect via native Android Wi-Fi hotspot (`192.168.43.1`).

3. **Dual Authentication**:
   - **Admin Setup QR**: Point the camera at the Setup QR generated in `/admin/gate_monitor.php` to pair immediately.
   - **4-Digit Station PIN**:
     - `1011` → Gate 1 (Engineer Entry)
     - `1012` → Gate 2 (Engineer Entry)
     - `2011` → Gate 1 (Ruh-e-Raqs Social Entry)
     - `2012` → Gate 2 (Ruh-e-Raqs Social Entry)
     - `9999` → Universal Gate (All Allowed)

---

## 🚀 How to Build & Install the APK

### Option A: Automated Cloud Build (GitHub Actions)
Every git push to `main` with changes to `android-gate-scanner/` automatically triggers `.github/workflows/build-apk.yml`.
1. Go to your GitHub repository -> **Actions** tab.
2. Select the latest **"Build SENTEC Gate Scanner APK"** workflow run.
3. Download `SENTEC-Gate-Scanner.apk` directly to your phone and install!

### Option B: Android Studio (Local Development)
1. Open Android Studio -> **Open Project**.
2. Select the `android-gate-scanner` folder.
3. Connect your Android phone with USB Debugging enabled.
4. Click **Run** (`Shift + F10`) or **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
