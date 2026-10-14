# ISSUE 003 — Bulk Location assignment can strand an approved physical handoff

**Severity:** High (physical custody/workflow)  
**Type:** Confirmed missing in-transit guard  
**Status:** Proposed fix only; NOT applied

## Evidence
- Administration::assign_location() updates holder_id of every active Hardcopy record at the chosen Location.
- The operation does not check for open transfers.
- Transfer_service::dispatch()/accept() requires the expected current_holder_id for an approved or in-transit transfer. Changing the holder mid-transfer makes the original transfer impossible to dispatch/accept.

## Copyable fix
In application/controllers/Administration.php inside assign_location(), immediately after the existing SELECT id FROM hardcopy_documents ... FOR UPDATE and before creating Document_access_service:

~~~php
$ids = array_map('intval', array_column($rows, 'id'));
$openTransfers = $this->db
    ->where_in('hardcopy_id', $ids)
    ->where_in('status', ['for_transfer','in_transit'])
    ->limit(1)->get('transfers')->row_array();
if ($openTransfers) {
    throw new DomainException(
        'Some documents at this Location have active transfers. '.
        'Complete or cancel those handoffs before reassigning.'
    );
}
~~~

For full concurrency protection, establish a consistent database lock order for transfers and documents and use SELECT ... FOR UPDATE for both sets. Avoid relying exclusively on a preflight query while a second user is approving/dispatching a transfer.

## Verify
Create a document, approve a transfer but do not dispatch it, then try Assign All for its Location: assignment must be rejected and all holders unchanged. Repeat with in_transit. Complete the handoff and retry: assignment should work. Exercise two browser sessions doing handoff and assignment simultaneously in a cloned database.
