<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    public function find_active($login)
    {
        return $this->db->select('users.*, roles.name AS role')->from('users')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.active', 1)
            ->group_start()->where('users.email', $login)
            ->or_where('users.username', $login)->group_end()
            ->get()->row_array();
    }
}
