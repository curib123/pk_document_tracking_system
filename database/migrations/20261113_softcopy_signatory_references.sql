-- Signatories References for softcopy revisions (optional, any number per revision).
-- Each row records one signatory exactly as entered (title, full name, position).
-- There are no master tables: dropdown options are the distinct values already saved
-- in this table. Rows are append-only; the application offers no edit or delete path.
-- Apply after the 20261112_* migrations. Safe to re-run.
CREATE TABLE IF NOT EXISTS `softcopy_signatory_references` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `softcopy_id` bigint(20) UNSIGNED NOT NULL,
  `revision_id` bigint(20) UNSIGNED NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `signatory_title` varchar(150) NOT NULL,
  `signatory_full_name` varchar(150) NOT NULL,
  `signatory_position` varchar(150) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ssr_revision` (`revision_id`,`sort_order`),
  KEY `ssr_softcopy` (`softcopy_id`),
  KEY `ssr_title` (`signatory_title`),
  KEY `ssr_full_name` (`signatory_full_name`),
  KEY `ssr_position` (`signatory_position`),
  KEY `ssr_created_by` (`created_by`),
  CONSTRAINT `ssr_softcopy_fk` FOREIGN KEY (`softcopy_id`) REFERENCES `softcopy_documents` (`id`),
  CONSTRAINT `ssr_revision_fk` FOREIGN KEY (`revision_id`) REFERENCES `softcopy_revisions` (`id`),
  CONSTRAINT `ssr_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
