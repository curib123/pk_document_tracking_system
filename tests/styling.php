<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root . '/application/helpers/styling_helper.php')) {
    fwrite(STDERR, "FAIL: centralized styling resolver is missing\n");
    exit(1);
}
require $root . '/application/helpers/styling_helper.php';
$checks = 0;
function styling_check(bool $ok, string $name): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($name); }
    ++$checks;
    echo "PASS $name\n";
}
putenv('PK_STYLING_ENABLED');
$config = pk_styling_config();
styling_check($config['enabled'] === true, 'red design is enabled by default');
styling_check(pk_module_styled($config, 'hardcopy'), 'known module is styled');
styling_check(!pk_module_styled($config, 'unknown_module'), 'unknown module remains plain');
$config['modules']['hardcopy'] = false;
styling_check(!pk_module_styled($config, 'hardcopy'), 'per-module false disables design');
styling_check(pk_module_styled($config, 'softcopy'), 'neighbor module stays enabled');
$config['modules']['hardcopy'] = 'false';
styling_check(!pk_module_styled($config, 'hardcopy'), 'truthy string does not enable design');
$config['enabled'] = false;
styling_check(!pk_module_styled($config, 'softcopy'), 'master switch overrides enabled module');
putenv('PK_STYLING_ENABLED=false');
styling_check(pk_styling_config()['enabled'] === false, 'environment false disables all styling');
putenv('PK_STYLING_ENABLED=1');
styling_check(pk_styling_config()['enabled'] === true, 'environment true enables configured design');
putenv('PK_STYLING_ENABLED=not-a-boolean');
styling_check(pk_styling_config()['enabled'] === false, 'invalid environment override fails closed');
putenv('PK_STYLING_ENABLED');
$config = pk_styling_config();
styling_check(count($config['modules']) >= 26, 'all existing modules and account are explicitly configured');
echo "$checks styling configuration checks passed.\n";
