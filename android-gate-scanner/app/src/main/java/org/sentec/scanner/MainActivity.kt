package org.sentec.scanner

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.content.pm.PackageManager
import android.graphics.Color
import android.os.Build
import android.os.Bundle
import android.view.View
import android.view.WindowManager
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.google.mlkit.vision.barcode.BarcodeScanner
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.sentec.scanner.database.AppDatabase
import org.sentec.scanner.database.AttendeeEntity
import org.sentec.scanner.database.AuditLogEntity
import org.sentec.scanner.databinding.ActivityMainBinding
import org.sentec.scanner.network.GateApiClient
import org.sentec.scanner.service.MasterForegroundService
import org.sentec.scanner.util.QrParser
import org.sentec.scanner.util.SoundHelper
import java.text.SimpleDateFormat
import java.util.*
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: SharedPreferences
    private lateinit var database: AppDatabase
    private lateinit var apiClient: GateApiClient
    private lateinit var soundHelper: SoundHelper
    private lateinit var cameraExecutor: ExecutorService

    private var camera: Camera? = null
    private var isTorchOn = false
    private var isScanCooldown = false
    private var isMasterMode = false
    private var deviceId: String = ""

    companion object {
        private const val CAMERA_PERMISSION_CODE = 200
        private const val PREFS_NAME = "sentec_gate_prefs"
        private const val KEY_STATION_TOKEN = "station_token"
        private const val KEY_STATION_ID = "station_id"
        private const val KEY_STATION_NAME = "station_name"
        private const val KEY_STATION_ROLE = "station_role"
        private const val KEY_VOLUNTEER = "volunteer_name"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Keep screen awake continuously while gate scanner is open
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        database = AppDatabase.getInstance(this)
        apiClient = GateApiClient()
        soundHelper = SoundHelper(this)
        cameraExecutor = Executors.newSingleThreadExecutor()

        deviceId = Build.MANUFACTURER + "_" + Build.MODEL + "_" + (Build.ID.take(4))

        setupUI()
        checkStationAuth()
        updateOutboxCounter()
    }

    private fun setupUI() {
        // Toggle Scanner vs Master Hub Mode
        binding.btnToggleMode.setOnClickListener {
            toggleMasterMode()
        }

        // Torch Toggle
        binding.btnTorch.setOnClickListener {
            toggleTorch()
        }

        // Manual Input Prompt
        binding.btnManualInput.setOnClickListener {
            showManualInputDialog()
        }

        // Unlock PIN Button
        binding.btnUnlockPin.setOnClickListener {
            val pin = binding.etStationPin.text.toString().trim()
            val volunteer = binding.etVolunteerName.text.toString().trim().ifEmpty { "Volunteer" }
            if (pin.length < 4) {
                Toast.makeText(this, "Please enter 4-digit PIN", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            authenticateWithPin(pin, volunteer)
        }

        // Master Hub Cloud Sync Buttons
        binding.btnHubDownloadWhitelist.setOnClickListener {
            downloadCloudWhitelist()
        }
        binding.btnHubFlushCloud.setOnClickListener {
            flushOutboxToCloud()
        }
    }

    private fun checkStationAuth() {
        val token = prefs.getString(KEY_STATION_TOKEN, null)
        val stationId = prefs.getString(KEY_STATION_ID, null)

        if (token.isNullOrEmpty() || stationId.isNullOrEmpty()) {
            // Show PIN / Setup Auth View
            showAuthView()
        } else {
            // Station is paired! Show Scanner
            val stationName = prefs.getString(KEY_STATION_NAME, stationId)
            val volunteer = prefs.getString(KEY_VOLUNTEER, "Volunteer")
            binding.tvStationBadge.text = "$stationName ($volunteer)"
            showScannerView()
        }
    }

    private fun showAuthView() {
        binding.viewAuthPin.visibility = View.VISIBLE
        binding.viewScanner.visibility = View.GONE
        binding.viewMasterHub.visibility = View.GONE
    }

    private fun showScannerView() {
        binding.viewAuthPin.visibility = View.GONE
        binding.viewScanner.visibility = View.VISIBLE
        binding.viewMasterHub.visibility = View.GONE

        if (checkCameraPermission()) {
            startCamera()
        } else {
            requestCameraPermission()
        }
    }

    private fun showMasterHubView() {
        binding.viewAuthPin.visibility = View.GONE
        binding.viewScanner.visibility = View.GONE
        binding.viewMasterHub.visibility = View.VISIBLE

        updateMasterHubStats()
    }

    private fun toggleMasterMode() {
        isMasterMode = !isMasterMode
        if (isMasterMode) {
            binding.btnToggleMode.text = "Scanner Mode"
            binding.btnToggleMode.setTextColor(ContextCompat.getColor(this, R.color.neon_green))
            binding.btnToggleMode.strokeColor = ContextCompat.getColorStateList(this, R.color.neon_green)

            // Start Foreground Service
            val intent = Intent(this, MasterForegroundService::class.java).apply {
                action = MasterForegroundService.ACTION_START
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                startForegroundService(intent)
            } else {
                startService(intent)
            }

            showMasterHubView()
        } else {
            binding.btnToggleMode.text = "Master Hub"
            binding.btnToggleMode.setTextColor(ContextCompat.getColor(this, R.color.warning_orange))
            binding.btnToggleMode.strokeColor = ContextCompat.getColorStateList(this, R.color.warning_orange)

            // Stop Foreground Service
            val intent = Intent(this, MasterForegroundService::class.java).apply {
                action = MasterForegroundService.ACTION_STOP
            }
            startService(intent)

            showScannerView()
        }
    }

    private fun authenticateWithPin(pin: String, volunteer: String) {
        lifecycleScope.launch {
            binding.btnUnlockPin.isEnabled = false
            binding.btnUnlockPin.text = "Authenticating..."

            val result = apiClient.authenticatePin(pin, volunteer, deviceId)
            binding.btnUnlockPin.isEnabled = true
            binding.btnUnlockPin.text = "Unlock Terminal"

            result.onSuccess { json ->
                if (json.has("token") && json.get("token").asString.isNotEmpty()) {
                    val token = json.get("token").asString
                    val stId = json.optString("station_id", "GATE_$pin")
                    val stName = json.optString("station_name", "Gate $pin")
                    val role = json.optString("role", "all")

                    prefs.edit()
                        .putString(KEY_STATION_TOKEN, token)
                        .putString(KEY_STATION_ID, stId)
                        .putString(KEY_STATION_NAME, stName)
                        .putString(KEY_STATION_ROLE, role)
                        .putString(KEY_VOLUNTEER, volunteer)
                        .apply()

                    Toast.makeText(this@MainActivity, "Station Paired: $stName", Toast.LENGTH_SHORT).show()
                    checkStationAuth()
                } else {
                    val msg = json.optString("message", "Invalid Station PIN")
                    Toast.makeText(this@MainActivity, msg, Toast.LENGTH_LONG).show()
                }
            }.onFailure { err ->
                // Local fallback validation for offline pin presets
                val offlinePins = mapOf(
                    "1011" to Triple("GATE_ENG_01", "Gate 1 - Engineer Entry", "engineer"),
                    "1012" to Triple("GATE_ENG_02", "Gate 2 - Engineer Entry", "engineer"),
                    "2011" to Triple("GATE_SOC_01", "Gate 1 - Ruh-e-Raqs Entry", "social"),
                    "2012" to Triple("GATE_SOC_02", "Gate 2 - Ruh-e-Raqs Entry", "social"),
                    "9999" to Triple("GATE_VIP_ALL", "Universal Gate (All)", "all")
                )
                if (offlinePins.containsKey(pin)) {
                    val st = offlinePins[pin]!!
                    prefs.edit()
                        .putString(KEY_STATION_TOKEN, "OFFLINE_LOCAL_TOKEN_$pin")
                        .putString(KEY_STATION_ID, st.first)
                        .putString(KEY_STATION_NAME, st.second)
                        .putString(KEY_STATION_ROLE, st.third)
                        .putString(KEY_VOLUNTEER, volunteer)
                        .apply()
                    Toast.makeText(this@MainActivity, "Offline Station Paired: ${st.second}", Toast.LENGTH_SHORT).show()
                    checkStationAuth()
                } else {
                    Toast.makeText(this@MainActivity, "Auth Error: ${err.message}", Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    // =========================================================================
    // CAMERAX & REAL-TIME ML KIT BARCODE SCANNING
    // =========================================================================

    private fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(this)
        cameraProviderFuture.addListener({
            val cameraProvider = cameraProviderFuture.get()

            val preview = Preview.Builder().build().also {
                it.setSurfaceProvider(binding.cameraPreview.surfaceProvider)
            }

            val options = BarcodeScannerOptions.Builder()
                .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                .build()
            val barcodeScanner = BarcodeScanning.getClient(options)

            val imageAnalysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()

            imageAnalysis.setAnalyzer(cameraExecutor) { imageProxy ->
                processImageProxy(barcodeScanner, imageProxy)
            }

            val cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA

            try {
                cameraProvider.unbindAll()
                camera = cameraProvider.bindToLifecycle(this, cameraSelector, preview, imageAnalysis)
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }, ContextCompat.getMainExecutor(this))
    }

    @androidx.annotation.OptIn(androidx.camera.core.ExperimentalGetImage::class)
    private fun processImageProxy(barcodeScanner: BarcodeScanner, imageProxy: ImageProxy) {
        val mediaImage = imageProxy.image
        if (mediaImage != null && !isScanCooldown) {
            val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)
            barcodeScanner.process(image)
                .addOnSuccessListener { barcodes ->
                    for (barcode in barcodes) {
                        val rawValue = barcode.rawValue ?: continue
                        if (!isScanCooldown) {
                            runOnUiThread {
                                handleDetectedBarcode(rawValue)
                            }
                            break
                        }
                    }
                }
                .addOnCompleteListener {
                    imageProxy.close()
                }
        } else {
            imageProxy.close()
        }
    }

    private fun handleDetectedBarcode(rawCode: String) {
        val parsed = QrParser.parse(rawCode)

        // If Station Setup QR scanned while unauthenticated
        if (parsed.isStationConfig) {
            prefs.edit()
                .putString(KEY_STATION_TOKEN, "QR_PAIR_${parsed.stationPin}")
                .putString(KEY_STATION_ID, parsed.stationId)
                .putString(KEY_STATION_NAME, "Gate ${parsed.stationId}")
                .putString(KEY_STATION_ROLE, parsed.stationRole)
                .apply()
            soundHelper.playSuccess()
            Toast.makeText(this, "Station Paired via QR: ${parsed.stationId}", Toast.LENGTH_SHORT).show()
            checkStationAuth()
            return
        }

        // Attendee QR Check-in
        triggerCheckIn(parsed.ticketId, rawCode)
    }

    private fun triggerCheckIn(ticketId: String, rawCode: String) {
        isScanCooldown = true
        val token = prefs.getString(KEY_STATION_TOKEN, "") ?: ""
        val stationId = prefs.getString(KEY_STATION_ID, "MOBILE_STATION") ?: "MOBILE_STATION"
        val volunteer = prefs.getString(KEY_VOLUNTEER, "Volunteer") ?: "Volunteer"

        lifecycleScope.launch {
            // Tier 1 & 2: Call ApiClient (Master Hub -> Cloud API)
            val netResult = apiClient.performScan(token, rawCode, deviceId)

            netResult.onSuccess { json ->
                val status = json.optString("status", "APPROVED")
                val isSuccess = json.optBoolean("success", false)

                if (isSuccess && status == "APPROVED") {
                    soundHelper.playSuccess()
                    val attendee = json.optJSONObject("attendee")
                    val name = attendee?.optString("name") ?: "Attendee"
                    val code = attendee?.optString("ticket_id") ?: ticketId
                    val event = attendee?.optString("event_name") ?: "Event Admission"
                    showResultBanner("APPROVED", name, code, event, true)
                } else if (status == "DUPLICATE_REJECTED") {
                    soundHelper.playDuplicateOrError()
                    val attendee = json.optJSONObject("attendee")
                    val name = attendee?.optString("name") ?: "Attendee"
                    val note = json.optString("message", "Already Scanned")
                    showResultBanner("DUPLICATE ENTRY DETECTED", name, ticketId, note, false)
                } else {
                    soundHelper.playDuplicateOrError()
                    val msg = json.optString("message", "Invalid Pass")
                    showResultBanner("NOT VERIFIED", "Unknown Pass", ticketId, msg, false)
                }
            }.onFailure {
                // Tier 3: Network completely unreachable -> Fallback to Room SQLite offline database
                resolveOfflineCheckIn(ticketId, stationId, volunteer)
            }

            updateOutboxCounter()

            // 1.8-second auto-cooldown reset
            delay(1800)
            binding.resultBannerCard.visibility = View.GONE
            isScanCooldown = false
        }
    }

    private suspend fun resolveOfflineCheckIn(ticketId: String, stationId: String, volunteer: String) = withContext(Dispatchers.IO) {
        val attendeeDao = database.attendeeDao()
        val auditDao = database.auditLogDao()
        val nowStr = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())

        val attendee = attendeeDao.findByTicket(ticketId)

        if (attendee == null) {
            withContext(Dispatchers.Main) {
                soundHelper.playDuplicateOrError()
                showResultBanner("OFFLINE: NOT FOUND", "Unregistered", ticketId, "Not in downloaded whitelist", false)
            }
        } else if (attendee.is_used) {
            withContext(Dispatchers.Main) {
                soundHelper.playDuplicateOrError()
                showResultBanner("OFFLINE: DUPLICATE", attendee.name, ticketId, "Already entered at ${attendee.used_at}", false)
            }
            // Queue duplicate rejection audit log
            val log = AuditLogEntity(
                ticket_id = ticketId,
                attendee_name = attendee.name,
                gate_type = attendee.gate_type,
                station_id = stationId,
                volunteer_id = volunteer,
                device_id = deviceId,
                status = "DUPLICATE_REJECTED",
                notes = "Offline duplicate rejected. Marked used at ${attendee.used_at}",
                created_at = nowStr,
                synced = false
            )
            auditDao.insert(log)
        } else {
            // APPROVED OFFLINE
            attendee.is_used = true
            attendee.used_at = nowStr
            attendee.used_by_station = stationId
            attendeeDao.update(attendee)

            val log = AuditLogEntity(
                ticket_id = ticketId,
                attendee_name = attendee.name,
                gate_type = attendee.gate_type,
                station_id = stationId,
                volunteer_id = volunteer,
                device_id = deviceId,
                status = "APPROVED",
                notes = "Offline approved and queued in outbox",
                created_at = nowStr,
                synced = false
            )
            auditDao.insert(log)

            withContext(Dispatchers.Main) {
                soundHelper.playSuccess()
                showResultBanner("APPROVED (OFFLINE)", attendee.name, ticketId, attendee.event_name, true)
            }
        }
    }

    private fun showResultBanner(status: String, name: String, ticket: String, detail: String, isSuccess: Boolean) {
        binding.resultBannerCard.visibility = View.VISIBLE
        binding.tvResultStatus.text = status
        binding.tvResultAttendee.text = name
        binding.tvResultTicket.text = "TICKET: $ticket"
        binding.tvResultDetail.text = detail

        if (isSuccess) {
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.neon_green)
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.neon_green))
        } else {
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.danger_red)
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
        }
    }

    private fun showManualInputDialog() {
        val input = EditText(this).apply {
            hint = "e.g. SOC-42 or ENG-108"
            setPadding(40, 30, 40, 30)
        }
        AlertDialog.Builder(this)
            .setTitle("Manual Pass Entry")
            .setView(input)
            .setPositiveButton("Verify") { _, _ ->
                val code = input.text.toString().trim()
                if (code.isNotEmpty()) {
                    triggerCheckIn(code, code)
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun toggleTorch() {
        val control = camera?.cameraControl ?: return
        isTorchOn = !isTorchOn
        control.enableTorch(isTorchOn)
        binding.btnTorch.setColorFilter(if (isTorchOn) Color.parseColor("#00FF94") else Color.WHITE)
    }

    private fun updateOutboxCounter() {
        lifecycleScope.launch(Dispatchers.IO) {
            val count = database.auditLogDao().countUnsynced()
            withContext(Dispatchers.Main) {
                binding.tvOutboxCount.text = "Queue: $count pending"
            }
        }
    }

    private fun updateMasterHubStats() {
        lifecycleScope.launch(Dispatchers.IO) {
            val admitted = database.attendeeDao().countAdmitted()
            val duplicates = database.auditLogDao().countDuplicates()
            withContext(Dispatchers.Main) {
                binding.tvHubAdmittedCount.text = admitted.toString()
                binding.tvHubDuplicateCount.text = duplicates.toString()
            }
        }
    }

    private fun downloadCloudWhitelist() {
        val token = prefs.getString(KEY_STATION_TOKEN, "") ?: ""
        lifecycleScope.launch {
            binding.btnHubDownloadWhitelist.isEnabled = false
            binding.btnHubDownloadWhitelist.text = "Downloading Whitelist..."

            val result = apiClient.fetchCloudWhitelist(token)
            binding.btnHubDownloadWhitelist.isEnabled = true
            binding.btnHubDownloadWhitelist.text = "Sync Whitelist from Cloud DB"

            result.onSuccess { json ->
                val items = json.optJSONArray("whitelist")
                if (items != null) {
                    val list = mutableListOf<AttendeeEntity>()
                    for (i in 0 until items.length()) {
                        val item = items.getJSONObject(i)
                        list.add(
                            AttendeeEntity(
                                ticket_id = item.getString("ticket_id"),
                                attendee_id = item.optInt("attendee_id", 0),
                                name = item.getString("name"),
                                roll_number = item.optString("roll_number"),
                                event_name = item.optString("event_name", "SENTEC Event"),
                                gate_type = item.optString("gate_type", "social"),
                                is_used = item.optInt("is_used", 0) == 1,
                                used_at = item.optString("used_at", null)
                            )
                        )
                    }
                    withContext(Dispatchers.IO) {
                        database.attendeeDao().insertAll(list)
                    }
                    Toast.makeText(this@MainActivity, "Saved ${list.size} attendees to SQLite!", Toast.LENGTH_SHORT).show()
                    updateMasterHubStats()
                }
            }.onFailure {
                Toast.makeText(this@MainActivity, "Download failed: ${it.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun flushOutboxToCloud() {
        val token = prefs.getString(KEY_STATION_TOKEN, "") ?: ""
        lifecycleScope.launch {
            binding.btnHubFlushCloud.isEnabled = false
            binding.btnHubFlushCloud.text = "Uploading..."

            val unsynced = withContext(Dispatchers.IO) { database.auditLogDao().getUnsynced() }
            if (unsynced.isEmpty()) {
                Toast.makeText(this@MainActivity, "Outbox is already clear!", Toast.LENGTH_SHORT).show()
                binding.btnHubFlushCloud.isEnabled = true
                binding.btnHubFlushCloud.text = "Upload Check-Ins to Cloud"
                return@launch
            }

            val jsonArray = com.google.gson.Gson().toJson(unsynced)
            val result = apiClient.pushBatchLogs(token, jsonArray)

            binding.btnHubFlushCloud.isEnabled = true
            binding.btnHubFlushCloud.text = "Upload Check-Ins to Cloud"

            result.onSuccess {
                withContext(Dispatchers.IO) {
                    database.auditLogDao().markSynced(unsynced.map { it.id })
                }
                Toast.makeText(this@MainActivity, "Flushed ${unsynced.size} logs to Cloud!", Toast.LENGTH_SHORT).show()
                updateOutboxCounter()
            }.onFailure {
                Toast.makeText(this@MainActivity, "Upload failed: ${it.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    private fun checkCameraPermission(): Boolean {
        return ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
    }

    private fun requestCameraPermission() {
        ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.CAMERA), CAMERA_PERMISSION_CODE)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == CAMERA_PERMISSION_CODE && grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            Toast.makeText(this, "Camera permission required for QR scanning", Toast.LENGTH_LONG).show()
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        soundHelper.release()
        cameraExecutor.shutdown()
    }
}

// Extension to safely read nullable JSON values
private fun com.google.gson.JsonObject.optString(key: String, fallback: String): String {
    return if (this.has(key) && !this.get(key).isJsonNull) this.get(key).asString else fallback
}
