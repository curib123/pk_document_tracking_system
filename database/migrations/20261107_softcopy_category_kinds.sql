-- Softcopy Categories now represent either a document-number pattern
-- or a folder-only hierarchy node. Existing categories remain numbered.
-- Apply after database/migrations/20261104_category_code_pattern.sql.
-- Safe to rerun. Existing documents and parent hierarchy are untouched.
SET @softcopy_category_kind_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'categories'
    AND column_name = 'category_type'
);
SET @softcopy_category_kind_sql = IF(
  @softcopy_category_kind_exists = 0,
  'ALTER TABLE `categories` ADD COLUMN `category_type` varchar(20) NOT NULL DEFAULT ''pattern'' AFTER `folder_name`',
  'SELECT 1'
);
PREPARE softcopy_category_kind_statement FROM @softcopy_category_kind_sql;
EXECUTE softcopy_category_kind_statement;
DEALLOCATE PREPARE softcopy_category_kind_statement;

-- Allow multiple folder-only categories to have no pattern while preserving
-- the existing UNIQUE(category_code_pattern) constraint for real numbered
-- categories. Multiple NULL values are allowed by MySQL/MariaDB unique keys.
ALTER TABLE `categories`
  MODIFY COLUMN `code_pattern` varchar(150) NULL DEFAULT NULL;

UPDATE `categories`
SET `code_pattern` = NULL
WHERE `category_type` = 'folder' AND `code_pattern` = '';

-- New folder-only rows use category_type = 'folder' and code_pattern = NULL.
-- Numbered categories use category_type = 'pattern' and PPM-001, etc.
-- Do not automatically convert existing parent categories into folders,
-- because they may already contain controlled documents.
