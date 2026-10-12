-- One-time migration for EXISTING pk_dts MariaDB/MySQL installations.
-- Test on a disposable clone and verify a full backup before running.
-- The historic UNIQUE(location_id) rule allowed just one document per shelf.
-- Physical Locations may now hold multiple independent Hardcopy documents.
-- Run ONCE against a database still having UNIQUE KEY location_id.
ALTER TABLE hardcopy_documents
    ADD INDEX idx_hardcopy_documents_location (location_id);

ALTER TABLE hardcopy_documents
    DROP INDEX location_id;
