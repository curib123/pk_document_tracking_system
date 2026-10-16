-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2026 at 08:58 AM
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

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `name`, `active`, `version`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN OFFICE', 1, 2, '2026-10-16 06:09:01', '2026-10-16 06:15:42'),
(2, 'PRODUCTION OFFICE', 1, 2, '2026-10-16 06:11:02', '2026-10-16 06:16:14'),
(3, 'MAINTENANCE OFFICE', 1, 2, '2026-10-16 06:14:17', '2026-10-16 06:15:59');

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

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `specific_id`, `asset_number`, `active`, `version`, `created_at`, `updated_at`) VALUES
(1, 7, '0012-0000424500008', 1, 1, '2026-10-16 06:19:54', '2026-10-16 06:19:54'),
(2, 7, '012-0000424500001', 1, 1, '2026-10-16 06:20:12', '2026-10-16 06:20:12'),
(3, 7, '012-0000424500002', 1, 1, '2026-10-16 06:20:35', '2026-10-16 06:20:35'),
(4, 6, 'PK - PNK - 00106947', 1, 1, '2026-10-16 06:21:00', '2026-10-16 06:21:00'),
(5, 1, 'PK-PNK-00106945', 1, 1, '2026-10-16 06:21:26', '2026-10-16 06:21:26');

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

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `folder_name`, `description`, `active`, `created_by`, `version`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Uncategorized', 'Uncategorized', 'Uncategorized', 1, 2, 1, '2026-10-16 05:47:05', '2026-10-16 05:47:05');

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

--
-- Dumping data for table `disposals`
--

INSERT INTO `disposals` (`id`, `request_id`, `domain`, `document_id`, `previous_status`, `previous_state`, `disposal_action`, `remarks`, `disposed_by`, `disposed_at`) VALUES
(1, NULL, 'hardcopy', 2, 'active', '{\"id\":\"2\",\"title\":\"ccccccccccccccccccccc\",\"area_id\":\"1\",\"specific_id\":\"1\",\"asset_id\":\"5\",\"location_id\":\"1\",\"sequence_number\":\"cccccccccc\",\"retention_enabled\":\"0\",\"retention_start_date\":null,\"retention_end_date\":null,\"holder_id\":\"2\",\"status\":\"active\",\"previous_status\":null,\"created_by\":\"2\",\"creation_source\":\"direct\",\"creation_reason\":\"\",\"source_request_id\":null,\"version\":\"1\",\"created_at\":\"2026-10-16 14:51:04\",\"updated_at\":\"2026-10-16 14:51:04\"}', 'shred', 'Shred', 2, '2026-10-16 06:51:35'),
(2, 5, 'hardcopy', 3, 'active', '{\"id\":\"3\",\"title\":\"cccccccccccc\",\"area_id\":\"1\",\"specific_id\":\"1\",\"asset_id\":\"5\",\"location_id\":\"1\",\"sequence_number\":\"cccccc\",\"retention_enabled\":\"0\",\"retention_start_date\":null,\"retention_end_date\":null,\"holder_id\":\"6\",\"status\":\"active\",\"previous_status\":null,\"created_by\":\"6\",\"creation_source\":\"request\",\"creation_reason\":\"cccccc\",\"source_request_id\":\"4\",\"version\":\"1\",\"created_at\":\"2026-10-16 14:52:59\",\"updated_at\":\"2026-10-16 14:52:59\"}', 'shred', 'Shred', 6, '2026-10-16 06:55:14');

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

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `original_name`, `storage_name`, `size`, `mime_type`, `fingerprint`, `extension`, `purpose`, `domain`, `document_id`, `uploaded_by`, `status`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `version`, `created_at`) VALUES
(1, 'Get_Started_With_Smallpdf _2_.pdf', '185a3def4468ab0645e3f2fefa66cad38451559f34ca74ed61b54ebe9d9d5388', 69173, 'application/pdf', 'abce4555988c34a8f924b1d8c1e7ede8e0b18e8565e7aaf5fca2374e3d8b6707', 'pdf', 'creation', 'softcopy', 1, 6, 'approved', 6, '2026-10-16 07:50:08', NULL, NULL, NULL, 1, '2026-10-16 05:48:51'),
(2, 'Get_Started_With_Smallpdf _2_.pdf', '13cb10a587d5e5943144867d1ff9db3de0c67f34177e8aaa3925f4891942d496', 69173, 'application/pdf', 'abce4555988c34a8f924b1d8c1e7ede8e0b18e8565e7aaf5fca2374e3d8b6707', 'pdf', 'creation', 'softcopy', 2, 4, 'approved', 4, '2026-10-16 08:39:18', NULL, NULL, NULL, 1, '2026-10-16 06:39:18');

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

