-- Run after schema.sql. Never seed known/default user passwords.
USE pk_dts;
INSERT IGNORE INTO roles (name,description,is_system) VALUES
 ('staff','Employee and request creator',1),
 ('plant_manager','Plant management approval',1),
 ('document_control_officer','Documents and workflow administration',1),
 ('internal_audit','Read-only document audit and request review',1),
 ('super_admin','Full system administration',1);
INSERT IGNORE INTO permissions (module,action) VALUES
 ('dashboard','view'),
 ('hardcopy','view'),('hardcopy','create'),('hardcopy','edit'),('hardcopy','delete'),
 ('softcopy','view'),('softcopy','create'),('softcopy','edit'),('softcopy','delete'),
 ('requests','view'),('requests','create'),('requests','edit'),('requests','delete'),('requests','submit'),
 ('tasks','view'),('tasks','approve'),('tasks','reject'),('tasks','return'),
 ('area','view'),('area','create'),('area','edit'),('area','delete'),
 ('specific','view'),('specific','create'),('specific','edit'),('specific','delete'),
 ('asset','view'),('asset','create'),('asset','edit'),('asset','delete'),
 ('location','view'),('location','create'),('location','edit'),('location','delete'),
 ('sequence','view'),('sequence','create'),('sequence','edit'),('sequence','delete'),
 ('softcopy-categories','view'),('softcopy-categories','create'),('softcopy-categories','edit'),('softcopy-categories','delete'),
 ('users','view'),('users','create'),('users','edit'),('users','delete'),
 ('roles','view'),('roles','create'),('roles','edit'),
 ('workflows','view'),('workflows','create'),('workflows','edit'),('workflows','delete');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='super_admin';
INSERT IGNORE INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='staff'
 AND (p.module='dashboard' AND p.action='view'
 OR p.module IN ('hardcopy','softcopy') AND p.action='view'
 OR p.module='requests' AND p.action IN ('view','create','edit','delete','submit')
 OR p.module='tasks' AND p.action='view');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='plant_manager'
 AND (p.module IN ('dashboard','hardcopy','softcopy','requests','tasks') AND p.action='view'
 OR p.module='tasks' AND p.action IN ('approve','reject','return'));
INSERT IGNORE INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='document_control_officer'
 AND (p.module IN ('dashboard','hardcopy','softcopy','requests','tasks','area','specific','asset','location','sequence','softcopy-categories','workflows')
 OR p.module='tasks' AND p.action IN ('approve','reject','return'));
INSERT IGNORE INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='internal_audit' AND p.action='view';
