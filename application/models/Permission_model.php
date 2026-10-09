<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Permission_model extends CI_Model
{
    public function allowed($user, $module, $action = 'view')
    {
        if (!$user) return FALSE;
        if (strtolower($user['role']) === 'administrator') return TRUE;
        // Admin sidebar alias: these actions have their own module in pk_dts.sql.
        $module = ['softcopy-categories' => 'categories','area'=>'areas','specific'=>'specifics',
            'asset'=>'assets','location'=>'locations','sequence'=>'sequences',
            'tasks'=>'requests'][$module] ?? $module;
        if ($module === 'requests' && $action === 'approve') $action = 'manage';
        return $this->db->from('role_permissions rp')
            ->join('permissions p','p.id=rp.permission_id')
            ->where('rp.role_id', (int) $user['role_id'])
            ->where('p.module_key', $module)
            ->where('p.action_key', $action)->count_all_results() > 0;
    }
    public function for_user($user)
    {
        if (!$user) return [];
        if (strtolower($user['role']) === 'administrator') return ['*'=>['*'=>TRUE]];
        $rows = $this->db->select('p.module_key,p.action_key')
            ->from('role_permissions rp')
            ->join('permissions p', 'p.id=rp.permission_id')
            ->where('rp.role_id',(int) $user['role_id'])->get()->result_array();
        $result = [];
        foreach ($rows as $p) $result[$p['module_key']][$p['action_key']] = TRUE;
        $result['tasks']['view'] = !empty($result['requests']['view']) ||
            !empty($result['requests']['manage']) ||
            !empty($result['transfer']['view']);
        return $result;
    }
}
