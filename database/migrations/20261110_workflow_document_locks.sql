-- Record the workflow approval that locked a controlled document attachment.
ALTER TABLE `files`
  ADD COLUMN `lock_request_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN `locked_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `lock_request_id`,
  ADD COLUMN `locked_at` datetime DEFAULT NULL AFTER `locked_by`,
  ADD KEY `files_locked_request` (`lock_request_id`);
