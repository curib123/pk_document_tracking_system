-- Series patterns belong to each existing Document No., NOT Softcopy Categories.
-- Apply after database/migrations/20261104_softcopy_series_number_index.sql.
-- The original unnumbered document is assigned one less than the first series
-- when the first new series is approved (e.g. 001 -> original 000, new 001).
-- Existing document rows are never renumbered by this migration itself.
CREATE TABLE IF NOT EXISTS `softcopy_document_series_patterns` (
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `code_pattern` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`,`document_number`),
  CONSTRAINT `softcopy_document_series_category_fk`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- An old experimental category.series_code_pattern column, if present in an
-- earlier feature checkout, is ignored and not dropped to avoid losing data.
