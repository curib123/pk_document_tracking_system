-- Rerunnable. Preserve existing file access on first installation of each action.
-- Rerunning never restores a permission later removed by an administrator.
SET @add_history = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='history');
SET @add_print = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='print');
SET @add_download = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='download');
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
('Files: History','files','Files','history','File History','View controlled file revision history within authorized documents.',1),
('Files: Print','files','Files','print','Print','Print the current approved file within authorized documents.',1),
('Files: Download','files','Files','download','Download','Download the current approved file within authorized documents.',1);
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT DISTINCT rp.role_id, added.id
FROM role_permissions rp
JOIN permissions existing ON existing.id=rp.permission_id AND existing.module_key='files' AND existing.action_key IN ('view','view_all')
JOIN permissions added ON added.module_key='files' AND
 ((added.action_key='history' AND @add_history) OR (added.action_key='print' AND @add_print) OR (added.action_key='download' AND @add_download));
