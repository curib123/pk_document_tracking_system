<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Current references only; completed request and retired workflow snapshots stay historical. */
class Role_usage
{
    public static function for_roles($db,array $roleIds): array
    {
        $usage=[];
        foreach ($roleIds as $id) $usage[(int)$id]=['user_count'=>0,'workflow_count'=>0,'request_count'=>0];
        if (!$usage) return [];
        $users=$db->select('role_id,COUNT(*) AS user_count',FALSE)->from('users')
            ->where_in('role_id',array_keys($usage))->group_by('role_id')->get()->result_array();
        foreach ($users as $row) $usage[(int)$row['role_id']]['user_count']=(int)$row['user_count'];
        $workflows=$db->select('id,graph')->from('workflow_versions')
            ->where("(status='draft' OR is_default=1)",NULL,FALSE)->get()->result_array();
        $requests=$db->select('id,snapshot')->from('requests')
            ->where_not_in('status',['completed','cancelled','rejected'])
            ->where('snapshot IS NOT NULL',NULL,FALSE)->get()->result_array();
        $steps=$db->select('s.request_id,s.assignment')->from('workflow_steps s')
            ->join('requests r','r.id=s.request_id')
            ->where_not_in('r.status',['completed','cancelled','rejected'])
            ->where_in('s.status',['active','pending'])->get()->result_array();
        $workflowRefs=[];$requestRefs=[];
        $add=function($approver,$recordId,&$references) use($usage) {
            if (is_array($approver) && ($approver['type']??'')==='role' && isset($usage[(int)($approver['value']??0)]))
                $references[(int)$approver['value']][(int)$recordId]=TRUE;
        };
        foreach ($workflows as $row) {
            $graph=json_decode($row['graph'],TRUE)?:[];
            foreach ($graph['steps']??[] as $step) $add($step['approver']??NULL,$row['id'],$workflowRefs);
        }
        foreach ($requests as $row) {
            $graph=json_decode($row['snapshot']??'null',TRUE)?:[];
            foreach ($graph['steps']??[] as $step) $add($step['approver']??NULL,$row['id'],$requestRefs);
        }
        foreach ($steps as $row) $add(json_decode($row['assignment'],TRUE),$row['request_id'],$requestRefs);
        foreach ($usage as $id=>&$counts) {
            $counts['workflow_count']=count($workflowRefs[$id]??[]);
            $counts['request_count']=count($requestRefs[$id]??[]);
        }
        unset($counts);
        return $usage;
    }
}
