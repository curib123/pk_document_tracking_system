-- Backfill locks for supported files already approved by Plant Manager.
-- Apply after 20261110_workflow_document_locks.sql and
-- 20261110_designate_plant_manager_document_lock.sql.
-- Submitted workflow snapshots and their remaining steps are not changed.
DROP TEMPORARY TABLE IF EXISTS `plant_manager_document_lock_backfill`;

CREATE TEMPORARY TABLE `plant_manager_document_lock_backfill` AS
SELECT
  ranked.`file_id`,
  ranked.`request_id`,
  ranked.`step_id`,
  ranked.`user_id`,
  ranked.`user_name`,
  ranked.`position_title`,
  ranked.`approved_at`
FROM (
  SELECT
    f.`id` AS `file_id`,
    r.`id` AS `request_id`,
    s.`id` AS `step_id`,
    h.`user_id`,
    h.`user_name`,
    h.`position_title`,
    h.`created_at` AS `approved_at`,
    ROW_NUMBER() OVER (
      PARTITION BY f.`id`
      ORDER BY h.`created_at`, h.`id`
    ) AS `approval_order`
  FROM `files` f
  JOIN `requests` r ON (
    (
      r.`type` IN ('softcopy_create', 'external_create')
      AND f.`purpose` = 'creation'
      AND (
        JSON_UNQUOTE(JSON_EXTRACT(r.`payload`, '$.controlled_file_id')) = CAST(f.`id` AS CHAR)
        OR JSON_CONTAINS(
          COALESCE(JSON_EXTRACT(r.`payload`, '$.controlled_file_ids'), JSON_ARRAY()),
          CAST(f.`id` AS CHAR)
        ) = 1
        OR JSON_CONTAINS(
          COALESCE(JSON_EXTRACT(r.`payload`, '$.controlled_file_ids'), JSON_ARRAY()),
          JSON_QUOTE(CAST(f.`id` AS CHAR))
        ) = 1
      )
    )
    OR (
      r.`type` IN ('softcopy_revise', 'external_revise')
      AND f.`purpose` = 'revision'
      AND (
        JSON_UNQUOTE(JSON_EXTRACT(r.`payload`, '$.revision_file_id')) = CAST(f.`id` AS CHAR)
        OR JSON_CONTAINS(
          COALESCE(JSON_EXTRACT(r.`payload`, '$.revision_file_ids'), JSON_ARRAY()),
          CAST(f.`id` AS CHAR)
        ) = 1
        OR JSON_CONTAINS(
          COALESCE(JSON_EXTRACT(r.`payload`, '$.revision_file_ids'), JSON_ARRAY()),
          JSON_QUOTE(CAST(f.`id` AS CHAR))
        ) = 1
      )
    )
  )
  JOIN `workflow_history` h
    ON h.`request_id` = r.`id`
    AND h.`action` = 'approved'
  JOIN `workflow_steps` s
    ON s.`id` = h.`step_id`
    AND s.`request_id` = h.`request_id`
  WHERE f.`uploaded_by` = r.`requested_by`
    AND f.`status` IN ('pending', 'approved', 'rejected', 'superseded')
    AND f.`locked_at` IS NULL
    AND f.`lock_request_id` IS NULL
    AND LOWER(f.`extension`) IN ('xlsx', 'docx', 'pdf')
    AND (
      (r.`type` IN ('softcopy_create', 'softcopy_revise') AND f.`domain` = 'softcopy')
      OR (r.`type` IN ('external_create', 'external_revise') AND f.`domain` = 'external')
    )
    AND (
      r.`type` IN ('softcopy_create', 'external_create')
      OR (
        r.`type` = 'softcopy_revise'
        AND f.`document_id` = r.`softcopy_id`
      )
      OR (
        r.`type` = 'external_revise'
        AND f.`document_id` = CAST(JSON_UNQUOTE(
          JSON_EXTRACT(r.`payload`, '$.external_document_id')
        ) AS UNSIGNED)
      )
    )
    AND JSON_UNQUOTE(JSON_EXTRACT(s.`assignment`, '$.type')) = 'role'
    AND JSON_UNQUOTE(JSON_EXTRACT(s.`assignment`, '$.label')) = 'Plant Manager'
    AND (
      COALESCE(JSON_CONTAINS_PATH(r.`snapshot`, 'one', '$.document_lock_step_key'), 0) = 0
      OR JSON_UNQUOTE(JSON_EXTRACT(r.`snapshot`, '$.document_lock_step_key')) = s.`node_key`
    )
) ranked
WHERE ranked.`approval_order` = 1;

UPDATE `files` f
JOIN `plant_manager_document_lock_backfill` b ON b.`file_id` = f.`id`
SET f.`lock_request_id` = b.`request_id`,
    f.`locked_by` = b.`user_id`,
    f.`locked_at` = b.`approved_at`
WHERE f.`locked_at` IS NULL
  AND f.`lock_request_id` IS NULL;

INSERT INTO `workflow_history` (
  `request_id`,
  `step_id`,
  `action`,
  `user_id`,
  `user_name`,
  `position_title`,
  `after_state`,
  `comments`,
  `created_at`
)
SELECT
  b.`request_id`,
  b.`step_id`,
  'document_locked',
  b.`user_id`,
  b.`user_name`,
  b.`position_title`,
  JSON_OBJECT(
    'file_id', b.`file_id`,
    'request_id', b.`request_id`,
    'designated_approver', 'Plant Manager',
    'approval_timestamp', b.`approved_at`,
    'retroactive', TRUE
  ),
  'Document lock backfilled from Plant Manager approval.',
  b.`approved_at`
FROM `plant_manager_document_lock_backfill` b
WHERE NOT EXISTS (
  SELECT 1
  FROM `workflow_history` existing
  WHERE existing.`request_id` = b.`request_id`
    AND existing.`action` = 'document_locked'
    AND JSON_UNQUOTE(JSON_EXTRACT(existing.`after_state`, '$.file_id')) = CAST(b.`file_id` AS CHAR)
);

DROP TEMPORARY TABLE `plant_manager_document_lock_backfill`;
