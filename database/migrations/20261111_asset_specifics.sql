-- One Asset Number can belong to many Specifics.
-- Each Asset Number stays a single record; asset_specifics lists every Specific it is used under.
-- Existing assets keep their current Specific as their first link.

CREATE TABLE IF NOT EXISTS `asset_specifics` (
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `specific_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`asset_id`, `specific_id`),
  KEY `idx_asset_specifics_specific` (`specific_id`),
  CONSTRAINT `asset_specifics_asset_fk` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  CONSTRAINT `asset_specifics_specific_fk` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `asset_specifics` (`asset_id`, `specific_id`)
SELECT `id`, `specific_id` FROM `assets` WHERE `specific_id` IS NOT NULL;

-- The single Specific column is replaced by the links above.
ALTER TABLE `assets`
  DROP FOREIGN KEY `assets_ibfk_1`,
  DROP INDEX `asset_lookup`,
  DROP INDEX `specific_id`,
  DROP COLUMN `specific_id`,
  ADD KEY `asset_lookup` (`active`, `asset_number`);
