<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard_model extends CI_Model
{
    public function counts($user)
    {
        $stats = ['draft'=>0,'submitted'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,
            'completed'=>0,'returned'=>0];
        $rows = $this->db->select('status, COUNT(*) AS total')->from('requests')
            ->where('requested_by', $user['id'])->group_by('status')->get()->result_array();
        foreach ($rows as $r) $stats[$r['status']] = (int) $r['total'];
        return $stats;
    }
}
