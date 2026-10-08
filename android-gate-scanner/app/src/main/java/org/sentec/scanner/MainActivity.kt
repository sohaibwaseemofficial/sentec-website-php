package org.sentec.scanner

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
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
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
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
    private val imageHttpClient = OkHttpClient()

    private var camera: Camera? = null
    private var isTorchOn = false
    private var isScanCooldown = false
    private var isMasterMode = false
    private var deviceId: String = ""

    // Active Inspected Attendee State
    private var activeInspectedTicket: String = ""
    private var activeInspectedRawCode: String = ""
    private var activeCanAdmit: Boolean = false
    private var activeFaceBitmap: Bitmap? = null
    private var activeIdCardBitmap: Bitmap? = null

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

        // Inspection Sheet Action Listeners
        binding.btnCloseInspection.setOnClickListener {
            dismissInspectionCard()
        }
        binding.btnRejectEntry.setOnClickListener {
            dismissInspectionCard()
        }
        binding.btnGrantEntry.setOnClickListener {
            confirmAdmission()
        }
        binding.boxFacePhoto.setOnClickListener {
            openLightbox("PARTICIPANT FACE PHOTO", activeFaceBitmap)
        }
        binding.boxIdCardPhoto.setOnClickListener {
            openLightbox("PARTICIPANT ID CARD / CNIC", activeIdCardBitmap)
        }
        binding.btnLightboxClose.setOnClickListener {
            binding.viewImageLightbox.visibility = View.GONE
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
                val token = json.optString("token", "")
                if (token.isNotEmpty()) {
                    val stationObj = json.optJSONObject("station")
                    val stId = stationObj?.optString("station_id") ?: json.optString("station_id", "GATE_$pin")
                    val stName = stationObj?.optString("station_name") ?: json.optString("station_name", "Gate $pin")
                    val role = stationObj?.optString("role") ?: json.optString("role", "all")

                    prefs.edit()
                        .putString(KEY_STATION_TOKEN, token)
                        .putString(KEY_STATION_ID, stId)
                        .putString(KEY_STATION_NAME, stName)
                        .putString(KEY_STATION_ROLE, role)
                        .putString(KEY_VOLUNTEER, volunteer)
                        .apply()

                    Toast.makeText(this@MainActivity, "Station Paired: $stName ($role)", Toast.LENGTH_SHORT).show()
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

        // Attendee QR Inspection & Pre-Admission Lookup
        triggerAttendeeLookup(parsed.ticketId, rawCode)
    }

    private fun triggerAttendeeLookup(ticketId: String, rawCode: String) {
        isScanCooldown = true
        activeInspectedTicket = ticketId
        activeInspectedRawCode = rawCode
        activeCanAdmit = false
        activeFaceBitmap = null
        activeIdCardBitmap = null

        val token = prefs.getString(KEY_STATION_TOKEN, "") ?: ""

        // Show Inspection Sheet in Loading State
        binding.resultBannerCard.visibility = View.VISIBLE
        binding.tvResultStatus.text = "LOOKING UP PARTICIPANT..."
        binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.accent_blue))
        binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.accent_blue)
        binding.tvResultAttendee.text = "Fetching Records..."
        binding.tvResultTicket.text = ticketId
        binding.tvResultCnic.text = ""
        binding.tvResultDetail.text = "Resolving participant info & ID photos..."
        binding.layoutInspectionPhotos.visibility = View.GONE
        binding.tvInspectionAlert.text = "Verifying pass against registration database..."
        binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#17202A"))
        binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.accent_blue))
        binding.btnGrantEntry.visibility = View.GONE
        binding.btnRejectEntry.text = "Cancel"

        lifecycleScope.launch {
            val netResult = apiClient.lookupAttendee(token, rawCode, deviceId)

            netResult.onSuccess { json ->
                val status = json.optString("status", "NOT_FOUND")
                val canAdmit = json.optBoolean("can_admit", false)
                val attendeeJson = json.optJSONObject("attendee")

                if (attendeeJson != null) {
                    displayInspectedAttendee(attendeeJson, status, canAdmit, json.optString("message", ""))
                } else {
                    displayLookupFailure(status, json.optString("message", "Ticket not found"))
                }
            }.onFailure {
                // Network unreachable -> fallback to Room SQLite whitelist
                resolveOfflineLookup(ticketId)
            }
        }
    }

    private fun displayInspectedAttendee(
        attendee: JSONObject,
        status: String,
        canAdmit: Boolean,
        message: String
    ) {
        activeCanAdmit = canAdmit
        val name = attendee.optString("name", "Unknown Participant")
        val ticket = attendee.optString("ticket_id", activeInspectedTicket)
        val cnic = attendee.optString("cnic", "")
        val roll = attendee.optString("roll_number", "")
        val event = attendee.optString("event_name", "SENTEC Event Entry")
        val gateType = attendee.optString("gate_type", "social")
        val module = attendee.optString("module", "")
        val team = attendee.optString("team", "")
        val faceUrl = attendee.optString("face_image", "")
        val idCardUrl = attendee.optString("id_card_image", "")
        val usedAt = attendee.optString("entry_time", attendee.optString("used_at", ""))

        binding.tvResultAttendee.text = name
        binding.tvResultTicket.text = ticket
        binding.tvResultCnic.text = if (cnic.isNotEmpty()) " • CNIC: $cnic" else if (roll.isNotEmpty()) " • Roll: $roll" else ""
        binding.tvResultDetail.text = event

        // Display Competition Module & Team Name (for Engineer's Code)
        if (module.isNotEmpty()) {
            binding.tvResultModule.visibility = View.VISIBLE
            val teamStr = if (team.isNotEmpty()) " | TEAM: $team" else ""
            binding.tvResultModule.text = "🎯 MODULE: $module$teamStr"
        } else {
            binding.tvResultModule.visibility = View.GONE
        }

        // Load & Show Photos Row (Clear color tint masks so preview images display clearly)
        binding.layoutInspectionPhotos.visibility = View.VISIBLE
        binding.ivFacePhoto.imageTintList = null
        binding.ivFacePhoto.clearColorFilter()
        binding.ivFacePhoto.setImageResource(android.R.drawable.ic_menu_myplaces)

        binding.ivIdCardPhoto.imageTintList = null
        binding.ivIdCardPhoto.clearColorFilter()
        binding.ivIdCardPhoto.setImageResource(android.R.drawable.ic_menu_gallery)

        if (faceUrl.isNotEmpty()) {
            fetchImageBitmap(faceUrl) { bmp ->
                if (bmp != null) {
                    activeFaceBitmap = bmp
                    binding.ivFacePhoto.imageTintList = null
                    binding.ivFacePhoto.clearColorFilter()
                    binding.ivFacePhoto.setImageBitmap(bmp)
                }
            }
        }

        if (idCardUrl.isNotEmpty()) {
            fetchImageBitmap(idCardUrl) { bmp ->
                if (bmp != null) {
                    activeIdCardBitmap = bmp
                    binding.ivIdCardPhoto.imageTintList = null
                    binding.ivIdCardPhoto.clearColorFilter()
                    binding.ivIdCardPhoto.setImageBitmap(bmp)
                }
            }
        }

        // Status Evaluation & UI Styling
        if (canAdmit && (status == "READY_TO_ADMIT" || status == "APPROVED")) {
            soundHelper.playSuccess()
            binding.tvResultStatus.text = "READY TO ADMIT // VERIFY ID"
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.neon_green))
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.neon_green)
            binding.tvInspectionAlert.text = "VERIFY ATTENDEE: Inspect face photo & physical ID card. If matched, tap GRANT ENTRY below to admit."
            binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#162A1F"))
            binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.neon_green))
            binding.btnGrantEntry.visibility = View.VISIBLE
            binding.btnGrantEntry.isEnabled = true
            binding.btnGrantEntry.text = "GRANT ENTRY (CONFIRM)"
            binding.btnRejectEntry.text = "Reject / Close"
        } else if (status == "DUPLICATE" || status == "DUPLICATE_REJECTED" || attendee.optBoolean("is_used", false) || attendee.optString("attendance_status") == "present") {
            soundHelper.playDuplicateOrError()
            binding.tvResultStatus.text = "RESTRICTION HIT // DUPLICATE DETECTED"
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.danger_red)
            val note = if (usedAt.isNotEmpty()) "Already marked present at $usedAt" else (if (message.isNotEmpty()) message else "Pass already checked in earlier today")
            binding.tvInspectionAlert.text = "⚠️ DUPLICATE ENTRY BLOCKED: $note. Entry is strictly DENIED."
            binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#331515"))
            binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
            binding.btnGrantEntry.visibility = View.GONE
            binding.btnRejectEntry.text = "Dismiss / Scan Next"
        } else if (status == "INVALID_ROLE") {
            soundHelper.playDuplicateOrError()
            binding.tvResultStatus.text = "RESTRICTION HIT // WRONG GATE"
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.warning_orange))
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.warning_orange)
            val gateName = if (gateType.equals("social", ignoreCase = true)) "RUH-E-RAQS Social Night" else "Engineer's Code"
            val correctGate = if (gateType.equals("social", ignoreCase = true)) "RUH-E-RAQS Social Gate" else "Engineer's Code Registration Gate"
            binding.tvInspectionAlert.text = "⚠️ RESTRICTION HIT: This pass belongs to $gateName! Entry denied at this checkpoint. Direct attendee to $correctGate."
            binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#332A15"))
            binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.warning_orange))
            binding.btnGrantEntry.visibility = View.GONE
            binding.btnRejectEntry.text = "Dismiss / Scan Next"
        } else {
            soundHelper.playDuplicateOrError()
            binding.tvResultStatus.text = "RESTRICTION HIT // ACCESS DENIED"
            binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
            binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.danger_red)
            binding.tvInspectionAlert.text = if (message.isNotEmpty()) message else "Entry restricted. Attendee must report to the Admin Desk."
            binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#331515"))
            binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
            binding.btnGrantEntry.visibility = View.GONE
            binding.btnRejectEntry.text = "Dismiss / Scan Next"
        }
    }

    private fun displayLookupFailure(status: String, message: String) {
        soundHelper.playDuplicateOrError()
        binding.tvResultStatus.text = "TICKET NOT FOUND"
        binding.tvResultStatus.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
        binding.resultBannerCard.strokeColor = ContextCompat.getColor(this, R.color.danger_red)
        binding.tvResultAttendee.text = "Unregistered Ticket"
        binding.tvResultTicket.text = activeInspectedTicket
        binding.tvResultCnic.text = ""
        binding.tvResultDetail.text = "No registration records matched this QR code"
        binding.layoutInspectionPhotos.visibility = View.GONE
        binding.tvInspectionAlert.text = message
        binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#331515"))
        binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this, R.color.danger_red))
        binding.btnGrantEntry.visibility = View.GONE
        binding.btnRejectEntry.text = "Scan Next"
    }

    private fun confirmAdmission() {
        if (!activeCanAdmit) return

        binding.btnGrantEntry.isEnabled = false
        binding.btnGrantEntry.text = "RECORDING ENTRY..."

        val token = prefs.getString(KEY_STATION_TOKEN, "") ?: ""
        val stationId = prefs.getString(KEY_STATION_ID, "MOBILE_STATION") ?: "MOBILE_STATION"
        val volunteer = prefs.getString(KEY_VOLUNTEER, "Volunteer") ?: "Volunteer"

        lifecycleScope.launch {
            val netResult = apiClient.admitAttendee(token, activeInspectedRawCode, deviceId)

            netResult.onSuccess { json ->
                val isSuccess = json.optBoolean("success", false)
                if (isSuccess) {
                    soundHelper.playSuccess()
                    binding.tvResultStatus.text = "ADMITTED & RECORDED"
                    binding.tvInspectionAlert.text = "Entry successfully granted & logged to system!"
                    binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#162A1F"))
                    binding.tvInspectionAlert.setTextColor(ContextCompat.getColor(this@MainActivity, R.color.neon_green))
                    binding.btnGrantEntry.text = "ADMITTED ✓"
                    updateOutboxCounter()

                    delay(1200)
                    dismissInspectionCard()
                } else {
                    soundHelper.playDuplicateOrError()
                    binding.tvResultStatus.text = "ADMISSION FAILED"
                    binding.tvInspectionAlert.text = json.optString("message", "Could not record check-in")
                    binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#331515"))
                    binding.btnGrantEntry.isEnabled = true
                    binding.btnGrantEntry.text = "RETRY ADMISSION"
                }
            }.onFailure {
                // Tier 3: Network offline admission
                resolveOfflineAdmission(activeInspectedTicket, stationId, volunteer)
            }
        }
    }

    private fun resolveOfflineLookup(ticketId: String) {
        lifecycleScope.launch(Dispatchers.IO) {
            val attendeeDao = database.attendeeDao()
            val attendee = attendeeDao.findByTicket(ticketId)
            val stationRole = prefs.getString(KEY_STATION_ROLE, "all") ?: "all"

            withContext(Dispatchers.Main) {
                if (attendee == null) {
                    displayLookupFailure("NOT_FOUND", "Ticket $ticketId not found in downloaded whitelist")
                } else {
                    val isWrongRole = stationRole != "all" && attendee.gate_type != "all" && attendee.gate_type != stationRole
                    val status = if (attendee.is_used) {
                        "DUPLICATE_REJECTED"
                    } else if (isWrongRole) {
                        "INVALID_ROLE"
                    } else {
                        "READY_TO_ADMIT"
                    }
                    val canAdmit = !attendee.is_used && !isWrongRole
                    val json = JSONObject().apply {
                        put("name", attendee.name)
                        put("ticket_id", attendee.ticket_id)
                        put("cnic", attendee.cnic ?: "")
                        put("roll_number", attendee.roll_number ?: "")
                        put("event_name", attendee.event_name)
                        put("gate_type", attendee.gate_type)
                        put("face_image", attendee.face_image ?: "")
                        put("id_card_image", attendee.id_card_image ?: "")
                        put("is_used", attendee.is_used)
                        put("used_at", attendee.used_at ?: "")
                    }
                    displayInspectedAttendee(
                        json,
                        status,
                        canAdmit,
                        if (attendee.is_used) "Marked entered at ${attendee.used_at}" else "Offline Whitelist Record"
                    )
                }
            }
        }
    }

    private suspend fun resolveOfflineAdmission(ticketId: String, stationId: String, volunteer: String) = withContext(Dispatchers.IO) {
        val attendeeDao = database.attendeeDao()
        val auditDao = database.auditLogDao()
        val nowStr = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())

        val attendee = attendeeDao.findByTicket(ticketId)

        if (attendee == null) {
            withContext(Dispatchers.Main) {
                displayLookupFailure("NOT_FOUND", "Ticket not found in offline whitelist")
            }
        } else if (attendee.is_used) {
            withContext(Dispatchers.Main) {
                soundHelper.playDuplicateOrError()
                binding.tvResultStatus.text = "OFFLINE DUPLICATE"
                binding.tvInspectionAlert.text = "Already admitted at ${attendee.used_at}!"
                binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#331515"))
                binding.btnGrantEntry.visibility = View.GONE
            }
        } else {
            // Mark used in SQLite
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
                notes = "Admitted offline & queued in outbox",
                created_at = nowStr,
                synced = false
            )
            auditDao.insert(log)

            withContext(Dispatchers.Main) {
                soundHelper.playSuccess()
                binding.tvResultStatus.text = "ADMITTED (OFFLINE QUEUED)"
                binding.tvInspectionAlert.text = "Admitted offline! Queued to sync when connected."
                binding.tvInspectionAlert.setBackgroundColor(Color.parseColor("#162A1F"))
                binding.btnGrantEntry.text = "ADMITTED OFFLINE ✓"
                updateOutboxCounter()

                delay(1200)
                dismissInspectionCard()
            }
        }
    }

    private fun dismissInspectionCard() {
        binding.resultBannerCard.visibility = View.GONE
        binding.viewImageLightbox.visibility = View.GONE
        activeFaceBitmap = null
        activeIdCardBitmap = null
        isScanCooldown = false
    }

    private fun openLightbox(title: String, bitmap: Bitmap?) {
        if (bitmap == null) {
            Toast.makeText(this, "Photo is not available for full-screen inspection", Toast.LENGTH_SHORT).show()
            return
        }
        binding.tvLightboxTitle.text = title
        binding.ivLightboxPhoto.setImageBitmap(bitmap)
        binding.viewImageLightbox.visibility = View.VISIBLE
    }

    private fun fetchImageBitmap(url: String, onLoaded: (Bitmap?) -> Unit) {
        if (url.isBlank()) return
        lifecycleScope.launch(Dispatchers.IO) {
            try {
                val req = Request.Builder().url(url).build()
                val resp = imageHttpClient.newCall(req).execute()
                if (resp.isSuccessful) {
                    val bytes = resp.body?.bytes()
                    if (bytes != null) {
                        val bmp = BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
                        withContext(Dispatchers.Main) {
                            onLoaded(bmp)
                        }
                    }
                }
            } catch (_: Exception) {}
        }
    }

    private fun showManualInputDialog() {
        val input = EditText(this).apply {
            hint = "e.g. SOC-REG-3-1 or ENG-108"
            setPadding(40, 30, 40, 30)
        }
        AlertDialog.Builder(this)
            .setTitle("Manual Pass Lookup")
            .setView(input)
            .setPositiveButton("Lookup & Verify") { _, _ ->
                val code = input.text.toString().trim()
                if (code.isNotEmpty()) {
                    handleDetectedBarcode(code)
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
                        val face = item.optString("face_image", "").ifEmpty { item.optString("p", "") }
                        val idCard = item.optString("id_card_image", "").ifEmpty { item.optString("card", "") }
                        val cnicVal = item.optString("cnic", "")
                        val rollVal = item.optString("roll_number", "")
                        val deptVal = item.optString("dept", "")
                        val usedAtVal = item.optString("used_at", "")

                        list.add(
                            AttendeeEntity(
                                ticket_id = item.getString("ticket_id"),
                                attendee_id = item.optInt("attendee_id", 0),
                                name = item.getString("name"),
                                cnic = cnicVal.ifEmpty { null },
                                roll_number = rollVal.ifEmpty { null },
                                department = deptVal.ifEmpty { null },
                                event_name = item.optString("event_name", "SENTEC Event"),
                                gate_type = item.optString("gate_type", "social"),
                                face_image = face.ifEmpty { null },
                                id_card_image = idCard.ifEmpty { null },
                                is_used = item.optInt("is_used", 0) == 1,
                                used_at = usedAtVal.ifEmpty { null }
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
