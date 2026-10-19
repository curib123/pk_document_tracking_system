<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Review File · PK DTS</title>
  <link rel="stylesheet" href="<?= base_url('assets/css/office-review.css') ?>">
</head>
<body>
  <main class="review-card">
    <p class="eyebrow">PK DOCUMENT CONTROL</p>
    <h1>Review File</h1>
    <p class="filename"><?= html_escape($file['original_name']) ?></p>
    <p>Choose an installed application to review this copy. Allow your browser to open the app when prompted.</p>
    <div class="review-actions">
      <a class="primary" href="<?= html_escape($office_url) ?>">Open in <?= html_escape($app) ?></a>
      <a href="<?= html_escape($wps_url) ?>">Open in WPS</a>
      <a href="<?= site_url('files/review/' . $request_id) ?>?download=1">Download</a>
    </div>
    <p class="hint">App links expire after 10 minutes. Refresh this page to renew them. Changes to a review copy do not update the system document.</p>
    <details><summary>Opening WPS for the first time?</summary>
      <p>Install the PK DTS opener once on your Windows computer. Download and extract the setup package, then follow its README instructions.</p>
      <a href="<?= site_url('files/office-setup') ?>">Download WPS opener setup</a>
    </details>
    <p class="hint">If the app cannot open this file, use Download and open the saved file in Word, Excel, or WPS.</p>
  </main>
</body>
</html>
