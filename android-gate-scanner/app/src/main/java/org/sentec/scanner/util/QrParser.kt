package org.sentec.scanner.util

import android.net.Uri
import org.json.JSONObject

data class ParsedQrResult(
    val ticketId: String,
    val gateType: String, // "social", "engineer", or "unknown"
    val raw: String,
    val isStationConfig: Boolean = false,
    val stationId: String? = null,
    val stationPin: String? = null,
    val stationRole: String? = null
)

object QrParser {

    fun parse(rawCode: String): ParsedQrResult {
        val trimmed = rawCode.trim()

        // 1. Check if Station Setup QR (JSON)
        if (trimmed.startsWith("{") && trimmed.endsWith("}")) {
            try {
                val json = JSONObject(trimmed)
                if (json.has("station_id") && json.has("pin")) {
                    return ParsedQrResult(
                        ticketId = "",
                        gateType = json.optString("role", "all"),
                        raw = trimmed,
                        isStationConfig = true,
                        stationId = json.getString("station_id"),
                        stationPin = json.getString("pin"),
                        stationRole = json.optString("role", "all")
                    )
                }
            } catch (_: Exception) {}
        }

        // 2. Check if Legacy URL: https://.../verify_social.php?attendee=42
        if (trimmed.startsWith("http://", ignoreCase = true) || trimmed.startsWith("https://", ignoreCase = true)) {
            try {
                val uri = Uri.parse(trimmed)
                val attendeeParam = uri.getQueryParameter("attendee") ?: uri.getQueryParameter("id")
                if (!attendeeParam.isNullOrEmpty()) {
                    val path = uri.path ?: ""
                    val isSocial = path.contains("social", ignoreCase = true)
                    val prefix = if (isSocial) "SOC" else "ENG"
                    val gateType = if (isSocial) "social" else "engineer"
                    return ParsedQrResult(
                        ticketId = "$prefix-$attendeeParam",
                        gateType = gateType,
                        raw = trimmed
                    )
                }
            } catch (_: Exception) {}
        }

        // 3. Compact Codes: SOC-REG-3-1, ENG-REG-3, SOC-42, ENG-108
        val upper = trimmed.uppercase()
        if (upper.startsWith("SOC-REG-") || upper.startsWith("SOCIAL-REG-")) {
            val parts = upper.split("-")
            val reg = parts.getOrNull(2) ?: ""
            val att = parts.getOrNull(3) ?: "1"
            return ParsedQrResult(ticketId = "SOC-REG-$reg-$att", gateType = "social", raw = trimmed)
        }
        if (upper.startsWith("ENG-REG-") || upper.startsWith("EVT-REG-")) {
            val parts = upper.split("-")
            val reg = parts.getOrNull(2) ?: ""
            return ParsedQrResult(ticketId = "ENG-REG-$reg", gateType = "engineer", raw = trimmed)
        }
        if (upper.startsWith("SOC-") || upper.startsWith("SOCIAL-")) {
            val num = upper.substringAfter("-")
            return ParsedQrResult(ticketId = "SOC-$num", gateType = "social", raw = trimmed)
        }
        if (upper.startsWith("ENG-") || upper.startsWith("EVT-")) {
            val num = upper.substringAfter("-")
            return ParsedQrResult(ticketId = "ENG-$num", gateType = "engineer", raw = trimmed)
        }

        // 4. Raw numeric
        if (trimmed.all { it.isDigit() }) {
            return ParsedQrResult(ticketId = trimmed, gateType = "unknown", raw = trimmed)
        }

        // Default fallback
        return ParsedQrResult(ticketId = trimmed, gateType = "unknown", raw = trimmed)
    }
}
