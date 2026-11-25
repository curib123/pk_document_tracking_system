-- Rerunnable. Separate permission to permanently delete disposed documents.
-- Left unassigned by default (administrators already hold every permission);
-- grant it from Administration > Roles & Permissions.
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
('Documents: Hard Delete','documents','Documents','hard_delete','Hard Delete',
 'Permanently delete a disposed document, its files and related records. This cannot be undone.',1);
