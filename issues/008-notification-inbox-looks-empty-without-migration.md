# ISSUE 008 — Missing receipts migration is silently displayed as an empty notification inbox

**Severity:** High (missed approval work after deployment)  
**Type:** Confirmed missing-migration failure mode  
**Status:** Proposed fix only; NOT applied

## Evidence
- Notification_model::unread_page() checks table_exists('task_notification_receipts') and returns 0 unread items if the table is absent.
- The bell then displays 0 even though workflow steps may require approval; users are not told notifications are unavailable.
- The migration file exists at database/migrations/20261013_task_notification_receipts.sql, but Git deployment does not run it on your XAMPP database.

## Copyable fix
In application/controllers/Notifications.php::inbox(), BEFORE calling unread_page(), insert:

~~~php
if (!$this->db->table_exists('task_notification_receipts')) {
    log_message('error',
        'Notification receipts migration has not been installed.');
    return $this->output
        ->set_status_header(503)
        ->set_content_type('application/json')
        ->set_output(json_encode([
            'error'=>'Notifications unavailable; contact the system administrator.'
        ]));
}
~~~

In public/assets/js/notification-inbox.js, replace the silent response check:

~~~js
if (!response.ok) return;
~~~

inside the load() method with:

~~~js
if (!response.ok) {
    if (!panel.hidden) {
        list.replaceChildren();
        const row = document.createElement('li');
        row.className = 'notification-inbox-empty';
        row.textContent = 'Notifications unavailable. Contact your administrator.';
        list.append(row);
    }
    return;
}
~~~

Keep the authenticated user guard and CSRF protection for mark-read.

## Deployment and verification
On your own XAMPP installation first verify the table exists. If absent, back up MariaDB, then run the additive, rerunnable migration on a CLONE before production deployment:

~~~powershell
Get-Content -Raw "database/migrations/20261013_task_notification_receipts.sql" |
  & "C:/xampp/mysql/bin/mysql.exe" -u root -p pk_dts
~~~

Repeat the test with and without the table, and verify the UI distinguishes 'no unread work' from an unavailable notification system.
