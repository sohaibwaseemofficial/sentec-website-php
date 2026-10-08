package org.sentec.scanner.database

import androidx.room.*

@Dao
interface AuditLogDao {
    @Insert
    suspend fun insert(log: AuditLogEntity): Long

    @Query("SELECT * FROM audit_logs WHERE synced = 0 ORDER BY id ASC")
    suspend fun getUnsynced(): List<AuditLogEntity>

    @Query("UPDATE audit_logs SET synced = 1 WHERE id IN (:ids)")
    suspend fun markSynced(ids: List<Long>)

    @Query("SELECT COUNT(*) FROM audit_logs WHERE synced = 0")
    suspend fun countUnsynced(): Int

    @Query("SELECT COUNT(*) FROM audit_logs WHERE status = 'DUPLICATE_REJECTED'")
    suspend fun countDuplicates(): Int

    @Query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50")
    suspend fun getRecent(): List<AuditLogEntity>
}
