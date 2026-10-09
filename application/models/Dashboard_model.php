<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard_model extends CI_Model
{
    public function my_status_totals($userId)
    {
        return $this->db->select('status,COUNT(*) AS total')->from('requests')
            ->where('requester_id', $userId)->group_by('status')->get()->result_array();
    }
}
