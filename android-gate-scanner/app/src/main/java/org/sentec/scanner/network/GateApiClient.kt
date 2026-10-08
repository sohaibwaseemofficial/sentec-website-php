package org.sentec.scanner.network

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.util.concurrent.TimeUnit

class GateApiClient(
    var masterHubUrl: String = "http://192.168.43.1:8080",
    var cloudBaseUrl: String = "https://sentecneduet.live/api/gate"
) {
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    // Fast client for local hotspot master hub (500ms timeout)
    private val localClient = OkHttpClient.Builder()
        .connectTimeout(500, TimeUnit.MILLISECONDS)
        .readTimeout(1000, TimeUnit.MILLISECONDS)
        .build()

    // Resilient client for cloud API
    private val cloudClient = OkHttpClient.Builder()
        .connectTimeout(5, TimeUnit.SECONDS)
        .readTimeout(10, TimeUnit.SECONDS)
        .build()

    /**
     * Authenticate station with 4-digit PIN
     */
    suspend fun authenticatePin(pin: String, volunteerName: String, deviceId: String): Result<JSONObject> = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("pin", pin)
            put("volunteer_name", volunteerName)
            put("device_id", deviceId)
        }
        val body = payload.toString().toRequestBody(jsonMediaType)

        // Try local master hub first
        try {
            val req = Request.Builder().url("$masterHubUrl/auth/pin").post(body).build()
            val resp = localClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val str = resp.body?.string() ?: "{}"
                return@withContext Result.success(JSONObject(str))
            }
        } catch (_: Exception) {}

        // Fallback to cloud API
        try {
            val req = Request.Builder().url("$cloudBaseUrl/auth_pin.php").post(body).build()
            val resp = cloudClient.newCall(req).execute()
            val str = resp.body?.string() ?: "{}"
            if (resp.isSuccessful) {
                return@withContext Result.success(JSONObject(str))
            }
            Result.failure(IOException("Cloud auth failed: ${resp.code}"))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Scan attendee pass: action can be "lookup" (pre-admission inspect) or "admit" (mark checked-in)
     */
    suspend fun performScan(
        token: String,
        qrPayload: String,
        deviceId: String,
        action: String = "admit",
        lat: Double? = null,
        lng: Double? = null
    ): Result<JSONObject> = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("qr_payload", qrPayload)
            put("device_id", deviceId)
            put("action", action)
            if (lat != null && lng != null) {
                put("lat", lat)
                put("lng", lng)
            }
        }
        val body = payload.toString().toRequestBody(jsonMediaType)

        // Tier 1: Try Local Hotspot Master Hub
        try {
            val req = Request.Builder()
                .url("$masterHubUrl/api/scan")
                .header("Authorization", "Bearer $token")
                .post(body)
                .build()
            val resp = localClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val str = resp.body?.string() ?: "{}"
                return@withContext Result.success(JSONObject(str))
            }
        } catch (_: Exception) {}

        // Tier 2: Try Central Cloud API
        try {
            val req = Request.Builder()
                .url("$cloudBaseUrl/scan.php")
                .header("Authorization", "Bearer $token")
                .post(body)
                .build()
            val resp = cloudClient.newCall(req).execute()
            val respString = resp.body?.string() ?: "{}"
            Result.success(JSONObject(respString))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun lookupAttendee(token: String, qrPayload: String, deviceId: String): Result<JSONObject> =
        performScan(token, qrPayload, deviceId, action = "lookup")

    suspend fun admitAttendee(token: String, qrPayload: String, deviceId: String): Result<JSONObject> =
        performScan(token, qrPayload, deviceId, action = "admit")

    /**
     * Fetch complete whitelist from Cloud for offline caching
     */
    suspend fun fetchCloudWhitelist(token: String): Result<JSONObject> = withContext(Dispatchers.IO) {
        try {
            val req = Request.Builder()
                .url("$cloudBaseUrl/seed.php")
                .header("Authorization", "Bearer $token")
                .get()
                .build()
            val resp = cloudClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val str = resp.body?.string() ?: "{}"
                Result.success(JSONObject(str))
            } else {
                Result.failure(IOException("Seed request returned ${resp.code}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Batch push unsynced outbox logs to cloud
     */
    suspend fun pushBatchLogs(token: String, logsJsonArray: String): Result<JSONObject> = withContext(Dispatchers.IO) {
        try {
            val payload = JSONObject().apply {
                put("logs", JSONArray(logsJsonArray))
            }
            val body = payload.toString().toRequestBody(jsonMediaType)
            val req = Request.Builder()
                .url("$cloudBaseUrl/sync_push.php")
                .header("Authorization", "Bearer $token")
                .post(body)
                .build()
            val resp = cloudClient.newCall(req).execute()
            val str = resp.body?.string() ?: "{}"
            Result.success(JSONObject(str))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
