-- Apply after 20261117_scanned_documents.sql. Safe to rerun.
-- Direct Master Data categories have no originating approval request.
ALTER TABLE scanned_categories MODIFY request_id bigint unsigned DEFAULT NULL;
SET @scanned_version_sql = IF(
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='scanned_categories' AND COLUMN_NAME='version') = 0,
 'ALTER TABLE scanned_categories ADD COLUMN version int NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE scanned_version_stmt FROM @scanned_version_sql;
EXECUTE scanned_version_stmt;
DEALLOCATE PREPARE scanned_version_stmt;

CREATE TABLE IF NOT EXISTS scanned_category_allocations (
 category_id bigint unsigned NOT NULL,
 user_id bigint unsigned NOT NULL,
 allocated_by bigint unsigned NOT NULL,
 allocated_at timestamp NOT NULL DEFAULT current_timestamp(),
 PRIMARY KEY (category_id,user_id),
 KEY scanned_allocation_user (user_id),
 FOREIGN KEY (category_id) REFERENCES scanned_categories(id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (allocated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
 ('Scanned Categories: View','scanned_categories','Scanned Categories','view','View','View scanned category folders in Master Data.',1),
 ('Scanned Categories: Create','scanned_categories','Scanned Categories','add','Create','Create approved root or nested scanned folders in Master Data.',1),
 ('Scanned Categories: Edit','scanned_categories','Scanned Categories','edit','Edit','Rename or move scanned folders in Master Data.',1),
 ('Scanned Categories: Allocate','scanned_categories','Scanned Categories','allocate','Allocate to Users','Allocate scanned folders to users for upload selection.',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),action_label=VALUES(action_label),description=VALUES(description);

-- Initial DCO grants are editable through Roles & Permissions; runtime uses permissions.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.module_key='scanned_categories'
 AND p.action_key IN ('view','add','edit','allocate')
WHERE r.name='Document Control Officer' AND r.active=1;
