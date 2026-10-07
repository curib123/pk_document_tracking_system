<?php
declare(strict_types=1);

namespace Pk\Core;

final class WorkflowGraph
{
    public static function defaults(): array
    {
        return ['steps'=>[]];
    }

    public static function forRole(int $roleId,string $name='Administrator Approval',string $roleLabel='Administrator'): array
    {
        return self::validate([
            'steps'=>[
                ['name'=>$name,'approver'=>['type'=>'role','value'=>$roleId,'label'=>$roleLabel]]
            ]
        ],true);
    }

    public static function validate(array $workflow,bool $requireStep=true): array
    {
        $steps=$workflow['steps'] ?? [];
        if (!is_array($steps)) throw new Problem('Workflow steps must be a list.');
        if ($requireStep && count($steps)<1) throw new Problem('Add at least one approval step before publishing.');
        if (count($steps)>30) throw new Problem('A workflow supports up to 30 approval steps.');

        $normalized=[];
        foreach (array_values($steps) as $index=>$step) {
            if (!is_array($step)) throw new Problem('Invalid workflow step.');
            $name=Rules::text($step,'name',120);
            $approver=$step['approver'] ?? [];
            if (!is_array($approver)) throw new Problem('Choose who approves '.$name.'.');
            $type=$approver['type'] ?? '';
            if (!in_array($type,['user','role','leader','requester'],true)) {
                throw new Problem('Approver must be a specific user, role, requester leader, or requester.');
            }
            $saved=['type'=>$type];
            if (in_array($type,['user','role'],true)) {
                $saved['value']=Rules::id(['value'=>$approver['value'] ?? null],'value');
            }
            if (isset($approver['label']) && is_string($approver['label']) && $approver['label']!=='') {
                $saved['label']=mb_substr($approver['label'],0,255);
            }
            $normalized[]=[
                'key'=>'step_'.($index+1),
                'name'=>$name,
                'approver'=>$saved,
            ];
        }
        return ['steps'=>$normalized];
    }

    public static function step(array $workflow,string $key): array
    {
        foreach ($workflow['steps'] ?? [] as $step) {
            if (($step['key'] ?? '')===$key) return $step;
        }
        throw new Problem('Workflow snapshot has a missing approval step.',409);
    }

    public static function firstKey(array $workflow): ?string
    {
        return $workflow['steps'][0]['key'] ?? null;
    }

    public static function nextKey(array $workflow,string $key): ?string
    {
        foreach (array_values($workflow['steps'] ?? []) as $index=>$step) {
            if (($step['key'] ?? '')!==$key) continue;
            return $workflow['steps'][$index+1]['key'] ?? null;
        }
        throw new Problem('Workflow snapshot has a missing approval step.',409);
    }
}
