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
    private function scope($tab,$user,$task,$q,$status,$filters=[])
    {
        $this->db->from('requests r');
        $this->db->where_in('r.type',$this->types($tab));
        if ($task) {
            $this->db->where('r.status','submitted');
            $this->db->join('workflow_steps ws',
              'ws.request_id=r.id AND ws.status="active"');
            $this->db->group_start()->where('ws.assigned_user_id',$user['id'])
                ->or_group_start()->where("JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.type')) = 'role'",NULL,FALSE)
                    ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.value')) AS UNSIGNED) = ".(int)$user['role_id'],NULL,FALSE)
                ->group_end()->group_end();
        } else $this->db->where('r.requested_by',$user['id']);
        if ($status!=='') $this->db->where('r.status',$status);
        if (!empty($filters['type'])) $this->db->where('r.type',$filters['type']);
        foreach (['from'=>' >=','to'=>' <='] as $key=>$operator) {
            if (!empty($filters[$key])) $this->db->where('r.created_at'.$operator,
                $filters[$key].($key==='to'?' 23:59:59':' 00:00:00'));
        }
        if ($q!=='') $this->db->group_start()->like('r.reference',$q)
            ->or_like('r.type',$q)
            ->or_where("JSON_UNQUOTE(JSON_EXTRACT(r.payload,'$.subject')) LIKE ".
                $this->db->escape('%'.$this->db->escape_like_str($q).'%')." ESCAPE '!'",NULL,FALSE)
            ->group_end();
    }
    public function listing($tab,$user,$task,$q,$status,$page,$limit,$filters=[])
    {
        $this->scope($tab,$user,$task,$q,$status,$filters);
        $total=(int)$this->db->count_all_results();
        $page=min(max(1,$page),max(1,(int)ceil($total/$limit)));
        $this->scope($tab,$user,$task,$q,$status,$filters);
        $this->db->select('r.*,CONCAT_WS(" ",u.first_name,u.last_name) AS requester_name',
          FALSE)->join('users u','u.id=r.requested_by')
            ->join('softcopy_documents sd','sd.id=r.softcopy_id','left')
            ->join('hardcopy_documents hd','hd.id=r.hardcopy_id','left')
            ->select('COALESCE(sd.title,hd.title) AS source_title,COALESCE(sd.document_number,hd.sequence_number) AS source_reference',FALSE);
        if ($task) $this->db->select('ws.label AS step_label,ws.id AS step_id');
        $sorts=['reference'=>'r.reference','type'=>'r.type','status'=>'r.status',
            'updated'=>'r.updated_at','updated_at'=>'r.updated_at',
            'created_at'=>'r.created_at','requester'=>'requester_name'];
        $sort=$sorts[$filters['sort']??'']??'r.updated_at';
        $direction=($filters['dir']??'DESC')==='ASC'?'ASC':'DESC';
        $rows=$this->db->order_by($sort,$direction)->order_by('r.id',$direction)
          ->limit($limit,($page-1)*$limit)->get()->result_array();
        $rows=$this->details($rows);
        return [$rows,$total,$page];
    }
    /** Resolve the selected request's proposal into human-readable labels in batches. */
    private function details($rows)
    {
        $references=[
            'categories'=>['name',['category_id'=>'Proposed Category']],
            'areas'=>['name',['area_id'=>'Proposed Area','destination_area_id'=>'Destination Area']],
            'specifics'=>['name',['specific_id'=>'Proposed Specific','destination_specific_id'=>'Destination Specific']],
            'assets'=>['asset_number',['asset_id'=>'Proposed Asset','destination_asset_id'=>'Destination Asset']],
            'locations'=>['name',['location_id'=>'Proposed Location','destination_location_id'=>'Destination Location']],
            'users'=>["CONCAT_WS(' ',first_name,last_name)",['holder_id'=>'Proposed Holder','recipient_id'=>'Receiving / Assigned User']]
        ];
        $payloads=[];$lookups=[];
        foreach ($rows as $row) $payloads[$row['id']]=json_decode($row['payload'],TRUE)?:[];
        foreach ($references as $table=>[$label,$fields]) {
            $ids=[];
            foreach ($payloads as $payload) foreach ($fields as $field=>$unused) {
                if (!empty($payload[$field])) $ids[]=(int)$payload[$field];
            }
            if ($ids) $lookups[$table]=array_column($this->db->select('id,'.$label.' AS label',FALSE)
                ->from($table)->where_in('id',array_unique($ids))->get()->result_array(),'label','id');
        }
        foreach ($rows as &$row) {
            $payload=$payloads[$row['id']];$details=[];
            if (!empty($row['source_title'])) $details['Selected Document']=$row['source_title'];
            if (!empty($row['source_reference'])) $details['Document Reference']=$row['source_reference'];
            foreach (['title'=>'Proposed Title','document_number'=>'Proposed Document Number','series_number'=>'Series',
                'sequence_number'=>'Copy / Sequence Number','new_revision_level'=>'Proposed Revision Level',
                'effective_date'=>'Effective Date (optional)','date_received'=>'Received Date','date_released'=>'Released Date',
                'page_number'=>'Pages','expires_at'=>'Access Expiry','retention_start_date'=>'Retention Start',
                'retention_end_date'=>'Retention End','creation_reason'=>'Creation Reason',
                'disposal_reason'=>'Disposal Reason','disposal_other'=>'Other Disposal Details'] as $key=>$label) {
                if (isset($payload[$key]) && is_scalar($payload[$key]) && (string)$payload[$key]!=='') $details[$label]=$payload[$key];
            }
            foreach ($references as $table=>[$unused,$fields]) foreach ($fields as $field=>$label) {
                if (!empty($payload[$field])) $details[$label]=$lookups[$table][(int)$payload[$field]]??'Previously selected record is unavailable';
            }
            $row['detail_labels']=$details;
        }
        unset($row);
        return $rows;
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
    public function approval_routes($ids)
    {
        if (!$ids) return [];
        $rows=$this->db->select('s.request_id,s.node_key,s.label,s.assignment,
                s.assigned_name,s.status,s.decision,s.acting_name,s.acted_at')
            ->from('workflow_steps s')
            ->where_in('s.request_id',$ids)
            ->order_by('s.request_id')->order_by('s.id')
            ->get()->result_array();
        $out=[];
        foreach ($rows as $s) {
            $assignment=json_decode($s['assignment'],TRUE)?:[];
            $s['approver_type']=$assignment['type']??'unknown';
            $s['approver_label']=$s['assigned_name'] ?:
                ($assignment['label']??'Assigned approver');
            $out[$s['request_id']][]=$s;
        }
        return $out;
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
