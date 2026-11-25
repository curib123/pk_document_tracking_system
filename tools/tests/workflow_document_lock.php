<?php
/**
 * Regression checks for single-step document locking in workflow snapshots.
 * Run: php tools/tests/workflow_document_lock.php
 */
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/services/workflow/workflow_graph.php';

function assertLockRule(bool $expected, bool $actual, string $label): void
{
    if ($expected !== $actual)
        throw new RuntimeException($label . ': expected ' . var_export($expected, TRUE) . ', got ' . var_export($actual, TRUE));
}

function assertInvalidLockGraph(array $graph, string $label): void
{
    try {
        Workflow_graph::validate($graph, TRUE);
    } catch (DomainException $error) {
        return;
    }
    throw new RuntimeException($label . ': expected a validation error.');
}

$graph = [
    'steps' => [
        ['key' => 'step_1', 'name' => 'Approver 1', 'approver' => ['type' => 'user', 'value' => 1]],
        ['key' => 'step_2', 'name' => 'Approver 2', 'approver' => ['type' => 'user', 'value' => 2]],
        ['key' => 'step_3', 'name' => 'Approver 3', 'approver' => ['type' => 'user', 'value' => 3]]
    ],
    'document_lock_step_key' => 'step_2'
];

Workflow_graph::validate($graph, TRUE);
assertLockRule(FALSE, Workflow_graph::is_document_lock_step($graph, 'step_1'), 'Approver 1 does not trigger locking');
assertLockRule(TRUE, Workflow_graph::is_document_lock_step($graph, 'step_2'), 'Only the designated Approver 2 triggers locking');
assertLockRule(FALSE, Workflow_graph::is_document_lock_step($graph, 'step_3'), 'Approver 3 does not trigger locking');
assertLockRule(FALSE, Workflow_graph::is_document_lock_step(['steps' => $graph['steps']], 'step_2'), 'Unset lock approver does not trigger locking');

$invalid = $graph;
$invalid['document_lock_step_key'] = 'step_4';
assertInvalidLockGraph($invalid, 'Lock approver must be a configured workflow step');
$invalid['document_lock_step_key'] = ['step_2', 'step_3'];
assertInvalidLockGraph($invalid, 'Lock approver selection must be singular');

echo "Workflow document-lock tests passed.\n";
