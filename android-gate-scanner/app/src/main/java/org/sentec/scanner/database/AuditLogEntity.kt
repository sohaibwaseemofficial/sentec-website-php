package org.sentec.scanner.database

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "audit_logs")
data class AuditLogEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val ticket_id: String,
    val attendee_name: String?,
    val gate_type: String,
    val station_id: String,
    val volunteer_id: String,
    val device_id: String,
    val status: String, // "APPROVED", "DUPLICATE_REJECTED", "INVALID_ROLE", "NOT_FOUND"
    val notes: String?,
    val created_at: String,
    var synced: Boolean = false
)
