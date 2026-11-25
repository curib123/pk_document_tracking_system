SET @softcopy_number_index_unique = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'softcopy_documents'
    AND index_name = 'document_number'
    AND non_unique = 0
);
SET @softcopy_number_index_sql = IF(
  @softcopy_number_index_unique > 0,
  'ALTER TABLE `softcopy_documents` DROP INDEX `document_number`',
  'SELECT 1'
);
PREPARE softcopy_number_index_stmt FROM @softcopy_number_index_sql;
EXECUTE softcopy_number_index_stmt;
DEALLOCATE PREPARE softcopy_number_index_stmt;

SET @softcopy_number_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'softcopy_documents'
    AND index_name = 'document_number'
);
SET @softcopy_number_index_sql = IF(
  @softcopy_number_index_exists = 0,
  'ALTER TABLE `softcopy_documents` ADD KEY `document_number` (`document_number`)',
  'SELECT 1'
);
PREPARE softcopy_number_index_stmt FROM @softcopy_number_index_sql;
EXECUTE softcopy_number_index_stmt;
DEALLOCATE PREPARE softcopy_number_index_stmt;

SET @softcopy_document_series_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'softcopy_documents'
    AND index_name = 'document_series'
    AND non_unique = 0
);
SET @softcopy_document_series_index_sql = IF(
  @softcopy_document_series_index_exists = 0,
  'ALTER TABLE `softcopy_documents` ADD UNIQUE KEY `document_series` (`document_number`,`series_number`)',
  'SELECT 1'
);
PREPARE softcopy_document_series_index_stmt FROM @softcopy_document_series_index_sql;
EXECUTE softcopy_document_series_index_stmt;
DEALLOCATE PREPARE softcopy_document_series_index_stmt;
