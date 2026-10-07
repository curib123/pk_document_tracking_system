<?php
// Compatibility entry for render-only tooling. Web controllers render the same partials.
if (!defined('PK_ROOT')) require dirname(__DIR__).'/bootstrap.php';
require_once PK_ROOT.'/application/helpers/ui_helper.php';
require __DIR__.'/templates/header.php';
require __DIR__.'/modules/index.php';
require __DIR__.'/templates/footer.php';
