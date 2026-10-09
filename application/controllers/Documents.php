<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Documents extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Document_model');
        $this->load->model('Place_model');
        require_once APPPATH.'services/documents/document_service.php';
    }
    public function index($domain)
    {
        $c=$this->Document_model->config($domain);
        $this->require_permission($domain,'view');
        $state=$this->table_state('updated_at');
        list($rows,$total,$state['page'])=$this->Document_model->listing(
            $domain,$state['q'],$state['status'],$state['page'],$state['limit']);
        $options=[];
        foreach (['areas','specifics','assets','locations','categories'] as $name)
            $options[$name]=$this->Place_model->options($name);
        $options['users']=$this->Document_model->active_users();
        $view=$domain==='hardcopy'?'pages/hardcopy_document/index':'pages/softcopy_document/index';
        $this->render($c['title'],$view,[
            'cfg'=>$c,'rows'=>$rows,'total'=>$total,'table'=>$state,'options'=>$options
        ]);
    }
    public function save($domain)
    {
        $c=$this->Document_model->config($domain);
        $this->require_permission($domain,'direct');
        $this->confirmed();
        try {
            (new Document_service())->save($domain,$this->input->post(),(int)$this->user['id']);
            $this->notice('Document saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('documents/'.$domain);
    }
    public function dispose($domain)
    {
        $this->Document_model->config($domain);
        $this->require_permission($domain,'direct');
        $this->confirmed();
        try {
            (new Document_service())->dispose($domain,(int)$this->input->post('id'),
                (int)$this->user['id'],(string)$this->input->post('remark'));
            $this->notice('Document disposed and history recorded.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('documents/'.$domain);
    }
}
