-- Scanned records become usable only after their request completes DCO approval.
CREATE TABLE IF NOT EXISTS scanned_categories (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 parent_id bigint unsigned DEFAULT NULL,
 parent_scope bigint unsigned GENERATED ALWAYS AS (COALESCE(parent_id,0)) STORED,
 name varchar(150) NOT NULL,
 created_by bigint unsigned NOT NULL,
 approved_by bigint unsigned NOT NULL,
 request_id bigint unsigned NOT NULL,
 created_at timestamp NOT NULL DEFAULT current_timestamp(),
 updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 KEY scanned_category_parent (parent_id),
 UNIQUE KEY scanned_category_sibling (parent_scope,name),
 UNIQUE KEY scanned_category_request (request_id),
 FOREIGN KEY (parent_id) REFERENCES scanned_categories(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (approved_by) REFERENCES users(id),
 FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS scanned_documents (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 title varchar(255) NOT NULL,
 category_id bigint unsigned NOT NULL,
 retention_start_date date NOT NULL,
 retention_end_date date NOT NULL,
 scan_due_date date DEFAULT NULL,
 status varchar(20) NOT NULL DEFAULT 'active',
 version int NOT NULL DEFAULT 1,
 created_by bigint unsigned NOT NULL,
 approved_by bigint unsigned NOT NULL,
 created_at timestamp NOT NULL DEFAULT current_timestamp(),
 updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 KEY scanned_owner_status (created_by,status),
 KEY scanned_retention (status,retention_end_date),
 FOREIGN KEY (category_id) REFERENCES scanned_categories(id),
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
 ('Scanned Documents: View','scanned_documents','Scanned Documents','view','View','View scanned records within document access.',1),
 ('Scanned Documents: Request','scanned_documents','Scanned Documents','request','Upload','Request registration of scanned documents.',1),
 ('Scanned Documents: Update','scanned_documents','Scanned Documents','edit','Request Update','Request changes, subject to DCO approval.',1),
 ('Scanned Documents: Delete','scanned_documents','Scanned Documents','delete','Request Deletion','Request deletion, subject to DCO approval.',1),
 ('Scanned Categories: Request','scanned_categories','Scanned Categories','request','Request Category','Request root or nested folders, subject to DCO approval.',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);

-- Users with existing document request grants receive the new upload capability.
-- Update/delete are deliberately assigned separately by an administrator.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT rp.role_id,np.id FROM role_permissions rp
JOIN permissions p ON p.id=rp.permission_id AND p.module_key='softcopy' AND p.action_key IN ('view','request')
JOIN permissions np ON np.module_key='scanned_documents' AND np.action_key=p.action_key;
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT rp.role_id,np.id FROM role_permissions rp
JOIN permissions p ON p.id=rp.permission_id AND p.module_key='scanned_documents' AND p.action_key='request'
JOIN permissions np ON np.module_key='scanned_categories' AND np.action_key='request';

INSERT INTO workflows (workflow_key,name,description,request_type,active,created_by)
SELECT t.type,t.name,'Requires final Document Control Officer approval.',t.type,1,u.id
FROM (
 SELECT 'scanned_create' AS type,'Scanned Document Upload' AS name UNION ALL
 SELECT 'scanned_update','Scanned Document Update' UNION ALL
 SELECT 'scanned_delete','Scanned Document Deletion' UNION ALL
 SELECT 'scanned_category_create','Scanned Category Approval'
) t JOIN (SELECT id FROM users ORDER BY id LIMIT 1) u
WHERE NOT EXISTS (SELECT 1 FROM workflows w WHERE w.workflow_key=t.type);

INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,
 JSON_OBJECT('steps',JSON_ARRAY(JSON_OBJECT('key','step_1','name','DCO Review and Approval',
 'approver',JSON_OBJECT('type','role','value',r.id,'label',r.name)))),w.created_by,NOW()
FROM workflows w JOIN roles r ON r.name='Document Control Officer' AND r.active=1
WHERE w.request_type IN ('scanned_create','scanned_update','scanned_delete','scanned_category_create')
 AND NOT EXISTS (SELECT 1 FROM workflow_versions v WHERE v.workflow_id=w.id);
