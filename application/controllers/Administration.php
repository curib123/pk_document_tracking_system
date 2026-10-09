<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Administration extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Administration_model');
        require_once APPPATH . 'services/administration/Administration_service.php';
    }

    public function users()
    {
        $this->require_permission('users');
        $this->render('User Management', 'pages/administration/users', [
            'module' => 'users', 'rows' => $this->Administration_model->users(),
            'roles_list' => $this->Administration_model->roles()
        ]);
    }

    public function save_user()
    {
        $this->require_permission('users', (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Administration_service())->save_user($this->input->post());
            $this->notice('User saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/users');
    }

    public function delete_user()
    {
        $this->require_permission('users', 'delete');
        $this->confirmed();
        try {
            (new Administration_service())->deactivate_user((int) $this->input->post('id'), (int) $this->user['id']);
            $this->notice('User deactivated.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/users');
    }

    public function roles()
    {
        $this->require_permission('roles');
        $granted = $this->Administration_model->role_grants();
        $this->render('Roles & Permissions', 'pages/administration/roles', [
            'module' => 'roles', 'rows' => $this->Administration_model->roles(),
            'permission_rows' => $this->Administration_model->permissions(),
            'granted' => $granted
        ]);
    }

    public function save_role()
    {
        $this->require_permission('roles', (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Administration_service())->save_role($this->input->post());
            $this->notice('Role and permissions saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/roles');
    }

    public function workflows()
    {
        $this->require_permission('workflows');
        $workflows = $this->Administration_model->workflows();
        $steps = [];
        foreach ($workflows as $workflow) $steps[$workflow['id']] = $this->Administration_model->workflow_steps($workflow['id']);
        $this->render('Workflow Builder', 'pages/administration/workflows', [
            'module' => 'workflows', 'rows' => $workflows, 'steps' => $steps,
            'users_list' => $this->Administration_model->users(),
            'roles_list' => $this->Administration_model->roles()
        ]);
    }

    public function save_workflow()
    {
        $this->require_permission('workflows', (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Administration_service())->save_workflow($this->input->post());
            $this->notice('Workflow saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/workflows');
    }

    public function save_step()
    {
        $this->require_permission('workflows', 'edit');
        $this->confirmed();
        try {
            (new Administration_service())->save_step($this->input->post());
            $this->notice('Workflow step saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/workflows');
    }

    public function delete_step()
    {
        $this->require_permission('workflows', 'delete');
        $this->confirmed();
        try {
            (new Administration_service())->delete_step((int) $this->input->post('id'));
            $this->notice('Workflow step removed.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('admin/workflows');
    }
}
