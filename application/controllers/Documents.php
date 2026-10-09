<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Documents extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Document_model');
        $this->load->model('Place_model');
        $this->load->model('File_model');
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
        $softcopy_options=$domain==='softcopy'?$this->Document_model->options('softcopy'):[];
        $category_options=$domain==='softcopy'?$options['categories']:[];
        $latest=[];
        $accessible=[];
        $filesByDocument=[];
        if ($domain==='softcopy') {
            $latest=$this->File_model->latest_for_documents(array_column($rows,'id'));
            foreach ($rows as $doc) {
                $file=$latest[$doc['id']]??NULL;
                if (!$file) continue;
                $fileData=$this->File_model->approved_file($file['id']);
                if ($fileData && $this->File_model->allowed($this->user,$fileData)) {
                    $accessible[$doc['id']]=TRUE;
                    $filesByDocument[$doc['id']]=$this->File_model->history($doc['id']);
                }
            }
        }
        $view=$domain==='hardcopy'?'pages/hardcopy_document/index':'pages/softcopy_document/index';
        $this->render($c['title'],$view,[
            'cfg'=>$c,'rows'=>$rows,'total'=>$total,'table'=>$state,'options'=>$options,
            'latest_files'=>$latest,'file_access'=>$accessible,'file_histories'=>$filesByDocument,
            'softcopy_options'=>$softcopy_options,'category_options'=>$category_options
        ]);
    }
    public function direct_softcopy()
    {
        $this->require_permission('softcopy','direct');
        $this->confirmed();
        try {
            require_once APPPATH.'services/softcopy/softcopy_direct_service.php';
            (new Softcopy_direct_service())->execute(
                $this->input->post(),(int)$this->user['id'],
                $_FILES['revision_attachment']??NULL
            );
            $this->notice('Softcopy action approved and applied directly.');
        } catch (DomainException $e) {
            $this->notice($e->getMessage(),'danger');
        }
        redirect('documents/softcopy');
    }

    public function save($domain)
    {
        $c=$this->Document_model->config($domain);
        if ($domain==='softcopy') return $this->direct_softcopy();
        $this->require_permission($domain,'direct');
        $this->confirmed();
        try {
            (new Document_service())->save($domain,$this->input->post(),$this->user);
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
