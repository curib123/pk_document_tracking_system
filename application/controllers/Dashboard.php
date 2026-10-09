<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    public function index()
    {
        $this->require_permission('dashboard');
        $this->load->model('Request_model');
        $mine = $this->db->select('status,COUNT(*) AS total')->from('requests')
            ->where('requester_id', $this->user['id'])->group_by('status')->get()->result_array();
        $stats = ['draft' => 0, 'pending' => 0, 'returned' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($mine as $item) $stats[$item['status']] = (int) $item['total'];
        $tasks = 0;
        if ($this->can('tasks')) {
            foreach (['softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign'] as $type) {
                $tasks += count($this->Request_model->tasks($type, $this->user));
            }
        }
        $this->render('Dashboard', 'pages/dashboard/index', [
            'stats' => $stats,
            'assigned_count' => $tasks
        ]);
    }
}
