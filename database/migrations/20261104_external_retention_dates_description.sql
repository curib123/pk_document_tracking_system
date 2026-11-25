ALTER TABLE `external_documents`
  ADD COLUMN `description` varchar(2000) NOT NULL DEFAULT '' AFTER `title`,
  ADD COLUMN `retention_start_date` date DEFAULT NULL AFTER `retention_period`,
  ADD COLUMN `retention_end_date` date DEFAULT NULL AFTER `retention_start_date`;

ALTER TABLE `external_revisions`
  ADD COLUMN `description` varchar(2000) NOT NULL DEFAULT '' AFTER `revision_level`,
  ADD COLUMN `retention_start_date` date DEFAULT NULL AFTER `retention_period`,
  ADD COLUMN `retention_end_date` date DEFAULT NULL AFTER `retention_start_date`;
