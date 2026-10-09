<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Administration_model extends CI_Model
{
    private function user_scope($q, $status)
    {
        $this->db->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('users leader', 'leader.id = u.leader_id', 'left');
        if ($status !== '') $this->db->where('u.active', (int) $status);
        if ($q !== '') {
            $this->db->group_start()->like('u.name', $q)
                ->or_like('u.username', $q)
                ->or_like('u.email', $q)->group_end();
        }
    }

    public function count_users($q, $status)
    {
        $this->user_scope($q, $status);
        return (int) $this->db->count_all_results();
    }

    public function page_users($q, $status, $limit, $offset, $sort, $dir)
    {
        $this->user_scope($q, $status);
        $fields = [
            'name' => 'u.name', 'username' => 'u.username',
            'email' => 'u.email', 'role' => 'r.name',
            'leader' => 'leader.name', 'active' => 'u.active'
        ];
        $sort = isset($fields[$sort]) ? $sort : 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        return $this->db->select('u.id,u.name,u.username,u.email,u.role_id,u.leader_id,
                u.active,r.name AS role_name,leader.name AS leader_name')
            ->order_by($fields[$sort], $dir)->order_by('u.id', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
    }

    // Small select-list data is not a DataTable and includes no password hashes.
    public function user_options()
    {
        return $this->db->select('id,name,active')->from('users')
            ->where('active', 1)->order_by('name')->get()->result_array();
    }

    private function role_scope($q, $status)
    {
        $this->db->from('roles r');
        if ($status !== '') $this->db->where('r.is_system', $status === 'system' ? 1 : 0);
        if ($q !== '') {
            $this->db->group_start()->like('r.name', $q)
                ->or_like('r.description', $q)->group_end();
        }
    }

    public function count_roles($q, $status)
    {
        $this->role_scope($q, $status);
        return (int) $this->db->count_all_results();
    }

    public function page_roles($q, $status, $limit, $offset, $sort, $dir)
    {
        $this->role_scope($q, $status);
        $fields = [
            'name' => 'r.name', 'description' => 'r.description',
            'category' => 'r.is_system', 'access' => 'permission_count'
        ];
        $sort = isset($fields[$sort]) ? $sort : 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        return $this->db->select('r.id,r.name,r.description,r.is_system,
            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count', FALSE)
            ->order_by($fields[$sort], $dir)->order_by('r.id', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
    }

    public function role_grants($roleIds)
    {
        if (!$roleIds) return [];
        return $this->db->from('role_permissions')->where_in('role_id', $roleIds)
            ->get()->result_array();
    }

    // Only for dropdowns; DataTable queries always use page_roles().
    public function roles()
    {
        return $this->db->order_by('name')->get('roles')->result_array();
    }

    public function permissions()
    {
        return $this->db->order_by('module')->order_by('action')->get('permissions')->result_array();
    }

    private function workflow_scope($q, $status, $type)
    {
        $this->db->from('workflows w');
        if ($status !== '') $this->db->where('w.active', (int) $status);
        if ($type !== '') $this->db->where('w.request_type', $type);
        if ($q !== '') {
            $this->db->group_start()->like('w.name', $q)
                ->or_like('w.request_type', str_replace(' ', '-', $q))->group_end();
        }
    }

    public function count_workflows($q, $status, $type)
    {
        $this->workflow_scope($q, $status, $type);
        return (int) $this->db->count_all_results();
    }

    public function page_workflows($q, $status, $type, $limit, $offset, $sort, $dir)
    {
        $this->workflow_scope($q, $status, $type);
        $fields = [
            'name' => 'w.name', 'type' => 'w.request_type',
            'version' => 'w.version', 'steps' => 'step_count',
            'default' => 'w.is_default', 'active' => 'w.active'
        ];
        $sort = isset($fields[$sort]) ? $sort : 'type';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        return $this->db->select('w.*,
            (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id) AS step_count', FALSE)
            ->order_by($fields[$sort], $dir)
            ->order_by('w.version', 'DESC')
            ->order_by('w.id', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
    }

    public function workflow_steps_for($ids)
    {
        if (!$ids) return [];
        $rows = $this->db->select('s.*, u.name AS user_name, r.name AS role_name')
            ->from('workflow_steps s')
            ->join('users u', 'u.id = s.approver_user_id', 'left')
            ->join('roles r', 'r.id = s.approver_role_id', 'left')
            ->where_in('s.workflow_id', $ids)
            ->order_by('s.workflow_id')->order_by('s.step_order')
            ->get()->result_array();
        $steps = [];
        foreach ($rows as $row) $steps[$row['workflow_id']][] = $row;
        return $steps;
    }
}
