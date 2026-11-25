SET @external_document_retention_columns = (
  SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', column_name, '`') ORDER BY ordinal_position SEPARATOR ', ')
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'external_documents'
    AND column_name IN ('retention_period', 'retention_start_date', 'retention_end_date')
);
SET @external_document_retention_sql = IF(
  @external_document_retention_columns IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `external_documents` ', @external_document_retention_columns)
);
PREPARE external_document_retention_stmt FROM @external_document_retention_sql;
EXECUTE external_document_retention_stmt;
DEALLOCATE PREPARE external_document_retention_stmt;

SET @external_revision_retention_columns = (
  SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', column_name, '`') ORDER BY ordinal_position SEPARATOR ', ')
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'external_revisions'
    AND column_name IN ('retention_period', 'retention_start_date', 'retention_end_date')
);
SET @external_revision_retention_sql = IF(
  @external_revision_retention_columns IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE `external_revisions` ', @external_revision_retention_columns)
);
PREPARE external_revision_retention_stmt FROM @external_revision_retention_sql;
EXECUTE external_revision_retention_stmt;
DEALLOCATE PREPARE external_revision_retention_stmt;
