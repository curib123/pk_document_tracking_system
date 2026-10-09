<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard_service
{
    private $ci;
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Dashboard_model');
        $this->ci->load->model('Request_model');
    }

    public function overview($user, $includeTasks)
    {
        $stats = ['draft' => 0, 'pending' => 0, 'returned' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($this->ci->Dashboard_model->my_status_totals($user['id']) as $item) {
            $stats[$item['status']] = (int) $item['total'];
        }
        $tasks = 0;
        if ($includeTasks) {
            foreach (['softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign'] as $type) {
                $tasks += $this->ci->Request_model->count_listing($type, $user, TRUE, '', '');
            }
        }
        return ['stats' => $stats, 'assigned_count' => $tasks];
    }
}
