<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$cases = [];
function test(string $name, callable $fn): void { global $cases; $cases[$name] = $fn; }
function ok(bool $condition, string $message = 'Assertion failed'): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $fn): void { try { $fn(); } catch (Pk\Core\Problem $e) { return; } throw new RuntimeException('Expected validation rejection'); }
require_once $root . '/application/bootstrap.php';
test('validation rejects missing required values', fn() => rejects(fn() => Pk\Core\Rules::text([], 'title')));
test('valid date is preserved', fn() => ok(Pk\Core\Rules::date(['date'=>'2026-10-06'], 'date') === '2026-10-06'));
test('invalid calendar date rejected', fn() => rejects(fn() => Pk\Core\Rules::date(['date'=>'2026-02-30'], 'date')));
test('password minimum and confirmation enforced', fn() => rejects(fn() => Pk\Core\Rules::password('short', 'short')));
test('boolean false stays false', fn() => ok(Pk\Core\Rules::boolean('false') === 0));
test('boolean true stays true', fn() => ok(Pk\Core\Rules::boolean('1') === 1));
test('valid graph accepted', fn() => ok(count(Pk\Core\WorkflowGraph::validate(Pk\Core\WorkflowGraph::defaults())['nodes']) === 5));
test('workflow cannot bypass approval', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][0]['next']='done'; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('workflow missing target rejected', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][1]['approve']='missing'; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('workflow cycles rejected', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][1]['approve']='review'; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('workflow unsupported assignment rejected', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][1]['assignment']['type']='everyone'; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('conditions compare values without evaluating code', fn()=>ok(Pk\Core\WorkflowGraph::condition(['field'=>'amount','operator'=>'gte','value'=>10], ['amount'=>11])));
test('unknown condition operation rejected', fn()=>rejects(fn()=>Pk\Core\WorkflowGraph::condition(['field'=>'x','operator'=>'eval','value'=>'phpinfo()'], [])));
test('PHP uploads rejected', fn()=>rejects(fn()=>Pk\Core\Rules::fileType('shell.php', 'text/plain')));
test('extension MIME mismatch rejected', fn()=>rejects(fn()=>Pk\Core\Rules::fileType('proof.pdf', 'image/png')));
test('PDF uploads accepted', fn()=>ok(Pk\Core\Rules::fileType('proof.pdf', 'application/pdf')==='pdf'));
test('bounded pagination', fn()=>ok(Pk\Core\Rules::page(['page'=>-1,'limit'=>900]) === [1,100]));
test('condition values must be scalar', fn()=>rejects(fn()=>Pk\Core\WorkflowGraph::condition(['field'=>'x','operator'=>'eq','value'=>[]], ['x'=>[]])));
test('state changes require POST', fn()=>rejects(fn()=>Pk\Core\Security::method('GET',true)));
test('read operations require GET', fn()=>rejects(fn()=>Pk\Core\Security::method('POST',false)));
test('CSRF rejects absent and mismatched tokens', fn()=>rejects(fn()=>Pk\Core\Security::csrf('expected','incorrect')));
test('CSRF accepts matching session token', fn()=>ok(Pk\Core\Security::csrf(str_repeat('a',64),str_repeat('a',64))===true));
test('session expired after thirty idle minutes', fn()=>ok(Pk\Core\Security::expired(['last_seen'=>100],1901)));
test('recent authenticated session not expired', fn()=>ok(!Pk\Core\Security::expired(['last_seen'=>100],1000)));
test('array node key rejected as validation error', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][0]['key']=[]; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('array start key rejected as validation error', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['start']=[]; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
test('nonobject approver assignment rejected', function () { $g=Pk\Core\WorkflowGraph::defaults(); $g['nodes'][1]['assignment']='wrong'; rejects(fn()=>Pk\Core\WorkflowGraph::validate($g)); });
$failed=0;
foreach ($cases as $name=>$fn) { try { $fn(); echo "PASS $name\n"; } catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; } }
echo count($cases)." tests; $failed failures\n";
exit($failed ? 1 : 0);
