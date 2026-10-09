-- DDL-only representation of user-supplied pk_dts.sql (Oct 09 2026).
-- Original 29 tables, indexes, foreign keys and types unchanged.
-- Exported user credentials, login attempts, and historical rows intentionally excluded.
-- NEVER run against an already populated database.
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2026 at 03:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pk_dts`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_grants`
--

CREATE TABLE `access_grants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `granted_by` bigint(20) UNSIGNED NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'access_granted',
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `specific_id` bigint(20) UNSIGNED NOT NULL,
  `asset_number` varchar(100) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `softcopy_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `folder_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposals`
--

CREATE TABLE `disposals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `previous_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`previous_state`)),
  `disposal_action` varchar(20) NOT NULL,
  `remarks` text NOT NULL,
  `disposed_by` bigint(20) UNSIGNED NOT NULL,
  `disposed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(100) NOT NULL,
  `size` bigint(20) UNSIGNED NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `fingerprint` char(64) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'upload',
  `domain` varchar(10) DEFAULT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hardcopy_documents`
--

CREATE TABLE `hardcopy_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `area_id` bigint(20) UNSIGNED DEFAULT NULL,
  `specific_id` bigint(20) UNSIGNED DEFAULT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sequence_number` varchar(100) DEFAULT NULL,
  `retention_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `retention_start_date` date DEFAULT NULL,
  `retention_end_date` date DEFAULT NULL,
  `holder_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `previous_status` varchar(30) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `creation_source` varchar(20) NOT NULL,
  `creation_reason` text NOT NULL,
  `source_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `area_id` bigint(20) UNSIGNED DEFAULT NULL,
  `specific_id` bigint(20) UNSIGNED DEFAULT NULL,
  `asset_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(100) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `archive_date` date DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_key` char(64) NOT NULL,
  `failures` int(11) NOT NULL DEFAULT 0,
  `window_started` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--


-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `module_label` varchar(100) NOT NULL,
  `action_key` varchar(60) NOT NULL,
  `action_label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--


-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `softcopy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hardcopy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `workflow_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `current_node` varchar(50) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `submitted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `revision_artifacts`
--

CREATE TABLE `revision_artifacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `revision_id` bigint(20) UNSIGNED NOT NULL,
  `artifact_type` varchar(20) NOT NULL,
  `file_id` bigint(20) UNSIGNED NOT NULL,
  `source_fingerprint` char(64) NOT NULL,
  `generator_version` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--


-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--


-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `version` int(11) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schema_migrations`
--


-- --------------------------------------------------------

--
-- Table structure for table `sequences`
--

CREATE TABLE `sequences` (
  `sequence_key` varchar(80) NOT NULL,
  `value` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--


-- --------------------------------------------------------

--
-- Table structure for table `softcopy_documents`
--

CREATE TABLE `softcopy_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `series_number` varchar(100) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `current_revision_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `previous_status` varchar(30) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `creation_source` varchar(20) NOT NULL,
  `creation_reason` text NOT NULL,
  `source_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `softcopy_revisions`
--

CREATE TABLE `softcopy_revisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `revision_number` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `effective_date` date NOT NULL,
  `page_number` int(10) UNSIGNED NOT NULL,
  `series_number` varchar(100) DEFAULT NULL,
  `document_title` varchar(255) NOT NULL,
  `previous_revision_level` varchar(30) DEFAULT NULL,
  `new_revision_level` varchar(30) NOT NULL,
  `previous_effective_date` date DEFAULT NULL,
  `new_effective_date` date NOT NULL,
  `date_received` date NOT NULL,
  `date_released` date NOT NULL,
  `approval_date` date NOT NULL,
  `file_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `specifics`
--

