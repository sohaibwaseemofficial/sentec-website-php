-- SQL Migration to add event_link column to events table
-- Run this in phpMyAdmin or MySQL console

ALTER TABLE `events` ADD COLUMN `event_link` VARCHAR(500) DEFAULT NULL AFTER `image_url`;
