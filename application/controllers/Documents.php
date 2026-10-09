<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Documents extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Document_model');
        $this->load->model('Place_model');
        $this->load->model('Folder_model');
        $this->load->model('File_model');
        require_once APPPATH.'services/documents/document_service.php';
    }
    public function index($domain)
    {
        $c=$this->Document_model->config($domain);
        $this->require_permission($domain,'view');
        $state=$this->table_state('updated_at');
        $folder=trim((string)$this->input->get('folder',TRUE));
        if (strlen($folder)>100) show_404();
        $browser=$this->Folder_model->browse($domain,$folder);
        list($rows,$total,$state['page'])=$this->Document_model->listing(
            $domain,$state['q'],$state['status'],$state['page'],$state['limit'],$browser,$this->user,$state);
        $options=[];
        foreach (['areas','specifics','assets','locations','categories'] as $name)
            $options[$name]=$this->Place_model->options($name);
        $options['users']=$this->Document_model->active_users();
        if ($domain==='hardcopy') {
            $options['locations']=$this->Document_model->hardcopy_locations();
        }
        $softcopy_options=$domain==='softcopy'?$this->Document_model->options('softcopy',$this->user,FALSE,TRUE):[];
        $category_options=$domain==='softcopy'?$options['categories']:[];
        $latest=[];
        $accessible=[];
        $filesByDocument=[];
        if ($domain==='softcopy') {
            $filesByDocument=$this->File_model->histories_for_documents(array_column($rows,'id'),$this->user);
            foreach ($filesByDocument as $documentId=>$history) {
                $accessible[$documentId]=TRUE;
                $latest[$documentId]=$history[0];
            }
        }
        $this->load->model('Document_detail_model');
        $documentDetails=$this->Document_detail_model->for_page($domain,$rows,$this->user,$filesByDocument);
        $view=$domain==='hardcopy'?'pages/hardcopy_document/index':'pages/softcopy_document/index';
        $this->render($c['title'],$view,[
            'cfg'=>$c,'document_details'=>$documentDetails,'owner_options'=>$this->Document_model->owner_options($domain,$this->user),'rows'=>$rows,'total'=>$total,'table'=>$state,'options'=>$options,
            'latest_files'=>$latest,'file_access'=>$accessible,'file_histories'=>$filesByDocument,
            'softcopy_options'=>$softcopy_options,'category_options'=>$category_options,
            'folder_browser'=>$browser,'folder_base'=>'documents/'.$domain,
            'folder_params'=>[
                'q'=>$state['q'],'status'=>$state['status'],'limit'=>$state['limit'],
                'sort'=>$state['sort'],'dir'=>strtolower($state['dir']),
                'from'=>$state['from'],'to'=>$state['to'],'owner'=>$state['owner'],
                'layout'=>$state['layout']
            ],
            'folder_value'=>$browser['folder'],'folder_page'=>$state['page'],
            'is_administrator'=>strcasecmp((string)$this->user['role'],'Administrator')===0
        ]);
    }
    public function direct_softcopy()
    {
        $this->require_permission('softcopy','direct');
        $this->confirmed();
        try {
            require_once APPPATH.'services/softcopy/softcopy_direct_service.php';
            if ($this->input->post('type')!=='softcopy_create' &&
                !$this->Document_model->manageable('softcopy',(int)$this->input->post('softcopy_id'),$this->user)) {
                throw new DomainException('Document is not available in your authorized scope.');
            }
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
        $this->require_permission('disposal','direct');
        $this->confirmed();
        try {
            require_once APPPATH.'services/documents/disposal_service.php';
            $payload=[
                'disposal_reason'=>$this->input->post('disposal_reason',TRUE),
                'disposal_other'=>$this->input->post('disposal_other',TRUE)
            ];
            if (!$this->Document_model->manageable($domain,(int)$this->input->post('id'),$this->user)) {
                throw new DomainException('Document is not available in your authorized scope.');
            }
            $reason=(new Disposal_service())->reason($payload);
            (new Document_service())->dispose($domain,(int)$this->input->post('id'),
                (int)$this->user['id'],$reason,$payload['disposal_reason']);
            $this->notice('Document disposed and history recorded.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('documents/'.$domain);
    }
}
