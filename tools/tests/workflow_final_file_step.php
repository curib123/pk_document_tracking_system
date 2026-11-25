<?php
// Finalize-step rules for the DCO final controlled file.
define('BASEPATH', __DIR__);
require __DIR__ . '/../../application/services/workflow/workflow_graph.php';

function step($n) { return ['key' => "step_$n", 'name' => "S$n", 'approver' => ['type' => 'role', 'value' => 1]]; }
$graph = ['steps' => [step(1), step(2)], 'document_lock_step_key' => 'step_1', 'finalize_step_key' => 'step_2'];
Workflow_graph::validate($graph, TRUE);
assert(Workflow_graph::is_finalize_step($graph, 'step_2') && !Workflow_graph::is_finalize_step($graph, 'step_1'));
assert(!Workflow_graph::is_finalize_step(['steps' => $graph['steps']], 'step_2'));
foreach (['step_1', 'step_9'] as $bad) {
    try { $g = $graph; $g['finalize_step_key'] = $bad; Workflow_graph::validate($g, TRUE); echo "FAIL $bad\n"; exit(1); }
    catch (DomainException $e) {}
}
echo "Final-file step tests passed.\n";
