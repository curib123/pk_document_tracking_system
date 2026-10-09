<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Requests extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Request_model');
        $this->load->model('Document_model');
        $this->load->model('Place_model');
        require_once APPPATH.'services/request/request_service.php';
    }
    private function listing($tab,$task)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests',$task?'manage':'view');
        $state=$this->table_state('updated_at');
        list($rows,$total,$state['page'])=$this->Request_model->listing(
           $tab,$this->user,$task,$state['q'],$state['status'],$state['page'],$state['limit']);
        $history=$this->Request_model->histories(array_column($rows,'id'));
        $this->render(($task?'My Tasks':'My Requests').' · '.ucwords(str_replace('-',' ',$tab)),
           $task?'pages/request/approving_assign_request/index':'pages/request/user_own_all_request/index',[
             'tab'=>$tab,'task'=>$task,'rows'=>$rows,'total'=>$total,'table'=>$state,
             'history'=>$history,
             'users'=>$this->Document_model->active_users(),
             'softcopy_options'=>$this->Document_model->options('softcopy'),
             'hardcopy_options'=>$this->Document_model->options('hardcopy'),
             'category_options'=>$this->Place_model->options('categories'),
             'location_options'=>$this->Place_model->options('locations')
           ]);
    }
    public function mine($tab) { $this->listing($tab,FALSE); }
    public function tasks($tab) { $this->listing($tab,TRUE); }
    public function save($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests','add');$this->confirmed();
        try {
            (new Request_service())->save($tab,$this->input->post(),$this->user);
            $this->notice('Request draft saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('my-requests/'.$tab);
    }
    public function submit($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests','submit');$this->confirmed();
        try {
            (new Request_service())->submit($tab,(int)$this->input->post('id'),$this->user);
            $this->notice('Request submitted for approval.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('my-requests/'.$tab);
    }
    public function decide($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests','manage');$this->confirmed();
        try {
            (new Request_service())->decide($tab,(int)$this->input->post('id'),
                $this->user,(string)$this->input->post('decision'),(string)$this->input->post('remark'));
            $this->notice('Approval decision recorded.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('my-tasks/'.$tab);
    }
    public function cancel($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests','cancel');$this->confirmed();
        try {
            (new Request_service())->cancel($tab,(int)$this->input->post('id'),$this->user);
            $this->notice('Draft cancelled.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('my-requests/'.$tab);
    }
}
