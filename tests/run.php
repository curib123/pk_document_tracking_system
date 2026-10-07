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
test('empty workflow draft accepted', fn() => ok(Pk\Core\WorkflowGraph::validate(Pk\Core\WorkflowGraph::defaults(),false)['steps'] === []));
test('published workflow requires an approval step', fn() => rejects(fn() => Pk\Core\WorkflowGraph::validate(Pk\Core\WorkflowGraph::defaults(),true)));
test('ordered workflow steps receive stable sequence keys', function () {
    $g=Pk\Core\WorkflowGraph::validate(['steps'=>[
        ['name'=>'Manager Review','approver'=>['type'=>'role','value'=>2]],
        ['name'=>'Final Review','approver'=>['type'=>'user','value'=>5]],
    ]],true);
    ok($g['steps'][0]['key']==='step_1' && $g['steps'][1]['key']==='step_2');
    ok(Pk\Core\WorkflowGraph::firstKey($g)==='step_1');
    ok(Pk\Core\WorkflowGraph::nextKey($g,'step_1')==='step_2');
    ok(Pk\Core\WorkflowGraph::nextKey($g,'step_2')===null);
});
test('specific user workflow approver accepted', fn() => ok(Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'User Review','approver'=>['type'=>'user','value'=>4]]]],true)['steps'][0]['approver']['value']===4));
test('role workflow approver accepted', fn() => ok(Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'Role Review','approver'=>['type'=>'role','value'=>3]]]],true)['steps'][0]['approver']['value']===3));
test('requester leader workflow approver accepted', fn() => ok(Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'Leader Review','approver'=>['type'=>'leader']]]],true)['steps'][0]['approver']['type']==='leader'));
test('requester workflow approver accepted', fn() => ok(Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'Requester Confirmation','approver'=>['type'=>'requester']]]],true)['steps'][0]['approver']['type']==='requester'));
test('unsupported workflow approver rejected', fn() => rejects(fn() => Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'Bad','approver'=>['type'=>'permission','value'=>'requests.approve']]]],true)));
test('specific user workflow approver requires a user', fn() => rejects(fn() => Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'User Review','approver'=>['type'=>'user']]]],true)));
test('PHP uploads rejected', fn()=>rejects(fn()=>Pk\Core\Rules::fileType('shell.php', 'text/plain')));
test('extension MIME mismatch rejected', fn()=>rejects(fn()=>Pk\Core\Rules::fileType('proof.pdf', 'image/png')));
test('PDF uploads accepted', fn()=>ok(Pk\Core\Rules::fileType('proof.pdf', 'application/pdf')==='pdf'));
test('bounded pagination', fn()=>ok(Pk\Core\Rules::page(['page'=>-1,'limit'=>900]) === [1,100]));
test('state changes require POST', fn()=>rejects(fn()=>Pk\Core\Security::method('GET',true)));
test('read operations require GET', fn()=>rejects(fn()=>Pk\Core\Security::method('POST',false)));
test('CSRF rejects absent and mismatched tokens', fn()=>rejects(fn()=>Pk\Core\Security::csrf('expected','incorrect')));
test('CSRF accepts matching session token', fn()=>ok(Pk\Core\Security::csrf(str_repeat('a',64),str_repeat('a',64))===true));
test('session expired after thirty idle minutes', fn()=>ok(Pk\Core\Security::expired(['last_seen'=>100],1901)));
test('recent authenticated session not expired', fn()=>ok(!Pk\Core\Security::expired(['last_seen'=>100],1000)));
test('workflow step name is required', fn()=>rejects(fn()=>Pk\Core\WorkflowGraph::validate(['steps'=>[['name'=>'','approver'=>['type'=>'requester']]]],true)));
test('workflow step count is bounded', fn()=>rejects(fn()=>Pk\Core\WorkflowGraph::validate(['steps'=>array_fill(0,31,['name'=>'Review','approver'=>['type'=>'requester']])],true)));
$failed=0;
foreach ($cases as $name=>$fn) { try { $fn(); echo "PASS $name\n"; } catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; } }
echo count($cases)." tests; $failed failures\n";
exit($failed ? 1 : 0);
