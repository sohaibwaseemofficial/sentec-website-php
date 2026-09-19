-- SENTEC Social Night migration
-- 1) Run this script on the Azure database to enable individual attendee tracking for Social Night registrations.

CREATE TABLE IF NOT EXISTS `social_attendees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` int NOT NULL,
  `group_code` varchar(40) NOT NULL,
  `person_index` tinyint unsigned NOT NULL,
  `label` varchar(32) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `cnic` varchar(50) DEFAULT NULL,
  `face_image` varchar(255) DEFAULT NULL,
  `id_card_image` varchar(255) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','submitted','confirmed') DEFAULT 'submitted',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `attendance_status` enum('pending','present') DEFAULT 'pending',
  `entry_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`registration_id`,`person_index`),
  KEY `idx_group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Backfill existing registrations so every participant becomes an attendee row.
INSERT INTO `social_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`face_image`,`id_card_image`,`payment_proof`,`payment_status`,`status`,`attendance_status`,`entry_time`)
SELECT id,
       CONCAT('grp-', id),
       1,
       'Primary',
       full_name,
       email,
       phone,
       cnic,
       face_image,
       id_card_image,
       payment_proof,
       payment_status,
       status,
       CASE WHEN attendance_status IN ('pending','present') THEN attendance_status ELSE 'pending' END,
       entry_time
FROM social_registrations
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  payment_proof = VALUES(payment_proof),
  payment_status = VALUES(payment_status),
  status = VALUES(status),
  attendance_status = VALUES(attendance_status),
  entry_time = VALUES(entry_time);

INSERT INTO `social_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`face_image`,`id_card_image`,`payment_proof`,`payment_status`,`status`,`attendance_status`,`entry_time`)
SELECT id,
       CONCAT('grp-', id),
       2,
       'Person 2',
       participant2_name,
       participant2_email,
       participant2_phone,
       participant2_cnic,
       participant2_face,
       participant2_card,
       payment_proof,
       payment_status,
       status,
       CASE WHEN attendance_status IN ('pending','present') THEN attendance_status ELSE 'pending' END,
       entry_time
FROM social_registrations
WHERE participant2_name IS NOT NULL AND participant2_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  payment_proof = VALUES(payment_proof),
  payment_status = VALUES(payment_status),
  status = VALUES(status),
  attendance_status = VALUES(attendance_status),
  entry_time = VALUES(entry_time);

INSERT INTO `social_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`face_image`,`id_card_image`,`payment_proof`,`payment_status`,`status`,`attendance_status`,`entry_time`)
SELECT id,
       CONCAT('grp-', id),
       3,
       'Person 3',
       participant3_name,
       participant3_email,
       participant3_phone,
       participant3_cnic,
       participant3_face,
       participant3_card,
       payment_proof,
       payment_status,
       status,
       CASE WHEN attendance_status IN ('pending','present') THEN attendance_status ELSE 'pending' END,
       entry_time
FROM social_registrations
WHERE participant3_name IS NOT NULL AND participant3_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  payment_proof = VALUES(payment_proof),
  payment_status = VALUES(payment_status),
  status = VALUES(status),
  attendance_status = VALUES(attendance_status),
  entry_time = VALUES(entry_time);
