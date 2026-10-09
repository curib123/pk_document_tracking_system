<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Document_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); $this->ci->load->model('Document_model'); }
    public function save($domain,$post,$actorId)
    {
        $cfg=$this->ci->Document_model->config($domain);
        $id=(int)($post['id']??0);
        $title=trim((string)($post['title']??''));
        if ($title==='' || mb_strlen($title)>255) throw new DomainException('Document title is required.');
        $data=['title'=>$title];
        if ($domain==='hardcopy') {
            foreach (['area_id','specific_id','asset_id','location_id'] as $field) {
                $data[$field]=!empty($post[$field])?(int)$post[$field]:NULL;
            }
            $data['sequence_number']=trim((string)($post['sequence_number']??'')) ?: NULL;
            $data['retention_enabled']=!empty($post['retention_enabled'])?1:0;
            $data['retention_start_date']=!empty($post['retention_start_date'])?$post['retention_start_date']:NULL;
            $data['retention_end_date']=!empty($post['retention_end_date'])?$post['retention_end_date']:NULL;
            $data['holder_id']=(int)($post['holder_id']??0) ?: $actorId;
            if ($data['retention_enabled'] && (!$data['retention_start_date'] || !$data['retention_end_date'] ||
                $data['retention_end_date']<$data['retention_start_date'])) {
                throw new DomainException('Retention period must include valid start and end dates.');
            }
        } else {
            $data['document_number']=trim((string)($post['document_number']??''));
            $data['series_number']=trim((string)($post['series_number']??'')) ?: NULL;
            $data['category_id']=(int)($post['category_id']??0);
            if ($data['document_number']==='' || !$data['category_id']) {
                throw new DomainException('Document number and category are required.');
            }
        }
        if (!$id) {
            $data['created_by']=$actorId;
            $data['creation_source']='direct';
            $data['creation_reason']=trim((string)($post['creation_reason']??''));
        }
        $this->ci->db->trans_begin();
        if ($id) {
            if (!$this->ci->Document_model->find($domain,$id)) {
                $this->ci->db->trans_rollback();throw new DomainException('Document was not found.');
            }
            $this->ci->db->where('id',$id)->update($cfg['table'],$data);
        } else $this->ci->db->insert($cfg['table'],$data);
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Document save failed. Check unique numbers and existing related records.');
        }
        $this->ci->db->trans_commit();
    }
    public function dispose($domain,$id,$actorId,$reason='')
    {
        $c=$this->ci->Document_model->config($domain);
        $row=$this->ci->Document_model->find($domain,$id);
        if (!$row || $row['status']==='disposed') throw new DomainException('Active document was not found.');
        $this->ci->db->trans_begin();
        $this->ci->db->where('id',$id)->update($c['table'],['previous_status'=>$row['status'],'status'=>'disposed']);
        $this->ci->db->insert('disposals',[
            'domain'=>$domain,'document_id'=>$id,'previous_status'=>$row['status'],
            'previous_state'=>json_encode($row,JSON_UNESCAPED_UNICODE),
            'disposal_action'=>'dispose','remarks'=>trim($reason),'disposed_by'=>$actorId
        ]);
        $this->ci->db->insert('status_history',[
            'domain'=>$domain,'document_id'=>$id,'previous_status'=>$row['status'],
            'new_status'=>'disposed','action'=>'direct_dispose','user_id'=>$actorId,
            'remarks'=>trim($reason)
        ]);
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();throw new DomainException('Disposal could not be recorded.');
        }
        $this->ci->db->trans_commit();
    }
}
