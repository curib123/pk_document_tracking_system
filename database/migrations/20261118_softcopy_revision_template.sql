-- Seed the permission and default DCO workflow. Safe to rerun.
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
 ('Softcopy: Request Template for Revision','softcopy','Softcopy','request_revision_template','Request Template for Revision',
  'Request and download a DCO-approved editable revision template for an existing softcopy document.',1);
SET @seed_revision_template_grants = ROW_COUNT();

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT rp.role_id,np.id FROM role_permissions rp
JOIN permissions p ON p.id=rp.permission_id AND p.module_key='softcopy' AND p.action_key='request'
JOIN permissions np ON np.module_key='softcopy' AND np.action_key='request_revision_template'
WHERE @seed_revision_template_grants = 1;

INSERT INTO workflows (workflow_key,name,description,request_type,active,created_by)
SELECT 'softcopy_revision_template','Request Template for Revision',
 'Document Control approves and provides an editable template in the final step.',
 'softcopy_revision_template',1,u.id
FROM (SELECT id FROM users ORDER BY id LIMIT 1) u
WHERE NOT EXISTS (SELECT 1 FROM workflows WHERE workflow_key='softcopy_revision_template');

INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at)
SELECT w.id,1,'published',1,
 JSON_OBJECT('steps',JSON_ARRAY(JSON_OBJECT('key','step_1','name','DCO Review and Approval',
 'approver',JSON_OBJECT('type','role','value',r.id,'label',r.name)))),w.created_by,NOW()
FROM workflows w JOIN roles r ON r.name='Document Control Officer' AND r.active=1
WHERE w.workflow_key='softcopy_revision_template'
 AND NOT EXISTS (SELECT 1 FROM workflow_versions v WHERE v.workflow_id=w.id);
