-- Folder-only categories do not have a document-number pattern.
-- Apply after database/migrations/20261107_softcopy_category_kinds.sql.
-- MySQL unique indexes allow multiple NULL values, so folder nodes can share
-- the category_code_pattern index with no synthetic or empty pattern value.
ALTER TABLE `categories`
  MODIFY COLUMN `code_pattern` varchar(150) DEFAULT 'DOC-001';

UPDATE `categories`
SET `code_pattern` = NULL
WHERE `category_type` = 'folder';
