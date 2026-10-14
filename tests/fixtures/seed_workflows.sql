-- PK DTS predefined request-workflow definitions.
-- Safe to rerun: creates only missing workflow TYPES, and does not touch
-- existing versions, published defaults, live approvals or request history.
-- Prerequisite: database schema and at least one active Administrator user.
-- After running, open Administration > Workflow Builder, click Start First
-- Draft for each missing route, add its approval steps, and Publish.
-- An empty route must never be published, since submission requires steps.

SET @workflow_admin_id := (
    SELECT u.id FROM users u
    JOIN roles r ON r.id=u.role_id
    WHERE u.active=1 AND r.active=1 AND r.name='Administrator'
    ORDER BY u.id ASC LIMIT 1
);

INSERT INTO workflows (workflow_key, name, request_type, active, created_by)
SELECT seeded.workflow_key, seeded.name, seeded.request_type, 1, @workflow_admin_id
FROM (
    SELECT 'softcopy_create' workflow_key, 'Softcopy Create approval' name, 'softcopy_create' request_type
    UNION ALL SELECT 'softcopy_revise','Softcopy Revise approval','softcopy_revise'
    UNION ALL SELECT 'softcopy_cancel','Softcopy Cancel approval','softcopy_cancel'
    UNION ALL SELECT 'hardcopy_create','Hardcopy Create approval','hardcopy_create'
    UNION ALL SELECT 'hardcopy_update','Hardcopy Update approval','hardcopy_update'
    UNION ALL SELECT 'transfer','Transfer approval','transfer'
    UNION ALL SELECT 'assignment','Assignment approval','assignment'
    UNION ALL SELECT 'access','Access approval','access'
    UNION ALL SELECT 'disposal','Disposal approval','disposal'
) AS seeded
WHERE @workflow_admin_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM workflows existing
    WHERE existing.request_type=seeded.request_type
);

-- Confirm the nine definitions (and whether any route is still unconfigured):
SELECT w.workflow_key, w.request_type, w.active,
       MAX(CASE WHEN v.status='published' AND v.is_default=1 THEN v.version_number END) AS published_default
FROM workflows w LEFT JOIN workflow_versions v ON v.workflow_id=w.id
GROUP BY w.id, w.workflow_key, w.request_type, w.active
ORDER BY w.request_type;
