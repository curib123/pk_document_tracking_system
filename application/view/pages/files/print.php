<?php $mime = (string) $file['mime_type'];
$printable = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], TRUE); ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print · <?= html_escape($file['original_name']) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/controlled-print.css') ?>">
    <script src="<?= base_url('assets/js/controlled-print.js') ?>" defer></script>
</head>

<body data-print-kind="<?= $mime === 'application/pdf' ? 'pdf' : ($printable ? 'page' : 'office') ?>">
    <header class="print-toolbar">
        <div><small>CONTROLLED FILE</small>
            <h1><?= html_escape($file['original_name']) ?></h1>
        </div>
        <div class="print-actions">
            <?php if ($printable): ?><button type="button" id="printButton" disabled>Print</button><?php endif; ?>
            <a href="<?= site_url('files/download/' . (int) $file['id']) ?>">Download</a>
        </div>
    </header>
    <p id="printStatus" role="status" aria-live="polite">
        <?= $printable ? 'Preparing print preview…' : 'Download this Office file and use Print in Word or Excel.' ?></p>
    <main>
        <?php if ($mime === 'text/plain'): ?>
            <pre class="print-text"><?= html_escape($text) ?></pre>
        <?php elseif (in_array($mime, ['image/jpeg', 'image/png'], TRUE)): ?>
            <img id="printImage" class="print-image" src="<?= site_url('files/print-content/' . (int) $file['id']) ?>"
                alt="<?= html_escape($file['original_name']) ?>">
        <?php elseif ($mime === 'application/pdf'): ?>
            <iframe id="printFrame" title="PDF print preview"
                src="<?= site_url('files/print-content/' . (int) $file['id']) ?>"></iframe>
        <?php else: ?>
            <div class="office-help">
                <h2>Print with your Office application</h2>
                <p>Open the downloaded file, choose File → Print, then select your printer.</p>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>