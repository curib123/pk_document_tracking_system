<?php
$path=dirname(__DIR__).'/application/services/workflow/workflow_graph.php';
if (!is_file($path)) {fwrite(STDERR,"Workflow graph policy is missing.\n");exit(1);}
require $path;
function check_graph($condition,$message) {if (!$condition) throw new RuntimeException($message);}
$step=['key'=>'step_1','name'=>'Manager approval','approver'=>['type'=>'requester_leader','value'=>NULL]];
Workflow_graph::validate(['steps'=>[$step]],TRUE);
Workflow_graph::validate(['steps'=>[]],FALSE);
$reordered=Workflow_graph::renumber([$step+['extra'=>'keep'],array_replace($step,['key'=>'step_8'])]);
check_graph($reordered[1]['key']==='step_2','Step keys must follow array order.');
$invalid=[['steps'=>[]],['steps'=>array_fill(0,31,$step)],['steps'=>[array_replace($step,['key'=>'step_9'])]],
 ['steps'=>[array_replace($step,['name'=>''])]],['steps'=>[array_replace($step,['approver'=>['type'=>'anonymous']])]],
 ['steps'=>[array_replace($step,['approver'=>['type'=>'role','value'=>0]])]],['steps'=>[['key'=>'step_1','name'=>'x']]],
 ['steps'=>'not an array']];
foreach ($invalid as $graph) {
    try {Workflow_graph::validate($graph,TRUE);throw new RuntimeException('Invalid graph accepted.');}
    catch (DomainException $expected) {}
}
echo "Workflow graph shape, publication, step ordering and eight invalid cases passed.\n";
