# ISSUE 013 — Print action opens a browser preview; printing is not automatically invoked

**Severity:** Low–Medium (user-experience accuracy)  
**Type:** Confirmed feature limitation  
**Status:** Proposed fix only; NOT applied

## Evidence
- The Print action in application/view/reusable_components/record_actions.php links to files/print/:id in a new tab.
- Files::print_file() returns an inline PDF/JPG/PNG/TXT HTTP response, but no HTML/JS invokes window.print().
- Browser-native PDF viewers typically provide a Print control; images and TXT may simply display without automatically opening a print dialog.
- DOCX/XLSX intentionally return 415, requiring a native office application.

## Copyable low-risk UI correction
If native preview is the intended behavior, change the branch in application/view/reusable_components/record_actions.php to accurately label the action:

~~~php
<?php elseif ($type==='print'): ?>
    <a class="btn-icon record-action record-action-print"
       href="<?= site_url($button['url']) ?>"
       target="_blank" rel="noopener"
       title="Open print preview" aria-label="Open print preview">
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        <span>Print Preview</span>
    </a>
~~~

For genuine one-click printing, add a separate **same-origin HTML print viewer** with a protected token/authorized file response, render image/text into a print-specific HTML document and call print() after the resource has fully loaded. PDF print invocation varies by browser; do not assume that scripting window.print() on a PDF blob will behave identically across Chrome, Edge and Firefox.

## Verify
Open a permitted PDF, image and TXT from desktop and Android browsers. Confirm preview works, the Print control is discoverable, and blocked users cannot access file bytes. Test blocked/superseded controlled files and DOCX/XLSX fallback. Keep Content-Security-Policy protections.
