<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class MY_Controller extends CI_Controller
{
    protected $user = NULL;
    private $auditContext = NULL;

    public function __construct()
    {
        parent::__construct();
        // Do not read/write database-backed identities while a restore
        // replaces tables. Applies to login, logout, and every controller.
        // The already-running restore request does not re-enter this constructor.
        if (is_file(PK_ROOT . '/storage/.restore-in-progress')) {
            show_error('System restore is underway. Please try again after maintenance.', 503);
            exit;
        }
        $this->load->model('Identity_model');
        $this->load->model('Permission_model');
        $id = (int) $this->session->userdata('user_id');
        if ($id)
            $this->user = $this->Identity_model->active_user($id);
        if (
            $id && (!$this->user ||
                (int) $this->session->userdata('session_version') !== (int) $this->user['session_version'])
        ) {
            $this->session->sess_destroy();
            $this->user = NULL;
        }
    }

    protected function authenticate()
    {
        // Block ordinary authenticated requests during a database restore.
        // The restoring administrator's Backups controller remains accessible.
        if (
            is_file(PK_ROOT . '/storage/.restore-in-progress') &&
            strtolower((string) $this->router->fetch_class()) !== 'backups'
        ) {
            show_error('System restore is underway. Please try again after maintenance.', 503);
            exit;
        }
        if (!$this->user) {
            $this->session->set_flashdata('auth_expired', TRUE);
            redirect('login');
            exit;
        }
        require_once APPPATH . 'services/authentication/authentication_service.php';
        if (
            !empty($this->user['require_password_change']) &&
            !Authentication_service::is_setup_action(
                $this->router->fetch_class(),
                $this->router->fetch_method()
            )
        ) {
            // The setup page has no dashboard/role dependency and no protected data.
            redirect('change-password');
            exit;
        }
        require_once APPPATH . 'services/files/file_service.php';
        (new File_service())->cleanup_daily();
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
        if ($this->input->method(TRUE) !== 'POST')
            show_error('Method not allowed.', 405);
        if ($this->input->post('confirmed') !== 'yes')
            show_error('Confirmation required.', 400);
        require_once APPPATH . 'services/audit/audit_context.php';
        try {
            $post = $this->input->post() ?: [];
            if (uri_string() === 'change-password' && $this->user) $post['id'] = $this->user['id'];
            $this->auditContext = new Audit_context($this->db, trim(uri_string(), '/'), $post);
        } catch (Throwable $error) {
            log_message('error', 'Audit context could not be captured: ' . $error->getMessage());
        }
    }

    protected function audit_created($table, $id)
    {
        if ($this->auditContext) $this->auditContext->add($table, (int) $id);
    }

    protected function audit_event($action, $subject = NULL, array $metadata = [], $actorId = NULL, $status = 'success')
    {
        $this->load->library('Audit_file');
        $written = $this->audit_file->append($actorId ?? (int) ($this->user['id'] ?? 0), $action, $subject, $metadata, $status);
        if (!$written) log_message('error', 'Unable to persist audit event: ' . $action);
        return $written;
    }

    protected function error_notice(Throwable $error)
    {
        if ($error instanceof DomainException) {
            $this->notice($error->getMessage(), 'danger');
            return;
        }
        log_message('error', get_class($error) . ': ' . $error->getMessage());
        $this->notice('The action could not be completed. Please try again or contact your administrator.', 'danger');
    }

    protected function notice($message, $type = 'success')
    {
        // Daily JSON audit is filesystem-only; never insert operational audit
        // events into an additional SQL table.
        if ($this->user && $this->input->method(TRUE) === 'POST') {
            $metadata = ['result_type' => $type];
            try {
                if ($this->auditContext) $metadata += $this->auditContext->finish();
            } catch (Throwable $error) {
                $metadata['snapshot_unavailable'] = TRUE;
                log_message('error', 'Audit result snapshot failed: ' . $error->getMessage());
            }
            if (!$this->audit_event(trim(uri_string(), '/'), NULL, $metadata, NULL, $type === 'danger' ? 'failed' : 'success')) {
                $message .= ' Audit logging failed. Contact your administrator.';
                if ($type === 'success') $type = 'warning';
            }
        }
        $this->session->set_flashdata('notice', $message);
        $this->session->set_flashdata('notice_type', $type);
    }

    protected function render($title, $view, $data = [])
    {
        $this->authenticate();
        if ($this->input->method(TRUE) === 'GET' && $this->input->get_request_header('X-PK-Table') !== '1')
            $this->audit_event('view/' . trim(uri_string(), '/'));
        $this->output->set_header('Cache-Control: no-store, private');
        $data['title'] = $title;
        $data['user'] = $this->user;
        $data['permissions'] = $this->Permission_model->for_user($this->user);
        $this->load->model('Notification_model');
        $data['navigation_counts'] = $this->Notification_model->for_user($this->user);
        $data['content_view'] = $view;
        // Reuse the same authorized view for in-place register updates.
        if ($this->input->method(TRUE) === 'GET' && $this->input->get_request_header('X-PK-Table') === '1') {
            $this->output->set_header('X-PK-Table: 1');
            $this->load->view($view, $data);
            return;
        }
        $this->load->view('layout/header', $data);
        $this->load->view('layout/sidebar_top_nav', $data);
        $this->load->view($view, $data);
        $this->load->view('layout/footer', $data);
    }

    protected function table_state($defaultSort = 'created_at')
    {
        require_once APPPATH . 'services/presentation/query_state.php';
        try {
            return Query_state::parse($this->input->get(NULL, TRUE) ?: [], $defaultSort);
        } catch (DomainException $e) {
            show_error($e->getMessage(), 400);
            exit;
        }
    }
}
