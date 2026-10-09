<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Administration extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Administration_model');
        $this->load->library('Table_pager');
        require_once APPPATH . 'services/administration/Administration_service.php';
    }

    public function users()
    {
        $this->require_permission('users');
        $params = $this->table_pager->read(
            (array) $this->input->get(NULL, TRUE),
            ['name','username','email','role','leader','active'], 'name', ['0','1']
        );
        $total = $this->Administration_model->count_users($params['q'], $params['status']);
        $offset = $this->table_pager->clamp($params, $total);
        $rows = $this->Administration_model->page_users(
            $params['q'], $params['status'], $params['limit'], $offset,
            $params['sort'], $params['dir']
        );
        $this->render('User Management', 'pages/administration/users', [
            'module' => 'users', 'rows' => $rows,
            'roles_list' => $this->Administration_model->roles(),
            'leaders_list' => $this->Administration_model->user_options(),
            'table' => $params, 'total' => $total
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
        $params = $this->table_pager->read(
            (array) $this->input->get(NULL, TRUE),
            ['name','description','category','access'], 'name', ['system','custom']
        );
        $total = $this->Administration_model->count_roles($params['q'], $params['status']);
        $offset = $this->table_pager->clamp($params, $total);
        $rows = $this->Administration_model->page_roles(
            $params['q'], $params['status'], $params['limit'], $offset,
            $params['sort'], $params['dir']
        );
        $granted = $this->Administration_model->role_grants(array_column($rows, 'id'));
        $this->render('Roles & Permissions', 'pages/administration/roles', [
            'module' => 'roles', 'rows' => $rows,
            'permission_rows' => $this->Administration_model->permissions(),
            'granted' => $granted, 'table' => $params, 'total' => $total
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
        $types = ['softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign'];
        $params = $this->table_pager->read(
            (array) $this->input->get(NULL, TRUE),
            ['name','type','version','steps','default','active'], 'type',
            ['0','1'], ['type' => $types]
        );
        $total = $this->Administration_model->count_workflows(
            $params['q'], $params['status'], $params['filters']['type']
        );
        $offset = $this->table_pager->clamp($params, $total);
        $workflows = $this->Administration_model->page_workflows(
            $params['q'], $params['status'], $params['filters']['type'],
            $params['limit'], $offset, $params['sort'], $params['dir']
        );
        $steps = $this->Administration_model->workflow_steps_for(array_column($workflows, 'id'));
        $this->render('Workflow Builder', 'pages/administration/workflows', [
            'module' => 'workflows', 'rows' => $workflows, 'steps' => $steps,
            'users_list' => $this->Administration_model->user_options(),
            'roles_list' => $this->Administration_model->roles(),
            'table' => $params, 'total' => $total
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

    public function clone_workflow()
    {
        $this->require_permission('workflows', 'create');
        $this->confirmed();
        try {
            (new Administration_service())->clone_workflow((int) $this->input->post('id'));
            $this->notice('New editable workflow version created. Review the steps before setting it as default.');
        } catch (DomainException $e) {
            $this->notice($e->getMessage(), 'danger');
        }
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
