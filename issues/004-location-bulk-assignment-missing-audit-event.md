# ISSUE 004 — Direct bulk Location reassignment has no explicit activity/audit record

**Severity:** High (traceability)  
**Type:** Confirmed missing audit in new bulk method  
**Status:** Proposed fix only; NOT applied

## Evidence
- Administration::assign_location() updates holder_id on up to 2,000 records through Document_access_service::apply().
- Document_access_service's Hardcopy branch only updates holder_id; it does not write status_history or Audit_file.
- The controller displays success but does not log which documents changed custody, who initiated it or who received them.

## Copyable minimum fix
In application/controllers/Administration.php, after a successful trans_commit() in assign_location(), before the success notice:

~~~php
$this->load->library('Audit_file');
$written = $this->audit_file->append(
    (int)$this->user['id'],
    'hardcopy_location_bulk_assigned',
    'location:'.$locationId,
    [
        'location_id'=>$locationId,
        'recipient_id'=>$recipientId,
        'document_ids'=>array_map('intval',array_column($rows,'id')),
        'count'=>count($rows)
    ],
    'success'
);
if (!$written) {
    log_message('error','Bulk Location assignment committed without a durable audit entry.');
    $this->notice(
        'Documents were assigned, but audit logging failed. Contact your administrator.',
        'warning'
    );
} else {
    $this->notice(count($rows).' Hardcopy documents assigned successfully.');
}
~~~

Replace the EXISTING unconditional success notice with the conditional notice above. Audit_file::append() is a separate filesystem write and cannot roll back a committed SQL transaction. For strict atomic auditability, introduce a transactional audit outbox table with a reliable JSON-file exporter rather than pretending this patch gives cross-filesystem transactions.

## Verify
Assign three documents in a test Location, inspect storage/audit/YYYY-MM-DD.json for actor, Location, recipient, document IDs and count. Repeat an unsuccessful assignment; it should not claim success. Simulate a read-only audit directory on a disposable test host and verify the operator receives a warning.