CREATE TABLE `specifics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `area_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status_history`
--

CREATE TABLE `status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `domain` varchar(10) NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `previous_status` varchar(30) NOT NULL,
  `new_status` varchar(30) NOT NULL,
  `action` varchar(60) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hardcopy_id` bigint(20) UNSIGNED NOT NULL,
  `origin` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`origin`)),
  `destination` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`destination`)),
  `current_holder_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `document_copy_number` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `comments` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'for_transfer',
  `recipient_status` varchar(20) NOT NULL DEFAULT 'pending',
  `transferred_by` bigint(20) UNSIGNED DEFAULT NULL,
  `transferred_at` datetime DEFAULT NULL,
  `accepted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `position_title` varchar(150) NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `leader_id` bigint(20) UNSIGNED DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `require_password_change` tinyint(4) NOT NULL DEFAULT 1,
  `session_version` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--


-- --------------------------------------------------------

--
-- Table structure for table `workflows`
--

CREATE TABLE `workflows` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workflow_key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `request_type` varchar(50) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 0,
  `active_request_type` varchar(50) GENERATED ALWAYS AS (case when `active` = 1 then `request_type` else NULL end) STORED,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflows`
--


-- --------------------------------------------------------

--
-- Table structure for table `workflow_history`
--

CREATE TABLE `workflow_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `step_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `position_title` varchar(150) NOT NULL,
  `before_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_state`)),
  `after_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_state`)),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `node_key` varchar(50) NOT NULL,
  `label` varchar(120) NOT NULL,
  `assignment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`assignment`)),
  `candidates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`candidates`)),
  `assigned_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_name` varchar(255) DEFAULT NULL,
  `assigned_position` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `decision` varchar(20) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `acting_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `acting_name` varchar(255) DEFAULT NULL,
  `acting_position` varchar(150) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acted_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_versions`
--

