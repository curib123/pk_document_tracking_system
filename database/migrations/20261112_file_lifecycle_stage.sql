-- Softcopy file lifecycle: proposed (requester upload) -> locked (authorized
-- approver, default Plant Manager) -> final (file uploaded by the Document
-- Control Officer at the designated finalize step).
-- Apply after the 20261110_* lock migrations. Not rerunnable (ADD COLUMN).
ALTER TABLE `files`
  ADD COLUMN `stage` varchar(20) NOT NULL DEFAULT 'proposed' AFTER `status`,
  ADD COLUMN `source_file_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `lock_request_id`,
  ADD COLUMN `finalized_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `source_file_id`,
  ADD COLUMN `finalized_at` datetime DEFAULT NULL AFTER `finalized_by`,
  ADD KEY `files_source_file` (`source_file_id`);

-- Backfill: locked files, then approved (current) files, keep everything else proposed.
UPDATE `files` SET `stage` = 'locked' WHERE `locked_at` IS NOT NULL;
UPDATE `files` SET `stage` = 'final'
WHERE `locked_at` IS NULL AND `status` = 'approved';

-- Designate the Document Control Officer step as the finalize step on current
-- softcopy workflows. Submitted requests keep their original snapshot and
-- continue to behave as before (no DCO upload required).
UPDATE `workflow_versions` v
JOIN `workflows` w ON w.`id` = v.`workflow_id`
SET v.`graph` = JSON_SET(
  v.`graph`,
  '$.finalize_step_key',
  JSON_EXTRACT(
    v.`graph`,
    REPLACE(
      JSON_UNQUOTE(JSON_SEARCH(v.`graph`, 'one', 'Document Control Officer', NULL, '$.steps[*].approver.label')),
      '.approver.label',
      '.key'
    )
  )
)
WHERE v.`status` = 'published'
  AND v.`is_default` = 1
  AND w.`request_type` IN ('softcopy_create', 'softcopy_revise')
  AND JSON_SEARCH(v.`graph`, 'one', 'Document Control Officer', NULL, '$.steps[*].approver.label') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(
    v.`graph`,
    REPLACE(
      JSON_UNQUOTE(JSON_SEARCH(v.`graph`, 'one', 'Document Control Officer', NULL, '$.steps[*].approver.label')),
      '.approver.label',
      '.approver.type'
    )
  )) = 'role';
