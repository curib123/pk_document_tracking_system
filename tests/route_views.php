<?php
$root=dirname(__DIR__).'/application/view/';
$views=['pages/request/user_own_all_request/index.php','pages/request/approving_assign_request/index.php',
    'pages/documents/shared_index.php','pages/places/shared_index.php'];
foreach ($views as $view) if (!is_file($root.$view) || !filesize($root.$view)) throw new RuntimeException('Missing required page view: '.$view);
echo "Required route views exist.\n";
