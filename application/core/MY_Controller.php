<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $user = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Permission_model');
        $id = (int) $this->session->userdata('user_id');
        if ($id) {
            $this->user = $this->db->select('users.id, users.name, users.email, users.role_id, users.leader_id, roles.name AS role')
                ->from('users')->join('roles', 'roles.id = users.role_id')
                ->where('users.id', $id)->where('users.active', 1)->get()->row_array();
        }
    }

    protected function authenticate()
    {
        if (!$this->user) {
            redirect('login');
            exit;
        }
    }

    protected function can($module, $action = 'view')
    {
        return $this->user && $this->Permission_model->allowed($this->user, $module, $action);
    }

    protected function require_permission($module, $action = 'view')
    {
        $this->authenticate();
        if (!$this->can($module, $action)) {
            show_error('You do not have permission for this action.', 403);
            exit;
        }
    }

    protected function require_post()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Method not allowed.', 405);
            exit;
        }
    }

    protected function confirmed()
    {
        $this->require_post();
        if ($this->input->post('confirmed') !== 'yes') {
            show_error('Action requires confirmation.', 400);
            exit;
        }
    }

    protected function notice($message, $type = 'success')
    {
        $this->session->set_flashdata('notice', $message);
        $this->session->set_flashdata('notice_type', $type);
    }

    protected function render($title, $content, $data = [])
    {
        $this->authenticate();
        $data['title'] = $title;
        $data['user'] = $this->user;
        $data['permissions'] = $this->Permission_model->for_user($this->user);
        $data['content_view'] = $content;
        $this->load->view('layout/header', $data);
        $this->load->view('layout/sidebar_top_nav', $data);
        $this->load->view($content, $data);
        $this->load->view('layout/footer', $data);
    }

    protected function back($fallback = 'dashboard')
    {
        $url = $this->input->post('return_to');
        // Prevent external or javascript: redirects.
        if ($url && preg_match('#^[a-z0-9/_?&=.%\\-]+$#i', $url) && strpos($url, '//') === FALSE) {
            redirect($url);
            return;
        }
        redirect($fallback);
    }
}
