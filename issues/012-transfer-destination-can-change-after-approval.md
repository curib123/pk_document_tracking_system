# Issue 012: Transfer destination hierarchy is not revalidated at acceptance

Severity: HIGH (physical document custody) | Status: Confirmed possible time-of-check/time-of-use race | Implemented: NO

Affected: application/services/request/request_service.php; application/services/transfer/transfer_service.php::accept().

Request_service validates the chosen destination when the request is submitted/approved, storing Area/Specific/Asset/Location IDs in transfers.destination JSON. Transfer_service::accept() later reloads only whether the Location is active and applies its *current* Area/Specific/Asset values. If a Place administrator changes the location hierarchy in between, a document can arrive at a different physical parent from the approved destination.

## Copyable fix

Inside Transfer_service::accept(), immediately after the existing active $location lookup and before updating hardcopy_documents, insert:

~~~php
foreach (['area_id','specific_id','asset_id'] as $field) {
    $approved = (int)($destination[$field] ?? 0);
    $current = (int)($location[$field] ?? 0);
    if ($approved !== $current) {
        throw new DomainException(
            'The destination hierarchy changed after approval. '.
            'Cancel and submit a new transfer.'
        );
    }
}
~~~

Keep the existing row locks, active-status checks, recipient checks, transaction rollback and workflow approval history unchanged.

If multiple Hardcopy files per Location are NOT authorized, also re-check current location occupancy inside the same transaction. If ISSUE 001 is approved, occupancy must no longer be enforced.

## Verify
Approve a transfer into Area A / Specific B / Asset C / Location D; change that Location's parent before acceptance; attempt to accept. It must fail safely without changing holder or location. An unchanged destination must still accept correctly. Test in parallel with a Location edit.
