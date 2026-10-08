-- SENTEC Social Night: Pass Tier Switches & Early Bird Pricing Settings
ALTER TABLE `social_registration_settings`
  ADD COLUMN IF NOT EXISTS `enable_individual` TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `enable_participant` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `enable_group` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `individual_price` INT NOT NULL DEFAULT 500,
  ADD COLUMN IF NOT EXISTS `individual_original_price` INT NOT NULL DEFAULT 700,
  ADD COLUMN IF NOT EXISTS `early_bird_active` TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `participant_price` INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `group_price` INT NOT NULL DEFAULT 1200;
