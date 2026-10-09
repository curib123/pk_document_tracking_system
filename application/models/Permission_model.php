<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Permission_model extends CI_Model
{
    private $role_cache=[];
    public function allowed($user, $module, $action = 'view')
    {
        if (!$user) return FALSE;
        if (strtolower($user['role']) === 'administrator') return TRUE;
        // Admin sidebar alias: these actions have their own module in pk_dts.sql.
        $module = ['softcopy-categories' => 'categories','area'=>'areas','specific'=>'specifics',
            'asset'=>'assets','location'=>'locations','sequence'=>'sequences',
            'tasks'=>'requests'][$module] ?? $module;
        if ($module === 'requests' && $action === 'approve') $action = 'manage';
        $permissions=$this->role_permissions((int)$user['role_id']);
        return !empty($permissions[$module][$action]);
    }
    private function role_permissions($roleId)
    {
        if (isset($this->role_cache[$roleId])) return $this->role_cache[$roleId];
        $rows=$this->db->select('p.module_key,p.action_key')->from('role_permissions rp')
            ->join('permissions p','p.id=rp.permission_id')->where('rp.role_id',$roleId)->get()->result_array();
        $result=[];
        foreach ($rows as $permission) $result[$permission['module_key']][$permission['action_key']]=TRUE;
        return $this->role_cache[$roleId]=$result;
    }

    public function for_user($user)
    {
        if (!$user) return [];
        if (strtolower($user['role']) === 'administrator') return ['*'=>['*'=>TRUE]];
        $result=$this->role_permissions((int)$user['role_id']);
        $result['tasks']['view'] = !empty($result['requests']['view']) ||
            !empty($result['requests']['manage']) ||
            !empty($result['transfer']['view']);
        if (!$result['tasks']['view']) {
            $result['tasks']['view']=$this->db->select('ws.id')->from('workflow_steps ws')->join('requests r','r.id=ws.request_id')
                ->where('r.status','submitted')->where('ws.status','active')->group_start()
                ->where('ws.assigned_user_id',(int)$user['id'])
                ->or_group_start()->where("JSON_UNQUOTE(JSON_EXTRACT(ws.assignment,'$.type'))='role'",NULL,FALSE)
                    ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(ws.assignment,'$.value')) AS UNSIGNED)=".(int)$user['role_id'],NULL,FALSE)
                ->group_end()->group_end()->limit(1)->get()->num_rows()>0;
        }
        return $result;
    }
}
