<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Document_model extends CI_Model
{
    public function config($domain)
    {
        $defs=[
            'hardcopy'=>['table'=>'hardcopy_documents','title'=>'Hardcopy Documents',
              'fields'=>['title','area_id','specific_id','asset_id','location_id','sequence_number',
                  'retention_enabled','retention_start_date','retention_end_date','holder_id']],
            'softcopy'=>['table'=>'softcopy_documents','title'=>'Softcopy Documents',
              'fields'=>['document_number','title','series_number','category_id']]
        ];
        if (!isset($defs[$domain])) show_404();
        return $defs[$domain]+['domain'=>$domain];
    }
    private function base_query($domain,$search,$status,$browser=NULL)
    {
        $c=$this->config($domain);
        $this->db->from($c['table'].' d');
        if ($browser!==NULL) {
            $this->load->model('Folder_model');
            $this->Folder_model->scope_documents($domain,$browser,'d');
        }
        if ($status!=='' && in_array($status,['active','disposed','archived'],TRUE))
            $this->db->where('d.status',$status);
        if ($search!=='') {
            $this->db->group_start()->like('d.title',$search);
            if ($domain==='softcopy') $this->db->or_like('d.document_number',$search);
            else $this->db->or_like('d.sequence_number',$search);
            $this->db->group_end();
        }
    }
    public function listing($domain,$q,$status,$page,$limit,$browser=NULL)
    {
        $this->base_query($domain,$q,$status,$browser);
        $total=(int)$this->db->count_all_results();
        $page=min(max(1,$page),max(1,(int)ceil($total/$limit)));
        $this->base_query($domain,$q,$status,$browser);
        $this->db->select('d.*, CONCAT_WS(" ", u.first_name,u.last_name) AS creator_name');
        $this->db->join('users u','u.id=d.created_by');
        if ($domain==='softcopy') {
            $this->db->join('categories c','c.id=d.category_id','left')
                ->select('c.name AS category_name');
        } else {
            $this->db->join('areas a','a.id=d.area_id','left')->select('a.name AS area_name')
                ->join('specifics s','s.id=d.specific_id','left')->select('s.name AS specific_name')
                ->join('assets b','b.id=d.asset_id','left')->select('b.asset_number AS asset_name')
                ->join('locations l','l.id=d.location_id','left')->select('l.name AS location_name')
                ->join('users h','h.id=d.holder_id','left')
                ->select('CONCAT_WS(" ",h.first_name,h.last_name) AS holder_name');
        }
        $rows=$this->db->order_by('d.updated_at','DESC')->order_by('d.id','DESC')
            ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }
    public function find($domain,$id)
    {
        $c=$this->config($domain);
        return $this->db->get_where($c['table'],['id'=>(int)$id])->row_array();
    }
    public function options($domain)
    {
        $c=$this->config($domain);
        $this->db->from($c['table'])->where('status', 'active');
        return $this->db->select($domain==='softcopy'
              ? 'id,document_number,title' :
              'id,title,area_id,specific_id,asset_id,location_id,sequence_number,
               retention_enabled,retention_start_date,retention_end_date,holder_id')
            ->order_by('title')->get()->result_array();
    }

    // Admin assignment choices are constrained to the currently open folder.
    public function options_in_folder($domain,$browser)
    {
        $c=$this->config($domain);
        $this->load->model('Folder_model');
        $this->db->from($c['table'].' d')->where('d.status','active');
        $this->Folder_model->scope_documents($domain,$browser,'d');
        $columns=$domain==='softcopy'
            ? 'd.id,d.document_number,d.title,d.category_id'
            : 'd.id,d.title,d.sequence_number,d.area_id,d.specific_id,
               d.asset_id,d.location_id,d.holder_id';
        return $this->db->select($columns)->order_by('d.title')
            ->limit(500)->get()->result_array();
    }

    // Predefined active location choices carry the existing hierarchy.
    // They never require a new schema, and inactive parents are not selectable.
    public function hardcopy_locations()
    {
        return $this->db->select('l.id,l.name,l.code,l.area_id,l.specific_id,l.asset_id')
            ->from('locations l')
            ->join('areas a','a.id=l.area_id','left')
            ->join('specifics s','s.id=l.specific_id','left')
            ->join('assets b','b.id=l.asset_id','left')
            ->where('l.active',1)
            ->group_start()->where('l.area_id IS NULL',NULL,FALSE)
                ->or_where('a.active',1)->group_end()
            ->group_start()->where('l.specific_id IS NULL',NULL,FALSE)
                ->or_where('s.active',1)->group_end()
            ->group_start()->where('l.asset_id IS NULL',NULL,FALSE)
                ->or_where('b.active',1)->group_end()
            ->order_by('l.name')->order_by('l.id')->get()->result_array();
    }

    // Immutable original place/holder descriptions for transfer requests.
    public function transfer_sources()
    {
        return $this->db->select('d.id,d.title,d.sequence_number,d.area_id,
            d.specific_id,d.asset_id,d.location_id,d.holder_id,
            a.name AS area_name,s.name AS specific_name,b.asset_number AS asset_name,
            l.name AS location_name,l.code AS location_code,
            CONCAT_WS(" ",h.first_name,h.last_name) AS holder_name',FALSE)
            ->from('hardcopy_documents d')
            ->join('areas a','a.id=d.area_id','left')
            ->join('specifics s','s.id=d.specific_id','left')
            ->join('assets b','b.id=d.asset_id','left')
            ->join('locations l','l.id=d.location_id','left')
            ->join('users h','h.id=d.holder_id')
            ->where('d.status','active')
            ->order_by('d.title')->order_by('d.id')->get()->result_array();
    }

    public function active_users()
    {
        return $this->db->select('id,CONCAT_WS(" ",first_name,last_name) AS name',FALSE)
            ->from('users')->where('active',1)->order_by('first_name')->get()->result_array();
    }
}
