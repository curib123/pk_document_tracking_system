-- Rerunnable. Adds a separate permission for downloading the raw, editable
-- copy of a file once it has been locked after Plant Manager approval.
-- Unlike 20261015_file_action_permissions.sql, this is intentionally left
-- unassigned by default: an administrator grants it (e.g. to Document
-- Control Officer) from Administration > Roles & Permissions.
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
('Files: Download Locked Files','files','Files','download_locked','Download Locked Files',
 'Download the original editable copy of a file after it has been locked by Plant Manager approval. Without this, locked files are read-only preview only.',1);
