package org.sentec.scanner.network

import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException
import java.util.concurrent.TimeUnit

class GateApiClient(
    var masterHubUrl: String = "http://192.168.43.1:8080",
    var cloudBaseUrl: String = "https://sentecneduet.live/api/gate"
) {
    private val gson = Gson()
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
    suspend fun authenticatePin(pin: String, volunteerName: String, deviceId: String): Result<JsonObject> = withContext(Dispatchers.IO) {
        val payload = JsonObject().apply {
            addProperty("pin", pin)
            addProperty("volunteer_name", volunteerName)
            addProperty("device_id", deviceId)
        }
        val body = payload.toString().toRequestBody(jsonMediaType)

        // Try local master hub first
        try {
            val req = Request.Builder().url("$masterHubUrl/auth/pin").post(body).build()
            val resp = localClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val json = gson.fromJson(resp.body?.string(), JsonObject::class.java)
                return@withContext Result.success(json)
            }
        } catch (_: Exception) {}

        // Fallback to cloud API
        try {
            val req = Request.Builder().url("$cloudBaseUrl/auth_pin.php").post(body).build()
            val resp = cloudClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val json = gson.fromJson(resp.body?.string(), JsonObject::class.java)
                return@withContext Result.success(json)
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
    ): Result<JsonObject> = withContext(Dispatchers.IO) {
        val payload = JsonObject().apply {
            addProperty("qr_payload", qrPayload)
            addProperty("device_id", deviceId)
            addProperty("action", action)
            if (lat != null && lng != null) {
                addProperty("lat", lat)
                addProperty("lng", lng)
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
                val json = gson.fromJson(resp.body?.string(), JsonObject::class.java)
                return@withContext Result.success(json)
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
            val json = gson.fromJson(respString, JsonObject::class.java)
            Result.success(json)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun lookupAttendee(token: String, qrPayload: String, deviceId: String): Result<JsonObject> =
        performScan(token, qrPayload, deviceId, action = "lookup")

    suspend fun admitAttendee(token: String, qrPayload: String, deviceId: String): Result<JsonObject> =
        performScan(token, qrPayload, deviceId, action = "admit")

    /**
     * Fetch complete whitelist from Cloud for offline caching
     */
    suspend fun fetchCloudWhitelist(token: String): Result<JsonObject> = withContext(Dispatchers.IO) {
        try {
            val req = Request.Builder()
                .url("$cloudBaseUrl/seed.php")
                .header("Authorization", "Bearer $token")
                .get()
                .build()
            val resp = cloudClient.newCall(req).execute()
            if (resp.isSuccessful) {
                val json = gson.fromJson(resp.body?.string(), JsonObject::class.java)
                Result.success(json)
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
    suspend fun pushBatchLogs(token: String, logsJsonArray: String): Result<JsonObject> = withContext(Dispatchers.IO) {
        try {
            val payload = JsonObject().apply {
                add("logs", Gson().fromJson(logsJsonArray, com.google.gson.JsonArray::class.java))
            }
            val body = payload.toString().toRequestBody(jsonMediaType)
            val req = Request.Builder()
                .url("$cloudBaseUrl/sync_push.php")
                .header("Authorization", "Bearer $token")
                .post(body)
                .build()
            val resp = cloudClient.newCall(req).execute()
            val json = gson.fromJson(resp.body?.string(), JsonObject::class.java)
            Result.success(json)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
