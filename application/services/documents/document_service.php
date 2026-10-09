<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Document_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); $this->ci->load->model('Document_model'); }
    public function save($domain,$post,$actor)
    {
        // Caller is the authenticated CI session user, never a posted role.
        $actorId=(int)$actor['id'];
        $isAdmin=strcasecmp((string)($actor['role']??''),'Administrator')===0;
        $cfg=$this->ci->Document_model->config($domain);
        $id=(int)($post['id']??0);
        $title=trim((string)($post['title']??''));
        if ($title==='' || mb_strlen($title)>255) throw new DomainException('Document title is required.');
        $data=['title'=>$title];
        if ($domain==='hardcopy') {
            foreach (['area_id','specific_id','asset_id','location_id'] as $field) {
                $data[$field]=!empty($post[$field])?(int)$post[$field]:NULL;
            }
            $this->validate_hardcopy_location($data, $id);

            $sequence=trim((string)($post['sequence_number']??''));
            if (mb_strlen($sequence)>100) throw new DomainException('Sequence / copy number is too long.');
            $data['sequence_number']=$sequence?:NULL;
            $data['retention_enabled']=!empty($post['retention_enabled'])?1:0;
            $data['retention_start_date']=$this->valid_date($post['retention_start_date']??'');
            $data['retention_end_date']=$this->valid_date($post['retention_end_date']??'');
            if ($data['retention_enabled'] && (!$data['retention_start_date'] ||
                !$data['retention_end_date'] ||
                $data['retention_end_date']<$data['retention_start_date'])) {
                throw new DomainException('Retention period requires valid start and end dates.');
            }
            if (!$data['retention_enabled']) {
                $data['retention_start_date']=NULL;
                $data['retention_end_date']=NULL;
            }

            $oldHolder=NULL;
            if ($id) {
                $existing=$this->ci->Document_model->find('hardcopy',$id);
                if (!$existing) throw new DomainException('Hardcopy document was not found.');
                $oldHolder=(int)$existing['holder_id'];
                if (!$isAdmin && $oldHolder!==$actorId) {
                    throw new DomainException('Only the current holder or an Administrator can edit this hardcopy.');
                }
            }

            // For non-admin users the session account is always the holder;
            // never accept an arbitrary holder_id or role from the request body.
            $holderId=$isAdmin
                ? ((int)($post['holder_id']??0) ?: ($oldHolder?:$actorId))
                : $actorId;
            if (!$this->ci->db->select('id')->get_where('users',[
                'id'=>$holderId,'active'=>1
            ])->row_array()) throw new DomainException('Choose an active document holder.');
            $data['holder_id']=$holderId;
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
    private function valid_date($value)
    {
        $value=trim((string)$value);
        if ($value==='') return NULL;
        $date=DateTime::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) {
            throw new DomainException('Enter a valid retention date.');
        }
        return $value;
    }

    /**
     * Resolve the active pk_dts Area > Specific > Asset hierarchy from the
     * actual database. A Location may have nullable ancestors in the source
     * schema; any non-null linked ancestors must match the document choices.
     */
    private function validate_hardcopy_location(&$data,$documentId)
    {
        $db=$this->ci->db;
        $area=(int)($data['area_id']??0);
        $specific=(int)($data['specific_id']??0);
        $asset=(int)($data['asset_id']??0);
        $location=(int)($data['location_id']??0);
        if ($location) {
            $row=$db->get_where('locations',['id'=>$location,'active'=>1])->row_array();
            if (!$row) throw new DomainException('Choose an active predefined location.');

            foreach (['area_id','specific_id','asset_id'] as $field) {
                $existing=(int)($data[$field]??0);
                $linked=(int)($row[$field]??0);
                if ($linked && $existing && $linked!==$existing) {
                    throw new DomainException('Selected location conflicts with '.$field.'.');
                }
                if ($linked) $data[$field]=$linked;
            }
            $area=(int)($data['area_id']??0);
            $specific=(int)($data['specific_id']??0);
            $asset=(int)($data['asset_id']??0);
            // Original pk_dts hardcopy_documents.location_id is UNIQUE.
            $db->from('hardcopy_documents')->where('location_id',$location);
            if ($documentId) $db->where('id !=',(int)$documentId);
            if ($db->count_all_results()>0) {
                throw new DomainException('This location is already assigned to another hardcopy document.');
            }
        }
        if ($asset) {
            $row=$db->select('b.id,b.specific_id,s.area_id')
                ->from('assets b')->join('specifics s','s.id=b.specific_id')
                ->join('areas a','a.id=s.area_id')
                ->where('b.id',$asset)->where('b.active',1)
                ->where('s.active',1)->where('a.active',1)
                ->get()->row_array();
            if (!$row) throw new DomainException('Choose an active asset within an active specific and area.');
            if (($specific && $specific!==(int)$row['specific_id']) ||
                ($area && $area!==(int)$row['area_id'])) {
                throw new DomainException('Asset does not match the selected area/specific.');
            }
            $specific=(int)$row['specific_id'];
            $area=(int)$row['area_id'];
        }
        if ($specific) {
            $row=$db->select('s.id,s.area_id')->from('specifics s')
                ->join('areas a','a.id=s.area_id')
                ->where('s.id',$specific)->where('s.active',1)
                ->where('a.active',1)->get()->row_array();
            if (!$row) throw new DomainException('Choose an active specific in an active area.');
            if ($area && $area!==(int)$row['area_id']) {
                throw new DomainException('Specific does not belong to the selected area.');
            }
            $area=(int)$row['area_id'];
        }
        if ($area && !$db->get_where('areas',['id'=>$area,'active'=>1])->row_array()) {
            throw new DomainException('Choose an active area.');
        }
        $data['area_id']=$area?:NULL;
        $data['specific_id']=$specific?:NULL;
        $data['asset_id']=$asset?:NULL;
        $data['location_id']=$location?:NULL;
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
