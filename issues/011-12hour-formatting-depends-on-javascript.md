# ISSUE 011 — 12-hour timestamps are converted only after rendering, not at the presentation source

**Severity:** Medium (UI accuracy/accessibility)  
**Type:** Confirmed implementation gap  
**Status:** Proposed fix only; NOT applied

## Evidence
- public/assets/js/plan-controls.js traverses text nodes under a hardcoded CSS selector list and rewrites YYYY-MM-DD HH:MM(:SS) to AM/PM.
- Server views still print raw SQL timestamps. If JavaScript is blocked, fails or a text node lives outside the covered selectors, 24-hour timestamps remain.
- A body-wide MutationObserver watches all subtree changes. On heavy tables/modals, repeated traversal can be costly.

## Copyable server-side formatter
Create application/services/presentation/display_datetime.php:

~~~php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

final class Display_datetime
{
    public static function manila($value): string
    {
        if (!is_string($value) || trim($value)==='') return '—';
        try {
            // Existing DATETIME fields are assumed to be Manila-local
            // only if that is how the configured DB connection stores them.
            $date = new DateTimeImmutable(
                $value, new DateTimeZone('Asia/Manila')
            );
            return $date->format('M j, Y g:i A');
        } catch (Throwable $error) {
            return '—';
        }
    }
}
~~~

Then, in a view rendering a known timestamp field, use:

~~~php
<?php require_once APPPATH.'services/presentation/display_datetime.php'; ?>
<?= html_escape(Display_datetime::manila($row['updated_at']??NULL)) ?>
~~~

Use a UTC source timezone instead if production DB timestamps are stored as UTC; confirm this first. Replace one known timestamp presentation at a time and only remove its DOM-formatting dependency after all affected views are converted.

## Verify
Disable JavaScript and inspect document/request dates, workflow history, dashboard, notifications and backup page. All displayed timestamps should still use AM/PM where required. Test 00:00, 12:00, 23:59 and date crossings. Make sure server-side filtering continues using machine-readable YYYY-MM-DD and is NOT affected by display formatting.
