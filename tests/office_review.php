<?php
define('BASEPATH', __DIR__);
$root = sys_get_temp_dir() . '/pk-office-test-' . bin2hex(random_bytes(6));
mkdir($root . '/storage', 0700, TRUE);
define('PK_ROOT', $root);
$path = __DIR__ . '/../application/services/files/office_review_ticket.php';
try {
    if (!is_file($path)) throw new RuntimeException('Office review tickets are missing.');
    require_once $path;
    $service = new Office_review_ticket();
    $ticket = $service->issue(['id'=>7,'session_version'=>3], 42, 9, 1000);
    $claims = $service->verify($ticket, 42, 1100);
    if ($claims['user_id'] !== 7 || $claims['file_id'] !== 9 || $claims['session_version'] !== 3)
        throw new RuntimeException('Review link is not bound to the reviewer and file.');
    foreach ([[$ticket,43,1100],[$ticket,42,1600],[substr($ticket,0,-1).'x',42,1100]] as [$value,$request,$now]) {
        try { $service->verify($value,$request,$now); throw new RuntimeException('Invalid review link was accepted.'); }
        catch (DomainException $expected) {}
    }
    echo "Office review signatures, request/file/reviewer binding, expiry and tamper rejection passed.\n";
} finally {
    foreach (glob($root . '/storage/*') as $file) unlink($file);
    if (is_file($root . '/storage/.office-review-key')) unlink($root . '/storage/.office-review-key');
    rmdir($root . '/storage'); rmdir($root);
}
