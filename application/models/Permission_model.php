<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Permission_model extends CI_Model
{
    public function allowed($user, $module, $action = 'view')
    {
        if (!$user) return FALSE;
        if ($user['role'] === 'super_admin') return TRUE;
        return $this->db->from('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', (int) $user['role_id'])
            ->where('p.module', $module)
            ->where('p.action', $action)->count_all_results() > 0;
    }

    public function for_user($user)
    {
        if ($user['role'] === 'super_admin') return ['*' => ['*' => TRUE]];
        $rows = $this->db->select('p.module, p.action')->from('permissions p')
            ->join('role_permissions rp', 'rp.permission_id = p.id')
            ->where('rp.role_id', $user['role_id'])->get()->result_array();
        $result = [];
        foreach ($rows as $row) $result[$row['module']][$row['action']] = TRUE;
        return $result;
    }
}
