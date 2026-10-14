<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'services/security/user_roles.php';
class Identity_model extends CI_Model
{
    public function active_user($id)
    {
        $row = $this->db->select('u.id,u.username,u.first_name,u.middle_name,u.last_name,
                u.position_title,u.role_id,u.leader_id,u.require_password_change,u.session_version,
                r.name AS role')
            ->from('users u')->join('roles r', 'r.id = u.role_id', 'left')
            ->where('u.id', (int) $id)->where('u.active', 1)->get()->row_array();
        if ($row) {
            $row = $this->with_roles($row);
            if (!$row)
                return NULL;
            $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $row['email'] = '';
        }
        return $row;
    }
    public function find_login($username)
    {
        $row = $this->db->select('u.*,r.name AS role')->from('users u')
            ->join('roles r', 'r.id = u.role_id', 'left')
            ->where('u.username', $username)->where('u.active', 1)
            ->limit(1)->get()->row_array();
        return $row ? $this->with_roles($row) : NULL;
    }
    private function with_roles(array $row)
    {
        $roles = User_roles::assigned($this->db, (int) $row['id'], (int) $row['role_id']);
        if (!$roles)
            return NULL;
        $row['role_ids'] = array_map('intval', array_column($roles, 'id'));
        $row['roles'] = $roles;
        $row['roles_label'] = implode(' · ', array_column($roles, 'name'));
        return $row;
    }
}
