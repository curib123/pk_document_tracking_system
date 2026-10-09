<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Identity_model extends CI_Model
{
    public function active_user($id)
    {
        $row = $this->db->select('u.id,u.username,u.first_name,u.middle_name,u.last_name,
                u.position_title,u.role_id,u.leader_id,u.require_password_change,u.session_version,
                r.name AS role')
            ->from('users u')->join('roles r', 'r.id = u.role_id')
            ->where('u.id', (int) $id)->where('u.active', 1)
            ->where('r.active', 1)->get()->row_array();
        if ($row) {
            $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $row['email'] = '';
        }
        return $row;
    }
    public function find_login($username)
    {
        return $this->db->select('u.*,r.name AS role')->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.username', $username)->where('u.active',1)
            ->where('r.active',1)->limit(1)->get()->row_array();
    }
}
