INSERT INTO `permissions`
  (`name`,`module_key`,`module_label`,`action_key`,`action_label`,`description`,`version`)
VALUES
  ('External Documents: View','external_documents','External Documents','view','View','Allows view operations for external documents.',1),
  ('External Documents: Request','external_documents','External Documents','request','Request','Allows create and revision request operations for external documents.',1),
  ('External Documents: Direct','external_documents','External Documents','direct','Direct','Allows direct registration operations for external documents.',1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `module_label`=VALUES(`module_label`),
  `action_label`=VALUES(`action_label`),
  `description`=VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT existing_grants.`role_id`, external_permission.`id`
FROM `role_permissions` existing_grants
JOIN `permissions` softcopy_permission ON softcopy_permission.`id`=existing_grants.`permission_id`
JOIN `permissions` external_permission
  ON external_permission.`module_key`='external_documents'
  AND external_permission.`action_key`=softcopy_permission.`action_key`
WHERE softcopy_permission.`module_key`='softcopy'
  AND softcopy_permission.`action_key` IN ('view','request','direct');
