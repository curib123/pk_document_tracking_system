CREATE TABLE `external_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `folder_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `external_categories_parent` (`parent_id`),
  KEY `external_categories_active_name` (`active`,`name`),
  CONSTRAINT `external_categories_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `external_categories` (`id`),
  CONSTRAINT `external_categories_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `external_categories` (`parent_id`,`name`,`folder_name`,`description`,`active`,`created_by`)
SELECT NULL,'Uncategorized','Uncategorized','Uncategorized external documents',1,`id`
FROM `users` ORDER BY `id` LIMIT 1;

ALTER TABLE `external_documents`
  ADD COLUMN `category_id` bigint(20) UNSIGNED NOT NULL DEFAULT 1 AFTER `description`,
  ADD KEY `external_documents_category` (`category_id`),
  ADD CONSTRAINT `external_documents_category_fk` FOREIGN KEY (`category_id`) REFERENCES `external_categories` (`id`);

INSERT INTO `permissions`
  (`name`,`module_key`,`module_label`,`action_key`,`action_label`,`description`,`version`)
VALUES
  ('External Categories: View','external_categories','External Categories','view','View','Allows view operations for external categories.',1),
  ('External Categories: Add','external_categories','External Categories','add','Add','Allows add operations for external categories.',1),
  ('External Categories: Edit','external_categories','External Categories','edit','Edit','Allows edit operations for external categories.',1),
  ('External Categories: Delete','external_categories','External Categories','delete','Delete','Allows delete operations for external categories.',1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `module_label`=VALUES(`module_label`),
  `action_label`=VALUES(`action_label`),
  `description`=VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT existing_grants.`role_id`, external_permission.`id`
FROM `role_permissions` existing_grants
JOIN `permissions` category_permission ON category_permission.`id`=existing_grants.`permission_id`
JOIN `permissions` external_permission
  ON external_permission.`module_key`='external_categories'
  AND external_permission.`action_key`=category_permission.`action_key`
WHERE category_permission.`module_key`='categories';
