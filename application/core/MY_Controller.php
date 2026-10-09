<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class MY_Controller extends CI_Controller
{
    protected $user = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Identity_model');
        $this->load->model('Permission_model');
        $id = (int) $this->session->userdata('user_id');
        if ($id) $this->user = $this->Identity_model->active_user($id);
        if ($id && (!$this->user ||
            (int)$this->session->userdata('session_version') !== (int)$this->user['session_version'])) {
            $this->session->sess_destroy();
            $this->user=NULL;
        }
    }

    protected function authenticate()
    {
        if (!$this->user) { redirect('login'); exit; }
        if (!empty($this->user['require_password_change']) &&
            !in_array($this->router->fetch_method(), ['change_password','logout'], TRUE)) {
            // Render a persistent required-password notice, but allow viewing the dashboard.
            $this->session->set_flashdata('notice', 'Please change your password before continuing.');
        }
    }

    protected function can($module, $action = 'view')
    {
        return $this->user && $this->Permission_model->allowed($this->user, $module, $action);
    }

    protected function require_permission($module, $action = 'view')
    {
        $this->authenticate();
        if (!empty($this->user['require_password_change']) && $this->router->fetch_class()!=='Dashboard') {
            show_error('Change your temporary password before using other modules.', 403);
            exit;
        }
        if (!$this->can($module, $action)) {
            show_error('You do not have permission for this action.', 403);
            exit;
        }
    }

    protected function confirmed()
    {
        if ($this->input->method(TRUE) !== 'POST') show_error('Method not allowed.', 405);
        if ($this->input->post('confirmed') !== 'yes') show_error('Confirmation required.', 400);
    }

    protected function notice($message, $type = 'success')
    {
        // Daily JSON audit is filesystem-only; never insert operational audit
        // events into an additional SQL table.
        if ($this->user && $this->input->method(TRUE)==='POST') {
            $this->load->library('Audit_file');
            if (!$this->audit_file->append((int)$this->user['id'],
                trim(uri_string(),'/'),NULL,[],
                $type==='success'?'success':'failed')) {
                log_message('error','Unable to write JSON audit file for successful action.');
            }
        }
        $this->session->set_flashdata('notice', $message);
        $this->session->set_flashdata('notice_type', $type);
    }

    protected function render($title, $view, $data = [])
    {
        $this->authenticate();
        $data['title'] = $title;
        $data['user'] = $this->user;
        $data['permissions'] = $this->Permission_model->for_user($this->user);
        $data['content_view'] = $view;
        $this->load->view('layout/header', $data);
        $this->load->view('layout/sidebar_top_nav', $data);
        $this->load->view($view, $data);
        $this->load->view('layout/footer', $data);
    }

    protected function table_state($defaultSort = 'created_at')
    {
        $limit = (int) $this->input->get('limit');
        if (!in_array($limit, [10,25,50,100], TRUE)) $limit = 10;
        return [
            'q' => mb_substr(trim((string) $this->input->get('q', TRUE)), 0, 100),
            'status' => (string) $this->input->get('status', TRUE),
            'page' => max(1, min(1000000, (int) $this->input->get('page'))),
            'limit' => $limit, 'sort' => (string) ($this->input->get('sort') ?: $defaultSort),
            'dir' => strtolower((string) $this->input->get('dir')) === 'desc' ? 'DESC' : 'ASC'
        ];
    }
}
