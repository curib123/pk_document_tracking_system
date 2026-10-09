<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Administration_model extends CI_Model
{
    public function users()
    {
        return $this->db->select('u.id,u.name,u.username,u.email,u.role_id,u.leader_id,u.active, r.name AS role_name, leader.name AS leader_name')
            ->from('users u')->join('roles r', 'r.id = u.role_id')
            ->join('users leader', 'leader.id = u.leader_id', 'left')
            ->order_by('u.name')->get()->result_array();
    }
    public function roles()
    {
        return $this->db->order_by('name')->get('roles')->result_array();
    }
    public function permissions()
    {
        return $this->db->order_by('module')->order_by('action')->get('permissions')->result_array();
    }
    public function workflows()
    {
        return $this->db->order_by('request_type')->order_by('version', 'DESC')->get('workflows')->result_array();
    }
    public function workflow_steps($id)
    {
        return $this->db->select('s.*, u.name AS user_name, r.name AS role_name')
            ->from('workflow_steps s')->join('users u', 'u.id = s.approver_user_id', 'left')
            ->join('roles r', 'r.id = s.approver_role_id', 'left')
            ->where('s.workflow_id', $id)->order_by('s.step_order')->get()->result_array();
    }
}
