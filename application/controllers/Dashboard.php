<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard extends MY_Controller
{
    public function index()
    {
        $this->require_permission('dashboard', 'view');
        // A first-login user must not receive analytics until their password changes.
        if (!empty($this->user['require_password_change'])) {
            $this->render('Secure Your Account', 'pages/authentication/first_login');
            return;
        }
        require_once APPPATH . 'services/dashboard/dashboard_service.php';
        $data = (new Dashboard_service())->summary($this->user);
        $this->render('Dashboard', 'pages/dashboard/index', $data);
    }
}
