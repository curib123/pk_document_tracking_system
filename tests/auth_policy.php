<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/services/authentication/authentication_service.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, $message.PHP_EOL); exit(1); } }
check(class_exists('Authentication_service'), 'Missing server-side authentication policy.');
$seen = [];
for ($i = 0; $i < 100; $i++) {
    $password = Authentication_service::temporary_password();
    check(strlen($password) >= 24 && !isset($seen[$password]), 'Temporary passwords must be long, independently generated values.');
    $seen[$password] = true;
}
foreach ([['Auth','setup'],['auth','change_password'],['AUTH','logout']] as $route) {
    check(Authentication_service::is_setup_action(...$route), 'Required onboarding route was blocked.');
}
foreach ([['Dashboard','index'],['Requests','tasks'],['Files','download'],['Other','change_password'],['Auth','login']] as $route) {
    check(!Authentication_service::is_setup_action(...$route), 'Protected route was incorrectly allowlisted.');
}
$cases = [
    ['OldTemporaryPassword!', 'NewPermanentPassword2026!', 'NewPermanentPassword2026!', true],
    ['SamePassword2026!', 'SamePassword2026!', 'SamePassword2026!', false],
    ['OldTemporaryPassword!', 'short', 'short', false],
    ['OldTemporaryPassword!', 'NewPermanentPassword2026!', 'MismatchPassword2026!', false],
    ['OldTemporaryPassword!', str_repeat('x',73), str_repeat('x',73), false],
];
foreach ($cases as [$old,$new,$confirmation,$valid]) {
    $accepted = true;
    try { Authentication_service::validate_new_password($old,$new,$confirmation); }
    catch (DomainException $e) { $accepted = false; }
    check($accepted === $valid, 'Password validation accepted an invalid value or rejected a valid value.');
}
echo "Authentication policy passed (100 generated credentials, 8 route gates, 5 validation cases).\n";
