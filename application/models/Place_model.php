<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Place_model extends CI_Model
{
    private $definitions = [
        'area' => ['table'=>'areas','label'=>'Areas','key'=>'name','fields'=>['name']],
        'specific' => ['table'=>'specifics','label'=>'Specifics','key'=>'name','fields'=>['name','area_id']],
        'asset' => ['table'=>'assets','label'=>'Assets','key'=>'asset_number','fields'=>['asset_number','specific_id']],
        'location' => ['table'=>'locations','label'=>'Locations','key'=>'name',
            'fields'=>['name','code','area_id','specific_id','asset_id','archive_date']],
        'sequence' => ['table'=>'sequences','label'=>'Sequences','key'=>'sequence_key',
            'fields'=>['sequence_key','value']],
        'softcopy-categories' => ['table'=>'categories','label'=>'Softcopy Categories',
            'key'=>'name','fields'=>['name','folder_name','description','parent_id']],
    ];
    public function config($slug)
    {
        if (!isset($this->definitions[$slug])) show_404();
        $c = $this->definitions[$slug]; $c['slug'] = $slug;
        $c['active'] = $slug !== 'sequence';
        $c['read_only'] = $slug === 'sequence';
        return $c;
    }
    private function scope($c, $q, $status)
    {
        $this->db->from($c['table'] . ' p');
        if ($q !== '') $this->db->like('p.' . $c['key'], $q);
        if ($c['active'] && $status !== '' && in_array($status, ['0','1'], TRUE)) {
            $this->db->where('p.active',(int) $status);
        }
    }
    public function listing($c, $q, $status, $page, $limit)
    {
        $this->scope($c,$q,$status);
        $total = (int) $this->db->count_all_results();
        $page = min(max(1,$page),max(1,(int) ceil($total/$limit)));
        $this->scope($c,$q,$status);
        $this->db->select('p.*');
        $joins = [
            'specific'=>[['areas a','a.id=p.area_id','a.name AS area_name']],
            'asset'=>[['specifics s','s.id=p.specific_id','s.name AS specific_name']],
            'location'=>[['areas a','a.id=p.area_id','a.name AS area_name'],
                ['specifics s','s.id=p.specific_id','s.name AS specific_name'],
                ['assets b','b.id=p.asset_id','b.asset_number AS asset_name']],
            'softcopy-categories'=>[['categories par','par.id=p.parent_id','par.name AS parent_name']]
        ];
        foreach ($joins[$c['slug']] ?? [] as $join) {
            $this->db->join($join[0],$join[1],'left')->select($join[2]);
        }
        $rows = $this->db->order_by('p.'.$c['key'],'ASC')
            ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }
    public function options($table)
    {
        $defs=['areas'=>'name','specifics'=>'name','assets'=>'asset_number',
            'locations'=>'name','categories'=>'name'];
        if (!isset($defs[$table])) throw new DomainException('Unknown option list.');

        // Location Upsert: preload the real Area -> Specific -> Asset relationships.
        // No artificial presets, extra tables, APIs or invented identifiers.
        if ($table==='specifics') {
            return $this->db->select('s.id,s.name,s.area_id')
                ->from('specifics s')
                ->join('areas a','a.id=s.area_id')
                ->where('s.active',1)->where('a.active',1)
                ->order_by('s.name')->order_by('s.id')->get()->result_array();
        }
        if ($table==='assets') {
            return $this->db->select('b.id,b.asset_number AS name,b.specific_id,s.area_id')
                ->from('assets b')->join('specifics s','s.id=b.specific_id')
                ->join('areas a','a.id=s.area_id')
                ->where('b.active',1)->where('s.active',1)->where('a.active',1)
                ->order_by('b.asset_number')->order_by('b.id')->get()->result_array();
        }
        return $this->db->select('id,'.$defs[$table].' AS name')->from($table)
            ->where('active',1)->order_by($defs[$table])->get()->result_array();
    }
    public function find($c,$id)
    {
        return $this->db->get_where($c['table'],['id'=>(int)$id])->row_array();
    }
}
