-- Designate the configured Plant Manager step on current document workflows.
-- Apply after 20261110_workflow_document_locks.sql.
-- Submitted requests retain their original immutable workflow snapshot.
UPDATE `workflow_versions` v
JOIN `workflows` w ON w.`id` = v.`workflow_id`
SET v.`graph` = JSON_SET(
  v.`graph`,
  '$.document_lock_step_key',
  JSON_EXTRACT(
    v.`graph`,
    REPLACE(
      JSON_UNQUOTE(JSON_SEARCH(v.`graph`, 'one', 'Plant Manager', NULL, '$.steps[*].approver.label')),
      '.approver.label',
      '.key'
    )
  )
)
WHERE v.`status` = 'published'
  AND v.`is_default` = 1
  AND w.`request_type` IN ('softcopy_create', 'softcopy_revise', 'external_create', 'external_revise')
  AND JSON_SEARCH(v.`graph`, 'one', 'Plant Manager', NULL, '$.steps[*].approver.label') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(
    v.`graph`,
    REPLACE(
      JSON_UNQUOTE(JSON_SEARCH(v.`graph`, 'one', 'Plant Manager', NULL, '$.steps[*].approver.label')),
      '.approver.label',
      '.approver.type'
    )
  )) = 'role';
