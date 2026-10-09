<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Request_model extends CI_Model
{
    private $groups=[
      'softcopy'=>['softcopy_create','softcopy_revise','softcopy_cancel'],
      'hardcopy'=>['hardcopy_create','hardcopy_update','disposal'],
      'hardcopy-transfer'=>['transfer'],
      'access-grant'=>['access'],
      'document-assign'=>['assignment']
    ];
    public function types($tab)
    {
        if (!isset($this->groups[$tab])) show_404();
        return $this->groups[$tab];
    }
    public function tabs() { return array_keys($this->groups); }
    private function scope($tab,$user,$task,$q,$status)
    {
        $this->db->from('requests r');
        $this->db->where_in('r.type',$this->types($tab));
        if ($task) {
            $this->db->join('workflow_steps ws',
              'ws.request_id=r.id AND ws.status="active"');
            $this->db->group_start()->where('ws.assigned_user_id',$user['id'])
                ->or_group_start()->where("JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.type')) = 'role'",NULL,FALSE)
                    ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.value')) AS UNSIGNED) = ".(int)$user['role_id'],NULL,FALSE)
                ->group_end()->group_end();
        } else $this->db->where('r.requested_by',$user['id']);
        if ($status!=='') $this->db->where('r.status',$status);
        if ($q!=='') $this->db->group_start()->like('r.reference',$q)
            ->or_like('r.type',$q)->group_end();
    }
    public function listing($tab,$user,$task,$q,$status,$page,$limit)
    {
        $this->scope($tab,$user,$task,$q,$status);
        $total=(int)$this->db->count_all_results();
        $page=min(max(1,$page),max(1,(int)ceil($total/$limit)));
        $this->scope($tab,$user,$task,$q,$status);
        $this->db->select('r.*,CONCAT_WS(" ",u.first_name,u.last_name) AS requester_name',
          FALSE)->join('users u','u.id=r.requested_by');
        if ($task) $this->db->select('ws.label AS step_label,ws.id AS step_id');
        $rows=$this->db->order_by('r.updated_at','DESC')->order_by('r.id','DESC')
          ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }
    public function histories($ids)
    {
        if (!$ids) return [];
        $rows=$this->db->select('request_id,step_id,action,user_name,position_title,comments,created_at')
            ->from('workflow_history')->where_in('request_id',$ids)
            ->order_by('created_at','ASC')->order_by('id','ASC')->get()->result_array();
        $result=[];
        foreach ($rows as $r) $result[$r['request_id']][]=$r;
        return $result;
    }
    public function active_workflow($type)
    {
        return $this->db->select('v.*,w.name AS workflow_name')
            ->from('workflows w')->join('workflow_versions v','v.workflow_id=w.id')
            ->where('w.request_type',$type)->where('w.active',1)
            ->where('v.is_default',1)->where('v.status','published')
            ->order_by('v.version_number','DESC')->limit(1)->get()->row_array();
    }
    public function request($id)
    {
        return $this->db->get_where('requests',['id'=>(int)$id])->row_array();
    }
}
