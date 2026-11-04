# PK Document Tracking System — Current System Reference

**Status:** As-built feature reference  
**Updated:** 4 November 2026

This document describes the current PK Document Tracking System (PK DTS)
features and the recent register and workflow-interface updates. Available
actions and records depend on the signed-in user's permissions and document
scope.

## 1. System overview

PK DTS is a CodeIgniter 3 application using PHP 8 and MySQL/MariaDB. It manages
physical and electronic records, approval requests, assigned work, controlled
files, permissions, audit logs, and system administration.

The interface includes:

- **Overview:** dashboard counts and status summaries scoped to the user's
  access.
- **Document Management:** Hardcopy, Softcopy, and External Documents
  registers.
- **Document Requests:** My Requests for proposals and My Tasks for assigned
  approvals and physical handoffs.
- **Notifications:** unread notices that link to related work.
- **Setup Places / administration:** reference data, categories, users, roles,
  permissions, workflows, assignments, audit, and backups, as permitted.

## 2. Document registers

All registers support server-side search, sorting, filters, pagination, and
record details. Table and folder layouts are available for the document
browsers.

### Hardcopy Documents

- Records use the Area → Specific → optional Asset → Location hierarchy and
  track the holder.
- Retention information can be recorded and filtered.
- Request-based changes follow their configured approval workflow.
- Permitted direct actions include create, update, and dispose. Disposal
  records history and affects location/access as configured by the operation.
- Transfers use a separate approval, dispatch, and recipient-acceptance
  process; a transfer is not complete until the handoff is accepted.

### Softcopy Documents

- Records have a document number, series, category, revision history, and
  controlled files.
- Categories support parent/child hierarchy and can be browsed as folders.
- Register filters include status, category, owner (where available), and
  created-date range.
- Permitted file actions include current-file download, printing, and file
  history, subject to file permissions and document access.
- Direct create/revise/cancel/dispose actions require the relevant direct
  permission. Create and revise require a valid uploaded file.
- Category assignment and bulk assignment include subcategories where the
  action specifies a category branch.

### External Documents

- External Documents have their own hierarchical **External Categories**,
  independent of Softcopy Categories. The register supports table and folder
  layouts.
- Register columns include document title, category, status, description,
  revision level, created date, and updated date. The attachment filename is
  not displayed in the table; an authorized **Download** row action is used
  instead.
- Filters include status, category, created-date range, and owner when the
  viewer can view all records. Search covers document metadata, current
  revision level, and the attachment filename.
- Direct create and revise actions require External Documents direct
  permission and a valid attachment. A direct revision publishes a new
  revision and automatically increments its revision number and level.
- Disposal requires both External Documents direct permission and disposal
  direct permission. It records the previous state, disposal action, remarks,
  and status history.
- External create and revise requests can select an External Category and use
  the configured approval workflows.
- Users with restricted access see only their own permitted documents and the
  corresponding category folders.

## 3. Requests, approvals, and tasks

- My Requests separates request types into tabs. Users can save eligible
  proposals as drafts, submit them, track progress, and use permitted
  cancellation or deletion actions.
- Request types cover Hardcopy and Softcopy creation or changes, External
  Document creation/revision, transfer, disposal, time-limited access, and
  document assignment.
- My Tasks presents approval work assigned to the user or an eligible role.
  Depending on workflow policy, an approver can approve, return, or reject a
  request and provide remarks.
- Published workflow versions are preserved for requests already in progress.
  Workflow administrators create and validate drafts before publishing them
  for future submissions.
- Hardcopy transfers include post-approval dispatch and acceptance tasks.
- Sidebar and request/task tab counts show actionable draft/returned requests
  and pending tasks. Nonzero counts have a shake/pulse animation; zero counts
  stay static, and the animation is disabled for reduced-motion preferences.
- Workflow-step cards show the configured step name and assignee without an
  extra “Approval step” or “Final approval” stage label.

## 4. Categories and setup places

