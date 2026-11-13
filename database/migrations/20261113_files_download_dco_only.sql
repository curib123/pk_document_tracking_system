-- Rerunnable. Only the Document Control Officer role may download files:
-- the original upload (files.download), the PDF copy (files.download_pdf shares
-- files.download) and locked originals (files.download_locked).
-- The Administrator role is a code-level superuser and is not affected by role grants.
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
('Files: Download Locked Files','files','Files','download_locked','Download Locked Files',
 'Download the original editable copy of a file after it has been locked by Plant Manager approval. Without this, locked files are read-only preview only.',1);

-- Remove both permissions from every role except Document Control Officer.
DELETE rp FROM role_permissions rp
JOIN permissions p ON p.id=rp.permission_id
 AND p.module_key='files' AND p.action_key IN ('download','download_locked')
JOIN roles r ON r.id=rp.role_id
WHERE r.name <> 'Document Control Officer';

-- Grant both to Document Control Officer.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.module_key='files' AND p.action_key IN ('download','download_locked')
WHERE r.name='Document Control Officer';
