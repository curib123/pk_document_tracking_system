-- Optional document descriptions; existing scanned records remain valid. Safe to rerun.
SET @scanned_description_sql = IF(
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='scanned_documents' AND COLUMN_NAME='description') = 0,
 'ALTER TABLE scanned_documents ADD COLUMN description varchar(2000) NOT NULL DEFAULT \'\' AFTER title', 'SELECT 1');
PREPARE scanned_description_stmt FROM @scanned_description_sql;
EXECUTE scanned_description_stmt;
DEALLOCATE PREPARE scanned_description_stmt;
