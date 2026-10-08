package org.sentec.scanner.network

import android.content.Context
import fi.iki.elonen.NanoHTTPD
import kotlinx.coroutines.runBlocking
import org.json.JSONArray
import org.json.JSONObject
import org.sentec.scanner.database.AppDatabase
import org.sentec.scanner.database.AuditLogEntity
import org.sentec.scanner.util.QrParser
import java.io.File
import java.io.FileInputStream
import java.text.SimpleDateFormat
import java.util.*

class MasterHubServer(
    port: Int = 8080,
    private val database: AppDatabase,
    private val context: Context? = null
) : NanoHTTPD(port) {

    private val dateFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())

    // Known PIN presets
    private val validPins = mapOf(
        "1011" to Triple("GATE_ENG_01", "Gate 1 - Engineer Entry", "engineer"),
        "1012" to Triple("GATE_ENG_02", "Gate 2 - Engineer Entry", "engineer"),
        "2011" to Triple("GATE_SOC_01", "Gate 1 - Ruh-e-Raqs Entry", "social"),
        "2012" to Triple("GATE_SOC_02", "Gate 2 - Ruh-e-Raqs Entry", "social"),
        "9999" to Triple("GATE_VIP_ALL", "Universal Gate (All)", "all")
    )

    override fun serve(session: IHTTPSession): Response {
        val uri = session.uri
        val method = session.method

        return when {
            method == Method.POST && uri == "/auth/pin" -> handlePinAuth(session)
            method == Method.POST && uri == "/api/scan" -> handleScan(session)
            method == Method.GET && uri == "/api/stats" -> handleStats()
            method == Method.POST && uri == "/api/sync" -> handleBatchSync(session)
            method == Method.GET && uri.startsWith("/media/") -> handleMedia(uri)
            else -> newFixedLengthResponse(Response.Status.NOT_FOUND, "text/plain", "Not Found")
        }
    }

    private fun getMediaUris(ticketId: String, cloudFace: String?, cloudCard: String?): Pair<String, String> {
        val cleanTicket = ticketId.replace("[^A-Za-z0-9_-]".toRegex(), "")
        val photoDir = File(context?.filesDir, "cached_photos")
        val localFace = File(photoDir, "${cleanTicket}_face.webp")
        val localCard = File(photoDir, "${cleanTicket}_card.webp")

        val faceUri = if (localFace.exists()) "http://192.168.43.1:8080/media/${cleanTicket}_face.webp" else (cloudFace ?: "")
        val cardUri = if (localCard.exists()) "http://192.168.43.1:8080/media/${cleanTicket}_card.webp" else (cloudCard ?: "")
        return Pair(faceUri, cardUri)
    }

    private fun handleMedia(uri: String): Response {
        val filename = uri.substringAfterLast("/")
        val photoDir = File(context?.filesDir, "cached_photos")
        val file = File(photoDir, filename)
        if (file.exists() && file.isFile) {
            val mime = when {
                filename.endsWith(".webp", true) -> "image/webp"
                filename.endsWith(".png", true) -> "image/png"
                else -> "image/jpeg"
            }
            return try {
                val fis = FileInputStream(file)
                newFixedLengthResponse(Response.Status.OK, mime, fis, file.length())
            } catch (e: Exception) {
                newFixedLengthResponse(Response.Status.INTERNAL_ERROR, "text/plain", "File read error: ${e.message}")
            }
        }
        return newFixedLengthResponse(Response.Status.NOT_FOUND, "text/plain", "Media not found")
    }

    private fun handlePinAuth(session: IHTTPSession): Response {
        return try {
            val map = HashMap<String, String>()
            session.parseBody(map)
            val postData = map["postData"] ?: "{}"
            val json = JSONObject(postData)
            val pin = json.optString("pin", "").trim()
            val volunteer = json.optString("volunteer_name", "Volunteer")

            val station = validPins[pin]
            if (station != null) {
                val resp = JSONObject().apply {
                    put("success", true)
                    put("token", "MASTER_SIGNED_${station.first}_$pin")
                    put("station_id", station.first)
                    put("station_name", station.second)
                    put("role", station.third)
                    put("volunteer_name", volunteer)
                }
                newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
            } else {
                val err = JSONObject().apply {
                    put("success", false)
                    put("message", "Invalid Station PIN: $pin")
                }
                newFixedLengthResponse(Response.Status.UNAUTHORIZED, "application/json", err.toString())
            }
        } catch (e: Exception) {
            newFixedLengthResponse(Response.Status.INTERNAL_ERROR, "application/json", "{\"success\":false,\"message\":\"${e.message}\"}")
        }
    }

    private fun handleScan(session: IHTTPSession): Response = runBlocking {
        return@runBlocking try {
            val map = HashMap<String, String>()
            session.parseBody(map)
            val postData = map["postData"] ?: "{}"
            val json = JSONObject(postData)
            val action = json.optString("action", "admit")
            val qr = json.optString("qr_payload", "")
            val deviceId = json.optString("device_id", "ScannerClient")

            val parsed = QrParser.parse(qr)
            val attendeeDao = database.attendeeDao()
            val auditLogDao = database.auditLogDao()

            val attendee = attendeeDao.findByTicket(parsed.ticketId)
            val nowStr = dateFormat.format(Date())

            if (attendee == null) {
                // Not found
                if (action != "lookup") {
                    val log = AuditLogEntity(
                        ticket_id = parsed.ticketId,
                        attendee_name = null,
                        gate_type = parsed.gateType,
                        station_id = "MASTER_HUB",
                        volunteer_id = "Client",
                        device_id = deviceId,
                        status = "NOT_FOUND",
                        notes = "Ticket ${parsed.ticketId} not in local database",
                        created_at = nowStr,
                        synced = false
                    )
                    auditLogDao.insert(log)
                }

                val resp = JSONObject().apply {
                    put("success", false)
                    put("status", "NOT_FOUND")
                    put("message", "Ticket code not recognized")
                    put("can_admit", false)
                }
                newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
            } else if (action == "lookup") {
                // PRE-ADMISSION INSPECTION: Return full details without altering database state
                val isDuplicate = attendee.is_used
                val statusStr = if (isDuplicate) "DUPLICATE_REJECTED" else "READY_TO_ADMIT"
                val msg = if (isDuplicate) "Pass already scanned at ${attendee.used_at ?: "earlier"}" else "Pass verified. Confirm physical ID."

                val (faceUri, cardUri) = getMediaUris(attendee.ticket_id, attendee.face_image, attendee.id_card_image)

                val resp = JSONObject().apply {
                    put("success", !isDuplicate)
                    put("status", statusStr)
                    put("message", msg)
                    put("can_admit", !isDuplicate)
                    put("attendee", JSONObject().apply {
                        put("name", attendee.name)
                        put("ticket_id", attendee.ticket_id)
                        put("cnic", attendee.cnic ?: "")
                        put("roll_number", attendee.roll_number ?: "")
                        put("event_name", attendee.event_name)
                        put("gate_type", attendee.gate_type)
                        put("face_image", faceUri)
                        put("id_card_image", cardUri)
                        put("is_used", attendee.is_used)
                        put("used_at", attendee.used_at ?: "")
                    })
                }
                newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
            } else if (attendee.is_used) {
                // DUPLICATE ATTEMPT ON ADMIT
                val log = AuditLogEntity(
                    ticket_id = attendee.ticket_id,
                    attendee_name = attendee.name,
                    gate_type = attendee.gate_type,
                    station_id = "MASTER_HUB",
                    volunteer_id = "Client",
                    device_id = deviceId,
                    status = "DUPLICATE_REJECTED",
                    notes = "Duplicate entry blocked. Originally scanned at ${attendee.used_at ?: "earlier"}",
                    created_at = nowStr,
                    synced = false
                )
                auditLogDao.insert(log)

                val (faceUri, cardUri) = getMediaUris(attendee.ticket_id, attendee.face_image, attendee.id_card_image)

                val resp = JSONObject().apply {
                    put("success", false)
                    put("status", "DUPLICATE_REJECTED")
                    put("message", "Already scanned at ${attendee.used_at ?: "earlier"}")
                    put("can_admit", false)
                    put("attendee", JSONObject().apply {
                        put("name", attendee.name)
                        put("ticket_id", attendee.ticket_id)
                        put("cnic", attendee.cnic ?: "")
                        put("roll_number", attendee.roll_number ?: "")
                        put("event_name", attendee.event_name)
                        put("gate_type", attendee.gate_type)
                        put("face_image", faceUri)
                        put("id_card_image", cardUri)
                        put("used_at", attendee.used_at ?: "")
                    })
                }
                newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
            } else {
                // APPROVED - Mark atomically in SQLite
                attendee.is_used = true
                attendee.used_at = nowStr
                attendee.used_by_station = "MASTER_HUB"
                attendeeDao.update(attendee)

                val log = AuditLogEntity(
                    ticket_id = attendee.ticket_id,
                    attendee_name = attendee.name,
                    gate_type = attendee.gate_type,
                    station_id = "MASTER_HUB",
                    volunteer_id = "Client",
                    device_id = deviceId,
                    status = "APPROVED",
                    notes = "Admitted at Master Hub",
                    created_at = nowStr,
                    synced = false
                )
                auditLogDao.insert(log)

                val (faceUri, cardUri) = getMediaUris(attendee.ticket_id, attendee.face_image, attendee.id_card_image)

                val resp = JSONObject().apply {
                    put("success", true)
                    put("status", "APPROVED")
                    put("message", "Verified & Admitted")
                    put("can_admit", true)
                    put("attendee", JSONObject().apply {
                        put("name", attendee.name)
                        put("ticket_id", attendee.ticket_id)
                        put("cnic", attendee.cnic ?: "")
                        put("roll_number", attendee.roll_number ?: "")
                        put("event_name", attendee.event_name)
                        put("gate_type", attendee.gate_type)
                        put("face_image", faceUri)
                        put("id_card_image", cardUri)
                    })
                }
                newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
            }
        } catch (e: Exception) {
            newFixedLengthResponse(Response.Status.INTERNAL_ERROR, "application/json", "{\"success\":false,\"message\":\"${e.message}\"}")
        }
    }

    private fun handleStats(): Response = runBlocking {
        val attendeeDao = database.attendeeDao()
        val auditDao = database.auditLogDao()

        val admitted = attendeeDao.countAdmitted()
        val total = attendeeDao.countTotal()
        val duplicates = auditDao.countDuplicates()

        val json = JSONObject().apply {
            put("success", true)
            put("stats", JSONObject().apply {
                put("total_whitelist", total)
                put("total_admitted", admitted)
                put("duplicate_rejections", duplicates)
            })
        }
        return@runBlocking newFixedLengthResponse(Response.Status.OK, "application/json", json.toString())
    }

    private fun handleBatchSync(session: IHTTPSession): Response = runBlocking {
        return@runBlocking try {
            val map = HashMap<String, String>()
            session.parseBody(map)
            val postData = map["postData"] ?: "{}"
            val json = JSONObject(postData)
            val logs = json.optJSONArray("logs") ?: JSONArray()

            val auditDao = database.auditLogDao()
            for (i in 0 until logs.length()) {
                val item = logs.getJSONObject(i)
                val log = AuditLogEntity(
                    ticket_id = item.optString("ticket_id"),
                    attendee_name = item.optString("attendee_name").ifEmpty { null },
                    gate_type = item.optString("gate_type", "social"),
                    station_id = item.optString("station_id", "CLIENT_DEVICE"),
                    volunteer_id = item.optString("volunteer_id", "Volunteer"),
                    device_id = item.optString("device_id"),
                    status = item.optString("status", "APPROVED"),
                    notes = item.optString("notes"),
                    created_at = item.optString("created_at"),
                    synced = false
                )
                auditDao.insert(log)
            }

            val resp = JSONObject().apply {
                put("success", true)
                put("synced_count", logs.length())
            }
            newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
        } catch (e: Exception) {
            newFixedLengthResponse(Response.Status.INTERNAL_ERROR, "application/json", "{\"success\":false,\"message\":\"${e.message}\"}")
        }
    }
}
