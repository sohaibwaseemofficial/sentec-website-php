package org.sentec.scanner.database

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "attendees")
data class AttendeeEntity(
    @PrimaryKey
    val ticket_id: String,
    val attendee_id: Int,
    val name: String,
    val cnic: String? = null,
    val roll_number: String? = null,
    val department: String? = null,
    val event_name: String,
    val gate_type: String, // "social" or "engineer"
    val face_image: String? = null,
    val id_card_image: String? = null,
    var is_used: Boolean = false,
    var used_at: String? = null,
    var used_by_station: String? = null
)

