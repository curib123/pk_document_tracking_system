
-- Default Request Workflows
-- For a NEW DTS installation only.

INSERT INTO workflows (workflow_key, name, request_type, active, created_by)
VALUES
('softcopy_create', 'Softcopy Create approval', 'softcopy_create', 1, 1),
('softcopy_revise', 'Softcopy Revise approval', 'softcopy_revise', 1, 1),
('softcopy_cancel', 'Softcopy Cancel approval', 'softcopy_cancel', 1, 1),
('hardcopy_create', 'Hardcopy Create approval', 'hardcopy_create', 1, 1),
('hardcopy_update', 'Hardcopy Update approval', 'hardcopy_update', 1, 1),
('transfer', 'Transfer approval', 'transfer', 1, 1),
('assignment', 'Assignment approval', 'assignment', 1, 1),
('access', 'Access approval', 'access', 1, 1),
('disposal', 'Disposal approval', 'disposal', 1, 1);

