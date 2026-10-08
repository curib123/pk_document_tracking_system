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
$deployment = pk_styling_config();
styling_check(is_bool($deployment['enabled']), 'deployment styling switch is boolean');
// Test the resolver independently of the users editable deployment choices.
$config = ['enabled' => true, 'default_enabled' => false,
    'modules' => ['hardcopy' => true, 'softcopy' => true]];
styling_check(pk_module_styled($config, 'hardcopy'), 'enabled module is styled');
styling_check(!pk_module_styled($config, 'unknown_module'), 'unknown module follows disabled default');
$config['modules']['hardcopy'] = false;
styling_check(!pk_module_styled($config, 'hardcopy'), 'per-module false disables design');
styling_check(pk_module_styled($config, 'softcopy'), 'neighbor module stays enabled');
$config['modules']['hardcopy'] = 'false';
styling_check(!pk_module_styled($config, 'hardcopy'), 'truthy string does not enable design');
$config['default_enabled'] = true;
styling_check(pk_module_styled($config, 'unknown_module'), 'unknown module follows enabled default');
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
styling_check($config === $deployment, 'test leaves deployment configuration unchanged');
styling_check(count($config['modules']) >= 26, 'all existing modules and account are explicitly configured');
echo "$checks styling configuration checks passed.\n";