CREATE TABLE `workflow_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workflow_id` bigint(20) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `graph` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`graph`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_versions`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_grants`
--
ALTER TABLE `access_grants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `revoked_by` (`revoked_by`),
  ADD KEY `access_check` (`domain`,`document_id`,`user_id`,`status`,`expires_at`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_number` (`asset_number`),
  ADD KEY `asset_lookup` (`active`,`specific_id`,`asset_number`),
  ADD KEY `specific_id` (`specific_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assigned_document` (`softcopy_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `disposals`
--
ALTER TABLE `disposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `disposed_by` (`disposed_by`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `storage_name` (`storage_name`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `rejected_by` (`rejected_by`),
  ADD KEY `document_files` (`domain`,`document_id`,`status`);

--
-- Indexes for table `hardcopy_documents`
--
ALTER TABLE `hardcopy_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_id` (`location_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `specific_id` (`specific_id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `holder_id` (`holder_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `hardcopy_status` (`status`,`title`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `location_hierarchy` (`active`,`asset_id`,`specific_id`,`area_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `specific_id` (`specific_id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_key`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `inbox` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `capability` (`module_key`,`action_key`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `softcopy_id` (`softcopy_id`),
  ADD KEY `hardcopy_id` (`hardcopy_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `request_queue` (`status`,`type`,`requested_by`),
  ADD KEY `request_workflow_version` (`workflow_version_id`,`status`);

--
-- Indexes for table `revision_artifacts`
--
ALTER TABLE `revision_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_id` (`file_id`),
  ADD UNIQUE KEY `artifact_source` (`revision_id`,`artifact_type`,`source_fingerprint`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`sequence_key`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `softcopy_documents`
--
ALTER TABLE `softcopy_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_number` (`document_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `softcopy_status` (`status`,`title`),
  ADD KEY `current_revision_fk` (`current_revision_id`);

--
-- Indexes for table `softcopy_revisions`
--
ALTER TABLE `softcopy_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_id` (`file_id`),
  ADD UNIQUE KEY `revision_per_document` (`document_id`,`revision_number`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `specifics`
--
ALTER TABLE `specifics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `specific_name` (`area_id`,`name`),
  ADD KEY `specific_lookup` (`active`,`area_id`,`name`);

--
-- Indexes for table `status_history`
--
ALTER TABLE `status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lifecycle` (`domain`,`document_id`,`created_at`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `hardcopy_id` (`hardcopy_id`),
  ADD KEY `current_holder_id` (`current_holder_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `transferred_by` (`transferred_by`),
  ADD KEY `accepted_by` (`accepted_by`),
  ADD KEY `transfer_queue` (`status`,`recipient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `leader_id` (`leader_id`);

--
-- Indexes for table `workflows`
--
ALTER TABLE `workflows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `workflow_key` (`workflow_key`),
  ADD UNIQUE KEY `one_active_workflow` (`active_request_type`),
  ADD KEY `workflow_type` (`request_type`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `step_id` (`step_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_history` (`request_id`,`created_at`,`id`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_user_id` (`assigned_user_id`),
  ADD KEY `acting_user_id` (`acting_user_id`),
  ADD KEY `pending_steps` (`status`,`request_id`),
  ADD KEY `request_steps` (`request_id`,`id`,`status`,`decision`);

--
-- Indexes for table `workflow_versions`
--
ALTER TABLE `workflow_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `workflow_version` (`workflow_id`,`version_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `publication` (`workflow_id`,`status`,`is_default`),
  ADD KEY `draft_workflow` (`workflow_id`,`status`,`version_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_grants`
--
ALTER TABLE `access_grants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposals`
--
ALTER TABLE `disposals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hardcopy_documents`
--
ALTER TABLE `hardcopy_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `revision_artifacts`
--
ALTER TABLE `revision_artifacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `softcopy_documents`
--
ALTER TABLE `softcopy_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `softcopy_revisions`
--
ALTER TABLE `softcopy_revisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `specifics`
--
ALTER TABLE `specifics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `status_history`
--
ALTER TABLE `status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `workflow_history`
--
ALTER TABLE `workflow_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_versions`
--
ALTER TABLE `workflow_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `access_grants`
--
ALTER TABLE `access_grants`
  ADD CONSTRAINT `access_grants_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `access_grants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `access_grants_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `access_grants_ibfk_4` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`);

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`softcopy_id`) REFERENCES `softcopy_documents` (`id`),
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposals`
--
ALTER TABLE `disposals`
  ADD CONSTRAINT `disposals_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `disposals_ibfk_2` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `files_ibfk_3` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `hardcopy_documents`
--
ALTER TABLE `hardcopy_documents`
  ADD CONSTRAINT `hardcopy_documents_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `hardcopy_documents_ibfk_2` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`),
  ADD CONSTRAINT `hardcopy_documents_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `hardcopy_documents_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `hardcopy_documents_ibfk_5` FOREIGN KEY (`holder_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `hardcopy_documents_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `locations_ibfk_2` FOREIGN KEY (`specific_id`) REFERENCES `specifics` (`id`),
  ADD CONSTRAINT `locations_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`softcopy_id`) REFERENCES `softcopy_documents` (`id`),
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`hardcopy_id`) REFERENCES `hardcopy_documents` (`id`),
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `requests_ibfk_4` FOREIGN KEY (`workflow_version_id`) REFERENCES `workflow_versions` (`id`);

--
-- Constraints for table `revision_artifacts`
--
ALTER TABLE `revision_artifacts`
  ADD CONSTRAINT `revision_artifacts_ibfk_1` FOREIGN KEY (`revision_id`) REFERENCES `softcopy_revisions` (`id`),
  ADD CONSTRAINT `revision_artifacts_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`);

--
-- Constraints for table `softcopy_documents`
--
ALTER TABLE `softcopy_documents`
  ADD CONSTRAINT `current_revision_fk` FOREIGN KEY (`current_revision_id`) REFERENCES `softcopy_revisions` (`id`),
  ADD CONSTRAINT `softcopy_documents_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `softcopy_documents_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `softcopy_revisions`
--
ALTER TABLE `softcopy_revisions`
  ADD CONSTRAINT `softcopy_revisions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `softcopy_documents` (`id`),
  ADD CONSTRAINT `softcopy_revisions_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`),
  ADD CONSTRAINT `softcopy_revisions_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `softcopy_revisions_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `specifics`
--
ALTER TABLE `specifics`
  ADD CONSTRAINT `specifics_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`);

--
-- Constraints for table `status_history`
--
ALTER TABLE `status_history`
  ADD CONSTRAINT `status_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`hardcopy_id`) REFERENCES `hardcopy_documents` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`current_holder_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transfers_ibfk_4` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transfers_ibfk_5` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transfers_ibfk_6` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflows`
--
ALTER TABLE `workflows`
  ADD CONSTRAINT `workflows_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD CONSTRAINT `workflow_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_2` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `workflow_steps_ibfk_2` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `workflow_steps_ibfk_3` FOREIGN KEY (`acting_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_versions`
--
ALTER TABLE `workflow_versions`
  ADD CONSTRAINT `workflow_versions_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`),
  ADD CONSTRAINT `workflow_versions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
