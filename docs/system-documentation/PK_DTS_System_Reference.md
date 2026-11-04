# PK Document Tracking System

## What this system does

PK Document Tracking System (PK DTS) helps staff store and find document
records, submit requests for changes, review approval work, and manage access
to documents and files.

What you can see and do depends on your account's permissions and which
documents you are allowed to access.

## Main areas

- **Overview:** Shows dashboard information available to your account.
- **Hardcopy Documents:** Tracks physical documents, their storage locations,
  holders, and retention dates.
- **Softcopy Documents:** Tracks controlled electronic documents, categories,
  revisions, and files.
- **External Documents:** Tracks external documents, their own categories,
  revisions, and files.
- **My Requests:** Shows requests you have created.
- **My Tasks:** Shows approvals and physical handoffs assigned to you.
- **Notifications:** Shows notices about requests and assigned work.
- **Setup Places and administration:** Lets authorized users manage categories,
  storage references, accounts, roles, workflows, assignments, backups, and
  other system settings.

## Finding documents

Each document register lets you search, filter, sort, and open document
details. Use **Table** or **Folder** to change how you browse.

### Hardcopy Documents

Hardcopy records describe physical documents. Their storage can be organized
by **Area**, **Specific**, optional **Asset**, and **Location**. A record also
tracks its holder and may include retention dates.

Use the register and document details to check where a record is stored, who
holds it, and its status. Some users can create, update, or dispose of
hardcopy records directly. Other changes may need approval.

Transfers have their own process: a request is approved first, then the
document is dispatched and the recipient confirms receipt.

### Softcopy Documents

Softcopy records describe controlled electronic documents. Each record can
have a document number, series, category, revision history, and controlled
files.

Use the **Category** filter or **Folder** layout to find documents by category
and subcategory. You can also filter by status, date, and owner when those
filters are available to you.

If permitted, you can view file history, download the current file, or print
it. Direct creation and revision require an uploaded file. The system keeps
older revision records in the history.

### External Documents

External Documents have a separate category list from Softcopy Documents.
Choose **Table** or **Folder** to browse the register or its category folders.

The register shows the title, category, status, description, revision level,
created date, and updated date. It does not show attachment filenames. If
you have permission, use the row's **Download** action to get the current
file.

Use the available filters to narrow the list by category, status, date, or
owner. The owner filter is shown only when your access allows you to view all
records.

Authorized users can add an External Document or use **Edit** to upload a
revision. Each revision automatically advances the revision number and level.
Disposal requires both External Documents direct permission and disposal
direct permission, and records the disposal details and status history.

## Categories and storage setup

Open **Setup Places** to manage the reference lists used by document
registers. Tabs and actions appear only when your account has permission.

- **Area, Specific, Asset, and Location** describe where Hardcopy documents
  are stored.
- **Softcopy Categories** organize Softcopy documents. Categories may have
  subcategories and numbering patterns.
- **External Categories** organize External Documents only. They are separate
  from Softcopy Categories and may also have subcategories.

Use the category's **Name** and **Folder** fields. You may also add a
description and choose a parent category. Use the status and parent filters
to find categories. Deactivating a category keeps its historical document
links.

## Requests, approvals, and tasks

Use **My Requests** to prepare and follow requests. Depending on the request
type, you can save a draft, submit it for approval, and check its progress.
The available request types include document creation or revision, transfer,
disposal, access, and assignment.

Use **My Tasks** to review work assigned to you or to a role you hold.
Depending on the workflow, you may approve, return, or reject a request.
Hardcopy transfer tasks also include dispatch and receipt confirmation.

Approval workflows are managed by authorized users. Published workflows
apply to new requests; requests already in progress keep their existing
approval route.

### Default approval routes

The supplied database starts with these published routes. The approvers are
shown in order:

| Request type | Default approval route |
| --- | --- |
| Softcopy create, revise, or cancel | Requester's leader → Plant Manager → Document Control Officer |
| Hardcopy create or update | Plant Manager → Document Control Officer |
| External Document create or revise | Requester's leader → Plant Manager → Document Control Officer |
| Hardcopy transfer | Plant Manager → Document Control Officer → Requester confirms |
| Document access | Plant Manager → Document Control Officer → Requester confirms |
| Document assignment | Plant Manager → Document Control Officer → Requester's leader |
| Disposal | Requester's leader → Plant Manager → Document Control Officer |

These are the defaults in the supplied database, not permanent rules. An
authorized workflow administrator can publish a different route. Check
**Workflow Builder** to see the current published default for a request type.
Requests already submitted keep the route they started with.

The counts beside **My Requests** and **My Tasks** show requests or tasks
needing attention. Nonzero counts animate. The animation stops when your
device is set to reduce motion.

## Files, access, and permissions

Your account's permissions determine which pages and actions are available.
Being able to see a document does not automatically give you permission to
edit it, approve a request, or download or print its file.

Controlled files are stored privately. Uploads are checked and must be no
larger than 15 MB. Files awaiting approval are not available as current
approved files.

Access grants and document assignments are different:

- An **access grant** gives a user time-limited permission to access a
  document. It can be revoked.
- An **assignment** records who a document is assigned to. Assigning a
  document does not itself create an access grant.

## Administration and records

Authorized administrators can manage user accounts, roles, permissions,
approval workflows, document assignments, and system backups.

The audit trail records system actions and approved record details. Backups
and restores cover system data and private files. A restore is a maintenance
operation and temporarily blocks normal use of the application.

Dates in the application use the `Asia/Manila` time zone.

## Database updates

For a new installation, use `database/pk_dts.sql` after reviewing it. It
contains existing data as well as the database structure; it is not an update
script for a live system.

For an existing installation, an administrator must review and apply the SQL
migrations required by that database. Migrations do not run automatically.
Back up the database first and run each migration against the same database
used by the application.

To add External Categories to an existing External Documents database, the
usual migration sequence is:

1. If needed, apply `20261104_external_retention_dates_description.sql` to
   add missing description fields.
2. Apply `20261104_remove_external_retention.sql` to remove old, unused
   External Documents retention fields.
3. Apply `20261104_external_categories.sql` to create External Categories,
   add the category link to External Documents, and add category permissions.

Check the installed database schema before running these migrations. Do not
re-import `pk_dts.sql` into an existing installation.

## Documentation files

- `README.md` explains installation, configuration, and validation.
- `tools/documentation/guide.json`, `process.json`, and `flowchart.json` are
  structured sources for user and process documentation.
- The PDF, PowerPoint, and Visio files in this folder are exported copies.
  Update or regenerate them before sharing if they do not match this current
  reference.
