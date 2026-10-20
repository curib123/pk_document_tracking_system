# PK DTS system structure and connection map

Snapshot: 19 October 2026. Source checkout: `C:\Users\PK\Desktop\PK_System\pk_document_tracking_system`.
Served XAMPP checkout: `C:\xampp\htdocs\pk_document_tracking_system`.
Canonical local login: `http://localhost/pk_document_tracking_system/login`.

This file maps every project-owned file and the installed Composer dependency files. Private runtime records, document bytes, sessions, screenshots and Git internals use patterns instead of exposing their contents or user-specific filenames. Files in `vendor/` are dependency-owned; change application code under `application/` and `public/assets/`.

## Request and file connections

```text
Browser /login, /documents/..., /my-requests/..., /admin/...
  -> root .htaccess (clean URLs; redirects /public/...)
  -> index.php -> public/index.php -> application/bootstrap.php
  -> vendor/codeigniter/framework/system/core/CodeIgniter.php
  -> application/config/{autoload,config,database,routes}.php
  -> application/core/MY_Controller.php (identity + action permissions + CSRF/confirmation)
  -> application/controllers/<Feature>.php
       |-> application/models/<Feature>_model.php -> MySQL tables
       |-> application/services/<feature>/*.php -> transaction/rules -> MySQL
       |-> Audit_context before/after -> Audit_file -> storage/audit/YYYY-MM-DD.json
       `-> application/view/layout + pages + reusable_components
             -> public/assets/{css,js,images}
             -> shared View modal in layout/footer.php
                  -> app.js -> document-view.js -> safe text/tabs/history/date formatting

Protected files (never linked as public filesystem paths)
  Files controller -> File_model permissions -> storage/documents/<random-storage-name>
    |-> download / print / browser review
    `-> review page -> ms-word / ms-excel / pk-dts-office (WPS)
         -> 10-minute signed review-content URL
         -> active account + current request/file permission recheck
         -> file bytes + audit event (token is omitted from the audit event)
```

## Feature ownership and principal connections

| Feature / route family                  | Controller           | Services and models                                                                               | Templates / browser assets                                                  | Main data                                                       |
| --------------------------------------- | -------------------- | ------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `/login`, `/logout`, `/change-password` | Auth                 | Identity_model, authentication_service, account_policy                                            | pages/authentication, auth/login-transition/logout-transition               | users, roles, user_roles, login_attempts                        |
| `/dashboard`                            | Dashboard            | Dashboard_model, dashboard_service                                                                | pages/dashboard, dashboard.js/css                                           | documents, requests, histories                                  |
| `/documents/hardcopy`                   | Documents            | Document_model, Document_detail_model, document_service, retention_countdown                      | pages/hardcopy_document -> pages/documents/shared_index; document-view      | hardcopy_documents, status_history, places                      |
| `/documents/softcopy`                   | Documents            | Document_model, File_model, softcopy_direct_service -> softcopy_operation_service -> file_service | pages/softcopy_document -> pages/documents/shared_index                     | softcopy_documents, softcopy_revisions, files, categories       |
| `/my-requests/{tab}`, `/my-tasks/{tab}` | Requests             | Request_model, request_service, request_policy, workflow_graph                                    | pages/request/shared_index and field fragments; row-actions, document-view  | requests, workflow_steps, workflow_history, workflow_versions   |
| Transfer dispatch/accept                | Requests             | Transfer_model, transfer_service, transfer_destination_policy                                     | transfer request fields / shared request register                           | transfers -> holder/storage only on acceptance                  |
| Direct/approved disposal                | Documents / Requests | document_service, disposal_service, disposal_details                                              | shared request/document View modal                                          | disposals, status_history, files                                |
| `/files/...`                            | Files                | File_model, file_service, office_review_ticket                                                    | pages/files/{print,review}; controlled-print, office-review; Windows opener | files and private storage/documents                             |
| `/places/...`                           | Places               | Place_model, place_service, category_parent_policy, hardcopy_place_policy                         | pages/places and folder fragments                                           | areas -> specifics -> assets -> locations; categories.parent_id |
| `/admin/users`, `/admin/roles`          | Administration       | Administration_model, administration_service, user_roles, role_usage                              | pages/user, pages/roles_and_permission                                      | users, user_roles, roles, role_permissions                      |
| `/admin/document-assignments`           | Administration       | Administration_model, Folder_model, Folder_tree::branch, document_access_service                  | pages/administration/document_assignments                                   | hardcopy holder_id; softcopy assignments                        |
| `/admin/workflows`                      | Administration       | administration_service -> workflow_builder_service / workflow_graph                               | pages/workflow_builder                                                      | workflows, workflow_versions, workflow_steps                    |
| `/notifications/...`                    | Notifications        | Notification_model                                                                                | task-notifications, notification-inbox; layout navigation                   | task_notification_receipts + active workflow steps              |
| `/admin/audit`                          | Audit                | Audit_service; MY_Controller -> Audit_context / Audit_file                                        | pages/administration/audit                                                  | locked daily private JSON files                                 |
| `/admin/backups`                        | Backups              | backup_service, backup_listing, retention_service                                                 | pages/administration/backups; backup.css                                    | encrypted backup archives and protected runtime data            |

## Domain relationships and behavior

```text
Identity: users <-> user_roles -> roles <-> role_permissions -> permissions
Softcopy: categories.parent_id -> categories -> softcopy_documents
          softcopy_documents.current_revision_id -> softcopy_revisions -> files
          assignments: softcopy_id + user_id + active + assigned_by/assigned_at
Hardcopy: areas -> specifics -> assets -> locations
          hardcopy_documents -> location_id + holder_id
          transfers: current_holder_id -> recipient_id + approved destination
Workflow: workflows -> workflow_versions (published graph)
          requests -> snapshot/current_node -> workflow_steps -> workflow_history
          final approval -> document/access/assignment/disposal/transfer operation
Audit:    authenticated action -> target IDs + before/after -> daily JSON file
```

- Category bulk assignment includes the selected parent/subcategory and all active descendants. It assigns all matching active softcopies, independent of visible table paging. Existing assignments to other users remain. A failed batch rolls back.
- Hardcopy bulk assignment uses Location; custody is stored in `hardcopy_documents.holder_id`. Softcopy assignment uses `assignments`; these domains do not share a synthetic assignment schema.
- The shared View modal validates legacy empty sections before rendering. SQL wall times use Asia/Manila; explicit ISO offsets convert to Manila. Date-only values omit time; invalid dates have a safe fallback. Formatting is idempotent to avoid observer loops.
- Request Reference is visible in request/task lists, details, notifications and related histories. Other Reference fields and generic reference headers remain hidden. Copy Number is hidden from document, request and assignment UI; stored copy values and historical links are preserved. Softcopy Document Number remains visible.
- WPS requires the per-user Windows opener plus an installed WPS Office application. Word/Excel use their installed application URI handlers. All review app links are read-only capabilities; saving a local review copy does not revise the stored document.