Setup Places contains permission-gated tabs for Area, Specific, Asset,
Location, Softcopy Categories, and External Categories.

- Area, Specific, Asset, and Location maintain the physical-storage
  relationships used by Hardcopy Documents.
- Softcopy Categories organize controlled Softcopy records and may contain
  numbering patterns.
- External Categories are stored separately and organize External Documents
  only. Each category can have a parent, folder name, description, and active
  state.
- Category lists can be searched and filtered by status or parent. Deactivation
  preserves historical document links.
- The External Categories tab uses the shared setup table and edit form.
  Category management permissions are separate from document direct-action
  permissions.

## 5. Access control and files

- Role permissions gate both navigation and actions. A visible register does
  not automatically grant edit, direct, approval, download, print, or
  administrative access.
- Document scope is applied to register queries, details, folders, and file
  actions. Folder visibility does not grant access to documents.
- Controlled files are stored privately. Uploads are type-checked and limited
  to 15 MB. Pending files remain private until approval or direct application.
- Superseded, rejected, or deleted files are not treated as current approved
  files; cleanup is reconciled separately from database transactions.
- Access grants are time-limited and can be revoked. Assignments and access
  grants are distinct operations.

## 6. Administration and operational controls

- User Management supports account creation, editing, role assignment,
  activation, and password setup according to permission.
- Roles and Permissions control module actions and access.
- Workflow Builder manages versioned approval routes; published versions are
  immutable.
- Document Assignments supports individual assignments and permitted bulk
  assignment by hardcopy location or Softcopy category branch. Bulk operations
  are bounded and subject to active-transfer checks for hardcopies.
- The Internal Audit Trail records application actions and approved metadata;
  filesystem audit logs are used rather than a separate operational audit
  table.
- Backup and restore tools manage database and private-file recovery. Restore
  is a maintenance operation that temporarily blocks ordinary requests.
- The application uses `Asia/Manila` for dates.

## 7. Database setup and upgrades

For a fresh database, import `database/pk_dts.sql` after reviewing it. The SQL
dump contains existing sample data and is not an upgrade script.

For existing installations, review and apply only the migrations required by
the installed schema; PK DTS has no automatic migration runner. External
Document upgrades may require the following order:

1. Apply `20261104_external_retention_dates_description.sql` only if the
   installed External Documents tables need the description columns.
2. Apply `20261104_remove_external_retention.sql` to remove obsolete External
   Document and External Revision retention columns.
3. Apply `20261104_external_categories.sql` to create the separate category
   table, seed Uncategorized, add the External Document category reference,
   and add category-management permissions.

Run migrations against the same database configured for the application.
Do not re-import the baseline SQL into a live database. Back up the database
before applying schema changes.

## 8. Recent register and interface updates

- Added direct External Document edit/revision and disposal actions with
  automatic revision increments and recorded disposal history.
- Added the External Document created date, category, owner, and status
  filters, reusable status badges, and table/folder layout.
- Removed attachment filenames from the External Documents register while
  retaining authorized Download actions.
- Added dedicated External Categories to Setup Places and category selection
  to direct and request-based External Document create/revise flows.
- Added category filters to both Softcopy and External Document registers and
  preserved the selected category across search, pagination, and layout
  changes.
- Added animated nonzero request/task counts with reduced-motion support.
- Removed the redundant workflow-builder step-stage captions.
- Added a shared External Categories setup view so the category tab resolves
  to the reusable Places interface.

## 9. Related documentation and exports

- `README.md` contains installation, configuration, migration, and validation
  instructions.
- `tools/documentation/guide.json`, `process.json`, and `flowchart.json` are
  structured documentation sources.
- The PDF, PowerPoint, and Visio files in this folder are generated/exported
  documents. This update adds the current Markdown reference and updates the
  guide source, but does not regenerate the PPTX, VSDX, or matching PDF
  exports. Update those files before redistributing them as current
  documentation.
