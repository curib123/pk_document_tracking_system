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
    /** Apply before COUNT, pagination, options or dashboard aggregation. */
    public function scope($domain, $viewer, $alias = 'd', $catalog = FALSE)
    {
        require_once APPPATH.'services/security/document_scope.php';
        $this->load->model('Permission_model');
        // Resolve capabilities before building a query: CI uses one shared query builder.
        $permissions=$this->Permission_model->for_user($viewer);
        return Document_scope::predicate($domain, $viewer['id']??0, $permissions, $alias, $catalog);
    }

    public function visible($domain, $id, $viewer, $catalog = FALSE)
    {
        $cfg=$this->config($domain);
        $scope=$this->scope($domain,$viewer,'d',$catalog);
        return $this->db->from($cfg['table'].' d')->where('d.id',(int)$id)
            ->where($scope,NULL,FALSE)->limit(1)->get()->row_array();
    }

    public function write_scope($domain,$viewer,$alias='d')
    {
        require_once APPPATH.'services/security/document_scope.php';
        $this->load->model('Permission_model');
        return Document_scope::write_predicate($domain,$viewer['id']??0,
            $this->Permission_model->for_user($viewer),$alias);
    }

    public function manageable($domain,$id,$viewer)
    {
        $cfg=$this->config($domain);
        $scope=$this->write_scope($domain,$viewer);
        return $this->db->from($cfg['table'].' d')->where('d.id',(int)$id)
            ->where('d.status','active')->where($scope,NULL,FALSE)->limit(1)->get()->row_array();
    }

    private function base_query($domain,$search,$status,$browser,$scope,$filters=[])
    {
        $c=$this->config($domain);
        $this->db->from($c['table'].' d')->where($scope,NULL,FALSE);
        if ($browser!==NULL) {
            $this->load->model('Folder_model');
            $this->Folder_model->scope_documents($domain,$browser,'d');
        }
        if ($status!=='' && in_array($status,['active','disposed','archived','cancelled'],TRUE))
            $this->db->where('d.status',$status);
        foreach (['from'=>' >=','to'=>' <='] as $name=>$operator) {
            if (!empty($filters[$name])) $this->db->where('d.created_at'.$operator,
                $filters[$name].($name==='to'?' 23:59:59':' 00:00:00'));
        }
        if (!empty($filters['owner'])) $this->db->where('d.created_by',(int)$filters['owner']);
        if ($search!=='') {
            $this->db->group_start()->like('d.title',$search);
            if ($domain==='softcopy') $this->db->or_like('d.document_number',$search);
            else $this->db->or_like('d.sequence_number',$search);
            $this->db->group_end();
        }
    }
    public function listing($domain,$q,$status,$page,$limit,$browser,$viewer,$filters=[])
    {
        $scope=$this->scope($domain,$viewer);
        $writeScope=$this->write_scope($domain,$viewer);
        $this->base_query($domain,$q,$status,$browser,$scope,$filters);
        $total=(int)$this->db->count_all_results();
        $page=min(max(1,$page),max(1,(int)ceil($total/$limit)));
        $this->base_query($domain,$q,$status,$browser,$scope,$filters);
        $this->db->select('d.*, CONCAT_WS(" ", u.first_name,u.last_name) AS creator_name')->select('('.$writeScope.') AS can_write',FALSE);
        $this->db->join('users u','u.id=d.created_by');
        if ($domain==='softcopy') {
            $this->db->join('categories c','c.id=d.category_id','left')
                ->select('c.name AS category_name')->join('softcopy_revisions cr','cr.id=d.current_revision_id','left')
                ->select('cr.revision_number,cr.new_revision_level,cr.new_effective_date,cr.page_number,cr.date_received,cr.date_released');
        } else {
            $this->db->join('areas a','a.id=d.area_id','left')->select('a.name AS area_name')
                ->join('specifics s','s.id=d.specific_id','left')->select('s.name AS specific_name')
                ->join('assets b','b.id=d.asset_id','left')->select('b.asset_number AS asset_name')
                ->join('locations l','l.id=d.location_id','left')->select('l.name AS location_name,l.code AS location_code')
                ->join('users h','h.id=d.holder_id','left')
                ->select('CONCAT_WS(" ",h.first_name,h.last_name) AS holder_name');
        }
        $sorts=['title'=>'d.title','status'=>'d.status','updated_at'=>'d.updated_at',
            'created_at'=>'d.created_at','document_number'=>'d.document_number',
            'sequence_number'=>'d.sequence_number'];
        if ($domain==='hardcopy') unset($sorts['document_number']);
        else unset($sorts['sequence_number']);
        $sort=$sorts[$filters['sort']??'']??'d.updated_at';
        $direction=($filters['dir']??'DESC')==='ASC'?'ASC':'DESC';
        $rows=$this->db->order_by($sort,$direction)->order_by('d.id',$direction)
            ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }
    public function find($domain,$id)
    {
        $c=$this->config($domain);
        return $this->db->get_where($c['table'],['id'=>(int)$id])->row_array();
    }
    public function owner_options($domain,$viewer)
    {
        $scope=$this->scope($domain,$viewer);
        $table=$this->config($domain)['table'];
        return $this->db->distinct()->select('u.id,CONCAT_WS(" ",u.first_name,u.last_name) AS name',FALSE)
            ->from($table.' d')->join('users u','u.id=d.created_by')
            ->where($scope,NULL,FALSE)->order_by('name')->get()->result_array();
    }

    public function options($domain,$viewer,$catalog=FALSE,$direct=FALSE)
    {
        $c=$this->config($domain);
        $scope=$direct?$this->write_scope($domain,$viewer):$this->scope($domain,$viewer,'d',$catalog);
        $this->db->from($c['table'].' d')->where('d.status','active')->where($scope,NULL,FALSE);
        if ($catalog) return $this->db->select($domain==='softcopy'
                ? 'd.id,d.document_number,d.title' : 'd.id,d.title,d.sequence_number')
            ->order_by('d.title')->get()->result_array();
        return $this->db->select($domain==='softcopy'
              ? 'id,document_number,title,series_number,category_id,version' :
              'id,title,area_id,specific_id,asset_id,location_id,sequence_number,
               retention_enabled,retention_start_date,retention_end_date,holder_id')
            ->order_by('title')->get()->result_array();
    }

    // Admin assignment choices are constrained to the currently open folder.
    public function options_in_folder($domain,$browser,$q='')
    {
        $c=$this->config($domain);
        $this->load->model('Folder_model');
        $this->db->from($c['table'].' d')->where('d.status','active');
        $this->Folder_model->scope_documents($domain,$browser,'d');
        $columns=$domain==='softcopy'
            ? 'd.id,d.document_number,d.title,d.category_id'
            : 'd.id,d.title,d.sequence_number,d.area_id,d.specific_id,
               d.asset_id,d.location_id,d.holder_id';
        if ($q!=='') $this->db->group_start()->like('d.title',$q)
            ->or_like($domain==='softcopy'?'d.document_number':'d.sequence_number',$q)->group_end();
        return $this->db->select($columns)->order_by('d.title')
            ->limit(500)->get()->result_array();
    }

    /** Validate one selected assignment document, independent of display limits. */
    public function available_in_folder($domain,$id,$browser)
    {
        $config=$this->config($domain);
        $this->load->model('Folder_model');
        $this->db->from($config['table'].' d')->where('d.id',(int)$id)->where('d.status','active');
        $this->Folder_model->scope_documents($domain,$browser,'d');
        return $this->db->count_all_results()>0;
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
    public function transfer_sources($viewer)
    {
        $scope=$this->scope('hardcopy',$viewer);
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
            ->where('d.status','active')->where($scope,NULL,FALSE)
            ->order_by('d.title')->order_by('d.id')->get()->result_array();
    }

    public function active_users()
    {
        return $this->db->select('id,CONCAT_WS(" ",first_name,last_name) AS name',FALSE)
            ->from('users')->where('active',1)->order_by('first_name')->get()->result_array();
    }
}
