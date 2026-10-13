# Issue 001: One Hardcopy document per Location contradicts folder requirements

Severity: HIGH | Status: Confirmed business-rule conflict | Implemented: NO

Affected: database/pk_dts.sql; application/services/documents/document_service.php; application/services/request/request_service.php.

The schema defines ADD UNIQUE KEY location_id (location_id) on hardcopy_documents. Both direct Hardcopy and transfer validation explicitly block a second document at an occupied Location. The folder requirement illustrates multiple documents under Location A. This is a decision about physical custody, not merely a visual defect.

## Copyable repair — only if multiple documents per Location are authorized

On a backed-up, verified database, use these SQL statements in order:

~~~sql
ALTER TABLE hardcopy_documents
  ADD INDEX idx_hardcopy_documents_location (location_id);

ALTER TABLE hardcopy_documents
  DROP INDEX location_id;
~~~

Inside Document_service::validate_hardcopy_location(), remove only the occupancy block beginning at the comment about the UNIQUE location_id and ending after the condition count_all_results()>0. Keep all Area, Specific, Asset, Location, permission and holder validation.

Inside Request_service::validate_transfer_destination(), delete only this restriction:

~~~php
if ($this->ci->db->from('hardcopy_documents')->where('location_id',$destination)
    ->where('id !=',(int)$hardcopyId)->count_all_results()>0)
    throw new DomainException('The destination is already occupied by another hardcopy.');
~~~

Change the corresponding ADD UNIQUE KEY location_id to ADD KEY in the installation schema for NEW databases. Reconcile the source schema contract tests before changing their assertions. NEVER import the installation schema over live data.

## Verify
~~~sql
SHOW INDEX FROM hardcopy_documents WHERE Column_name='location_id';
SELECT location_id,COUNT(*) FROM hardcopy_documents
WHERE location_id IS NOT NULL GROUP BY location_id;
~~~
The location index should be non-unique. Save two Hardcopy records at one Location, then separately test transfers, custody, disposal, audit history and folder display with both records.

Caution: This change is not safe unless the office confirms that one physical Location may contain several documents.
