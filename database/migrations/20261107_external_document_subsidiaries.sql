CREATE TABLE `subsidiaries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subsidiaries_active_name` (`active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `external_documents`
  ADD COLUMN `document_number` varchar(150) DEFAULT NULL AFTER `title`,
  ADD COLUMN `subsidiary_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `document_number`,
  ADD KEY `external_documents_number` (`document_number`),
  ADD KEY `external_documents_subsidiary` (`subsidiary_id`),
  ADD CONSTRAINT `external_documents_subsidiary_fk`
    FOREIGN KEY (`subsidiary_id`) REFERENCES `subsidiaries` (`id`);

INSERT INTO `permissions`
  (`name`,`module_key`,`module_label`,`action_key`,`action_label`,`description`,`version`)
VALUES
  ('Subsidiaries: View','subsidiaries','Subsidiaries','view','View','Allows view operations for subsidiaries.',1),
  ('Subsidiaries: Add','subsidiaries','Subsidiaries','add','Add','Allows add operations for subsidiaries.',1),
  ('Subsidiaries: Edit','subsidiaries','Subsidiaries','edit','Edit','Allows edit operations for subsidiaries.',1),
  ('Subsidiaries: Delete','subsidiaries','Subsidiaries','delete','Delete','Allows delete operations for subsidiaries.',1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `module_label`=VALUES(`module_label`),
  `action_label`=VALUES(`action_label`),
  `description`=VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT existing_grants.`role_id`, subsidiary_permission.`id`
FROM `role_permissions` existing_grants
JOIN `permissions` external_category_permission
  ON external_category_permission.`id`=existing_grants.`permission_id`
JOIN `permissions` subsidiary_permission
  ON subsidiary_permission.`module_key`='subsidiaries'
  AND subsidiary_permission.`action_key`=external_category_permission.`action_key`
WHERE external_category_permission.`module_key`='external_categories';
