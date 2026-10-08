package org.sentec.scanner.database

import androidx.room.*

@Dao
interface AttendeeDao {
    @Query("SELECT * FROM attendees WHERE ticket_id = :ticketId LIMIT 1")
    suspend fun findByTicket(ticketId: String): AttendeeEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(attendees: List<AttendeeEntity>)

    @Update
    suspend fun update(attendee: AttendeeEntity)

    @Query("SELECT COUNT(*) FROM attendees WHERE is_used = 1")
    suspend fun countAdmitted(): Int

    @Query("SELECT COUNT(*) FROM attendees")
    suspend fun countTotal(): Int

    @Query("DELETE FROM attendees")
    suspend fun clearAll()
}
