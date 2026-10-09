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
        require_once APPPATH.'services/authentication/authentication_service.php';
        if (!empty($this->user['require_password_change']) &&
            !Authentication_service::is_setup_action(
                $this->router->fetch_class(), $this->router->fetch_method())) {
            // The setup page has no dashboard/role dependency and no protected data.
            redirect('change-password');
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
        $this->output->set_header('Cache-Control: no-store, private');
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
        require_once APPPATH.'services/presentation/query_state.php';
        try {
            return Query_state::parse($this->input->get(NULL,TRUE)?:[], $defaultSort);
        } catch (DomainException $e) {
            show_error($e->getMessage(),400);
            exit;
        }
    }
}