--
-- Dumping data for table `hardcopy_documents`
--

INSERT INTO `hardcopy_documents` (`id`, `title`, `area_id`, `specific_id`, `asset_id`, `location_id`, `sequence_number`, `retention_enabled`, `retention_start_date`, `retention_end_date`, `holder_id`, `status`, `previous_status`, `created_by`, `creation_source`, `creation_reason`, `source_request_id`, `version`, `created_at`, `updated_at`) VALUES
(1, 'TRANSFORMER MONITORING', 1, 1, 5, 2, NULL, 1, '2026-10-16', '2028-06-16', 6, 'active', NULL, 6, 'request', 'TRANSFORMER MONITORING', 2, 1, '2026-10-16 06:33:25', '2026-10-16 06:46:20'),
(2, 'ccccccccccccccccccccc', 1, 1, 5, 1, 'cccccccccc', 0, NULL, NULL, 2, 'disposed', 'active', 2, 'direct', '', NULL, 2, '2026-10-16 06:51:04', '2026-10-16 06:51:35'),
(3, 'cccccccccccc', 1, 1, 5, NULL, 'cccccc', 0, NULL, NULL, 6, 'disposed', 'active', 6, 'request', 'cccccc', 4, 2, '2026-10-16 06:52:59', '2026-10-16 06:55:14');

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
  `sequence_number` int(10) UNSIGNED NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `archive_date` date DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `area_id`, `specific_id`, `asset_id`, `name`, `sequence_number`, `active`, `archive_date`, `version`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5, 'A', 1, 1, NULL, 1, '2026-10-16 06:22:04', '2026-10-16 06:22:04'),
