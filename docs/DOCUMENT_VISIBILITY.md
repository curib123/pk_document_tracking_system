# Permission-based document visibility

Document visibility is decided by capabilities and current record relationships, not by runtime role-name checks. The Staff default is restricted, but administrators can configure the same policy on any custom role.

## Role Permissions

Keep `softcopy.view` and/or `hardcopy.view` to make the respective module available. These module permissions do not grant visibility to all records by themselves.

| Capability | Effect |
| --- | --- |
| `documents.view_assigned` | Active softcopies explicitly assigned to the current user. Active hardcopies whose named current holder is the user. |
| `documents.view_granted` | Active documents with a grant matching the current user, document ID and domain, with status `access_granted`, no revocation timestamp, and an expiration strictly later than the database current time. |
| `documents.view_all` | All document metadata, including otherwise hidden records. Does not grant unrestricted file-content access. |
| `documents.access_all` | Existing administrative override for all document metadata and content. Retained for compatibility. |

Assigned and granted visibility combine with OR. Staff receives the first two permissions, not either all-document override. Creating a document or requesting its creation does not independently grant document access. For hardcopy documents, assignment means current physical custody (`holder_id`); a completed transfer changes this relationship. Softcopies continue to use the existing assignments table.

No visibility capability means no document rows. A role name containing Administrator or Staff is not special at runtime. A custom role may be granted assigned-only, granted-only, both, or an explicit broader permission.

## Enforcement

`Document_visibility_model` supplies the same SQL predicate before listing counts, pagination, search, lookup selection, dashboard aggregation and detail lookup. `selected` IDs remain inside that boundary and cannot expose hidden options. File metadata and downloads also check document visibility; owning an uploaded attachment does not bypass lost access to its linked document. Unlinked personal uploads and explicitly authorized attachment-review operations retain their existing lifecycle.

The policy is checked on each request; expiration needs no maintenance job. Revoked/returned grants, removed assignments, disposal and holder changes take effect on the next read. Existing request/approval history remains subject to its own requester/approver permissions; it is not erased when a document grant ends. Broad audit and administrative permissions remain separate privileges and should not be assigned to a restricted Staff role.

Hidden document details return the same 404 as an unavailable record. A previously saved download URL is denied after access expires or is removed. Already downloaded copies cannot be remotely recalled.

## Existing XAMPP installation

Back up the database and pull the updated code. Run from the repository root:

```bat
C:\xampp\php\php.exe bin\sync_document_visibility.php
```

This is a permission-data upgrade; it does not change table columns or the schema version (v7). It creates the three new capability rows, sets the initial Staff restricted defaults, and registers the capabilities on Administrator. Existing Administrator/DCO broad access and unrelated/custom role permissions are preserved. Review custom roles with only module View permissions and explicitly select their desired document scopes.

The command is transactional. When all three capabilities already exist, it preserves subsequent role customization instead of reapplying default grants. Fresh installations seed the capabilities and Staff defaults directly, so no extra command is required there. Configure future changes under Roles → Assign permissions → Documents.

Staff cannot browse a catalog of hidden documents merely to request access. Use an administrator/DCO direct grant or assignment, or a controlled reference supplied for an access request. Grant `documents.view_all` only to roles intentionally allowed to discover the full metadata catalog.

## Regression tests

`tests/document_visibility.php` runs against an isolated MySQL database and checks denied enumeration, creator-only access, guessed IDs, lookup selection, filtered counts, duplicate grants, expiration, revocation, file download links, removed assignments, hardcopy custody, disposal, custom roles and administrative overrides. `tests/document_visibility_sync.php` verifies upgrade preservation and idempotency. Neither test is intended for a production database.
