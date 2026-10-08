-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2026 at 08:18 AM
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
(1, 1, NULL, 'draft_created', 1, 'System  Administrator', 'Administrator', 'null', '{\"type\":\"hardcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"ffdfffff\\\",\\\"reason\\\":\\\"www\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"holder_id\\\":1,\\\"sequence_number\\\":\\\"111\\\",\\\"retention_enabled\\\":1,\\\"retention_start_date\\\":\\\"2026-10-07\\\",\\\"retention_end_date\\\":\\\"2028-10-07\\\"}\"}', NULL, '2026-10-07 07:51:51'),
(5, 2, NULL, 'draft_created', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"type\":\"hardcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"sample_hardcopy_1\\\",\\\"reason\\\":\\\"new documents ample\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"holder_id\\\":7,\\\"sequence_number\\\":\\\"1\\\",\\\"retention_enabled\\\":1,\\\"retention_start_date\\\":\\\"2026-10-07\\\",\\\"retention_end_date\\\":\\\"2028-10-07\\\"}\"}', NULL, '2026-10-07 08:22:38'),
(6, 2, NULL, 'submitted', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"workflow_version_id\":13}', NULL, '2026-10-07 08:25:43'),
(7, 2, 1, 'assigned', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-07 08:25:43'),
(8, 2, 1, 'return', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":1,\"request_id\":2,\"node_key\":\"step_1\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-07 16:25:43\",\"acted_at\":null,\"version\":1}', '{\"status\":\"returned\",\"current_node\":null,\"returned_to\":\"requester\",\"returned_to_user_id\":7}', 'ghjhjng', '2026-10-07 08:34:36'),
(9, 2, NULL, 'submitted', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"workflow_version_id\":13}', NULL, '2026-10-07 08:36:01'),
(10, 2, 2, 'assigned', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-07 08:36:01'),
(11, 2, 2, 'approve', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":2,\"request_id\":2,\"node_key\":\"step_1\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-07 16:36:01\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":null,\"final_approval\":true}', 'fgbfgf', '2026-10-07 08:44:45'),
(12, 2, NULL, 'approved', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', 'null', '{\"workflow_complete\":true}', NULL, '2026-10-07 08:44:45'),
(13, 1, NULL, 'cancelled', 1, 'System  Administrator', 'Administrator', '{\"id\":1,\"reference\":\"REQ-2026-000001\",\"type\":\"hardcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"requested_by\":1,\"payload\":\"{\\\"title\\\":\\\"ffdfffff\\\",\\\"reason\\\":\\\"www\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"holder_id\\\":1,\\\"sequence_number\\\":\\\"111\\\",\\\"retention_enabled\\\":1,\\\"retention_start_date\\\":\\\"2026-10-07\\\",\\\"retention_end_date\\\":\\\"2028-10-07\\\"}\",\"workflow_version_id\":null,\"snapshot\":null,\"current_node\":null,\"status\":\"draft\",\"result\":null,\"version\":1,\"submitted_at\":null,\"completed_at\":null,\"created_at\":\"2026-10-07 15:51:51\",\"updated_at\":\"2026-10-07 15:51:51\"}', 'null', 'jhgh', '2026-10-07 09:42:00'),
(14, 3, NULL, 'draft_created', 1, 'System  Administrator', 'Administrator', 'null', '{\"type\":\"softcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"sample_softcopy_1\\\",\\\"reason\\\":\\\"grgfgg\\\",\\\"category_id\\\":1,\\\"document_number\\\":\\\"11111111111111111\\\",\\\"series_number\\\":\\\"0001\\\",\\\"file_id\\\":1,\\\"effective_date\\\":\\\"2026-10-07\\\",\\\"page_number\\\":1,\\\"new_revision_level\\\":\\\"001\\\",\\\"date_received\\\":\\\"2026-10-07\\\",\\\"date_released\\\":\\\"2026-10-07\\\"}\"}', NULL, '2026-10-07 09:47:37'),
(16, 4, NULL, 'draft_created', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"type\":\"softcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"sample_softcopy_1\\\",\\\"reason\\\":\\\"ghjghjhg\\\",\\\"category_id\\\":1,\\\"document_number\\\":\\\"11111\\\",\\\"series_number\\\":\\\"11111111\\\",\\\"file_id\\\":2,\\\"effective_date\\\":\\\"2026-10-10\\\",\\\"page_number\\\":1,\\\"new_revision_level\\\":\\\"000000\\\",\\\"date_received\\\":\\\"2026-10-07\\\",\\\"date_released\\\":\\\"2026-10-07\\\"}\"}', NULL, '2026-10-07 09:49:09'),
(17, 4, NULL, 'submitted', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"workflow_version_id\":10}', NULL, '2026-10-07 09:49:21'),
(18, 4, 3, 'assigned', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"step_name\":\"Noted by:\",\"approver\":{\"type\":\"leader\",\"label\":\"Requester\'s Leader\"},\"candidates\":[{\"id\":6,\"name\":\"staff1 staff1 staff1\",\"position\":\"staff1\"}]}', NULL, '2026-10-07 09:49:21'),
(19, 4, 3, 'approve', 6, 'staff1 staff1 staff1', 'staff1', '{\"id\":3,\"request_id\":4,\"node_key\":\"step_1\",\"label\":\"Noted by:\",\"assignment\":\"{\\\"type\\\":\\\"leader\\\",\\\"label\\\":\\\"Requester\'s Leader\\\"}\",\"candidates\":\"[{\\\"id\\\":6,\\\"name\\\":\\\"staff1 staff1 staff1\\\",\\\"position\\\":\\\"staff1\\\"}]\",\"assigned_user_id\":6,\"assigned_name\":\"staff1 staff1 staff1\",\"assigned_position\":\"staff1\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-07 17:49:21\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_2\",\"final_approval\":false}', 'bhgfhthfg', '2026-10-07 09:51:03'),
(20, 4, 4, 'assigned', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-07 09:51:03'),
(21, 4, 4, 'approve', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":4,\"request_id\":4,\"node_key\":\"step_2\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-07 17:51:03\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_3\",\"final_approval\":false}', 'jhgjmh', '2026-10-07 09:51:48'),
(22, 4, 5, 'assigned', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', 'null', '{\"step_name\":\"Documentation Officer Approver\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"},\"candidates\":[{\"id\":3,\"name\":\"aaa aaa aaa\",\"position\":\"aaa\"},{\"id\":9,\"name\":\"dco dco dco\",\"position\":\"dco\"}]}', NULL, '2026-10-07 09:51:48'),
(23, 4, 5, 'approve', 9, 'dco dco dco', 'dco', '{\"id\":5,\"request_id\":4,\"node_key\":\"step_3\",\"label\":\"Documentation Officer Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":2,\\\"label\\\":\\\"Document Control Officer\\\"}\",\"candidates\":\"[{\\\"id\\\":3,\\\"name\\\":\\\"aaa aaa aaa\\\",\\\"position\\\":\\\"aaa\\\"},{\\\"id\\\":9,\\\"name\\\":\\\"dco dco dco\\\",\\\"position\\\":\\\"dco\\\"}]\",\"assigned_user_id\":null,\"assigned_name\":\"Document Control Officer\",\"assigned_position\":\"Role\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-07 17:51:48\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":null,\"final_approval\":true}', 'kjhkhjk', '2026-10-07 09:54:05'),
(24, 4, NULL, 'approved', 9, 'dco dco dco', 'dco', 'null', '{\"workflow_complete\":true}', NULL, '2026-10-07 09:54:05'),
(25, 5, NULL, 'draft_created', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"type\":\"access\",\"softcopy_id\":1,\"hardcopy_id\":null,\"payload\":\"{\\\"reason\\\":\\\"gfh\\\",\\\"expiration_date\\\":\\\"2026-10-09\\\",\\\"base_document_version\\\":2}\"}', NULL, '2026-10-08 00:56:58'),
(26, 5, NULL, 'submitted', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"workflow_version_id\":8}', NULL, '2026-10-08 00:57:09'),
(27, 5, 6, 'assigned', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"step_name\":\"Document approval\",\"approver\":{\"type\":\"role\",\"value\":1,\"label\":\"Administrator\"},\"candidates\":[{\"id\":1,\"name\":\"System  Administrator\",\"position\":\"Administrator\"}]}', NULL, '2026-10-08 00:57:09'),
(28, 6, NULL, 'draft_created', 1, 'System  Administrator', 'Administrator', 'null', '{\"type\":\"softcopy_cancel\",\"softcopy_id\":1,\"hardcopy_id\":null,\"payload\":\"{\\\"reason\\\":\\\"i\'ppi\\\",\\\"base_document_version\\\":2}\"}', NULL, '2026-10-08 01:16:08'),
(36, 7, NULL, 'draft_created', 1, 'System  Administrator', 'Administrator', 'null', '{\"type\":\"softcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"jujj\\\",\\\"reason\\\":\\\"hi\\\",\\\"category_id\\\":2,\\\"document_number\\\":\\\"jjjjjjjj\\\",\\\"series_number\\\":\\\"7uj7\\\",\\\"file_id\\\":4,\\\"effective_date\\\":\\\"2026-10-26\\\",\\\"page_number\\\":1,\\\"new_revision_level\\\":null,\\\"date_received\\\":\\\"2026-10-08\\\",\\\"date_released\\\":\\\"2026-10-08\\\"}\"}', NULL, '2026-10-08 03:34:32'),
(38, 8, NULL, 'draft_created', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"type\":\"softcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"11111111\\\",\\\"reason\\\":\\\"111111111\\\",\\\"category_id\\\":2,\\\"document_number\\\":\\\"111111111111\\\",\\\"series_number\\\":\\\"111111111\\\",\\\"file_id\\\":5,\\\"effective_date\\\":\\\"2026-10-27\\\",\\\"page_number\\\":1,\\\"new_revision_level\\\":\\\"1\\\",\\\"date_received\\\":\\\"2026-10-07\\\",\\\"date_released\\\":\\\"2026-10-08\\\"}\"}', NULL, '2026-10-08 03:36:04'),
(39, 8, NULL, 'submitted', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"workflow_version_id\":10}', NULL, '2026-10-08 03:36:08'),
(40, 8, 7, 'assigned', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"step_name\":\"Noted by:\",\"approver\":{\"type\":\"leader\",\"label\":\"Requester\'s Leader\"},\"candidates\":[{\"id\":6,\"name\":\"staff1 staff1 staff1\",\"position\":\"staff1\"}]}', NULL, '2026-10-08 03:36:08'),
(41, 9, NULL, 'draft_created', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"type\":\"hardcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"aaaaa\\\",\\\"reason\\\":\\\"fgggg\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"holder_id\\\":6,\\\"sequence_number\\\":\\\"1\\\",\\\"retention_enabled\\\":1,\\\"retention_start_date\\\":\\\"2026-10-08\\\",\\\"retention_end_date\\\":\\\"2028-10-01\\\"}\"}', NULL, '2026-10-08 05:45:46'),
(42, 9, NULL, 'submitted', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"workflow_version_id\":13}', NULL, '2026-10-08 05:45:57'),
(43, 9, 8, 'assigned', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-08 05:45:57'),
(44, 8, 7, 'approve', 6, 'staff1 staff1 staff1', 'staff1', '{\"id\":7,\"request_id\":8,\"node_key\":\"step_1\",\"label\":\"Noted by:\",\"assignment\":\"{\\\"type\\\":\\\"leader\\\",\\\"label\\\":\\\"Requester\'s Leader\\\"}\",\"candidates\":\"[{\\\"id\\\":6,\\\"name\\\":\\\"staff1 staff1 staff1\\\",\\\"position\\\":\\\"staff1\\\"}]\",\"assigned_user_id\":6,\"assigned_name\":\"staff1 staff1 staff1\",\"assigned_position\":\"staff1\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 11:36:08\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_2\",\"final_approval\":false}', 'ythtdy', '2026-10-08 05:46:35'),
(45, 8, 9, 'assigned', 6, 'staff1 staff1 staff1', 'staff1', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-08 05:46:35'),
(46, 8, 9, 'approve', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":9,\"request_id\":8,\"node_key\":\"step_2\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 13:46:35\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_3\",\"final_approval\":false}', NULL, '2026-10-08 05:47:02'),
(47, 8, 10, 'assigned', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', 'null', '{\"step_name\":\"Documentation Officer Approver\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"},\"candidates\":[{\"id\":3,\"name\":\"aaa aaa aaa\",\"position\":\"aaa\"},{\"id\":9,\"name\":\"dco dco dco\",\"position\":\"dco\"}]}', NULL, '2026-10-08 05:47:02'),
(57, 9, 8, 'reject', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":8,\"request_id\":9,\"node_key\":\"step_1\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 13:45:57\",\"acted_at\":null,\"version\":1}', '{\"status\":\"rejected\",\"current_node\":null,\"workflow_complete\":true}', NULL, '2026-10-08 05:52:07'),
(58, 10, NULL, 'draft_created', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"type\":\"hardcopy_create\",\"softcopy_id\":null,\"hardcopy_id\":null,\"payload\":\"{\\\"title\\\":\\\"2222\\\",\\\"reason\\\":\\\"22\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"holder_id\\\":7,\\\"sequence_number\\\":\\\"2\\\",\\\"retention_enabled\\\":0,\\\"retention_start_date\\\":null,\\\"retention_end_date\\\":null}\"}', NULL, '2026-10-08 05:52:52'),
(59, 10, NULL, 'submitted', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"workflow_version_id\":13}', NULL, '2026-10-08 05:52:55'),
(60, 10, 11, 'assigned', 7, 'staff2 staff2 staff2', 'staff2', 'null', '{\"step_name\":\"Plant Manager Approver\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-08 05:52:55'),
(61, 5, 6, 'approve', 1, 'System  Administrator', 'Administrator', '{\"id\":6,\"request_id\":5,\"node_key\":\"step_1\",\"label\":\"Document approval\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":1,\\\"label\\\":\\\"Administrator\\\"}\",\"candidates\":\"[{\\\"id\\\":1,\\\"name\\\":\\\"System  Administrator\\\",\\\"position\\\":\\\"Administrator\\\"}]\",\"assigned_user_id\":1,\"assigned_name\":\"System  Administrator\",\"assigned_position\":\"Administrator\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 08:57:09\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":null,\"final_approval\":true}', 'nbbbnb', '2026-10-08 05:54:04'),
(62, 5, NULL, 'approved', 1, 'System  Administrator', 'Administrator', 'null', '{\"workflow_complete\":true}', NULL, '2026-10-08 05:54:04'),
(63, 11, NULL, 'draft_created', 1, 'System  Administrator', 'Administrator', 'null', '{\"type\":\"transfer\",\"softcopy_id\":null,\"hardcopy_id\":1,\"payload\":\"{\\\"reason\\\":\\\"dssssss\\\",\\\"area_id\\\":1,\\\"specific_id\\\":1,\\\"asset_id\\\":1,\\\"location_id\\\":1,\\\"recipient_id\\\":6,\\\"document_copy_number\\\":\\\"1111111111\\\",\\\"sequence_number\\\":\\\"3\\\",\\\"base_document_version\\\":1}\"}', NULL, '2026-10-08 05:59:43'),
(64, 11, NULL, 'submitted', 1, 'System  Administrator', 'Administrator', 'null', '{\"workflow_version_id\":16}', NULL, '2026-10-08 05:59:53'),
(65, 11, 12, 'assigned', 1, 'System  Administrator', 'Administrator', 'null', '{\"step_name\":\"Plant Manager Approval\",\"approver\":{\"type\":\"role\",\"value\":3,\"label\":\"Plant Manager\"},\"candidates\":[{\"id\":8,\"name\":\"plantmanager plantmanager plantmanager\",\"position\":\"plantmanager\"}]}', NULL, '2026-10-08 05:59:53'),
(66, 11, 12, 'approve', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":12,\"request_id\":11,\"node_key\":\"step_1\",\"label\":\"Plant Manager Approval\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 13:59:53\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_2\",\"final_approval\":false}', 'hfggf', '2026-10-08 06:00:35'),
(67, 11, 13, 'assigned', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', 'null', '{\"step_name\":\"Document Controller Officer Approval\",\"approver\":{\"type\":\"role\",\"value\":2,\"label\":\"Document Control Officer\"},\"candidates\":[{\"id\":3,\"name\":\"aaa aaa aaa\",\"position\":\"aaa\"},{\"id\":9,\"name\":\"dco dco dco\",\"position\":\"dco\"}]}', NULL, '2026-10-08 06:00:35'),
(76, 10, 11, 'approve', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', '{\"id\":11,\"request_id\":10,\"node_key\":\"step_1\",\"label\":\"Plant Manager Approver\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":3,\\\"label\\\":\\\"Plant Manager\\\"}\",\"candidates\":\"[{\\\"id\\\":8,\\\"name\\\":\\\"plantmanager plantmanager plantmanager\\\",\\\"position\\\":\\\"plantmanager\\\"}]\",\"assigned_user_id\":8,\"assigned_name\":\"plantmanager plantmanager plantmanager\",\"assigned_position\":\"plantmanager\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 13:52:55\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":null,\"final_approval\":true}', 'uyy', '2026-10-08 06:08:16'),
(77, 10, NULL, 'approved', 8, 'plantmanager plantmanager plantmanager', 'plantmanager', 'null', '{\"workflow_complete\":true}', NULL, '2026-10-08 06:08:16'),
(78, 11, 13, 'approve', 9, 'dco dco dco', 'dco', '{\"id\":13,\"request_id\":11,\"node_key\":\"step_2\",\"label\":\"Document Controller Officer Approval\",\"assignment\":\"{\\\"type\\\":\\\"role\\\",\\\"value\\\":2,\\\"label\\\":\\\"Document Control Officer\\\"}\",\"candidates\":\"[{\\\"id\\\":3,\\\"name\\\":\\\"aaa aaa aaa\\\",\\\"position\\\":\\\"aaa\\\"},{\\\"id\\\":9,\\\"name\\\":\\\"dco dco dco\\\",\\\"position\\\":\\\"dco\\\"}]\",\"assigned_user_id\":null,\"assigned_name\":\"Document Control Officer\",\"assigned_position\":\"Role\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 14:00:35\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":\"step_3\",\"final_approval\":false}', 'hgfdffg', '2026-10-08 06:09:34'),
(79, 11, 14, 'assigned', 9, 'dco dco dco', 'dco', 'null', '{\"step_name\":\"Requester Confirmation Approval\",\"approver\":{\"type\":\"requester\",\"label\":\"Requester\"},\"candidates\":[{\"id\":1,\"name\":\"System  Administrator\",\"position\":\"Administrator\"}]}', NULL, '2026-10-08 06:09:34'),
(80, 11, 14, 'approve', 1, 'System  Administrator', 'Administrator', '{\"id\":14,\"request_id\":11,\"node_key\":\"step_3\",\"label\":\"Requester Confirmation Approval\",\"assignment\":\"{\\\"type\\\":\\\"requester\\\",\\\"label\\\":\\\"Requester\\\"}\",\"candidates\":\"[{\\\"id\\\":1,\\\"name\\\":\\\"System  Administrator\\\",\\\"position\\\":\\\"Administrator\\\"}]\",\"assigned_user_id\":1,\"assigned_name\":\"System  Administrator\",\"assigned_position\":\"Administrator\",\"status\":\"pending\",\"decision\":null,\"comments\":null,\"acting_user_id\":null,\"acting_name\":null,\"acting_position\":null,\"assigned_at\":\"2026-10-08 14:09:34\",\"acted_at\":null,\"version\":1}', '{\"status\":\"pending\",\"next_node\":null,\"final_approval\":true}', 'llp', '2026-10-08 06:18:17'),
(81, 11, NULL, 'approved', 1, 'System  Administrator', 'Administrator', 'null', '{\"workflow_complete\":true}', NULL, '2026-10-08 06:18:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `step_id` (`step_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_history` (`request_id`,`created_at`,`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `workflow_history`
--
ALTER TABLE `workflow_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD CONSTRAINT `workflow_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_2` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
