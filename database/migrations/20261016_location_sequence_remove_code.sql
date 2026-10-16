-- ONE-TIME upgrade for existing PK DTS installations (MySQL 8 / MariaDB 10.4+).
-- Perform an encrypted application + database backup FIRST and test on a cloned DB.
-- DDL auto-commits: this migration intentionally retires old Location Code values
-- and the obsolete standalone sequences table. Store a backup for rollback.
-- Not for a fresh database imported from database/pk_dts.sql.

ALTER TABLE locations
    ADD COLUMN sequence_number INT UNSIGNED NULL AFTER name;

-- Preserve all existing location rows. Sequence starts at 1 within each
-- Specific; stable ordering by the original location ID.
UPDATE locations AS location_row
JOIN (
    SELECT ranked.id, ranked.location_position
    FROM (
        SELECT id,
            ROW_NUMBER() OVER (PARTITION BY COALESCE(specific_id, 0) ORDER BY id) AS location_position
        FROM locations
    ) AS ranked
) AS assigned ON assigned.id = location_row.id
SET location_row.sequence_number = assigned.location_position;

ALTER TABLE locations
    MODIFY COLUMN sequence_number INT UNSIGNED NOT NULL,
    DROP INDEX `code`,
    DROP COLUMN `code`,
    ADD UNIQUE KEY idx_locations_specific_sequence (specific_id, sequence_number);

-- Retire the standalone counter view and its permission, not any documents.
DELETE rp FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE p.module_key = 'sequences';

DELETE FROM permissions WHERE module_key = 'sequences';
DROP TABLE IF EXISTS sequences;
