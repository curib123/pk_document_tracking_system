<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Places extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Place_model');
        require_once APPPATH.'services/places/place_service.php';
    }
    public function index($slug)
    {
        $cfg=$this->Place_model->config($slug);
        $this->require_permission($cfg['table'],'view');
        $state=$this->table_state($cfg['key']);
        list($rows,$total,$state['page'])=$this->Place_model->listing($cfg,
            $state['q'],$state['status'],$state['page'],$state['limit']);
        $options=[];
        foreach (['areas','specifics','assets','categories'] as $source) {
            $options[$source]=$this->Place_model->options($source);
        }
        $views=['area'=>'area','specific'=>'specific','asset'=>'asset_number',
            'location'=>'location','sequence'=>'sequence','softcopy-categories'=>'softcopy_category'];
        $this->render('Places · '.$cfg['label'],'pages/places/'.$views[$slug].'/index',
            ['cfg'=>$cfg,'rows'=>$rows,'total'=>$total,'table'=>$state,'options'=>$options]);
    }
    public function save($slug)
    {
        $cfg=$this->Place_model->config($slug);
        if (!empty($cfg['read_only'])) show_error('System sequences are read-only.', 403);
        $this->require_permission($cfg['table'],(int)$this->input->post('id')?'edit':'add');
        $this->confirmed();
        try {
            (new Place_service())->save($cfg,$this->input->post(),(int)$this->user['id']);
            $this->notice('Record saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('places/'.$slug);
    }
    public function deactivate($slug)
    {
        $cfg=$this->Place_model->config($slug);
        $this->require_permission($cfg['table'],'delete');
        $this->confirmed();
        try { (new Place_service())->deactivate($cfg,(int)$this->input->post('id'));
            $this->notice('Record deactivated.');
        } catch (DomainException $e) { $this->notice($e->getMessage(),'danger'); }
        redirect('places/'.$slug);
    }
}