(2, 1, 1, 5, 'AA', 2, 1, NULL, 1, '2026-10-16 06:22:19', '2026-10-16 06:22:19'),
(3, 3, 3, NULL, 'B', 3, 1, NULL, 1, '2026-10-16 06:22:41', '2026-10-16 06:22:41'),
(4, 2, 2, NULL, 'BB', 4, 1, NULL, 1, '2026-10-16 06:22:59', '2026-10-16 06:22:59'),
(5, 2, 2, NULL, 'C', 5, 1, NULL, 1, '2026-10-16 06:23:15', '2026-10-16 06:23:15');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_key` char(64) NOT NULL,
  `failures` int(11) NOT NULL DEFAULT 0,
  `window_started` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(84, 'Settings: View', 'settings', 'Settings', 'view', 'View', 'Allows view operations for settings.', 1),
(85, 'Settings: Edit', 'settings', 'Settings', 'edit', 'Edit', 'Allows edit operations for settings.', 1),
(86, 'Files: History', 'files', 'Files', 'history', 'File History', 'View controlled file revision history within authorized documents.', 1),
(87, 'Files: Print', 'files', 'Files', 'print', 'Print', 'Print the current approved file within authorized documents.', 1),
(88, 'Files: Download', 'files', 'Files', 'download', 'Download', 'Download the current approved file within authorized documents.', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `reference`, `type`, `softcopy_id`, `hardcopy_id`, `requested_by`, `payload`, `workflow_version_id`, `snapshot`, `current_node`, `status`, `result`, `version`, `submitted_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'REQ-20261016-13C2C991A9', 'softcopy_create', 1, NULL, 6, '{\"subject\":\"New Policy\",\"remarks\":\"New Policy sa Handwashing\",\"title\":\"Handwashing Policy\",\"document_number\":\"policy-00001\",\"series_number\":\"1\",\"category_id\":1,\"recipient_id\":0,\"expires_at\":\"\",\"destination_location_id\":0,\"destination_area_id\":0,\"destination_specific_id\":0,\"destination_asset_id\":0,\"new_revision_level\":\"\",\"effective_date\":\"\",\"date_received\":\"2026-10-16\",\"date_released\":\"\",\"page_number\":1,\"document_domain\":\"softcopy\",\"disposal_reason\":\"\",\"disposal_other\":\"\",\"area_id\":0,\"specific_id\":0,\"asset_id\":0,\"location_id\":0,\"sequence_number\":\"\",\"holder_id\":0,\"retention_enabled\":0,\"retention_start_date\":\"\",\"retention_end_date\":\"\",\"creation_reason\":\"\",\"controlled_file_id\":1}', 10, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', NULL, 'completed', '{\"document_id\":1,\"file_id\":1,\"revision_id\":1}', 4, '2026-10-16 07:49:46', '2026-10-16 07:50:08', '2026-10-16 05:48:51', '2026-10-16 05:50:08'),
(2, 'REQ-20261016-750F22AA12', 'hardcopy_create', NULL, 1, 6, '{\"subject\":\"TRANSFORMER MONITORING\",\"remarks\":\"\",\"title\":\"TRANSFORMER MONITORING\",\"document_number\":\"\",\"series_number\":\"\",\"category_id\":0,\"recipient_id\":0,\"expires_at\":\"\",\"destination_location_id\":0,\"destination_area_id\":0,\"destination_specific_id\":0,\"destination_asset_id\":0,\"new_revision_level\":\"\",\"effective_date\":\"\",\"date_received\":\"2026-10-16\",\"date_released\":\"\",\"page_number\":1,\"document_domain\":\"softcopy\",\"disposal_reason\":\"\",\"disposal_other\":\"\",\"area_id\":3,\"specific_id\":3,\"asset_id\":null,\"location_id\":3,\"sequence_number\":null,\"holder_id\":6,\"retention_enabled\":1,\"retention_start_date\":\"2026-10-16\",\"retention_end_date\":\"2028-06-16\",\"creation_reason\":\"TRANSFORMER MONITORING\"}', 14, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', NULL, 'completed', '{\"document_id\":1}', 2, '2026-10-16 08:27:40', '2026-10-16 08:33:25', '2026-10-16 06:27:28', '2026-10-16 06:33:25'),
(3, 'REQ-20261016-52DA278E12', 'transfer', NULL, 1, 6, '{\"subject\":\"Transfer: TRANSFORMER MONITORING\",\"remarks\":\"dbfhdsbdhd\",\"title\":\"\",\"document_number\":\"\",\"series_number\":\"\",\"category_id\":0,\"recipient_id\":6,\"expires_at\":\"\",\"destination_location_id\":2,\"destination_area_id\":1,\"destination_specific_id\":1,\"destination_asset_id\":5,\"new_revision_level\":\"\",\"effective_date\":\"\",\"date_received\":\"2026-10-16\",\"date_released\":\"\",\"page_number\":1,\"document_domain\":\"softcopy\",\"disposal_reason\":\"\",\"disposal_other\":\"\",\"area_id\":0,\"specific_id\":0,\"asset_id\":0,\"location_id\":0,\"sequence_number\":\"\",\"holder_id\":0,\"retention_enabled\":0,\"retention_start_date\":\"\",\"retention_end_date\":\"\",\"creation_reason\":\"\"}', 19, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager  Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', NULL, 'completed', '{\"transfer_id\":1}', 2, '2026-10-16 08:42:38', '2026-10-16 08:46:20', '2026-10-16 06:42:13', '2026-10-16 06:46:20'),
(4, 'REQ-20261016-94705577E4', 'hardcopy_create', NULL, 3, 6, '{\"subject\":\"ccccccccccccccccccccccccccccc\",\"remarks\":\"\",\"title\":\"cccccccccccc\",\"document_number\":\"\",\"series_number\":\"\",\"category_id\":0,\"recipient_id\":0,\"expires_at\":\"\",\"destination_location_id\":0,\"destination_area_id\":0,\"destination_specific_id\":0,\"destination_asset_id\":0,\"new_revision_level\":\"\",\"effective_date\":\"\",\"date_received\":\"2026-10-16\",\"date_released\":\"\",\"page_number\":1,\"document_domain\":\"softcopy\",\"disposal_reason\":\"\",\"disposal_other\":\"\",\"area_id\":1,\"specific_id\":1,\"asset_id\":5,\"location_id\":1,\"sequence_number\":\"cccccc\",\"holder_id\":6,\"retention_enabled\":0,\"retention_start_date\":null,\"retention_end_date\":null,\"creation_reason\":\"cccccc\"}', 14, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', NULL, 'completed', '{\"document_id\":3}', 2, '2026-10-16 08:52:22', '2026-10-16 08:52:59', '2026-10-16 06:52:17', '2026-10-16 06:52:59'),
(5, 'REQ-20261016-DB3863291E', 'disposal', NULL, 3, 6, '{\"subject\":\"xdcxxxxxxxxxxxxxdxz\",\"remarks\":\"dxczc zz\",\"title\":\"\",\"document_number\":\"\",\"series_number\":\"\",\"category_id\":0,\"recipient_id\":0,\"expires_at\":\"\",\"destination_location_id\":0,\"destination_area_id\":0,\"destination_specific_id\":0,\"destination_asset_id\":0,\"new_revision_level\":\"\",\"effective_date\":\"\",\"date_received\":\"2026-10-16\",\"date_released\":\"\",\"page_number\":1,\"document_domain\":\"softcopy\",\"disposal_reason\":\"shred\",\"disposal_other\":\"\",\"area_id\":0,\"specific_id\":0,\"asset_id\":0,\"location_id\":0,\"sequence_number\":\"\",\"holder_id\":0,\"retention_enabled\":0,\"retention_start_date\":\"\",\"retention_end_date\":\"\",\"creation_reason\":\"\",\"disposal_description\":\"Shred\"}', 13, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', NULL, 'completed', '{\"disposal\":\"recorded\"}', 2, '2026-10-16 08:53:52', '2026-10-16 08:55:14', '2026-10-16 06:53:49', '2026-10-16 06:55:14');

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

INSERT INTO `roles` (`id`, `name`, `active`, `version`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 1, 1, '2026-10-08 01:51:10', '2026-10-08 01:51:10'),
(2, 'Document Control Officer', 1, 1, '2026-10-08 01:51:10', '2026-10-08 01:51:10'),
(3, 'Plant Manager', 1, 1, '2026-10-08 01:51:10', '2026-10-08 01:51:10'),
(4, 'Internal Auditor', 1, 1, '2026-10-08 01:51:10', '2026-10-08 01:51:10'),
(5, 'Staff', 1, 1, '2026-10-08 01:51:10', '2026-10-08 01:51:10');

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
(1, 84),
(1, 85),
(1, 86),
(1, 87),
(1, 88),
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
(2, 86),
(2, 87),
(2, 88),
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
(3, 86),
(3, 87),
(3, 88),
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
(4, 86),
(4, 87),
(4, 88),
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
(5, 81),
(5, 86),
(5, 87),
(5, 88);

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `version` int(11) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
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

INSERT INTO `settings` (`id`, `setting_key`, `value`, `version`, `updated_at`) VALUES
(1, 'appearance', '{\"theme_scope\":\"global\",\"color_mode\":\"system\",\"color_theme\":\"default\",\"styling_enabled\":false}', 1, '2026-10-08 01:51:10');

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

--
-- Dumping data for table `softcopy_documents`
--

INSERT INTO `softcopy_documents` (`id`, `document_number`, `series_number`, `title`, `category_id`, `current_revision_id`, `status`, `previous_status`, `created_by`, `creation_source`, `creation_reason`, `source_request_id`, `version`, `created_at`, `updated_at`) VALUES
(1, 'policy-00001', '1', 'Handwashing Policy', 1, 1, 'active', NULL, 6, 'request', 'New Policy sa Handwashing', 1, 1, '2026-10-16 05:50:08', '2026-10-16 05:50:08'),
(2, 'ppms-001', '1', 'Egg Releasing Form', 1, 2, 'active', NULL, 4, 'direct', 'New softcopy Document', NULL, 1, '2026-10-16 06:39:18', '2026-10-16 06:39:18');

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

--
-- Dumping data for table `softcopy_revisions`
--

INSERT INTO `softcopy_revisions` (`id`, `document_id`, `revision_number`, `reason`, `effective_date`, `page_number`, `series_number`, `document_title`, `previous_revision_level`, `new_revision_level`, `previous_effective_date`, `new_effective_date`, `date_received`, `date_released`, `approval_date`, `file_id`, `uploaded_by`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 1, 1, 'New Policy sa Handwashing', '2026-10-16', 1, '1', 'Handwashing Policy', NULL, '00', NULL, '2026-10-16', '2026-10-16', '2026-10-16', '2026-10-16', 1, 6, 6, '2026-10-16 05:50:08', '2026-10-16 05:50:08'),
(2, 2, 1, 'New softcopy Document', '2026-10-16', 1, '1', 'Egg Releasing Form', NULL, '00', NULL, '2026-10-16', '2026-10-16', '2026-10-16', '2026-10-16', 2, 4, 4, '2026-10-16 06:39:18', '2026-10-16 06:39:18');

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

--
-- Dumping data for table `specifics`
--

INSERT INTO `specifics` (`id`, `area_id`, `name`, `active`, `version`, `created_at`, `updated_at`) VALUES
(1, 1, 'AP/AR/PAYROLL\'S FILE ORGANIZER', 1, 1, '2026-10-16 06:16:27', '2026-10-16 06:16:27'),
(2, 2, 'AT THE BACK OF QA\'S TABLE', 1, 1, '2026-10-16 06:16:55', '2026-10-16 06:16:55'),
(3, 3, 'MAINTENANCE MOBILE CABINET', 1, 1, '2026-10-16 06:17:17', '2026-10-16 06:17:17'),
(4, 1, 'PM\'S FILE ORGANIZER', 1, 1, '2026-10-16 06:17:47', '2026-10-16 06:17:47'),
(5, 1, 'PM\'S MOBILE CABINET 01', 1, 1, '2026-10-16 06:18:06', '2026-10-16 06:18:06'),
(6, 2, 'IN FRONT GSD MOBILE CABINET', 1, 1, '2026-10-16 06:18:44', '2026-10-16 06:18:44'),
(7, 1, 'BACK OFFICE STORAGE', 1, 1, '2026-10-16 06:19:28', '2026-10-16 06:19:28');

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

--
-- Dumping data for table `status_history`
--

INSERT INTO `status_history` (`id`, `domain`, `document_id`, `previous_status`, `new_status`, `action`, `user_id`, `remarks`, `created_at`) VALUES
(1, 'softcopy', 2, 'new', 'active', 'direct_create', 4, '', '2026-10-16 06:39:18'),
(2, 'hardcopy', 2, '', 'active', 'direct_create', 2, '', '2026-10-16 06:51:04'),
(3, 'hardcopy', 2, 'active', 'disposed', 'direct_dispose', 2, 'Shred', '2026-10-16 06:51:35'),
(4, 'hardcopy', 3, 'active', 'disposed', 'disposed', 6, 'Shred', '2026-10-16 06:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `task_notification_receipts`
--

CREATE TABLE `task_notification_receipts` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `step_id` bigint(20) UNSIGNED NOT NULL,
  `activation` int(11) NOT NULL,
  `shown_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_notification_receipts`
--

INSERT INTO `task_notification_receipts` (`user_id`, `step_id`, `activation`, `shown_at`) VALUES
(3, 3, 1, '2026-10-16 14:30:40'),
(3, 3, 2, '2026-10-16 14:32:14'),
(3, 5, 1, '2026-10-16 14:44:33'),
(3, 8, 1, '2026-10-16 14:52:39'),
(3, 10, 1, '2026-10-16 14:54:12'),
(4, 4, 2, '2026-10-16 14:31:32'),
(4, 4, 3, '2026-10-16 14:32:37'),
(4, 6, 2, '2026-10-16 14:44:45'),
(4, 9, 2, '2026-10-16 14:52:56'),
(4, 11, 2, '2026-10-16 14:54:30'),
(6, 7, 2, '2026-10-16 14:45:12'),
(6, 12, 2, '2026-10-16 14:54:56');

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

--
-- Dumping data for table `transfers`
--

INSERT INTO `transfers` (`id`, `request_id`, `hardcopy_id`, `origin`, `destination`, `current_holder_id`, `recipient_id`, `document_copy_number`, `reason`, `comments`, `status`, `recipient_status`, `transferred_by`, `transferred_at`, `accepted_by`, `accepted_at`, `version`, `created_at`) VALUES
(1, 3, 1, '{\"id\":\"1\",\"title\":\"TRANSFORMER MONITORING\",\"area_id\":\"3\",\"specific_id\":\"3\",\"asset_id\":null,\"location_id\":\"3\",\"sequence_number\":null,\"retention_enabled\":\"1\",\"retention_start_date\":\"2026-10-16\",\"retention_end_date\":\"2028-06-16\",\"holder_id\":\"6\",\"status\":\"active\",\"previous_status\":null,\"created_by\":\"6\",\"creation_source\":\"request\",\"creation_reason\":\"TRANSFORMER MONITORING\",\"source_request_id\":\"2\",\"version\":\"1\",\"created_at\":\"2026-10-16 14:33:25\",\"updated_at\":\"2026-10-16 14:33:25\"}', '{\"area_id\":\"1\",\"specific_id\":\"1\",\"asset_id\":\"5\",\"location_id\":\"2\",\"recipient_id\":6}', 6, 6, '', 'dbfhdsbdhd', NULL, 'accepted', 'accepted', 6, '2026-10-16 08:46:13', 6, '2026-10-16 08:46:20', 1, '2026-10-16 06:45:37');

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

INSERT INTO `users` (`id`, `username`, `first_name`, `middle_name`, `last_name`, `position_title`, `role_id`, `leader_id`, `password_hash`, `require_password_change`, `session_version`, `active`, `version`, `created_at`, `updated_at`) VALUES
(2, 'curibjp', 'John Paul', NULL, 'Curib', 'System Programmer I', 1, NULL, '$2y$10$ecbhsSik6gzW.NyV4Nh1g.HGZkoNV.JVrI2vJADpCvLkITfwPfOE2', 0, 1, 1, 1, '2026-10-16 05:33:19', '2026-10-16 05:33:19'),
(3, 'plant_manager', 'Plant', NULL, 'Manager', 'Plant Manager', 3, NULL, '$2y$10$VLhmlfv8NQ6D5Sf76V2st.94hxVjYZoVN9NQICnxAlwq5TJwg5WJC', 0, 2, 1, 1, '2026-10-16 05:37:39', '2026-10-16 05:38:08'),
(4, 'dco', 'Document', 'Control', 'Officer', 'Document Control Officer', 2, NULL, '$2y$10$RNp/jIHlyLA9bmbYjJCfQ.3YNVA26CsRJ6S8fLj276shkZNWbG41.', 0, 2, 1, 1, '2026-10-16 05:39:14', '2026-10-16 05:39:52'),
(5, 'admin_staff_1', 'Admin', 'Staff', 'I', 'Accounting Clerk 1', 5, NULL, '$2y$10$61fHc8XUbfq/7xmyem9SFewMApWurr7BIoUF0V9hei7Uu45iuS8uK', 0, 2, 1, 1, '2026-10-16 05:41:40', '2026-10-16 05:42:23'),
(6, 'production_staff_1', 'Production', 'Staff', 'I', 'Service Staff III', 5, 5, '$2y$10$t/Ux4kBtsCjY7fC66AAx6.5wR/f62tdSpIsBC64X7lV8QiT4xY/yq', 0, 3, 1, 2, '2026-10-16 05:43:47', '2026-10-16 05:45:03');

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

INSERT INTO `workflows` (`id`, `workflow_key`, `name`, `description`, `request_type`, `active`, `created_by`, `version`, `created_at`, `updated_at`) VALUES
(10, 'softcopy_create', 'Softcopy Create approval', NULL, 'softcopy_create', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(11, 'softcopy_revise', 'Softcopy Revise approval', NULL, 'softcopy_revise', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(13, 'hardcopy_create', 'Hardcopy Create approval', NULL, 'hardcopy_create', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(14, 'hardcopy_update', 'Hardcopy Update approval', NULL, 'hardcopy_update', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(15, 'transfer', 'Transfer approval', NULL, 'transfer', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(16, 'assignment', 'Assignment approval', NULL, 'assignment', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(17, 'access', 'Access approval', NULL, 'access', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41'),
(18, 'disposal', 'Disposal approval', NULL, 'disposal', 1, 2, 1, '2026-10-16 05:45:41', '2026-10-16 05:45:41');

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

--
-- Dumping data for table `workflow_history`
--

INSERT INTO `workflow_history` (`id`, `request_id`, `step_id`, `action`, `user_id`, `user_name`, `position_title`, `before_state`, `after_state`, `comments`, `created_at`) VALUES
(1, 1, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 05:49:25'),
(2, 1, 1, 'returned', 6, 'Production I', 'Service Staff III', NULL, NULL, '', '2026-10-16 05:49:36'),
(3, 1, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 05:49:46'),
(4, 1, 2, 'approved', 6, 'Production I', 'Service Staff III', NULL, NULL, '', '2026-10-16 05:50:08'),
(5, 2, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 06:27:40'),
(6, 2, 3, 'approved', 3, 'Plant Manager', 'Plant Manager', NULL, NULL, '', '2026-10-16 06:30:59'),
(7, 2, 4, 'returned', 4, 'Document Officer', 'Document Control Officer', NULL, NULL, '', '2026-10-16 06:31:59'),
(8, 2, 3, 'approved', 3, 'Plant Manager', 'Plant Manager', NULL, NULL, '', '2026-10-16 06:32:27'),
(9, 2, 4, 'approved', 4, 'Document Officer', 'Document Control Officer', NULL, NULL, '', '2026-10-16 06:33:25'),
(10, 3, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 06:42:38'),
(11, 3, 5, 'approved', 3, 'Plant Manager', 'Plant Manager', NULL, NULL, '', '2026-10-16 06:44:37'),
(12, 3, 6, 'approved', 4, 'Document Officer', 'Document Control Officer', NULL, NULL, '', '2026-10-16 06:45:03'),
(13, 3, 7, 'approved', 6, 'Production I', 'Service Staff III', NULL, NULL, '', '2026-10-16 06:45:37'),
(14, 3, NULL, 'transfer_accepted', 6, 'Production I', 'Service Staff III', NULL, NULL, 'Physical document received by its intended recipient.', '2026-10-16 06:46:20'),
(15, 4, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 06:52:22'),
(16, 4, 8, 'approved', 3, 'Plant Manager', 'Plant Manager', NULL, NULL, '', '2026-10-16 06:52:42'),
(17, 4, 9, 'approved', 4, 'Document Officer', 'Document Control Officer', NULL, NULL, '', '2026-10-16 06:52:59'),
(18, 5, NULL, 'submitted', 6, 'Production I', 'Service Staff III', NULL, NULL, NULL, '2026-10-16 06:53:52'),
(19, 5, 10, 'approved', 3, 'Plant Manager', 'Plant Manager', NULL, NULL, '', '2026-10-16 06:54:19'),
(20, 5, 11, 'approved', 4, 'Document Officer', 'Document Control Officer', NULL, NULL, '', '2026-10-16 06:54:45'),
(21, 5, 12, 'approved', 6, 'Production I', 'Service Staff III', NULL, NULL, '', '2026-10-16 06:55:14');

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

--
-- Dumping data for table `workflow_steps`
--

INSERT INTO `workflow_steps` (`id`, `request_id`, `node_key`, `label`, `assignment`, `candidates`, `assigned_user_id`, `assigned_name`, `assigned_position`, `status`, `decision`, `comments`, `acting_user_id`, `acting_name`, `acting_position`, `assigned_at`, `acted_at`, `version`) VALUES
(1, 1, 'step_1', 'Requester Confirmation', '{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}', '[]', 6, 'Production I', 'Service Staff III', 'returned', 'returned', '', 6, 'Production I', 'Service Staff III', '2026-10-16 05:49:25', '2026-10-16 07:49:36', 1),
(2, 1, 'step_1', 'Requester Confirmation', '{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}', '[]', 6, 'Production I', 'Service Staff III', 'approved', 'approved', '', 6, 'Production I', 'Service Staff III', '2026-10-16 05:49:46', '2026-10-16 07:50:08', 1),
(3, 2, 'step_1', 'Plant Manager Review & Approval', '{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 3, 'Plant Manager', 'Plant Manager', '2026-10-16 06:27:40', '2026-10-16 08:32:27', 2),
(4, 2, 'step_2', 'Document Control Officer Review & Approval', '{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 4, 'Document Officer', 'Document Control Officer', '2026-10-16 06:27:40', '2026-10-16 08:33:25', 3),
(5, 3, 'step_1', 'Plant Manager  Approval', '{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 3, 'Plant Manager', 'Plant Manager', '2026-10-16 06:42:38', '2026-10-16 08:44:37', 1),
(6, 3, 'step_2', 'Document Control Officer Approval', '{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 4, 'Document Officer', 'Document Control Officer', '2026-10-16 06:42:38', '2026-10-16 08:45:03', 2),
(7, 3, 'step_3', 'Requester Confirmation', '{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}', '[]', 6, 'Production I', 'Service Staff III', 'approved', 'approved', '', 6, 'Production I', 'Service Staff III', '2026-10-16 06:42:38', '2026-10-16 08:45:37', 2),
(8, 4, 'step_1', 'Plant Manager Review & Approval', '{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 3, 'Plant Manager', 'Plant Manager', '2026-10-16 06:52:22', '2026-10-16 08:52:42', 1),
(9, 4, 'step_2', 'Document Control Officer Review & Approval', '{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 4, 'Document Officer', 'Document Control Officer', '2026-10-16 06:52:22', '2026-10-16 08:52:59', 2),
(10, 5, 'step_1', 'Plant Manager Approval', '{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 3, 'Plant Manager', 'Plant Manager', '2026-10-16 06:53:52', '2026-10-16 08:54:19', 1),
(11, 5, 'step_2', 'Document Control Officer', '{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}', '[]', NULL, NULL, NULL, 'approved', 'approved', '', 4, 'Document Officer', 'Document Control Officer', '2026-10-16 06:53:52', '2026-10-16 08:54:45', 2),
(12, 5, 'step_3', 'Requester Confirmation', '{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}', '[]', 6, 'Production I', 'Service Staff III', 'approved', 'approved', '', 6, 'Production I', 'Service Staff III', '2026-10-16 06:53:52', '2026-10-16 08:55:14', 2);

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

INSERT INTO `workflow_versions` (`id`, `workflow_id`, `version_number`, `status`, `is_default`, `graph`, `created_by`, `published_at`, `version`, `created_at`) VALUES
(10, 10, 1, 'published', 0, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', 2, '2026-10-16 07:46:34', 1, '2026-10-16 05:46:14'),
(11, 17, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', 2, '2026-10-16 07:54:45', 1, '2026-10-16 05:52:40'),
(12, 16, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Approval\",\"approver\":{\"type\":\"requester_leader\",\"value\":null,\"label\":\"Requester\'s leader\"}}]}', 2, '2026-10-16 07:56:21', 1, '2026-10-16 05:54:54'),
(13, 18, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', 2, '2026-10-16 07:58:07', 1, '2026-10-16 05:56:32'),
(14, 13, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', 2, '2026-10-16 07:59:59', 1, '2026-10-16 05:58:21'),
(15, 14, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', 2, '2026-10-16 08:01:02', 1, '2026-10-16 06:00:20'),
(16, 12, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Noted By\",\"approver\":{\"type\":\"requester_leader\",\"value\":null,\"label\":\"Requester\'s leader\"}},{\"key\":\"step_2\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_3\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', 2, '2026-10-16 08:02:24', 1, '2026-10-16 06:01:14'),
(17, 10, 2, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Noted By\",\"approver\":{\"type\":\"requester_leader\",\"value\":null,\"label\":\"Requester\'s leader\"}},{\"key\":\"step_2\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_3\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', 2, '2026-10-16 08:04:34', 1, '2026-10-16 06:03:41'),
(18, 11, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Noted By\",\"approver\":{\"type\":\"requester_leader\",\"value\":null,\"label\":\"Requester\'s leader\"}},{\"key\":\"step_2\",\"name\":\"Plant Manager Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_3\",\"name\":\"Document Control Officer Review & Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}}]}', 2, '2026-10-16 08:05:56', 1, '2026-10-16 06:04:49'),
(19, 15, 1, 'published', 1, '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Plant Manager  Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"}},{\"key\":\"step_2\",\"name\":\"Document Control Officer Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"}},{\"key\":\"step_3\",\"name\":\"Requester Confirmation\",\"approver\":{\"type\":\"requester\",\"value\":null,\"label\":\"Requester account\"}}]}', 2, '2026-10-16 08:07:49', 1, '2026-10-16 06:06:08');

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
  ADD KEY `idx_hardcopy_documents_location` (`location_id`),
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
  ADD UNIQUE KEY `idx_locations_specific_sequence` (`specific_id`,`sequence_number`),
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
-- Indexes for table `task_notification_receipts`
--
ALTER TABLE `task_notification_receipts`
  ADD PRIMARY KEY (`user_id`,`step_id`,`activation`),
  ADD KEY `idx_task_receipt_step` (`step_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disposals`
--
ALTER TABLE `disposals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hardcopy_documents`
--
ALTER TABLE `hardcopy_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `softcopy_revisions`
--
ALTER TABLE `softcopy_revisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `specifics`
--
ALTER TABLE `specifics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `status_history`
--
ALTER TABLE `status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `workflow_history`
--
ALTER TABLE `workflow_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `workflow_versions`
--
ALTER TABLE `workflow_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
-- Constraints for table `task_notification_receipts`
--
ALTER TABLE `task_notification_receipts`
  ADD CONSTRAINT `fk_task_receipt_step` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_task_receipt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
