-- Optional starter workflow presets for a NEW installation only.
-- Run after importing pk_dts.sql, seed.sql, and creating the first administrator.
-- Do not run against an existing populated DTS database.
START TRANSACTION;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'softcopy_create','Softcopy Create approval','softcopy_create',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='softcopy_create')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='softcopy_create'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'softcopy_revise','Softcopy Revise approval','softcopy_revise',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='softcopy_revise')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='softcopy_revise'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'softcopy_cancel','Softcopy Cancel approval','softcopy_cancel',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='softcopy_cancel')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='softcopy_cancel'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'hardcopy_create','Hardcopy Create approval','hardcopy_create',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='hardcopy_create')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='hardcopy_create'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'hardcopy_update','Hardcopy Update approval','hardcopy_update',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='hardcopy_update')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='hardcopy_update'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'transfer','Transfer approval','transfer',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='transfer')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='transfer'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'assignment','Assignment approval','assignment',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='assignment')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='assignment'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'access','Access approval','access',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='access')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='access'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
INSERT INTO workflows (workflow_key,name,request_type,active,created_by)
SELECT 'disposal','Disposal approval','disposal',1,u.id
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name='Administrator' AND u.active=1 AND NOT EXISTS
 (SELECT 1 FROM workflows WHERE request_type='disposal')
ORDER BY u.id LIMIT 1;
INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,'{"steps":[{"key":"step_1","name":"Administrator Approval","approver":{"type":"role","value":1,"label":"Administrator"}}]}',u.id,CURRENT_TIMESTAMP
FROM workflows w JOIN users u ON u.id=w.created_by
WHERE w.workflow_key='disposal'
AND NOT EXISTS (SELECT 1 FROM workflow_versions WHERE workflow_id=w.id)
LIMIT 1;
COMMIT;
