<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Administration extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Administration_model');
        require_once APPPATH.'services/administration/administration_service.php';
    }
    private function listing($kind)
    {
        $map=['users'=>'users','roles'=>'roles','workflows'=>'workflows'];
        $module=$map[$kind];
        $this->require_permission($module,'view');
        $t=$this->table_state();
        list($rows,$total,$t['page'])=$this->Administration_model->$kind(
            $t['q'],$t['status'],$t['page'],$t['limit']);
        $views=['users'=>'pages/user/index',
            'roles'=>'pages/roles_and_permission/index',
            'workflows'=>'pages/workflow_builder/index'];
        $this->render(ucwords($kind),$views[$kind],[
            'kind'=>$kind,'rows'=>$rows,'total'=>$total,'table'=>$t,
            'roles'=>$this->Administration_model->role_options(),
            'users'=>$this->Administration_model->user_options(),
            'permission_rows'=>$kind==='roles'?$this->Administration_model->permissions():[],
            'grants'=>$kind==='roles'?$this->Administration_model->grants(array_column($rows,'id')):[]
        ]);
    }
    public function users(){$this->listing('users');}
    public function roles(){$this->listing('roles');}
    public function workflows(){$this->listing('workflows');}

    private function mutate($module,$action,$callback,$redirect)
    {
        $this->require_permission($module,$action);
        $this->confirmed();
        try { $callback();$this->notice('Changes saved.'); }
        catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect($redirect);
    }
    public function save_user() {
        $this->mutate('users',(int)$this->input->post('id')?'edit':'add',function(){
            (new Administration_service())->save_user($this->input->post(),$this->user['id']);
        },'admin/users');
    }
    public function deactivate_user() {
        $this->mutate('users','delete',function(){
            (new Administration_service())->deactivate_user((int)$this->input->post('id'),$this->user['id']);
        },'admin/users');
    }
    public function save_role() {
        $this->mutate('roles',(int)$this->input->post('id')?'edit':'add',function(){
            (new Administration_service())->save_role($this->input->post());
        },'admin/roles');
    }
    public function save_workflow() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->save_workflow($this->input->post(),$this->user['id']);
        },'admin/workflows');
    }
    public function save_workflow_step() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->save_workflow_step($this->input->post());
        },'admin/workflows');
    }
    public function remove_workflow_step() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->remove_workflow_step($this->input->post());
        },'admin/workflows');
    }
    public function publish_workflow() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->publish_workflow((int)$this->input->post('id'),$this->user['id']);
        },'admin/workflows');
    }
    public function clone_workflow() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->clone_workflow((int)$this->input->post('id'),$this->user['id']);
        },'admin/workflows');
    }
}
