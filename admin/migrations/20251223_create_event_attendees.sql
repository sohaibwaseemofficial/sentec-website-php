-- SENTEC Event day attendance migration
-- 1) Run this script on the production database to enable per-member, per-day attendance for event registrations.

CREATE TABLE IF NOT EXISTS `event_attendees` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` int NOT NULL,
  `group_code` varchar(40) NOT NULL,
  `person_index` tinyint unsigned NOT NULL,
  `label` varchar(32) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `cnic` varchar(50) DEFAULT NULL,
  `roll_number` varchar(100) DEFAULT NULL,
  `face_image` varchar(255) DEFAULT NULL,
  `id_card_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `day1_status` enum('pending','present') DEFAULT 'pending',
  `day2_status` enum('pending','present') DEFAULT 'pending',
  `day1_entry_time` datetime DEFAULT NULL,
  `day2_entry_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`registration_id`,`person_index`),
  KEY `idx_group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Backfill existing registrations into attendee rows (one record per participant)
INSERT INTO `event_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`roll_number`,`face_image`,`id_card_image`,`status`,`day1_status`,`day2_status`,`day1_entry_time`,`day2_entry_time`)
SELECT id,
       CONCAT('evt-', id),
       1,
       'Leader',
       participant1_name,
       participant1_email,
       participant1_contact,
       participant1_cnic,
       participant1_roll_number,
       participant1_face_image,
       participant1_id_card,
       status,
       'pending',
       'pending',
       NULL,
       NULL
FROM event_registrations
WHERE participant1_name IS NOT NULL AND participant1_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  roll_number = VALUES(roll_number),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  status = VALUES(status);

INSERT INTO `event_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`roll_number`,`face_image`,`id_card_image`,`status`,`day1_status`,`day2_status`,`day1_entry_time`,`day2_entry_time`)
SELECT id,
       CONCAT('evt-', id),
       2,
       'Member 2',
       participant2_name,
       participant2_email,
       participant2_contact,
       participant2_cnic,
       participant2_roll_number,
       participant2_face_image,
       participant2_id_card,
       status,
       'pending',
       'pending',
       NULL,
       NULL
FROM event_registrations
WHERE participant2_name IS NOT NULL AND participant2_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  roll_number = VALUES(roll_number),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  status = VALUES(status);

INSERT INTO `event_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`roll_number`,`face_image`,`id_card_image`,`status`,`day1_status`,`day2_status`,`day1_entry_time`,`day2_entry_time`)
SELECT id,
       CONCAT('evt-', id),
       3,
       'Member 3',
       participant3_name,
       participant3_email,
       participant3_contact,
       participant3_cnic,
       participant3_roll_number,
       participant3_face_image,
       participant3_id_card,
       status,
       'pending',
       'pending',
       NULL,
       NULL
FROM event_registrations
WHERE participant3_name IS NOT NULL AND participant3_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  roll_number = VALUES(roll_number),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  status = VALUES(status);

INSERT INTO `event_attendees`
(`registration_id`,`group_code`,`person_index`,`label`,`full_name`,`email`,`phone`,`cnic`,`roll_number`,`face_image`,`id_card_image`,`status`,`day1_status`,`day2_status`,`day1_entry_time`,`day2_entry_time`)
SELECT id,
       CONCAT('evt-', id),
       4,
       'Member 4',
       participant4_name,
       participant4_email,
       participant4_contact,
       participant4_cnic,
       participant4_roll_number,
       participant4_face_image,
       participant4_id_card,
       status,
       'pending',
       'pending',
       NULL,
       NULL
FROM event_registrations
WHERE participant4_name IS NOT NULL AND participant4_name <> ''
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  email = VALUES(email),
  phone = VALUES(phone),
  cnic = VALUES(cnic),
  roll_number = VALUES(roll_number),
  face_image = VALUES(face_image),
  id_card_image = VALUES(id_card_image),
  status = VALUES(status);
