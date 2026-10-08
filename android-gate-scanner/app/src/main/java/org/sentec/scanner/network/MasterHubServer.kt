package org.sentec.scanner.network

import fi.iki.elonen.NanoHTTPD
import kotlinx.coroutines.runBlocking
import org.json.JSONArray
import org.json.JSONObject
import org.sentec.scanner.database.AppDatabase
import org.sentec.scanner.database.AuditLogEntity
import org.sentec.scanner.util.QrParser
import java.text.SimpleDateFormat
import java.util.*

class MasterHubServer(
    port: Int = 8080,
    private val database: AppDatabase
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
            else -> newFixedLengthResponse(Response.Status.NOT_FOUND, "text/plain", "Not Found")
        }
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
                        put("face_image", attendee.face_image ?: "")
                        put("id_card_image", attendee.id_card_image ?: "")
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
                        put("face_image", attendee.face_image ?: "")
                        put("id_card_image", attendee.id_card_image ?: "")
                        put("used_at", attendee.used_at)
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
                        put("face_image", attendee.face_image ?: "")
                        put("id_card_image", attendee.id_card_image ?: "")
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
                    attendee_name = item.optString("attendee_name"),
                    gate_type = item.optString("gate_type"),
                    station_id = item.optString("station_id"),
                    volunteer_id = item.optString("volunteer_id"),
                    device_id = item.optString("device_id"),
                    status = item.optString("status"),
                    notes = item.optString("notes"),
                    created_at = item.optString("created_at"),
                    synced = false
                )
                auditDao.insert(log)
            }

            val resp = JSONObject().apply {
                put("success", true)
                put("received", logs.length())
            }
            newFixedLengthResponse(Response.Status.OK, "application/json", resp.toString())
        } catch (e: Exception) {
            newFixedLengthResponse(Response.Status.INTERNAL_ERROR, "application/json", "{\"success\":false,\"message\":\"${e.message}\"}")
        }
    }
}
