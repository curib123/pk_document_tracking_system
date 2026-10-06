# Functional PK Document Tracking System

Source of truth: the repository's `# CodeIgniter 3 – Document Tracking Syst.md` at ddc9f418a46f9eaacea4872e7515fa1a1616a83a.

Build CodeIgniter 3 / MySQL / server-side PHP MVC with thin controllers and service-based rules. The current user request overrides Bootstrap, custom CSS, colors and visual theming: use semantic HTML and one native-dialog interaction system, with no stylesheet, CSS framework or design pass. Search, pagination and module navigation remain ordinary navigation; all record operations, details, approvals, confirmations, file operations, account actions and settings run through dialogs.

Preserve separate softcopy/hardcopy tables; requests are processes, not documents. Enforce published workflow snapshots, append-only trails, bounded condition routing, explicit assignment, correction/resubmission and recipient acceptance. Direct creation is a capability initially granted only to Administrator and Document Control Officer. Every mutation checks permissions and optimistic concurrency inside a transaction. Files are private and MIME/extension/size checked. Accounts are administrator-created and require first-login password change.

Implementation decisions: PHP 8.2+; CI3 3.1.13 installed through Composer, MySQL 8.0+ (MariaDB compatibility not verified); plain external JavaScript instead of jQuery/DataTables to remove runtime/CDN dependencies. The reusable table retains server-side search, paging and ordering. Theme values are stored for future use but intentionally not styled. PDF artifact stamping uses FPDI/FPDF; optional LibreOffice conversion is explicitly configured for DOCX/XLSX. No inventory, registration, chat, public upload or speculative online service.
