<?php
declare(strict_types=1);
namespace Pk\Core;
final class WorkflowGraph
{
    public static function defaults(): array
    {
        return ['start'=>'start','nodes'=>[
            ['key'=>'start','type'=>'start','next'=>'review'],
            ['key'=>'review','type'=>'approval','label'=>'Document approval','assignment'=>['type'=>'permission','value'=>'requests.approve'],'approve'=>'done','reject'=>'rejected','return'=>'returned'],
            ['key'=>'done','type'=>'end','outcome'=>'approved'],
            ['key'=>'rejected','type'=>'end','outcome'=>'rejected'],
            ['key'=>'returned','type'=>'end','outcome'=>'returned'],
        ]];
    }
    public static function validate(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        if (!is_array($nodes) || count($nodes) < 3 || count($nodes) > 60) throw new Problem('A workflow needs 3–60 nodes.');
        $map = []; $starts = 0;
        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $node['key'] ?? '') || isset($map[$node['key']])) throw new Problem('Node keys must be unique lowercase identifiers.');
            $type = $node['type'] ?? '';
            if (!in_array($type, ['start','approval','condition','end'], true)) throw new Problem('Unknown workflow node type.');
            if ($type === 'start') ++$starts;
            if ($type === 'approval') {
                Rules::text($node, 'label', 120);
                $assignment = $node['assignment'] ?? [];
                if (!in_array($assignment['type'] ?? '', ['user','role','permission','leader','document'], true)) throw new Problem('Invalid approver assignment type.');
                if (in_array($assignment['type'], ['user','role'], true)) Rules::id(['value'=>$assignment['value'] ?? null], 'value');
                elseif ($assignment['type'] !== 'leader') Rules::text($assignment, 'value', 120);
            }
            if ($type === 'condition') self::condition($node, []);
            if ($type === 'end' && !in_array($node['outcome'] ?? '', ['approved','rejected','returned','cancelled'], true)) throw new Problem('Invalid workflow outcome.');
            $map[$node['key']] = $node;
        }
        $start = $graph['start'] ?? '';
        if ($starts !== 1 || !is_string($start) || !isset($map[$start]) || $map[$start]['type'] !== 'start') throw new Problem('Exactly one matching start node is required.');
        $seen = []; $visited = [];
        $walk = function (string $key, array $path, bool $approved) use (&$walk, &$seen, &$visited, $map): void {
            if (!isset($map[$key])) throw new Problem("Missing workflow target: $key.");
            if (isset($path[$key])) throw new Problem('Workflow cycles are not allowed; use Return for Correction.');
            $state=$key.':'.($approved?'1':'0'); if (isset($visited[$state])) return;
            $node = $map[$key]; $path[$key] = true; $seen[$key] = true;
            if ($node['type'] === 'end') {
                if ($node['outcome'] === 'approved' && !$approved) throw new Problem('An approved path must pass through an approval decision.');
                return;
            }
            foreach (self::edges($node) as $decision => $target) {
                if (!is_string($target) || $target === '') throw new Problem('Every node path needs a target.');
                $walk($target, $path, $approved || ($node['type']==='approval' && $decision==='approve'));
            }
            $visited[$state]=true;
        };
        $walk($start, [], false);
        if (count($seen) !== count($map)) throw new Problem('Remove unreachable workflow nodes.');
        return ['start'=>$start,'nodes'=>array_values($map)];
    }
    public static function edges(array $node): array
    {
        return match ($node['type']) {
            'start' => ['next'=>$node['next'] ?? ''],
            'approval' => ['approve'=>$node['approve'] ?? '', 'reject'=>$node['reject'] ?? '', 'return'=>$node['return'] ?? ''],
            'condition' => ['true'=>$node['true'] ?? '', 'false'=>$node['false'] ?? ''],
            default => [],
        };
    }
    public static function condition(array $node, array $payload): bool
    {
        $field = $node['field'] ?? '';
        if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field)) throw new Problem('Condition field must be a payload field name.');
        $left = $payload[$field] ?? null; $right = $node['value'] ?? null;
        if (($left !== null && !is_scalar($left)) || ($right !== null && !is_scalar($right))) throw new Problem('Condition values must be scalar.');
        return match ($node['operator'] ?? '') {
            'eq' => $left !== null && (string)$left === (string)$right,
            'ne' => $left !== null && (string)$left !== (string)$right,
            'gt' => is_numeric($left) && is_numeric($right) && (float)$left > (float)$right,
            'gte' => is_numeric($left) && is_numeric($right) && (float)$left >= (float)$right,
            'lt' => is_numeric($left) && is_numeric($right) && (float)$left < (float)$right,
            'lte' => is_numeric($left) && is_numeric($right) && (float)$left <= (float)$right,
            'contains' => is_scalar($left) && is_scalar($right) && str_contains((string)$left,(string)$right),
            default => throw new Problem('Unsupported condition operator.'),
        };
    }
    public static function node(array $graph, string $key): array
    {
        foreach ($graph['nodes'] as $node) if ($node['key'] === $key) return $node;
        throw new Problem('Workflow snapshot has a missing node.', 409);
    }
}
