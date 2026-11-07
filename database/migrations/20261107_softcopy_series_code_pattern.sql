-- Add an independent, per-category pattern for automatically generated Softcopy Series No.
-- Apply after the category code-pattern migration. Existing category rows get 001.
-- Safe to rerun; existing data and series numbers are preserved.
SET @category_series_code_pattern_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'categories'
    AND column_name = 'series_code_pattern'
);
SET @category_series_code_pattern_sql = IF(
  @category_series_code_pattern_exists = 0,
  "ALTER TABLE `categories` ADD COLUMN `series_code_pattern` varchar(100) NOT NULL DEFAULT '001' AFTER `code_pattern`",
  'SELECT 1'
);
PREPARE category_series_code_pattern_stmt FROM @category_series_code_pattern_sql;
EXECUTE category_series_code_pattern_stmt;
DEALLOCATE PREPARE category_series_code_pattern_stmt;
