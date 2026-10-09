<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    public function index()
    {
        $this->require_permission('dashboard');
        require_once APPPATH . 'services/dashboard/dashboard_service.php';
        $data = (new Dashboard_service())->overview($this->user, $this->can('tasks'));
        $this->render('Dashboard', 'pages/dashboard/index', $data);
    }
}
