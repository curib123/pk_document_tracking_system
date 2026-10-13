<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Permission_model extends CI_Model
{
    private $permissions_cache=NULL;
    public function allowed($user, $module, $action = 'view')
    {
        if (!$user) return FALSE;
        // Admin sidebar alias: these actions have their own module in pk_dts.sql.
        $module = ['softcopy-categories' => 'categories','area'=>'areas','specific'=>'specifics',
            'asset'=>'assets','location'=>'locations','sequence'=>'sequences',
            'tasks'=>'requests'][$module] ?? $module;
        if ($module === 'requests' && $action === 'approve') $action = 'manage';
        $permissions=$this->system_permissions();
        return isset($permissions['*']) || !empty($permissions[$module][$action]);
    }
    private function system_permissions()
    {
        if ($this->permissions_cache!==NULL) return $this->permissions_cache;
        // An action is allowed when ANY active system role grants it. Left
        // joins retain the Administrator's built-in grant even without rows
        // in role_permissions. The same request-local result powers the UI.
        $rows=$this->db->select('r.name AS role_name,p.module_key,p.action_key')->from('roles r')
            ->join('role_permissions rp','rp.role_id=r.id','left')
            ->join('permissions p','p.id=rp.permission_id','left')->where('r.active',1)->get()->result_array();
        $result=[];
        foreach ($rows as $permission) {
            if (strcasecmp(trim((string)$permission['role_name']),'Administrator')===0)
                $result['*']['*']=TRUE;
            if (!empty($permission['module_key']) && !empty($permission['action_key']))
                $result[$permission['module_key']][$permission['action_key']]=TRUE;
        }
        return $this->permissions_cache=$result;
    }

    public function for_user($user)
    {
        if (!$user) return [];
        $result=$this->system_permissions();
        if (isset($result['*'])) return $result;
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
