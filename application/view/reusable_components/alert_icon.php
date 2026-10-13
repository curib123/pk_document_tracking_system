<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <?php if (($alert_kind??'info')==='warning'): ?>
        <path d="M10.3 3.5a2 2 0 0 1 3.4 0l8 14a2 2 0 0 1-1.7 3H4a2 2 0 0 1-1.7-3z" />
        <path d="M12 9v4m0 4h.01" />
    <?php else: ?>
        <circle cx="12" cy="12" r="9" />
        <?php if (($alert_kind??'info')==='success'): ?>
            <path d="m8 12 2.5 2.5L16 9" />
        <?php elseif (($alert_kind??'info')==='danger'): ?>
            <path d="M12 8v5m0 3h.01" />
        <?php else: ?>
            <path d="M12 11v5m0-8h.01" />
        <?php endif; ?>
    <?php endif; ?>
</svg>
