<?php
final class Request_policy
{
    public static function module(string $type): string
    {
        $modules=['softcopy_create'=>'softcopy','softcopy_revise'=>'softcopy','softcopy_cancel'=>'softcopy',
            'hardcopy_create'=>'hardcopy','hardcopy_update'=>'hardcopy','transfer'=>'transfer',
            'assignment'=>'assignment','access'=>'access','disposal'=>'disposal'];
        if (!isset($modules[$type])) throw new DomainException('Unsupported request type.');
        return $modules[$type];
    }

    public static function editable(?array $request,int $actor,string $type,array $post): void
    {
        if (!$request || (int)$request['requested_by']!==$actor || $request['type']!==$type ||
            !in_array($request['status'],['draft','returned'],TRUE))
            throw new DomainException('Only your matching draft or returned request can be edited.');
        if (isset($post['version']) && $post['version']!=='' && (int)$post['version']!==(int)$request['version'])
            throw new DomainException('This request changed in another session. Refresh before editing.');
    }
}
