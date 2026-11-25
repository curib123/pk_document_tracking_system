-- Rerunnable repair for existing installations missing these schema constraints.
-- Back up and test on a restored copy first. Existing workflow_versions must
-- reference valid workflows and users. Recover any missing parent records from
-- a verified backup before running; this migration never deletes orphaned rows.
-- MySQL/MariaDB DDL commits implicitly.

SET @add_workflow_parent = NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'workflow_versions'
      AND CONSTRAINT_NAME = 'workflow_versions_ibfk_1'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @workflow_parent_sql = IF(@add_workflow_parent,
    'ALTER TABLE workflow_versions ADD CONSTRAINT workflow_versions_ibfk_1 FOREIGN KEY (workflow_id) REFERENCES workflows (id)',
    'SELECT ''Workflow parent constraint already present'' AS migration_status');
PREPARE workflow_parent_statement FROM @workflow_parent_sql;
EXECUTE workflow_parent_statement;
DEALLOCATE PREPARE workflow_parent_statement;

SET @add_workflow_creator = NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'workflow_versions'
      AND CONSTRAINT_NAME = 'workflow_versions_ibfk_2'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @workflow_creator_sql = IF(@add_workflow_creator,
    'ALTER TABLE workflow_versions ADD CONSTRAINT workflow_versions_ibfk_2 FOREIGN KEY (created_by) REFERENCES users (id)',
    'SELECT ''Workflow creator constraint already present'' AS migration_status');
PREPARE workflow_creator_statement FROM @workflow_creator_sql;
EXECUTE workflow_creator_statement;
DEALLOCATE PREPARE workflow_creator_statement;