## Complete project file tree

Every entry below is a project-owned tracked or non-ignored source file. Comments identify responsibility; the feature table above identifies its runtime connections.

```text
pk_document_tracking_system/
|-- .github/  # Repository automation
|   `-- workflows/
|       `-- pk-dts-schema.yml  # CI schema installation and regression checks
|-- application/  # Application-owned CodeIgniter MVC code
|   |-- config/  # Framework configuration and route entry points
|   |   |-- autoload.php  # Shared libraries/helpers loaded by CodeIgniter
|   |   |-- config.php  # Sessions, CSRF, cookies, logging and base URL
|   |   |-- constants.php  # Application constants
|   |   |-- database.php  # MySQL config from environment; separate test DB guard
|   |   `-- routes.php  # HTTP route -> controller method
|   |-- controllers/  # HTTP authorization, form handling, redirects and responses
|   |   |-- Administration.php  # Users, roles, workflow configuration and direct document assignments -> services/models/views
|   |   |-- Audit.php  # Permission-scoped daily audit listing -> services/models/views
|   |   |-- Auth.php  # Login/logout and first-login/password setup -> services/models/views
|   |   |-- Backups.php  # Encrypted backup create/import/restore/download/retention -> services/models/views
|   |   |-- Dashboard.php  # Dashboard metrics and recent activity -> services/models/views
|   |   |-- Documents.php  # System document registers, direct actions and disposal -> services/models/views
|   |   |-- Files.php  # Uploads, protected download/print/review, native review content and WPS setup ZIP -> services/models/views
|   |   |-- Notifications.php  # Pending approval claims and inbox receipts -> services/models/views
|   |   |-- Places.php  # Physical storage places and category hierarchy -> services/models/views
|   |   `-- Requests.php  # My Requests/My Tasks, draft/submit/decide/cancel and physical handoffs -> services/models/views
|   |-- core/  # Framework extensions
|   |   `-- MY_Controller.php  # Identity/permissions, confirmation, audit context, notices and shared page rendering
|   |-- libraries/  # CodeIgniter libraries
|   |   `-- Audit_file.php  # Locked atomic daily JSON audit writer -> storage/audit
|   |-- models/  # Database queries and scoped read models
|   |   |-- Administration_model.php  # Users/roles/workflows and assignment browsing
|   |   |-- Dashboard_model.php  # Dashboard summaries
|   |   |-- Document_detail_model.php  # Batched shared-viewer data and scoped histories
|   |   |-- Document_model.php  # Document configuration, authorized registers and choices
|   |   |-- File_model.php  # Current files, history and active-reviewer authorization
|   |   |-- Folder_model.php  # Active place/category tree and folder scope
|   |   |-- Identity_model.php  # Active account and login identity
|   |   |-- Notification_model.php  # Approval occurrences, inbox pages and seen claims
|   |   |-- Permission_model.php  # Role/user action permissions
|   |   |-- Place_model.php  # Places/categories query configuration and options
|   |   |-- Request_model.php  # Scoped request/task listing, proposal details and approval routes
|   |   `-- Transfer_model.php  # Physical handoff listing and scope
|   |-- services/  # Business rules and focused presentation policies
|   |   |-- administration/  # Feature policy/service
|   |   |   |-- account_policy.php  # Account/role delegation policy
|   |   |   |-- administration_service.php  # Account/role administration and workflow-builder delegation
|   |   |   `-- role_usage.php  # Live role usage protections
|   |   |-- audit/  # Feature policy/service
|   |   |   |-- audit_context.php  # Approved input + before/after snapshots -> MY_Controller notices
|   |   |   `-- audit_service.php  # Reads/filter/pages locked daily audit JSON -> Audit controller
|   |   |-- authentication/  # Feature policy/service
|   |   |   `-- authentication_service.php  # Password/setup policy
|   |   |-- backup/  # Feature policy/service
|   |   |   |-- backup_listing.php  # Backup inventory and metadata
|   |   |   |-- backup_service.php  # Encrypted backup/recovery and safe runtime maintenance
|   |   |   `-- retention_service.php  # Scheduled/daily retention policy
|   |   |-- dashboard/  # Feature policy/service
|   |   |   `-- dashboard_service.php  # Dashboard presentation aggregation
|   |   |-- documents/  # Feature policy/service
|   |   |   |-- disposal_service.php  # Shared disposal choices and reason validation
|   |   |   |-- document_access_service.php  # Softcopy assignments/access grants; hardcopy holder changes
|   |   |   |-- document_service.php  # Hardcopy validation/direct save/disposal and approved hardcopy proposals
|   |   |   `-- retention_countdown.php  # Human-readable hardcopy disposal countdown
|   |   |-- files/  # Feature policy/service
|   |   |   |-- file_service.php  # Stage/validate revisions, current-file lifecycle and deferred byte cleanup
|   |   |   `-- office_review_ticket.php  # Signed 10-minute reviewer/request/file capability; rechecks current access on fetch
|   |   |-- places/  # Feature policy/service
|   |   |   |-- category_parent_policy.php  # Cycle-safe category parent validation
|   |   |   |-- hardcopy_place_policy.php  # Allowed hardcopy storage relationships
|   |   |   `-- place_service.php  # Places/category create/edit/deactivate business rules
|   |   |-- presentation/  # Feature policy/service
|   |   |   |-- disposal_details.php  # Proposed/recorded disposal details for shared viewers
|   |   |   |-- document_folder_tree.php  # Document browser projection and folder keys
|   |   |   |-- folder_search.php  # Folder/name search and visible contextual paths
|   |   |   |-- folder_tree.php  # Cycle-safe tree builder and complete descendant branch selection
|   |   |   |-- folder_visibility.php  # Permission-based folder visibility
|   |   |   |-- hardcopy_folder_tree.php  # Physical documents projected into storage hierarchy
|   |   |   `-- query_state.php  # One shared filter/paging/date-range parser for registers
|   |   |-- request/  # Feature policy/service
|   |   |   |-- request_policy.php  # Request visibility/editability and authorization rules
|   |   |   `-- request_service.php  # Transactional draft/submit/decision/return/cancel and final approved actions
|   |   |-- security/  # Feature policy/service
|   |   |   |-- document_scope.php  # SQL predicates for scoped document/file visibility
|   |   |   `-- user_roles.php  # Multi-role membership and compatibility
|   |   |-- softcopy/  # Feature policy/service
|   |   |   |-- softcopy_direct_service.php  # Direct authorized softcopy create/revise/cancel transaction
|   |   |   `-- softcopy_operation_service.php  # Shared approved softcopy operations and revision/status history
|   |   |-- transfer/  # Feature policy/service
|   |   |   |-- transfer_destination_policy.php  # Reject changed physical destination hierarchy after approval
|   |   |   `-- transfer_service.php  # Dispatch/accept handoff; custody/storage change only on acceptance
|   |   `-- workflow/  # Feature policy/service
|   |       |-- workflow_builder_service.php  # Versioned approval-step editing/publish/clone
|   |       `-- workflow_graph.php  # Workflow graph validation and normalization
|   |-- view/  # PHP templates (the actual view directory is singular)
|   |   |-- layout/  # Header/sidebar/footer and global shared modals
|   |   |   |-- footer.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- header.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   `-- sidebar_top_nav.php  # Template/fragment -> matching feature controller and shared layout
|   |   |-- pages/  # Feature pages selected by controllers
|   |   |   |-- administration/
|   |   |   |   |-- audit.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- backups.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- document_assignments.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- authentication/
|   |   |   |   |-- first_login.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- password_fields.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- dashboard/
|   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- documents/
|   |   |   |   `-- shared_index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- files/
|   |   |   |   |-- print.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- review.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- hardcopy_document/
|   |   |   |   |-- modal_action/
|   |   |   |   |   `-- upsert.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- places/
|   |   |   |   |-- area/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- asset_number/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- location/
|   |   |   |   |   |-- modal_action/
|   |   |   |   |   |   `-- upsert.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- softcopy_category/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- specific/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- shared_index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- request/
|   |   |   |   |-- approving_assign_request/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- user_own_all_request/
|   |   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- disposal_fields.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- hardcopy_transfer_fields.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   |-- shared_index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- softcopy_fields.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- roles_and_permission/
|   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- softcopy_document/
|   |   |   |   `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |-- user/
|   |   |   |   |-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   |   `-- temporary_credentials.php  # Template/fragment -> matching feature controller and shared layout
|   |   |   `-- workflow_builder/
|   |   |       `-- index.php  # Template/fragment -> matching feature controller and shared layout
|   |   `-- reusable_components/  # Shared datatable, row actions, folders, PWA and auth fragments
|   |       |-- alert_icon.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- auth_alert.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- document_folder_list.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- login_transition.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- logout_transition.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- pwa_head.php  # Template/fragment -> matching feature controller and shared layout
|   |       |-- record_actions.php  # View/Edit/Review/Print/Download/decision buttons -> app.js + row-actions.js
|   |       `-- reusable_datatable.php  # Table/grid/folder renderer -> record_actions.php + live-table.js
|   `-- bootstrap.php  # Root paths, Manila timezone, base URL and daily retention
|-- database/  # Install schema and ordered additive migrations
|   |-- migrations/  # Versioned schema/permission updates
|   |   |-- 20261013_allow_multiple_hardcopies_per_location.sql  # Additive migration: allow multiple hardcopies per location
|   |   |-- 20261013_task_notification_receipts.sql  # Additive migration: task notification receipts
|   |   |-- 20261013_user_role_assignments.sql  # Additive migration: user role assignments
|   |   |-- 20261015_file_action_permissions.sql  # Additive migration: file action permissions
|   |   `-- 20261016_location_sequence_remove_code.sql  # Additive migration: location sequence remove code
|   `-- pk_dts.sql  # Schema/export snapshot; inspect its existing rows before any import
|-- docs/  # Recorded validation evidence
|   `-- validation-2026-10-15.json  # Saved validation evidence
|-- guide/  # Manual verification and maintenance guides
|   |-- 01-smoke-and-navigation.md  # Manual guide: 01 smoke and navigation
|   |-- 02-permissions-and-requests.md  # Manual guide: 02 permissions and requests
|   |-- 03-document-search-and-modal.md  # Manual guide: 03 document search and modal
|   |-- 04-backup-schema-and-recovery.md  # Manual guide: 04 backup schema and recovery
|   |-- 05-results-template.md  # Manual guide: 05 results template
|   |-- 06-assets-and-maintenance.md  # Manual guide: 06 assets and maintenance
|   |-- 07-current-plan-manual-test.md  # Manual guide: 07 current plan manual test
|   `-- README.md  # Manual guide: README
|-- public/  # Public front controller and browser assets
|   |-- assets/  # Browser resources
|   |   |-- css/  # Application styles
|   |   |   |-- action-feedback.css  # Styles for action feedback views/components
|   |   |   |-- app.css  # Styles for app views/components
|   |   |   |-- backup.css  # Styles for backup views/components
|   |   |   |-- colors.css  # Styles for colors views/components
|   |   |   |-- controlled-print.css  # Styles for controlled print views/components
|   |   |   |-- dashboard.css  # Styles for dashboard views/components
|   |   |   |-- document-view.css  # Styles for document view views/components
|   |   |   |-- login-transition.css  # Styles for login transition views/components
|   |   |   |-- logout-transition.css  # Styles for logout transition views/components
|   |   |   |-- motion.css  # Styles for motion views/components
|   |   |   |-- office-review.css  # Styles for office review views/components
|   |   |   |-- plan-enhancements.css  # Styles for plan enhancements views/components
|   |   |   |-- pwa.css  # Styles for pwa views/components
|   |   |   |-- roles-permission.css  # Styles for roles permission views/components
|   |   |   |-- unified-ux.css  # Styles for unified ux views/components
|   |   |   `-- view-modals.css  # Styles for view modals views/components
|   |   |-- images/  # Brand and PWA images
|   |   |   |-- building.jpg  # Static brand/PWA image
|   |   |   |-- peanut-kisses.png  # Static brand/PWA image
|   |   |   |-- pwa-192.png  # Static brand/PWA image
|   |   |   `-- pwa-512.png  # Static brand/PWA image
|   |   `-- js/  # Shared browser behavior
|   |       |-- action-feedback.js  # Pending/result interaction feedback
|   |       |-- app.js  # Shared modal/form enhancements, local fallback Bootstrap and row view/edit behavior
|   |       |-- auth.js  # Account form enhancements
|   |       |-- controlled-print.js  # Protected browser print action
|   |       |-- dashboard.js  # Dashboard visual behavior
|   |       |-- document-tree.js  # Document folder navigation
|   |       |-- document-view.js  # Safe shared record/document renderer, tabs, timeline and uniform Manila date display
|   |       |-- live-table.js  # In-place register filters/paging and stale-response protection
|   |       |-- login-transition.js  # Successful login handoff animation
|   |       |-- logout-transition.js  # Session invalidation/sign-out transition
|   |       |-- motion.js  # Navigation/motion accessibility behavior
|   |       |-- notification-inbox.js  # Paginated unread approval inbox
|   |       |-- plan-controls.js  # Date-range controls and idempotent shared date formatting
|   |       |-- pwa.js  # Install-button lifecycle and worker registration
|   |       |-- row-actions.js  # Confirmation, decision/disposal dialogs and form actions
|   |       `-- task-notifications.js  # Durable once-per-occurrence task alerts
|   |-- .htaccess  # Legacy URL redirect plus fallback when public is the server document root
|   |-- index.php  # Bootstraps application then vendor CodeIgniter framework
|   |-- manifest.php  # Install metadata, canonical scope and stable legacy PWA identity
|   `-- service-worker.js  # Live network requests; authenticated pages/files are not cached
|-- tests/  # Unit/contract tests, isolated browser checks and disposable SQL fixtures
|   |-- fixtures/  # Disposable test schema and seed data
|   |   |-- pk_dts.sql  # Test-only fixture; imported only into owned test databases
|   |   |-- seed.sql  # Test-only fixture; imported only into owned test databases
|   |   `-- seed_workflows.sql  # Test-only fixture; imported only into owned test databases
|   |-- action_errors.php  # Regression: action errors
|   |-- action_feedback.test.js  # Regression: action feedback.test
|   |-- apache_root_urls.py  # Real Apache routing/private-path checks using an owned static fixture
|   |-- approval_step_tiles.php  # Regression: approval step tiles
|   |-- audit_context.php  # Regression: audit context
|   |-- audit_listing.php  # Regression: audit listing
|   |-- backup_contract.php  # Regression: backup contract
|   |-- backup_integration.sh  # Regression: backup integration
|   |-- backup_listing.php  # Regression: backup listing
|   |-- backup_listing_regression.php  # Regression: backup listing regression
|   |-- backup_retention.php  # Regression: backup retention
|   |-- browser_document_fixture.php  # Browser check: browser document fixture
|   |-- browser_document_workflows.py  # Owns disposable DB/app copy and Edge browser lifecycle
|   |-- browser_issue_checks.py  # Category/audit/native-review and Request Reference/hidden-copy browser checks
|   |-- browser_register_checks.py  # Browser check: browser register checks
|   |-- browser_storage_route_checks.py  # Browser check: browser storage route checks
|   |-- browser_task_notification_checks.py  # Browser check: browser task notification checks
|   |-- browser_transfer_history_checks.py  # Browser check: browser transfer history checks
|   |-- browser_user_assignment_checks.py  # Browser check: browser user assignment checks
|   |-- browser_viewer_design_checks.py  # Browser check: browser viewer design checks
|   |-- browser_workflow_stepper_checks.py  # Browser check: browser workflow stepper checks
|   |-- category_assignment.php  # Regression: category assignment
|   |-- colors_actions_contract.php  # Regression: colors actions contract
|   |-- controlled_file_history.php  # Regression: controlled file history
|   |-- controlled_file_print.php  # Regression: controlled file print
|   |-- controlled_file_replacement.php  # Regression: controlled file replacement
|   |-- controlled_print.test.js  # Regression: controlled print.test
|   |-- current_plan_contract.php  # Regression: current plan contract
|   |-- dashboard_copy_contract.php  # Regression: dashboard copy contract
|   |-- dashboard_visual.test.js  # Regression: dashboard visual.test
|   |-- date_controls.test.js  # Regression: date controls.test
|   |-- date_range.php  # Regression: date range
|   |-- disposal_view_details.php  # Regression: disposal view details
|   |-- document_approval_history.php  # Regression: document approval history
|   |-- document_folder_layout.php  # Regression: document folder layout
|   |-- document_transfer_history.php  # Regression: document transfer history
|   |-- document_view.test.js  # Regression: document view.test
|   |-- file_action_permissions.php  # Regression: file action permissions
|   |-- file_response_safety.php  # Regression: file response safety
|   |-- folder_search.php  # Regression: folder search
|   |-- hardcopy_after_transfer_scope.php  # Regression: hardcopy after transfer scope
|   |-- hardcopy_contextual_folders.php  # Regression: hardcopy contextual folders
|   |-- hardcopy_folder_projection.php  # Regression: hardcopy folder projection
|   |-- high_priority_security.php  # Regression: high priority security
|   |-- live_table_contract.test.js  # Regression: live table contract.test
|   |-- login_transition.test.js  # Regression: login transition.test
|   |-- login_transition_handoff.php  # Regression: login transition handoff
|   |-- logout_handoff.php  # Regression: logout handoff
|   |-- logout_transition.test.js  # Regression: logout transition.test
|   |-- modal_dismissal.test.js  # Regression: modal dismissal.test
|   |-- motion_navigation.test.js  # Regression: motion navigation.test
|   |-- office_opener.ps1  # Native opener URI validation without launching an app
|   |-- office_review.php  # Regression: office review
|   |-- plan_contract.php  # Regression: plan contract
|   |-- plan_implementation.php  # Regression: plan implementation
|   |-- pwa.test.js  # Regression: pwa.test
|   |-- pwa_manifest.php  # Regression: pwa manifest
|   |-- request_disposal.php  # Regression: request disposal
|   |-- request_return.php  # Regression: request return
|   |-- request_view_new_types.php  # Regression: request view new types
|   |-- retention_countdown.php  # Regression: retention countdown
|   |-- role_lifecycle.php  # Regression: role lifecycle
|   |-- root_urls.php  # Regression: root urls
|   |-- row_actions.test.js  # Regression: row actions.test
|   |-- run_unit.sh  # PHP/JS syntax and unit/contract suite
|   |-- schema_install.py  # Regression: schema install
|   |-- smoke.sh  # Regression: smoke
|   |-- task_notifications.php  # Regression: task notifications
|   |-- task_notifications.test.js  # Regression: task notifications.test
|   |-- topnav_optional_asset.php  # Regression: topnav optional asset
|   |-- user_role_assignments.php  # Regression: user role assignments
|   `-- user_role_permissions.php  # Regression: user role permissions
|-- tools/  # Administrative maintenance and integration tools
|   |-- office-opener/  # Per-user Windows bridge for direct WPS launch
|   |   |-- install.ps1  # Pins site URL and registers/unregisters pk-dts-office in HKCU
|   |   |-- open-office.ps1  # Validates site/path, fetches signed review copy and launches installed WPS
|   |   `-- README.txt  # One-time setup and uninstall instructions
|   |-- audit_assets.cjs  # CLI maintenance utility: audit assets
|   |-- backup.php  # CLI maintenance utility: backup
|   |-- build_pwa_icons.ps1  # CLI maintenance utility: build pwa icons
|   |-- create_admin.php  # CLI maintenance utility: create admin
|   |-- install_backup_schedule.ps1  # CLI maintenance utility: install backup schedule
|   `-- sync_local_xampp.ps1  # Clean-tree fast-forward Desktop -> XAMPP plus HTTP asset hash checks
|-- .gitignore  # Private data, dependencies and generated screenshot exclusions
|-- .htaccess  # Clean URL routing, legacy /public redirects and private-path protection
|-- composer.json  # PHP dependencies and Composer test command
|-- composer.lock  # Pinned PHP dependency versions
|-- index.php  # Canonical web entry -> public/index.php
|-- package-lock.json  # Pinned frontend package metadata
|-- package.json  # Frontend package metadata; some legacy build-script targets are absent
|-- plan.md  # Requested issues/backlog
|-- SYSTEM_BUSINESS_LOGIC.mmd  # Copyable Mermaid code for the business workflow
`-- SYSTEM_MAP.md  # This complete structural and connection map
```

## Private and generated runtime layout

```text
storage/
|-- audit/YYYY-MM-DD.json        # actor, action, target, outcome and approved before/after metadata
|-- audit/.audit.lock            # shared writer/read locking
|-- documents/<random-name>      # controlled attachment bytes; File_service owns their lifecycle
|-- sessions/<session-file>      # CodeIgniter session state; never serve directly
|-- logs/<date>.log              # protected application error logs
|-- .office-review-key           # private signing key for short-lived native review tickets
|-- <backup/retention artifacts> # Backup_service owns archive layout and cleanup markers
`-- .gitkeep                    # tracked placeholder
.git/                           # Git internal history/index/config, private
node_modules/                   # optional npm-installed dependencies, private/ignored
tests/*.png                     # generated browser screenshots, ignored
%LOCALAPPDATA%/PK DTS/
|-- OfficeOpener/{open-office.ps1,config.json} # per-user WPS handler and pinned site
`-- ReviewCopies/<guid>/review.{docx,xlsx}   # local read-only WPS review copies
HKCU/Software/Classes/pk-dts-office/          # per-user Windows URI registration
```

## Installed Composer dependency file inventory

These files are generated/installed from `composer.lock`. They belong to CodeIgniter or the PDF libraries, not a feature controller. Restore them with Composer instead of editing framework files. This inventory reflects the installed dependency tree at the snapshot date.

```text
`-- vendor/
    |-- codeigniter/
    |   `-- framework/
    |       |-- application/
    |       |   |-- cache/
    |       |   |   `-- index.html
    |       |   |-- config/
    |       |   |   |-- autoload.php
    |       |   |   |-- config.php
    |       |   |   |-- constants.php
    |       |   |   |-- database.php
    |       |   |   |-- doctypes.php
    |       |   |   |-- foreign_chars.php
    |       |   |   |-- hooks.php
    |       |   |   |-- index.html
    |       |   |   |-- memcached.php
    |       |   |   |-- migration.php
    |       |   |   |-- mimes.php
    |       |   |   |-- profiler.php
    |       |   |   |-- routes.php
    |       |   |   |-- smileys.php
    |       |   |   `-- user_agents.php
    |       |   |-- controllers/
    |       |   |   |-- index.html
    |       |   |   `-- Welcome.php
    |       |   |-- core/
    |       |   |   `-- index.html
    |       |   |-- helpers/
    |       |   |   `-- index.html
    |       |   |-- hooks/
    |       |   |   `-- index.html
    |       |   |-- language/
    |       |   |   |-- english/
    |       |   |   |   `-- index.html
    |       |   |   `-- index.html
    |       |   |-- libraries/
    |       |   |   `-- index.html
    |       |   |-- logs/
    |       |   |   `-- index.html
    |       |   |-- models/
    |       |   |   `-- index.html
    |       |   |-- third_party/
    |       |   |   `-- index.html
    |       |   |-- views/
    |       |   |   |-- errors/
    |       |   |   |   |-- cli/
    |       |   |   |   |   |-- error_404.php
    |       |   |   |   |   |-- error_db.php
    |       |   |   |   |   |-- error_exception.php
    |       |   |   |   |   |-- error_general.php
    |       |   |   |   |   |-- error_php.php
    |       |   |   |   |   `-- index.html
    |       |   |   |   |-- html/
    |       |   |   |   |   |-- error_404.php
    |       |   |   |   |   |-- error_db.php
    |       |   |   |   |   |-- error_exception.php
    |       |   |   |   |   |-- error_general.php
    |       |   |   |   |   |-- error_php.php
    |       |   |   |   |   `-- index.html
    |       |   |   |   `-- index.html
    |       |   |   |-- index.html
    |       |   |   `-- welcome_message.php
    |       |   |-- .htaccess
    |       |   `-- index.html
    |       |-- system/
    |       |   |-- core/
    |       |   |   |-- compat/
    |       |   |   |   |-- hash.php
    |       |   |   |   |-- index.html
    |       |   |   |   |-- mbstring.php
    |       |   |   |   |-- password.php
    |       |   |   |   `-- standard.php
    |       |   |   |-- Benchmark.php
    |       |   |   |-- CodeIgniter.php
    |       |   |   |-- Common.php
    |       |   |   |-- Config.php
    |       |   |   |-- Controller.php
    |       |   |   |-- Exceptions.php
    |       |   |   |-- Hooks.php
    |       |   |   |-- index.html
    |       |   |   |-- Input.php
    |       |   |   |-- Lang.php
    |       |   |   |-- Loader.php
    |       |   |   |-- Log.php
    |       |   |   |-- Model.php
    |       |   |   |-- Output.php
    |       |   |   |-- Router.php
    |       |   |   |-- Security.php
    |       |   |   |-- URI.php
    |       |   |   `-- Utf8.php
    |       |   |-- database/
    |       |   |   |-- drivers/
    |       |   |   |   |-- cubrid/
    |       |   |   |   |   |-- cubrid_driver.php
    |       |   |   |   |   |-- cubrid_forge.php
    |       |   |   |   |   |-- cubrid_result.php
    |       |   |   |   |   |-- cubrid_utility.php
    |       |   |   |   |   `-- index.html
    |       |   |   |   |-- ibase/
    |       |   |   |   |   |-- ibase_driver.php
    |       |   |   |   |   |-- ibase_forge.php
    |       |   |   |   |   |-- ibase_result.php
    |       |   |   |   |   |-- ibase_utility.php
    |       |   |   |   |   `-- index.html
    |       |   |   |   |-- mssql/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- mssql_driver.php
    |       |   |   |   |   |-- mssql_forge.php
    |       |   |   |   |   |-- mssql_result.php
    |       |   |   |   |   `-- mssql_utility.php
    |       |   |   |   |-- mysql/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- mysql_driver.php
    |       |   |   |   |   |-- mysql_forge.php
    |       |   |   |   |   |-- mysql_result.php
    |       |   |   |   |   `-- mysql_utility.php
    |       |   |   |   |-- mysqli/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- mysqli_driver.php
    |       |   |   |   |   |-- mysqli_forge.php
    |       |   |   |   |   |-- mysqli_result.php
    |       |   |   |   |   `-- mysqli_utility.php
    |       |   |   |   |-- oci8/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- oci8_driver.php
    |       |   |   |   |   |-- oci8_forge.php
    |       |   |   |   |   |-- oci8_result.php
    |       |   |   |   |   `-- oci8_utility.php
    |       |   |   |   |-- odbc/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- odbc_driver.php
    |       |   |   |   |   |-- odbc_forge.php
    |       |   |   |   |   |-- odbc_result.php
    |       |   |   |   |   `-- odbc_utility.php
    |       |   |   |   |-- pdo/
    |       |   |   |   |   |-- subdrivers/
    |       |   |   |   |   |   |-- index.html
    |       |   |   |   |   |   |-- pdo_4d_driver.php
    |       |   |   |   |   |   |-- pdo_4d_forge.php
    |       |   |   |   |   |   |-- pdo_cubrid_driver.php
    |       |   |   |   |   |   |-- pdo_cubrid_forge.php
    |       |   |   |   |   |   |-- pdo_dblib_driver.php
    |       |   |   |   |   |   |-- pdo_dblib_forge.php
    |       |   |   |   |   |   |-- pdo_firebird_driver.php
    |       |   |   |   |   |   |-- pdo_firebird_forge.php
    |       |   |   |   |   |   |-- pdo_ibm_driver.php
    |       |   |   |   |   |   |-- pdo_ibm_forge.php
    |       |   |   |   |   |   |-- pdo_informix_driver.php
    |       |   |   |   |   |   |-- pdo_informix_forge.php
    |       |   |   |   |   |   |-- pdo_mysql_driver.php
    |       |   |   |   |   |   |-- pdo_mysql_forge.php
    |       |   |   |   |   |   |-- pdo_oci_driver.php
    |       |   |   |   |   |   |-- pdo_oci_forge.php
    |       |   |   |   |   |   |-- pdo_odbc_driver.php
    |       |   |   |   |   |   |-- pdo_odbc_forge.php
    |       |   |   |   |   |   |-- pdo_pgsql_driver.php
    |       |   |   |   |   |   |-- pdo_pgsql_forge.php
    |       |   |   |   |   |   |-- pdo_sqlite_driver.php
    |       |   |   |   |   |   |-- pdo_sqlite_forge.php
    |       |   |   |   |   |   |-- pdo_sqlsrv_driver.php
    |       |   |   |   |   |   `-- pdo_sqlsrv_forge.php
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- pdo_driver.php
    |       |   |   |   |   |-- pdo_forge.php
    |       |   |   |   |   |-- pdo_result.php
    |       |   |   |   |   `-- pdo_utility.php
    |       |   |   |   |-- postgre/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- postgre_driver.php
    |       |   |   |   |   |-- postgre_forge.php
    |       |   |   |   |   |-- postgre_result.php
    |       |   |   |   |   `-- postgre_utility.php
    |       |   |   |   |-- sqlite/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- sqlite_driver.php
    |       |   |   |   |   |-- sqlite_forge.php
    |       |   |   |   |   |-- sqlite_result.php
    |       |   |   |   |   `-- sqlite_utility.php
    |       |   |   |   |-- sqlite3/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- sqlite3_driver.php
    |       |   |   |   |   |-- sqlite3_forge.php
    |       |   |   |   |   |-- sqlite3_result.php
    |       |   |   |   |   `-- sqlite3_utility.php
    |       |   |   |   |-- sqlsrv/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- sqlsrv_driver.php
    |       |   |   |   |   |-- sqlsrv_forge.php
    |       |   |   |   |   |-- sqlsrv_result.php
    |       |   |   |   |   `-- sqlsrv_utility.php
    |       |   |   |   `-- index.html
    |       |   |   |-- DB.php
    |       |   |   |-- DB_cache.php
    |       |   |   |-- DB_driver.php
    |       |   |   |-- DB_forge.php
    |       |   |   |-- DB_query_builder.php
    |       |   |   |-- DB_result.php
    |       |   |   |-- DB_utility.php
    |       |   |   `-- index.html
    |       |   |-- fonts/
    |       |   |   |-- index.html
    |       |   |   `-- texb.ttf
    |       |   |-- helpers/
    |       |   |   |-- array_helper.php
    |       |   |   |-- captcha_helper.php
    |       |   |   |-- cookie_helper.php
    |       |   |   |-- date_helper.php
    |       |   |   |-- directory_helper.php
    |       |   |   |-- download_helper.php
    |       |   |   |-- email_helper.php
    |       |   |   |-- file_helper.php
    |       |   |   |-- form_helper.php
    |       |   |   |-- html_helper.php
    |       |   |   |-- index.html
    |       |   |   |-- inflector_helper.php
    |       |   |   |-- language_helper.php
    |       |   |   |-- number_helper.php
    |       |   |   |-- path_helper.php
    |       |   |   |-- security_helper.php
    |       |   |   |-- smiley_helper.php
    |       |   |   |-- string_helper.php
    |       |   |   |-- text_helper.php
    |       |   |   |-- typography_helper.php
    |       |   |   |-- url_helper.php
    |       |   |   `-- xml_helper.php
    |       |   |-- language/
    |       |   |   |-- english/
    |       |   |   |   |-- calendar_lang.php
    |       |   |   |   |-- date_lang.php
    |       |   |   |   |-- db_lang.php
    |       |   |   |   |-- email_lang.php
    |       |   |   |   |-- form_validation_lang.php
    |       |   |   |   |-- ftp_lang.php
    |       |   |   |   |-- imglib_lang.php
    |       |   |   |   |-- index.html
    |       |   |   |   |-- migration_lang.php
    |       |   |   |   |-- number_lang.php
    |       |   |   |   |-- pagination_lang.php
    |       |   |   |   |-- profiler_lang.php
    |       |   |   |   |-- unit_test_lang.php
    |       |   |   |   `-- upload_lang.php
    |       |   |   `-- index.html
    |       |   |-- libraries/
    |       |   |   |-- Cache/
    |       |   |   |   |-- drivers/
    |       |   |   |   |   |-- Cache_apc.php
    |       |   |   |   |   |-- Cache_dummy.php
    |       |   |   |   |   |-- Cache_file.php
    |       |   |   |   |   |-- Cache_memcached.php
    |       |   |   |   |   |-- Cache_redis.php
    |       |   |   |   |   |-- Cache_wincache.php
    |       |   |   |   |   `-- index.html
    |       |   |   |   |-- Cache.php
    |       |   |   |   `-- index.html
    |       |   |   |-- Javascript/
    |       |   |   |   |-- index.html
    |       |   |   |   `-- Jquery.php
    |       |   |   |-- Session/
    |       |   |   |   |-- drivers/
    |       |   |   |   |   |-- index.html
    |       |   |   |   |   |-- Session_database_driver.php
    |       |   |   |   |   |-- Session_files_driver.php
    |       |   |   |   |   |-- Session_memcached_driver.php
    |       |   |   |   |   `-- Session_redis_driver.php
    |       |   |   |   |-- CI_Session_driver_interface.php
    |       |   |   |   |-- index.html
    |       |   |   |   |-- OldSessionWrapper.php
    |       |   |   |   |-- PHP8SessionWrapper.php
    |       |   |   |   |-- Session.php
    |       |   |   |   |-- Session_driver.php
    |       |   |   |   |-- SessionHandlerInterface.php
    |       |   |   |   `-- SessionUpdateTimestampHandlerInterface.php
    |       |   |   |-- Calendar.php
    |       |   |   |-- Cart.php
    |       |   |   |-- Driver.php
    |       |   |   |-- Email.php
    |       |   |   |-- Encrypt.php
    |       |   |   |-- Encryption.php
    |       |   |   |-- Form_validation.php
    |       |   |   |-- Ftp.php
    |       |   |   |-- Image_lib.php
    |       |   |   |-- index.html
    |       |   |   |-- Javascript.php
    |       |   |   |-- Migration.php
    |       |   |   |-- Pagination.php
    |       |   |   |-- Parser.php
    |       |   |   |-- Profiler.php
    |       |   |   |-- Table.php
    |       |   |   |-- Trackback.php
    |       |   |   |-- Typography.php
    |       |   |   |-- Unit_test.php
    |       |   |   |-- Upload.php
    |       |   |   |-- User_agent.php
    |       |   |   |-- Xmlrpc.php
    |       |   |   |-- Xmlrpcs.php
    |       |   |   `-- Zip.php
    |       |   |-- .htaccess
    |       |   `-- index.html
    |       |-- .editorconfig
    |       |-- .gitignore
    |       |-- composer.json
    |       |-- index.php
    |       |-- license.txt
    |       `-- readme.rst
    |-- composer/
    |   |-- autoload_classmap.php
    |   |-- autoload_namespaces.php
    |   |-- autoload_psr4.php
    |   |-- autoload_real.php
    |   |-- autoload_static.php
    |   |-- ClassLoader.php
    |   |-- installed.json
    |   |-- installed.php
    |   |-- InstalledVersions.php
    |   |-- LICENSE
    |   `-- platform_check.php
    |-- setasign/
    |   |-- fpdf/
    |   |   |-- doc/
    |   |   |   |-- __construct.htm
    |   |   |   |-- acceptpagebreak.htm
    |   |   |   |-- addfont.htm
    |   |   |   |-- addlink.htm
    |   |   |   |-- addpage.htm
    |   |   |   |-- aliasnbpages.htm
    |   |   |   |-- cell.htm
    |   |   |   |-- close.htm
    |   |   |   |-- error.htm
    |   |   |   |-- footer.htm
    |   |   |   |-- getpageheight.htm
    |   |   |   |-- getpagewidth.htm
    |   |   |   |-- getstringwidth.htm
    |   |   |   |-- getx.htm
    |   |   |   |-- gety.htm
    |   |   |   |-- header.htm
    |   |   |   |-- image.htm
    |   |   |   |-- index.htm
    |   |   |   |-- line.htm
    |   |   |   |-- link.htm
    |   |   |   |-- ln.htm
    |   |   |   |-- multicell.htm
    |   |   |   |-- output.htm
    |   |   |   |-- pageno.htm
    |   |   |   |-- rect.htm
    |   |   |   |-- setauthor.htm
    |   |   |   |-- setautopagebreak.htm
    |   |   |   |-- setcompression.htm
    |   |   |   |-- setcreator.htm
    |   |   |   |-- setdisplaymode.htm
    |   |   |   |-- setdrawcolor.htm
    |   |   |   |-- setfillcolor.htm
    |   |   |   |-- setfont.htm
    |   |   |   |-- setfontsize.htm
    |   |   |   |-- setkeywords.htm
    |   |   |   |-- setleftmargin.htm
    |   |   |   |-- setlinewidth.htm
    |   |   |   |-- setlink.htm
    |   |   |   |-- setmargins.htm
    |   |   |   |-- setrightmargin.htm
    |   |   |   |-- setsubject.htm
    |   |   |   |-- settextcolor.htm
    |   |   |   |-- settitle.htm
    |   |   |   |-- settopmargin.htm
    |   |   |   |-- setx.htm
    |   |   |   |-- setxy.htm
    |   |   |   |-- sety.htm
    |   |   |   |-- text.htm
    |   |   |   `-- write.htm
    |   |   |-- font/
    |   |   |   |-- courier.php
    |   |   |   |-- courierb.php
    |   |   |   |-- courierbi.php
    |   |   |   |-- courieri.php
    |   |   |   |-- helvetica.php
    |   |   |   |-- helveticab.php
    |   |   |   |-- helveticabi.php
    |   |   |   |-- helveticai.php
    |   |   |   |-- symbol.php
    |   |   |   |-- times.php
    |   |   |   |-- timesb.php
    |   |   |   |-- timesbi.php
    |   |   |   |-- timesi.php
    |   |   |   `-- zapfdingbats.php
    |   |   |-- makefont/
    |   |   |   |-- cp1250.map
    |   |   |   |-- cp1251.map
    |   |   |   |-- cp1252.map
    |   |   |   |-- cp1253.map
    |   |   |   |-- cp1254.map
    |   |   |   |-- cp1255.map
    |   |   |   |-- cp1257.map
    |   |   |   |-- cp1258.map
    |   |   |   |-- cp874.map
    |   |   |   |-- iso-8859-1.map
    |   |   |   |-- iso-8859-11.map
    |   |   |   |-- iso-8859-15.map
    |   |   |   |-- iso-8859-16.map
    |   |   |   |-- iso-8859-2.map
    |   |   |   |-- iso-8859-4.map
    |   |   |   |-- iso-8859-5.map
    |   |   |   |-- iso-8859-7.map
    |   |   |   |-- iso-8859-9.map
    |   |   |   |-- koi8-r.map
    |   |   |   |-- koi8-u.map
    |   |   |   |-- makefont.php
    |   |   |   `-- ttfparser.php
    |   |   |-- tutorial/
    |   |   |   |-- 20k_c1.txt
    |   |   |   |-- 20k_c2.txt
    |   |   |   |-- calligra.php
    |   |   |   |-- calligra.ttf
    |   |   |   |-- calligra.z
    |   |   |   |-- countries.txt
    |   |   |   |-- index.htm
    |   |   |   |-- logo.png
    |   |   |   |-- makefont.php
    |   |   |   |-- tuto1.htm
    |   |   |   |-- tuto1.php
    |   |   |   |-- tuto2.htm
    |   |   |   |-- tuto2.php
    |   |   |   |-- tuto3.htm
    |   |   |   |-- tuto3.php
    |   |   |   |-- tuto4.htm
    |   |   |   |-- tuto4.php
    |   |   |   |-- tuto5.htm
    |   |   |   |-- tuto5.php
    |   |   |   |-- tuto6.htm
    |   |   |   |-- tuto6.php
    |   |   |   |-- tuto7.htm
    |   |   |   `-- tuto7.php
    |   |   |-- changelog.htm
    |   |   |-- composer.json
    |   |   |-- FAQ.htm
    |   |   |-- fpdf.css
    |   |   |-- fpdf.php
    |   |   |-- install.txt
    |   |   |-- license.txt
    |   |   `-- README.md
    |   `-- fpdi/
    |       |-- src/
    |       |   |-- Math/
    |       |   |   |-- Matrix.php
    |       |   |   `-- Vector.php
    |       |   |-- PdfParser/
    |       |   |   |-- CrossReference/
    |       |   |   |   |-- AbstractReader.php
    |       |   |   |   |-- CrossReference.php
    |       |   |   |   |-- CrossReferenceException.php
    |       |   |   |   |-- FixedReader.php
    |       |   |   |   |-- LineReader.php
    |       |   |   |   `-- ReaderInterface.php
    |       |   |   |-- Filter/
    |       |   |   |   |-- Ascii85.php
    |       |   |   |   |-- Ascii85Exception.php
    |       |   |   |   |-- AsciiHex.php
    |       |   |   |   |-- FilterException.php
    |       |   |   |   |-- FilterInterface.php
    |       |   |   |   |-- Flate.php
    |       |   |   |   |-- FlateException.php
    |       |   |   |   |-- Lzw.php
    |       |   |   |   `-- LzwException.php
    |       |   |   |-- Type/
    |       |   |   |   |-- PdfArray.php
    |       |   |   |   |-- PdfBoolean.php
    |       |   |   |   |-- PdfDictionary.php
    |       |   |   |   |-- PdfHexString.php
    |       |   |   |   |-- PdfIndirectObject.php
    |       |   |   |   |-- PdfIndirectObjectReference.php
    |       |   |   |   |-- PdfName.php
    |       |   |   |   |-- PdfNull.php
    |       |   |   |   |-- PdfNumeric.php
    |       |   |   |   |-- PdfStream.php
    |       |   |   |   |-- PdfString.php
    |       |   |   |   |-- PdfToken.php
    |       |   |   |   |-- PdfType.php
    |       |   |   |   `-- PdfTypeException.php
    |       |   |   |-- PdfParser.php
    |       |   |   |-- PdfParserException.php
    |       |   |   |-- StreamReader.php
    |       |   |   `-- Tokenizer.php
    |       |   |-- PdfReader/
    |       |   |   |-- DataStructure/
    |       |   |   |   `-- Rectangle.php
    |       |   |   |-- Page.php
    |       |   |   |-- PageBoundaries.php
    |       |   |   |-- PdfReader.php
    |       |   |   `-- PdfReaderException.php
    |       |   |-- Tcpdf/
    |       |   |   `-- Fpdi.php
    |       |   |-- Tfpdf/
    |       |   |   |-- FpdfTpl.php
    |       |   |   `-- Fpdi.php
    |       |   |-- autoload.php
    |       |   |-- FpdfTpl.php
    |       |   |-- FpdfTplTrait.php
    |       |   |-- FpdfTrait.php
    |       |   |-- Fpdi.php
    |       |   |-- FpdiException.php
    |       |   |-- FpdiTrait.php
    |       |   |-- GraphicsState.php
    |       |   `-- TcpdfFpdi.php
    |       |-- composer.json
    |       |-- LICENSE.txt
    |       |-- README.md
    |       `-- SECURITY.md
    `-- autoload.php
```

