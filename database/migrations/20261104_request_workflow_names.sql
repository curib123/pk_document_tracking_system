UPDATE `workflows`
SET `name` = CASE `request_type`
  WHEN 'softcopy_create' THEN 'Softcopy Request'
  WHEN 'softcopy_revise' THEN 'Softcopy Request'
  WHEN 'softcopy_cancel' THEN 'Softcopy Request'
  WHEN 'external_create' THEN 'External Document Request'
  WHEN 'external_revise' THEN 'External Document Request'
  WHEN 'hardcopy_create' THEN 'Hardcopy Request'
  WHEN 'hardcopy_update' THEN 'Hardcopy Request'
  WHEN 'transfer' THEN 'Hardcopy Transfer Request'
  WHEN 'disposal' THEN 'Disposal Request'
  WHEN 'access' THEN 'Document Access Request'
  WHEN 'assignment' THEN 'Document Assignment Request'
END
WHERE (`request_type` = 'softcopy_create' AND `name` = 'Softcopy Create approval')
   OR (`request_type` = 'softcopy_revise' AND `name` = 'Softcopy Revise approval')
   OR (`request_type` = 'softcopy_cancel' AND `name` = 'Softcopy Cancel approval')
   OR (`request_type` = 'external_create' AND `name` = 'External Create approval')
   OR (`request_type` = 'external_revise' AND `name` = 'External Revise approval')
   OR (`request_type` = 'hardcopy_create' AND `name` = 'Hardcopy Create approval')
   OR (`request_type` = 'hardcopy_update' AND `name` = 'Hardcopy Update approval')
   OR (`request_type` = 'transfer' AND `name` = 'Transfer approval')
   OR (`request_type` = 'disposal' AND `name` IN ('Disposal approval', 'Disposal Request'))
   OR (`request_type` = 'access' AND `name` = 'Access approval')
   OR (`request_type` = 'assignment' AND `name` = 'Assignment approval');
