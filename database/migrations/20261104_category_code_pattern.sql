ALTER TABLE `categories`
  ADD COLUMN `code_pattern` varchar(150) NOT NULL DEFAULT 'DOC-001' AFTER `folder_name`;

UPDATE `categories` SET `code_pattern` = CONCAT('DOC-', `id`, '-001');

ALTER TABLE `categories`
  ADD UNIQUE KEY `category_code_pattern` (`code_pattern`);

ALTER TABLE `softcopy_documents`
  DROP INDEX `document_number`,
  ADD KEY `document_number` (`document_number`),
  ADD UNIQUE KEY `document_series` (`document_number`,`series_number`);

CREATE TABLE `external_documents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` varchar(2000) NOT NULL DEFAULT '',
  `retention_period` varchar(100) NOT NULL,
  `retention_start_date` date DEFAULT NULL,
  `retention_end_date` date DEFAULT NULL,
  `current_revision_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `external_documents_creator_status` (`created_by`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `external_revisions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `revision_number` int(10) UNSIGNED NOT NULL,
  `revision_level` varchar(30) NOT NULL,
  `description` varchar(2000) NOT NULL DEFAULT '',
  `retention_period` varchar(100) NOT NULL,
  `retention_start_date` date DEFAULT NULL,
  `retention_end_date` date DEFAULT NULL,
  `file_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_revision_number` (`document_id`,`revision_number`),
  UNIQUE KEY `external_revision_file` (`file_id`),
  KEY `external_revision_uploaded_by` (`uploaded_by`),
  KEY `external_revision_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `workflows` (`workflow_key`,`name`,`description`,`request_type`,`active`,`created_by`)
SELECT 'external_create','External Create approval',`description`,'external_create',1,`created_by`
FROM `workflows` WHERE `request_type`='softcopy_create' AND `active`=1 LIMIT 1;

INSERT INTO `workflows` (`workflow_key`,`name`,`description`,`request_type`,`active`,`created_by`)
SELECT 'external_revise','External Revise approval',`description`,'external_revise',1,`created_by`
FROM `workflows` WHERE `request_type`='softcopy_revise' AND `active`=1 LIMIT 1;

INSERT INTO `workflow_versions` (`workflow_id`,`version_number`,`status`,`is_default`,`graph`,`created_by`,`published_at`)
SELECT target.`id`,1,'published',1,source_version.`graph`,target.`created_by`,CURRENT_TIMESTAMP
FROM `workflows` target
JOIN `workflows` source ON source.`request_type`='softcopy_create' AND source.`active`=1
JOIN `workflow_versions` source_version ON source_version.`workflow_id`=source.`id`
  AND source_version.`is_default`=1 AND source_version.`status`='published'
WHERE target.`request_type`='external_create' AND target.`active`=1;

INSERT INTO `workflow_versions` (`workflow_id`,`version_number`,`status`,`is_default`,`graph`,`created_by`,`published_at`)
SELECT target.`id`,1,'published',1,source_version.`graph`,target.`created_by`,CURRENT_TIMESTAMP
FROM `workflows` target
JOIN `workflows` source ON source.`request_type`='softcopy_revise' AND source.`active`=1
JOIN `workflow_versions` source_version ON source_version.`workflow_id`=source.`id`
  AND source_version.`is_default`=1 AND source_version.`status`='published'
WHERE target.`request_type`='external_revise' AND target.`active`=1;

ALTER TABLE `external_documents`
  ADD CONSTRAINT `external_documents_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `external_documents_current_revision_fk` FOREIGN KEY (`current_revision_id`) REFERENCES `external_revisions` (`id`);

ALTER TABLE `external_revisions`
  ADD CONSTRAINT `external_revisions_document_fk` FOREIGN KEY (`document_id`) REFERENCES `external_documents` (`id`),
  ADD CONSTRAINT `external_revisions_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`),
  ADD CONSTRAINT `external_revisions_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `external_revisions_approved_by_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);
