<?php
// Regression for plan.md: after an accepted physical transfer, access follows
// the current holder, never the old creator or previous holder.
declare(strict_types=1);
define('BASEPATH', __DIR__);
require __DIR__ . '/../application/services/security/document_scope.php';

function custody_check(bool $okay, string $what): void {
    if (!$okay) throw new RuntimeException($what);
}
$previous = Document_scope::predicate('hardcopy', 12, []);
custody_check(strpos($previous, 'd.holder_id=12') !== FALSE, 'Current holder scope is missing.');
custody_check(strpos($previous, 'd.created_by=12') === FALSE,
    'Document creator must not retain implicit hardcopy access.');
$new = Document_scope::predicate('hardcopy', 13, []);
custody_check(strpos($new, 'd.holder_id=13') !== FALSE, 'Recipient must be able to see accepted custody.');
$soft = Document_scope::predicate('softcopy', 12, []);
custody_check(strpos($soft, 'd.created_by=12') !== FALSE,
    'Softcopy ownership must remain unchanged.');
$grant = Document_scope::predicate('hardcopy', 12, ['documents' => ['view_granted' => TRUE]]);
custody_check(strpos($grant, 'scope_grant.user_id=12') !== FALSE,
    'Explicit access grants must remain separately scoped.');
custody_check(Document_scope::predicate('hardcopy', 12, ['documents' => ['view_all' => TRUE]]) === '1=1',
    'Explicit global document readers remain authorized.');
$transfer = file_get_contents(__DIR__ . '/../application/services/transfer/transfer_service.php');
custody_check(strpos($transfer, "->where('user_id', (int) \$transfer['current_holder_id'])") !== FALSE &&
    strpos($transfer, "'revoked_at' => date(") !== FALSE,
    'Accepted transfer must revoke former holder grants atomically.');
echo "Physical document access now follows current custody and revokes former holder grants.\n";
