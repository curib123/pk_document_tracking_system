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
    /** Administrative direct assignment. Approval requests remain in My Requests. */
    public function document_assignments()
    {
        $this->authenticate();
        if (strcasecmp((string)$this->user['role'],'Administrator')!==0)
            show_error('Administrator access is required.',403);
        $this->load->model('Document_model');
        $this->load->model('Folder_model');
        $domain=(string)($this->input->get('domain',TRUE)?:'hardcopy');
        if (!in_array($domain,['hardcopy','softcopy'],TRUE)) show_404();
        $folder=trim((string)$this->input->get('folder',TRUE));
        if (strlen($folder)>100) show_404();
        $browser=$this->Folder_model->browse($domain,$folder);
        $this->render('Assign Documents','pages/administration/document_assignments',[
            'selected_domain'=>$domain,'folder_value'=>$browser['folder'],
            'folder_browser'=>$browser,'folder_base'=>'admin/document-assignments',
            'folder_params'=>['domain'=>$domain],
            'softcopy_options'=>$domain==='softcopy'?
                $this->Document_model->options_in_folder('softcopy',$browser):[],
            'hardcopy_options'=>$domain==='hardcopy'?
                $this->Document_model->options_in_folder('hardcopy',$browser):[],
            'users_list'=>$this->Administration_model->user_options(),
            'assignment_rows'=>$this->Administration_model->document_assignments($domain,$browser)
        ]);
    }
    public function save_document_assignment()
    {
        $this->authenticate();
        if (strcasecmp((string)$this->user['role'],'Administrator')!==0)
            show_error('Administrator access is required.',403);
        $this->confirmed();
        $domain=(string)$this->input->post('document_domain',TRUE);
        $payload=['document_domain'=>$domain];
        $soft=(int)$this->input->post('softcopy_id');
        $hard=(int)$this->input->post('hardcopy_id');
        $recipient=(int)$this->input->post('recipient_id');
        $folder=trim((string)$this->input->post('folder',TRUE));
        try {
            $this->load->model('Folder_model');
            $this->load->model('Document_model');
            $browser=$this->Folder_model->browse($domain,$folder);
            $documentId=$domain==='softcopy'?$soft:$hard;
            $allowed=array_column($this->Document_model->options_in_folder($domain,$browser),'id');
            if (!in_array($documentId,array_map('intval',$allowed),TRUE))
                throw new DomainException('The document is not available in the selected folder.');
            require_once APPPATH.'services/documents/document_access_service.php';
            $this->db->trans_begin();
            (new Document_access_service())->apply(
                'assignment',$payload,$soft,$hard,$recipient,(int)$this->user['id'],NULL
            );
            if ($this->db->trans_status()===FALSE)
                throw new DomainException('Assignment transaction failed.');
            $this->db->trans_commit();
            $this->notice('Document assigned successfully.');
        } catch (DomainException $e) {
            $this->db->trans_rollback();
            $this->notice($e->getMessage(),'danger');
        }
        $query=['domain'=>in_array($domain,['hardcopy','softcopy'],TRUE)?$domain:'hardcopy'];
        if ($folder!=='') $query['folder']=$folder;
        redirect('admin/document-assignments?'.http_build_query($query));
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
    public function move_workflow_step() {
        $this->mutate('workflows','edit',function(){
            (new Administration_service())->move_workflow_step($this->input->post());
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
