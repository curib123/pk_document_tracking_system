# Issue 016: Optional Softcopy effective date is silently replaced with today's date

Severity: MEDIUM (document accuracy) | Status: Confirmed UI/schema mismatch | Implemented: NO

Affected: application/services/softcopy/softcopy_operation_service.php; database/pk_dts.sql; application/view/pages/request/softcopy_fields.php.

The Softcopy form treats Effective Date as optional. However, softcopy_revisions.effective_date and new_effective_date are NOT NULL, and the service replaces blank initial/effective dates with date('Y-m-d'). A user who intentionally leaves Effective Date blank later sees a date they did not choose.

## Fix option A — retain the current schema
If office policy means "blank defaults to approval date", keep the database logic, but make this behavior explicit in the existing UI label/help and request confirmation. A copyable label example:

~~~php
<label class="form-label" for="effectiveDate">
  Effective Date (blank uses approval date)
</label>
~~~

Adjust the actual input ID in softcopy_fields.php to match the current form. No schema migration required.

## Fix option B — a truly optional effective date (requires approval)
Back up production. Make both columns nullable **using a reviewed migration only**:

~~~sql
ALTER TABLE softcopy_revisions
  MODIFY effective_date DATE NULL,
  MODIFY new_effective_date DATE NULL;
~~~

Then replace the revision effective date default in Softcopy_operation_service with:

~~~php
$effectiveInput = trim((string)($payload['effective_date'] ?? ''));
$effective = $effectiveInput !== '' ? $effectiveInput : NULL;
~~~

Keep validate_revision() checking any nonblank date. Also update the create path to store NULL when blank, while retaining valid received/released/approval dates. Update UI read paths and source schema contract tests for nullable dates.

## Verify
Create/revise a Softcopy with blank Effective Date, compare database and View Details. If choosing A, the date is today's and the form says so. If B, SQL NULL survives and View Details consistently shows No date. Never change a NOT NULL database column without testing exports and revision-history queries.
