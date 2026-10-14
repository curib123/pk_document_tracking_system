# ISSUE 002 — Old Access/Assignment drafts retain an arbitrary recipient on submission

**Severity:** High (assignment/custody integrity)  
**Type:** Confirmed inconsistency in update vs submit paths  
**Status:** Proposed fix only; NOT applied

## Evidence
- application/services/request/request_service.php::save_draft() now forces recipient_id to the signed-in requester for Access and Assignment.
- submit() decodes the stored payload and validates the recipient is active, but never reasserts that self-service recipient_id equals requested_by.
- Drafts created before the policy change may therefore submit with an older different recipient without being edited.

## Copyable fix — inside Request_service::submit()
Immediately after $currentPayload is decoded, before the call to authorize(), insert:

~~~php
if (in_array($r['type'], ['access','assignment'], TRUE)) {
    $currentPayload['recipient_id'] = (int)$r['requested_by'];
    $encoded = json_encode($currentPayload, JSON_UNESCAPED_UNICODE);
    if ($encoded === FALSE) {
        throw new DomainException('Request data could not be saved.');
    }
    if (!$this->ci->db->where('id',(int)$r['id'])
        ->update('requests',['payload'=>$encoded])) {
        throw new DomainException('Could not normalize the request recipient.');
    }
    $r['payload'] = $encoded;
}
~~~

This code runs under the existing row lock and transaction. Keep the active-user check, permission scope and workflow snapshot untouched. For immutable/audited submitted requests, do not retroactively rewrite historical approval records; apply only on new submission from draft or returned.

## Verify
In a TEST database only, create an Access draft with recipient_id pointing to another user, then submit while signed in as the requester. Confirm requests.payload recipient_id becomes requested_by, approval applies access only to the requester, and hardcopy transfer requests still permit a designated different recipient. Repeat for Assignment and for returning/resubmitting a request. Check audit/activity records.
