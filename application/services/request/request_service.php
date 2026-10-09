<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Request_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); $this->ci->load->model('Request_model'); }

    public function save($tab,$post,$user)
    {
        $allowed=$this->ci->Request_model->types($tab);
        $type=trim((string)($post['type']??$allowed[0]));
        if (!in_array($type,$allowed,TRUE)) throw new DomainException('Request type does not match this tab.');
        $id=(int)($post['id']??0);
        $subject=trim((string)($post['subject']??''));
        if ($subject==='' || mb_strlen($subject)>255) throw new DomainException('Subject is required.');
        $payload=[
          'subject'=>$subject,
          'remarks'=>trim((string)($post['remarks']??'')),
          'title'=>trim((string)($post['title']??'')),
          'document_number'=>trim((string)($post['document_number']??'')),
          'category_id'=>(int)($post['category_id']??0),
          'recipient_id'=>(int)($post['recipient_id']??0),
          'expires_at'=>trim((string)($post['expires_at']??'')),
          'destination_location_id'=>(int)($post['destination_location_id']??0)
        ];
        $softcopyId=(int)($post['softcopy_id']??0);
        $hardcopyId=(int)($post['hardcopy_id']??0);
        if (in_array($type,['softcopy_revise','softcopy_cancel','assignment','access'],TRUE) &&
            !$softcopyId) throw new DomainException('Select a softcopy document.');
        if (in_array($type,['hardcopy_update','transfer','disposal'],TRUE) &&
            !$hardcopyId) throw new DomainException('Select a hardcopy document.');
        if (in_array($type,['assignment','access','transfer'],TRUE) && !$payload['recipient_id'])
            throw new DomainException('Select a recipient or assigned user.');
        if ($type==='access' && ($payload['expires_at']==='' ||
            strtotime($payload['expires_at'])===FALSE))
            throw new DomainException('Select an access expiry date.');
        if ($type==='transfer' && !$payload['destination_location_id'])
            throw new DomainException('Choose the destination location.');

        $data=['type'=>$type,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),
            'softcopy_id'=>$softcopyId?:NULL,'hardcopy_id'=>$hardcopyId?:NULL];
        if ($id) {
            $existing=$this->ci->Request_model->request($id);
            if (!$existing || $existing['requested_by']!=$user['id'] ||
                !in_array($existing['status'],['draft','returned'],TRUE)) {
                throw new DomainException('Only your drafts and returned requests are editable.');
            }
            $this->ci->db->where('id',$id)->update('requests',$data);
            if ($this->ci->db->error()['code']) throw new DomainException('Could not update draft.');
            return $id;
        }
        $data+=['reference'=>'REQ-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),
            'status'=>'draft','requested_by'=>$user['id']];
        if (!$this->ci->db->insert('requests',$data)) throw new DomainException('Could not create request.');
        return (int)$this->ci->db->insert_id();
    }

    public function submit($tab,$id,$user)
    {
        $this->ci->db->trans_begin();
        try {
            $rows=$this->ci->db->query('SELECT * FROM requests WHERE id=? FOR UPDATE',[$id])->result_array();
            $r=$rows[0]??NULL;
            if (!$r || $r['requested_by']!=$user['id'] ||
                !in_array($r['status'],['draft','returned'],TRUE) ||
                !in_array($r['type'],$this->ci->Request_model->types($tab),TRUE))
                throw new DomainException('Request cannot be submitted.');
            $workflow=$this->ci->Request_model->active_workflow($r['type']);
            if (!$workflow) throw new DomainException('A published default workflow is required.');
            $graph=json_decode($workflow['graph'],TRUE);
            $steps=$graph['steps']??[];
            if (!$steps) throw new DomainException('Workflow has no approval steps.');
            // Preserve prior workflow steps and their historical foreign keys
            // when a returned request is corrected and resubmitted.
            $existingSteps=$this->ci->db->from('workflow_steps')
                ->where('request_id',$id)->order_by('id')->get()->result_array();
            foreach ($steps as $i=>$step) {
                $approver=$step['approver']??[];
                $type=$approver['type']??'';
                if (!in_array($type,['user','role','requester_leader','requester'],TRUE))
                    throw new DomainException('Workflow contains unsupported approver type.');
                $assigned=NULL;
                if ($type==='user') $assigned=(int)($approver['value']??0);
                if ($type==='requester') $assigned=(int)$r['requested_by'];
                if ($type==='requester_leader') {
                    $owner=$this->ci->db->get_where('users',['id'=>$r['requested_by']])->row_array();
                    $assigned=$owner['leader_id']??NULL;
                }
                if ($type!=='role' && !$assigned) throw new DomainException('Workflow approver could not be resolved.');
                $stepData=[
                  'request_id'=>$id,'node_key'=>(string)($step['key']??'step_'.($i+1)),
                  'label'=>(string)($step['name']??'Review'),
                  'assignment'=>json_encode($approver),
                  'candidates'=>'[]','assigned_user_id'=>$assigned,
                  'status'=>$i===0?'active':'pending',
                  'decision'=>NULL,'comments'=>NULL,'acting_user_id'=>NULL,
                  'acting_name'=>NULL,'acting_position'=>NULL,'acted_at'=>NULL
                ];
                if (isset($existingSteps[$i])) {
                    $this->ci->db->where('id',$existingSteps[$i]['id'])->update('workflow_steps',$stepData);
                } else {
                    $this->ci->db->insert('workflow_steps',$stepData);
                }
            }
            $this->ci->db->where('id',$id)->update('requests',[
              'workflow_version_id'=>$workflow['id'],'snapshot'=>$workflow['graph'],
              'current_node'=>$steps[0]['key'],'status'=>'submitted',
              'submitted_at'=>date('Y-m-d H:i:s')
            ]);
            $this->history($id,NULL,'submitted',$user,NULL);
            if ($this->ci->db->trans_status()===FALSE) throw new DomainException('Workflow submission failed.');
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error','Request submission error '.$e->getMessage());
            throw new DomainException('Could not submit request.');
        }
    }

    private function history($id,$stepId,$action,$user,$comment)
    {
        $this->ci->db->insert('workflow_history',[
          'request_id'=>$id,'step_id'=>$stepId,'action'=>$action,'user_id'=>$user['id'],
          'user_name'=>$user['name'],'position_title'=>$user['position_title']?:'',
          'comments'=>$comment
        ]);
    }

    public function decide($tab,$id,$user,$decision,$comment)
    {
        if (!in_array($decision,['approved','rejected','returned'],TRUE))
            throw new DomainException('Invalid decision.');
        $this->ci->db->trans_begin();
        try {
            $locked=$this->ci->db->query('SELECT * FROM requests WHERE id=? FOR UPDATE',[$id])->result_array();
            $r=$locked[0]??NULL;
            if (!$r || $r['status']!=='submitted' ||
                !in_array($r['type'],$this->ci->Request_model->types($tab),TRUE))
                throw new DomainException('Request is not awaiting review.');
            $step=$this->ci->db->get_where('workflow_steps',[
                'request_id'=>$id,'node_key'=>$r['current_node'],'status'=>'active'
            ])->row_array();
            if (!$step) throw new DomainException('Workflow step is missing.');
            $assignment=json_decode($step['assignment'],TRUE);
            $role=$assignment['type']??'';
            $authorized=(int)$step['assigned_user_id']===(int)$user['id'] ||
               ($role==='role' && (int)($assignment['value']??0)===(int)$user['role_id']);
            if (!$authorized) throw new DomainException('Only the assigned approver may decide.');
            $this->ci->db->where('id',$step['id'])->update('workflow_steps',[
                'status'=>$decision,'decision'=>$decision,'comments'=>trim($comment),
                'acting_user_id'=>$user['id'],'acting_name'=>$user['name'],
                'acting_position'=>$user['position_title']?:'',
                'acted_at'=>date('Y-m-d H:i:s')
            ]);
            $this->history($id,$step['id'],$decision,$user,trim($comment));
            if ($decision==='approved') {
                $next=$this->ci->db->from('workflow_steps')->where('request_id',$id)
                    ->where('status','pending')->order_by('id')->limit(1)->get()->row_array();
                if ($next) {
                    $this->ci->db->where('id',$next['id'])->update('workflow_steps',['status'=>'active']);
                    $this->ci->db->where('id',$id)->update('requests',['current_node'=>$next['node_key']]);
                } else {
                    $this->ci->db->where('id',$id)->update('requests',[
                       'status'=>'approved','current_node'=>NULL,'completed_at'=>date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                $this->ci->db->where('id',$id)->update('requests',[
                    'status'=>$decision,'current_node'=>NULL,'completed_at'=>date('Y-m-d H:i:s')
                ]);
            }
            if ($this->ci->db->trans_status()===FALSE) throw new DomainException('Approval decision failed.');
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error','Workflow decision error '.$e->getMessage());
            throw new DomainException('Could not save decision.');
        }
    }

    public function cancel($tab,$id,$user)
    {
        $r=$this->ci->Request_model->request($id);
        if (!$r || $r['requested_by']!=$user['id'] ||
            !in_array($r['type'],$this->ci->Request_model->types($tab),TRUE) ||
            $r['status']!=='draft') throw new DomainException('Only your draft can be cancelled.');
        $this->ci->db->where('id',$id)->update('requests',['status'=>'cancelled']);
    }
}
