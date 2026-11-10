<?php
/** Pure PHP 8.0-compatible approval-route rules shared by publication and request execution. */
final class Workflow_graph
{
    public static function is_document_lock_step(array $graph, string $stepKey): bool
    {
        return is_string($graph['document_lock_step_key'] ?? NULL) &&
            $graph['document_lock_step_key'] === $stepKey;
    }

    public static function renumber(array $steps): array
    {
        $steps = array_values($steps);
        foreach ($steps as $i => &$step)
            $step['key'] = 'step_' . ($i + 1);
        unset($step);
        return $steps;
    }

    public static function validate(array $graph, bool $published): void
    {
        $steps = $graph['steps'] ?? NULL;
        if (!is_array($steps) || (is_array($steps) && $steps !== array_values($steps)) || count($steps) > 30 || ($published && !$steps))
            throw new DomainException('An Approval Process Timeline must contain 1–30 ordered steps before publication.');
        foreach ($steps as $index => $step) {
            if (!is_array($step) || ($step['key'] ?? '') !== 'step_' . ($index + 1))
                throw new DomainException('Approval step keys must match their sequence.');
            $name = $step['name'] ?? NULL;
            if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 120)
                throw new DomainException('Each step needs a name of up to 120 characters.');
            $approver = $step['approver'] ?? [];
            $type = $approver['type'] ?? '';
            if (!in_array($type, ['user', 'role', 'requester_leader', 'requester'], TRUE))
                throw new DomainException('Choose a supported approver type for every step.');
            if (in_array($type, ['user', 'role'], TRUE) && (int) ($approver['value'] ?? 0) < 1)
                throw new DomainException('Select an approver account or role.');
        }
        $lockStep = $graph['document_lock_step_key'] ?? NULL;
        if ($lockStep !== NULL && (!is_string($lockStep) || !in_array($lockStep, array_column($steps, 'key'), TRUE)))
            throw new DomainException('Choose a document-locking approver from the configured approval steps.');
    }
}