## Verification and maintenance connections

- `tests/run_unit.sh`: tracked PHP/JS syntax plus independent PHP/Node regressions. On Windows use PHP and Node directly when Bash is unavailable.
- `tests/browser_document_workflows.py`: creates its own test database and temporary app copy; flags select focused checks. It never imports fixtures into the company database.
- `PK_BROWSER_ISSUES_ONLY=1`: category assignment/audit/native review/setup ZIP checks.
- `PK_BROWSER_REFERENCE_UI_ONLY=1`: Request Reference across requests/tasks/notifications and hidden Copy Number preservation during edits and request saves.
- `PK_BROWSER_VIEWERS_ONLY=1`: ten shared viewer routes at 1440/390/320 pixels with CDN resources blocked.
- `PK_BROWSER_USER_ASSIGNMENTS_ONLY=1`: assigned-document tabs/search and return to the selected user's assignment form.
- `tests/apache_root_urls.py`: real Apache clean route, legacy redirect, static asset, manifest and private-path checks without a database.
- `tests/office_review.php` and `tests/office_opener.ps1`: ticket signatures/expiry and native URI constraints.
- `tools/sync_local_xampp.ps1`: after clean commits, fast-forward the separate served checkout and verify actual HTTP asset bytes. It refuses dirty or divergent checkouts.
- `tools/office-opener/install.ps1 -BaseUrl http://localhost/pk_document_tracking_system/`: install the per-user WPS handler; `-Uninstall` unregisters it. Other workstations use the setup ZIP from Review File.
- `tools/backup.php` / `tools/install_backup_schedule.ps1`: backup and retention maintenance. Keep runtime data outside public routes.

To update this map after adding/moving files, refresh the project tree from `git ls-files --cached --others --exclude-standard`; refresh the dependency inventory only after Composer changes. Update the feature ownership table when a route, service, model, template or shared asset changes responsibility.
