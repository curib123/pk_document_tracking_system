Approval task alerts use one durable receipt per user, step, and activation. Apply
`database/migrations/20261013_task_notification_receipts.sql` to an installed
database before enabling the alerts. It is safe to rerun and does not edit requests,
decisions, users, or existing notification records.

Each activation increments `workflow_steps.version`, including reactivation after
a return. An alert therefore appears once for that approval occurrence, even
after refresh, logout/login, or access from another browser. Direct assignments
and all active assigned roles count; Administrator access alone is not assignment.

The signed-in UI checks for new tasks every 15 seconds while visible. Alerts wait
until other modals, login/logout transitions, and form submissions are finished.
The CSRF-protected claim saves a receipt only when the UI is ready to present the
modal; an atomic unique key prevents competing tabs from presenting it twice.
An unseen task that was already decided is omitted. Pending badges remain visible
after an alert has been shown, until the workflow task is actually decided.

Verify the persistence and workflow transitions with `php tests/task_notifications.php`.
Its writes use temporary tables and do not change installed requests or receipts.
