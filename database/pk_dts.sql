-- PK DTS fresh-install schema and default data, refreshed 2026-10-15.
-- MariaDB 10.4 / MySQL 8 compatible; application runtime PHP 8.0.
-- Import into an EMPTY database. Existing installs use database/migrations.
-- Contains default roles, permissions and appearance only.
-- Create the first administrator with: php tools/create_admin.php <username>
-- No operational records, user identities, password hashes or workflow history.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `granted_by` bigint(20) unsigned NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` bigint(20) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'access_granted',
  `version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `user_id` (`user_id`),
  KEY `granted_by` (`granted_by`),
  KEY `revoked_by` (`revoked_by`),
  KEY `access_check` (`domain`,`document_id`,`user_id`,`status`,`expires_at`),
  CONSTRAINT `access_grants_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  CONSTRAINT `access_grants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `access_grants_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `access_grants_ibfk_4` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `specific_id` bigint(20) unsigned NOT NULL,
  `asset_number` varchar(100) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_number` (`asset_number`),
  KEY `asset_lookup` (`active`,`specific_id`,`asset_number`),
  KEY `specific_id` (`specific_id`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `softcopy_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assigned_document` (`softcopy_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`softcopy_id`) REFERENCES `softcopy_documents` (`id`),
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `folder_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disposals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `previous_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`previous_state`)),
  `disposal_action` varchar(20) NOT NULL,
  `remarks` text NOT NULL,
  `disposed_by` bigint(20) unsigned NOT NULL,
  `disposed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `disposed_by` (`disposed_by`),
  CONSTRAINT `disposals_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  CONSTRAINT `disposals_ibfk_2` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(100) NOT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `fingerprint` char(64) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'upload',
  `domain` varchar(10) DEFAULT NULL,
  `document_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_name` (`storage_name`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `approved_by` (`approved_by`),
  KEY `rejected_by` (`rejected_by`),
  KEY `document_files` (`domain`,`document_id`,`status`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `files_ibfk_3` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hardcopy_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `specific_id` bigint(20) unsigned DEFAULT NULL,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `sequence_number` varchar(100) DEFAULT NULL,
  `retention_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `retention_start_date` date DEFAULT NULL,
  `retention_end_date` date DEFAULT NULL,
  `holder_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `previous_status` varchar(30) DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `creation_source` varchar(20) NOT NULL,
  `creation_reason` text NOT NULL,
  `source_request_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `area_id` (`area_id`),
  KEY `specific_id` (`specific_id`),
  KEY `asset_id` (`asset_id`),
  KEY `holder_id` (`holder_id`),
  KEY `created_by` (`created_by`),
  KEY `hardcopy_status` (`status`,`title`),
  KEY `idx_hardcopy_documents_location` (`location_id`),
  CONSTRAINT `hardcopy_documents_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  CONSTRAINT `hardcopy_documents_ibfk_2` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`),
  CONSTRAINT `hardcopy_documents_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  CONSTRAINT `hardcopy_documents_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `hardcopy_documents_ibfk_5` FOREIGN KEY (`holder_id`) REFERENCES `users` (`id`),
  CONSTRAINT `hardcopy_documents_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `specific_id` bigint(20) unsigned DEFAULT NULL,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(100) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `archive_date` date DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `location_hierarchy` (`active`,`asset_id`,`specific_id`,`area_id`),
  KEY `area_id` (`area_id`),
  KEY `specific_id` (`specific_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  CONSTRAINT `locations_ibfk_2` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`),
  CONSTRAINT `locations_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_key` char(64) NOT NULL,
  `failures` int(11) NOT NULL DEFAULT 0,
  `window_started` datetime NOT NULL,
  PRIMARY KEY (`attempt_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `inbox` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `module_label` varchar(100) NOT NULL,
  `action_key` varchar(60) NOT NULL,
  `action_label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `capability` (`module_key`,`action_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `softcopy_id` bigint(20) unsigned DEFAULT NULL,
  `hardcopy_id` bigint(20) unsigned DEFAULT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `workflow_version_id` bigint(20) unsigned DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `current_node` varchar(50) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `submitted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `softcopy_id` (`softcopy_id`),
  KEY `hardcopy_id` (`hardcopy_id`),
  KEY `requested_by` (`requested_by`),
  KEY `request_queue` (`status`,`type`,`requested_by`),
  KEY `request_workflow_version` (`workflow_version_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revision_artifacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` bigint(20) unsigned NOT NULL,
  `artifact_type` varchar(20) NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `source_fingerprint` char(64) NOT NULL,
  `generator_version` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_id` (`file_id`),
  UNIQUE KEY `artifact_source` (`revision_id`,`artifact_type`,`source_fingerprint`),
  CONSTRAINT `revision_artifacts_ibfk_1` FOREIGN KEY (`revision_id`) REFERENCES `softcopy_revisions` (`id`),
  CONSTRAINT `revision_artifacts_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `version` int(11) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sequences` (
  `sequence_key` varchar(80) NOT NULL,
  `value` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`sequence_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(80) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `softcopy_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_number` varchar(100) NOT NULL,
  `series_number` varchar(100) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `current_revision_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `previous_status` varchar(30) DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `creation_source` varchar(20) NOT NULL,
  `creation_reason` text NOT NULL,
  `source_request_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_number` (`document_number`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `softcopy_status` (`status`,`title`),
  KEY `current_revision_fk` (`current_revision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `softcopy_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `revision_number` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `effective_date` date NOT NULL,
  `page_number` int(10) unsigned NOT NULL,
  `series_number` varchar(100) DEFAULT NULL,
  `document_title` varchar(255) NOT NULL,
  `previous_revision_level` varchar(30) DEFAULT NULL,
  `new_revision_level` varchar(30) NOT NULL,
  `previous_effective_date` date DEFAULT NULL,
  `new_effective_date` date NOT NULL,
  `date_received` date NOT NULL,
  `date_released` date NOT NULL,
  `approval_date` date NOT NULL,
  `file_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_id` (`file_id`),
  UNIQUE KEY `revision_per_document` (`document_id`,`revision_number`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `specifics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `area_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `specific_name` (`area_id`,`name`),
  KEY `specific_lookup` (`active`,`area_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `new_status` varchar(30) NOT NULL,
  `action` varchar(60) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `lifecycle` (`domain`,`document_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_notification_receipts` (
  `user_id` bigint(20) unsigned NOT NULL,
  `step_id` bigint(20) unsigned NOT NULL,
  `activation` int(11) NOT NULL,
  `shown_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`step_id`,`activation`),
  KEY `idx_task_receipt_step` (`step_id`),
  CONSTRAINT `fk_task_receipt_step` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_receipt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `hardcopy_id` bigint(20) unsigned NOT NULL,
  `origin` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`origin`)),
  `destination` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`destination`)),
  `current_holder_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `document_copy_number` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `comments` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'for_transfer',
  `recipient_status` varchar(20) NOT NULL DEFAULT 'pending',
  `transferred_by` bigint(20) unsigned DEFAULT NULL,
  `transferred_at` datetime DEFAULT NULL,
  `accepted_by` bigint(20) unsigned DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `hardcopy_id` (`hardcopy_id`),
  KEY `current_holder_id` (`current_holder_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `transferred_by` (`transferred_by`),
  KEY `accepted_by` (`accepted_by`),
  KEY `transfer_queue` (`status`,`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `position_title` varchar(150) NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `leader_id` bigint(20) unsigned DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `require_password_change` tinyint(4) NOT NULL DEFAULT 1,
  `session_version` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  KEY `leader_id` (`leader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflow_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `step_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `position_title` varchar(150) NOT NULL,
  `before_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_state`)),
  `after_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_state`)),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `step_id` (`step_id`),
  KEY `user_id` (`user_id`),
  KEY `request_history` (`request_id`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflow_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `node_key` varchar(50) NOT NULL,
  `label` varchar(120) NOT NULL,
  `assignment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`assignment`)),
  `candidates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`candidates`)),
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_name` varchar(255) DEFAULT NULL,
  `assigned_position` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `decision` varchar(20) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `acting_user_id` bigint(20) unsigned DEFAULT NULL,
  `acting_name` varchar(255) DEFAULT NULL,
  `acting_position` varchar(150) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acted_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `assigned_user_id` (`assigned_user_id`),
  KEY `acting_user_id` (`acting_user_id`),
  KEY `pending_steps` (`status`,`request_id`),
  KEY `request_steps` (`request_id`,`id`,`status`,`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflow_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` bigint(20) unsigned NOT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `graph` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`graph`)),
  `created_by` bigint(20) unsigned NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_version` (`workflow_id`,`version_number`),
  KEY `created_by` (`created_by`),
  KEY `publication` (`workflow_id`,`status`,`is_default`),
  KEY `draft_workflow` (`workflow_id`,`status`,`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `request_type` varchar(50) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 0,
  `active_request_type` varchar(50) GENERATED ALWAYS AS (case when `active` = 1 then `request_type` else NULL end) STORED,
  `created_by` bigint(20) unsigned NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_key` (`workflow_key`),
  UNIQUE KEY `one_active_workflow` (`active_request_type`),
  KEY `workflow_type` (`request_type`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


START TRANSACTION;
-- Original static PK DTS role, permission, and UI-setting seeds from user-supplied SQL.
-- No live user/password/attempt data. Fresh database installation only.
INSERT INTO `roles` (`id`, `name`, `active`, `version`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 1, 1, '2026-10-08 09:51:10', '2026-10-08 09:51:10'),
(2, 'Document Control Officer', 1, 1, '2026-10-08 09:51:10', '2026-10-08 09:51:10'),
(3, 'Plant Manager', 1, 1, '2026-10-08 09:51:10', '2026-10-08 09:51:10'),
(4, 'Internal Auditor', 1, 1, '2026-10-08 09:51:10', '2026-10-08 09:51:10'),
(5, 'Staff', 1, 1, '2026-10-08 09:51:10', '2026-10-08 09:51:10');

INSERT INTO `permissions` (`id`, `name`, `module_key`, `module_label`, `action_key`, `action_label`, `description`, `version`) VALUES
(1, 'Users: View', 'users', 'Users', 'view', 'View', 'Allows view operations for users.', 1),
(2, 'Users: Add', 'users', 'Users', 'add', 'Add', 'Allows add operations for users.', 1),
(3, 'Users: Edit', 'users', 'Users', 'edit', 'Edit', 'Allows edit operations for users.', 1),
(4, 'Users: Delete', 'users', 'Users', 'delete', 'Delete', 'Allows delete operations for users.', 1),
(5, 'Roles: View', 'roles', 'Roles', 'view', 'View', 'Allows view operations for roles.', 1),
(6, 'Roles: Add', 'roles', 'Roles', 'add', 'Add', 'Allows add operations for roles.', 1),
(7, 'Roles: Edit', 'roles', 'Roles', 'edit', 'Edit', 'Allows edit operations for roles.', 1),
(8, 'Roles: Delete', 'roles', 'Roles', 'delete', 'Delete', 'Allows delete operations for roles.', 1),
(9, 'Permissions: View', 'permissions', 'Permissions', 'view', 'View', 'Allows view operations for permissions.', 1),
(10, 'Permissions: Add', 'permissions', 'Permissions', 'add', 'Add', 'Allows add operations for permissions.', 1),
(11, 'Permissions: Edit', 'permissions', 'Permissions', 'edit', 'Edit', 'Allows edit operations for permissions.', 1),
(12, 'Permissions: Delete', 'permissions', 'Permissions', 'delete', 'Delete', 'Allows delete operations for permissions.', 1),
(13, 'Areas: View', 'areas', 'Areas', 'view', 'View', 'Allows view operations for areas.', 1),
(14, 'Areas: Add', 'areas', 'Areas', 'add', 'Add', 'Allows add operations for areas.', 1),
(15, 'Areas: Edit', 'areas', 'Areas', 'edit', 'Edit', 'Allows edit operations for areas.', 1),
(16, 'Areas: Delete', 'areas', 'Areas', 'delete', 'Delete', 'Allows delete operations for areas.', 1),
(17, 'Specifics: View', 'specifics', 'Specifics', 'view', 'View', 'Allows view operations for specifics.', 1),
(18, 'Specifics: Add', 'specifics', 'Specifics', 'add', 'Add', 'Allows add operations for specifics.', 1),
(19, 'Specifics: Edit', 'specifics', 'Specifics', 'edit', 'Edit', 'Allows edit operations for specifics.', 1),
(20, 'Specifics: Delete', 'specifics', 'Specifics', 'delete', 'Delete', 'Allows delete operations for specifics.', 1),
(21, 'Assets: View', 'assets', 'Assets', 'view', 'View', 'Allows view operations for assets.', 1),
(22, 'Assets: Add', 'assets', 'Assets', 'add', 'Add', 'Allows add operations for assets.', 1),
(23, 'Assets: Edit', 'assets', 'Assets', 'edit', 'Edit', 'Allows edit operations for assets.', 1),
(24, 'Assets: Delete', 'assets', 'Assets', 'delete', 'Delete', 'Allows delete operations for assets.', 1),
(25, 'Locations: View', 'locations', 'Locations', 'view', 'View', 'Allows view operations for locations.', 1),
(26, 'Locations: Add', 'locations', 'Locations', 'add', 'Add', 'Allows add operations for locations.', 1),
(27, 'Locations: Edit', 'locations', 'Locations', 'edit', 'Edit', 'Allows edit operations for locations.', 1),
(28, 'Locations: Delete', 'locations', 'Locations', 'delete', 'Delete', 'Allows delete operations for locations.', 1),
(29, 'Categories: View', 'categories', 'Categories', 'view', 'View', 'Allows view operations for categories.', 1),
(30, 'Categories: Add', 'categories', 'Categories', 'add', 'Add', 'Allows add operations for categories.', 1),
(31, 'Categories: Edit', 'categories', 'Categories', 'edit', 'Edit', 'Allows edit operations for categories.', 1),
(32, 'Categories: Delete', 'categories', 'Categories', 'delete', 'Delete', 'Allows delete operations for categories.', 1),
(33, 'Dashboard: View', 'dashboard', 'Dashboard', 'view', 'View', 'Allows view operations for dashboard.', 1),
(34, 'Softcopy: View', 'softcopy', 'Softcopy', 'view', 'View', 'Allows view operations for softcopy.', 1),
(35, 'Softcopy: Request', 'softcopy', 'Softcopy', 'request', 'Request', 'Allows request operations for softcopy.', 1),
(36, 'Softcopy: Direct', 'softcopy', 'Softcopy', 'direct', 'Direct', 'Allows direct operations for softcopy.', 1),
(37, 'Hardcopy: View', 'hardcopy', 'Hardcopy', 'view', 'View', 'Allows view operations for hardcopy.', 1),
(38, 'Hardcopy: Request', 'hardcopy', 'Hardcopy', 'request', 'Request', 'Allows request operations for hardcopy.', 1),
(39, 'Hardcopy: Direct', 'hardcopy', 'Hardcopy', 'direct', 'Direct', 'Allows direct operations for hardcopy.', 1),
(40, 'Documents: Access All', 'documents', 'Documents', 'access_all', 'Access All', 'Allows access_all operations for documents.', 1),
(41, 'Documents: View Assigned', 'documents', 'Documents', 'view_assigned', 'View Assigned', 'Allows view_assigned operations for documents.', 1),
(42, 'Documents: View Granted', 'documents', 'Documents', 'view_granted', 'View Granted', 'Allows view_granted operations for documents.', 1),
(43, 'Documents: View All', 'documents', 'Documents', 'view_all', 'View All', 'Allows view_all operations for documents.', 1),
(44, 'Documents: Request Catalog', 'documents', 'Documents', 'request_catalog', 'Request Catalog', 'Allows request_catalog operations for documents.', 1),
(45, 'Files: View', 'files', 'Files', 'view', 'View', 'Allows view operations for files.', 1),
(46, 'Files: View All', 'files', 'Files', 'view_all', 'View All', 'Allows view_all operations for files.', 1),
(47, 'Files: Upload', 'files', 'Files', 'upload', 'Upload', 'Allows upload operations for files.', 1),
(48, 'Files: Approve', 'files', 'Files', 'approve', 'Approve', 'Allows approve operations for files.', 1),
(49, 'Files: Generate', 'files', 'Files', 'generate', 'Generate', 'Allows generate operations for files.', 1),
(50, 'Requests: View', 'requests', 'Requests', 'view', 'View', 'Allows view operations for requests.', 1),
(51, 'Requests: Add', 'requests', 'Requests', 'add', 'Add', 'Allows add operations for requests.', 1),
(52, 'Requests: Edit', 'requests', 'Requests', 'edit', 'Edit', 'Allows edit operations for requests.', 1),
(53, 'Requests: Submit', 'requests', 'Requests', 'submit', 'Submit', 'Allows submit operations for requests.', 1),
(54, 'Requests: Cancel', 'requests', 'Requests', 'cancel', 'Cancel', 'Allows cancel operations for requests.', 1),
(55, 'Requests: Manage', 'requests', 'Requests', 'manage', 'Manage', 'Allows manage operations for requests.', 1),
(56, 'Requests: View All', 'requests', 'Requests', 'view_all', 'View All', 'Allows view_all operations for requests.', 1),
(57, 'Workflows: View', 'workflows', 'Workflows', 'view', 'View', 'Allows view operations for workflows.', 1),
(58, 'Workflows: Edit', 'workflows', 'Workflows', 'edit', 'Edit', 'Allows edit operations for workflows.', 1),
(59, 'Workflows: Reassign', 'workflows', 'Workflows', 'reassign', 'Reassign', 'Allows reassign operations for workflows.', 1),
(60, 'Transfer: View', 'transfer', 'Transfer', 'view', 'View', 'Allows view operations for transfer.', 1),
(61, 'Transfer: View All', 'transfer', 'Transfer', 'view_all', 'View All', 'Allows view_all operations for transfer.', 1),
(62, 'Transfer: Request', 'transfer', 'Transfer', 'request', 'Request', 'Allows request operations for transfer.', 1),
(63, 'Transfer: Manage', 'transfer', 'Transfer', 'manage', 'Manage', 'Allows manage operations for transfer.', 1),
(64, 'Transfer: Direct', 'transfer', 'Transfer', 'direct', 'Direct', 'Allows direct operations for transfer.', 1),
(65, 'Access: View', 'access', 'Access', 'view', 'View', 'Allows view operations for access.', 1),
(66, 'Access: View All', 'access', 'Access', 'view_all', 'View All', 'Allows view_all operations for access.', 1),
(67, 'Access: Request', 'access', 'Access', 'request', 'Request', 'Allows request operations for access.', 1),
(68, 'Access: Revoke', 'access', 'Access', 'revoke', 'Revoke', 'Allows revoke operations for access.', 1),
(69, 'Access: Manage', 'access', 'Access', 'manage', 'Manage', 'Allows manage operations for access.', 1),
(70, 'Access: Direct', 'access', 'Access', 'direct', 'Direct', 'Allows direct operations for access.', 1),
(71, 'Assignment: View', 'assignment', 'Assignment', 'view', 'View', 'Allows view operations for assignment.', 1),
(72, 'Assignment: View All', 'assignment', 'Assignment', 'view_all', 'View All', 'Allows view_all operations for assignment.', 1),
(73, 'Assignment: Request', 'assignment', 'Assignment', 'request', 'Request', 'Allows request operations for assignment.', 1),
(74, 'Assignment: Manage', 'assignment', 'Assignment', 'manage', 'Manage', 'Allows manage operations for assignment.', 1),
(75, 'Assignment: Direct', 'assignment', 'Assignment', 'direct', 'Direct', 'Allows direct operations for assignment.', 1),
(76, 'Disposal: View', 'disposal', 'Disposal', 'view', 'View', 'Allows view operations for disposal.', 1),
(77, 'Disposal: View All', 'disposal', 'Disposal', 'view_all', 'View All', 'Allows view_all operations for disposal.', 1),
(78, 'Disposal: Request', 'disposal', 'Disposal', 'request', 'Request', 'Allows request operations for disposal.', 1),
(79, 'Disposal: Direct', 'disposal', 'Disposal', 'direct', 'Direct', 'Allows direct operations for disposal.', 1),
(80, 'Notifications: View', 'notifications', 'Notifications', 'view', 'View', 'Allows view operations for notifications.', 1),
(81, 'Notifications: Edit', 'notifications', 'Notifications', 'edit', 'Edit', 'Allows edit operations for notifications.', 1),
(82, 'Audit: View', 'audit', 'Audit', 'view', 'View', 'Allows view operations for audit.', 1),
(83, 'Sequences: View', 'sequences', 'Sequences', 'view', 'View', 'Allows view operations for sequences.', 1),
(84, 'Settings: View', 'settings', 'Settings', 'view', 'View', 'Allows view operations for settings.', 1),
(85, 'Settings: Edit', 'settings', 'Settings', 'edit', 'Edit', 'Allows edit operations for settings.', 1);

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(1, 45),
(1, 46),
(1, 47),
(1, 48),
(1, 49),
(1, 50),
(1, 51),
(1, 52),
(1, 53),
(1, 54),
(1, 55),
(1, 56),
(1, 57),
(1, 58),
(1, 59),
(1, 60),
(1, 61),
(1, 62),
(1, 63),
(1, 64),
(1, 65),
(1, 66),
(1, 67),
(1, 68),
(1, 69),
(1, 70),
(1, 71),
(1, 72),
(1, 73),
(1, 74),
(1, 75),
(1, 76),
(1, 77),
(1, 78),
(1, 79),
(1, 80),
(1, 81),
(1, 82),
(1, 83),
(1, 84),
(1, 85),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(2, 22),
(2, 23),
(2, 24),
(2, 25),
(2, 26),
(2, 27),
(2, 28),
(2, 29),
(2, 30),
(2, 31),
(2, 32),
(2, 33),
(2, 34),
(2, 35),
(2, 36),
(2, 37),
(2, 38),
(2, 39),
(2, 40),
(2, 41),
(2, 42),
(2, 44),
(2, 45),
(2, 47),
(2, 48),
(2, 49),
(2, 50),
(2, 51),
(2, 52),
(2, 53),
(2, 54),
(2, 55),
(2, 56),
(2, 60),
(2, 62),
(2, 63),
(2, 64),
(2, 65),
(2, 67),
(2, 68),
(2, 69),
(2, 70),
(2, 71),
(2, 73),
(2, 74),
(2, 75),
(2, 76),
(2, 78),
(2, 79),
(2, 80),
(2, 81),
(2, 82),
(2, 83),
(3, 29),
(3, 33),
(3, 34),
(3, 35),
(3, 37),
(3, 38),
(3, 40),
(3, 41),
(3, 42),
(3, 44),
(3, 45),
(3, 47),
(3, 50),
(3, 51),
(3, 52),
(3, 53),
(3, 54),
(3, 56),
(3, 60),
(3, 62),
(3, 65),
(3, 67),
(3, 71),
(3, 73),
(3, 76),
(3, 78),
(3, 80),
(3, 81),
(4, 29),
(4, 33),
(4, 34),
(4, 37),
(4, 40),
(4, 45),
(4, 46),
(4, 50),
(4, 56),
(4, 60),
(4, 61),
(4, 65),
(4, 66),
(4, 71),
(4, 72),
(4, 76),
(4, 77),
(4, 80),
(4, 81),
(4, 82),
(5, 29),
(5, 33),
(5, 34),
(5, 35),
(5, 37),
(5, 38),
(5, 41),
(5, 42),
(5, 44),
(5, 45),
(5, 47),
(5, 50),
(5, 51),
(5, 52),
(5, 53),
(5, 54),
(5, 60),
(5, 62),
(5, 65),
(5, 67),
(5, 71),
(5, 73),
(5, 76),
(5, 78),
(5, 80),
(5, 81);

INSERT INTO `settings` (`id`, `setting_key`, `value`, `version`, `updated_at`) VALUES
(1, 'appearance', '{\"theme_scope\":\"global\",\"color_mode\":\"system\",\"color_theme\":\"default\",\"styling_enabled\":false}', 1, '2026-10-08 09:51:10');

-- Rerunnable. Preserve existing file access on first installation of each action.
-- Rerunning never restores a permission later removed by an administrator.
SET @add_history = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='history');
SET @add_print = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='print');
SET @add_download = NOT EXISTS (SELECT 1 FROM permissions WHERE module_key='files' AND action_key='download');
INSERT IGNORE INTO permissions (name,module_key,module_label,action_key,action_label,description,version) VALUES
('Files: History','files','Files','history','File History','View controlled file revision history within authorized documents.',1),
('Files: Print','files','Files','print','Print','Print the current approved file within authorized documents.',1),
('Files: Download','files','Files','download','Download','Download the current approved file within authorized documents.',1);
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT DISTINCT rp.role_id, added.id
FROM role_permissions rp
JOIN permissions existing ON existing.id=rp.permission_id AND existing.module_key='files' AND existing.action_key IN ('view','view_all')
JOIN permissions added ON added.module_key='files' AND
 ((added.action_key='history' AND @add_history) OR (added.action_key='print' AND @add_print) OR (added.action_key='download' AND @add_download));

COMMIT;
