CREATE TABLE `external_revision_files` (
  `revision_id` bigint(20) UNSIGNED NOT NULL,
  `file_id` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`revision_id`,`file_id`),
  UNIQUE KEY `external_revision_file_attachment` (`file_id`),
  CONSTRAINT `external_revision_files_revision_fk`
    FOREIGN KEY (`revision_id`) REFERENCES `external_revisions` (`id`),
  CONSTRAINT `external_revision_files_file_fk`
    FOREIGN KEY (`file_id`) REFERENCES `files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `external_revision_files` (`revision_id`,`file_id`)
SELECT `id`,`file_id` FROM `external_revisions`;
