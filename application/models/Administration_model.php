<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Administration_model extends CI_Model
{
    private function count_and_page($table,$select,$joins,$filter,$q,$searchFields,$page,$limit,$sort)
    {
        $apply=function() use($table,$joins,$filter,$q,$searchFields) {
            $this->db->from($table);
            foreach ($joins as $join) $this->db->join($join[0],$join[1],$join[2]??'inner');
            foreach ($filter as $col=>$val) $this->db->where($col,$val);
            if ($q!=='') {
                $this->db->group_start();
                foreach ($searchFields as $i=>$field) {
                    if ($i===0) $this->db->like($field,$q);
                    else $this->db->or_like($field,$q);
                }
                $this->db->group_end();
            }
        };
        $apply();$total=(int)$this->db->count_all_results();
        $page=min(max(1,$page),max(1,(int)ceil($total/$limit)));
        $apply();
        $rows=$this->db->select($select,FALSE)->order_by($sort,'ASC')
            ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }
    public function users($q,$status,$page,$limit)
    {
        return $this->count_and_page('users u',
            'u.id,u.username,u.first_name,u.last_name,u.position_title,u.role_id,
             u.leader_id,u.active,u.require_password_change,u.version,
             r.name AS role_name,
             CONCAT_WS(" ",l.first_name,l.last_name) AS leader_name',
            [['roles r','r.id=u.role_id'],['users l','l.id=u.leader_id','left']],
            in_array($status,['0','1'],TRUE)?['u.active'=>(int)$status]:[],
            $q,['u.username','u.first_name','u.last_name'],$page,$limit,'u.username');
    }
    public function roles($q,$status,$page,$limit)
    {
        return $this->count_and_page('roles r','r.*',[],
            in_array($status,['0','1'],TRUE)?['r.active'=>(int)$status]:[],
            $q,['r.name'],$page,$limit,'r.name');
    }
    public function workflows($q,$status,$page,$limit)
    {
        $rows=$this->count_and_page('workflows w',
            'w.id,w.workflow_key,w.name,w.description,w.request_type,w.active,w.version',
            [],in_array($status,['0','1'],TRUE)?['w.active'=>(int)$status]:[],
            $q,['w.name','w.request_type'],$page,$limit,'w.name');
        $ids=array_column($rows[0],'id');
        if ($ids) {
            $versions=$this->db->select('*')->from('workflow_versions')
                ->where_in('workflow_id',$ids)->order_by('version_number','DESC')->get()->result_array();
            $versionsByWorkflow=[];
            foreach ($versions as $v) if (!isset($versionsByWorkflow[$v['workflow_id']])) {
                $versionsByWorkflow[$v['workflow_id']]=$v;
            }
            foreach ($rows[0] as &$w) {
                $v=$versionsByWorkflow[$w['id']]??NULL;
                $w['latest_version_id']=$v['id']??NULL;
                $w['version_number']=$v['version_number']??0;
                $w['version_status']=$v['status']??'not configured';
                $w['is_default']=$v['is_default']??0;
                $w['graph']=$v['graph']??'{"steps":[]}';
            }
            unset($w);
        }
        return $rows;
    }
    public function role_options()
    {
        return $this->db->select('id,name')->from('roles')->where('active',1)
            ->order_by('name')->get()->result_array();
    }
    public function user_options()
    {
        return $this->db->select('id,CONCAT_WS(" ",first_name,last_name) AS name',FALSE)
            ->from('users')->where('active',1)->order_by('first_name')->get()->result_array();
    }
    public function permissions()
    {
        return $this->db->select('id,module_key,module_label,action_key,action_label')
            ->from('permissions')->order_by('module_label')->order_by('action_label')
            ->get()->result_array();
    }
    public function grants($roleIds)
    {
        if (!$roleIds) return [];
        $out=[];
        $rows=$this->db->from('role_permissions')->where_in('role_id',$roleIds)->get()->result_array();
        foreach ($rows as $r) $out[$r['role_id']][]=(int)$r['permission_id'];
        return $out;
    }
}
