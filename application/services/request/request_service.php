<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Request_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); $this->ci->load->model('Request_model'); }

    public function save($tab,$post,$user,$attachment=NULL)
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
          'series_number'=>trim((string)($post['series_number']??'')),
          'category_id'=>(int)($post['category_id']??0),
          'recipient_id'=>(int)($post['recipient_id']??0),
          'expires_at'=>trim((string)($post['expires_at']??'')),
          'destination_location_id'=>(int)($post['destination_location_id']??0),
          'new_revision_level'=>trim((string)($post['new_revision_level']??'')),
          'effective_date'=>trim((string)($post['effective_date']??'')),
          'date_received'=>trim((string)($post['date_received']??'')),
          'date_released'=>trim((string)($post['date_released']??'')),
          'page_number'=>max(1,(int)($post['page_number']??1)),
          'area_id'=>(int)($post['area_id']??0),
          'specific_id'=>(int)($post['specific_id']??0),
          'asset_id'=>(int)($post['asset_id']??0),
          'location_id'=>(int)($post['location_id']??0),
          'sequence_number'=>trim((string)($post['sequence_number']??'')),
          'holder_id'=>(int)($post['holder_id']??0),
          'retention_enabled'=>!empty($post['retention_enabled'])?1:0,
          'retention_start_date'=>trim((string)($post['retention_start_date']??'')),
          'retention_end_date'=>trim((string)($post['retention_end_date']??'')),
          'creation_reason'=>trim((string)($post['creation_reason']??''))
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
        $fileId=NULL;
        $fileField=$type==='softcopy_create'?'controlled_file_id':'revision_file_id';
        if (in_array($type,['softcopy_create','softcopy_revise'],TRUE)) {
            if ($type==='softcopy_revise') {
                if (!$this->ci->db->get_where('softcopy_documents',[
                    'id'=>$softcopyId,'status'=>'active'
                ])->row_array()) throw new DomainException('Choose an active softcopy to revise.');
                require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
                (new Softcopy_operation_service())->validate_revision($payload);
            }
            if (!empty($attachment['name'])) {
                require_once APPPATH.'services/files/file_service.php';
                $files=new File_service();
                $fileId=$type==='softcopy_create'
                    ? $files->stage_creation((int)$user['id'],$attachment)
                    : $files->stage_revision($softcopyId,(int)$user['id'],$attachment);
            }
            if ($id) {
                $existing=$this->ci->Request_model->request($id);
                $old=json_decode($existing['payload']??'{}',TRUE)?:[];
                if (!$existing || (int)$existing['requested_by']!==(int)$user['id'] ||
                    !in_array($existing['status'],['draft','returned'],TRUE) ||
                    $existing['type']!==$type) {
                    throw new DomainException('Only your matching draft may be edited.');
                }
                $payload[$fileField]=$fileId?:((int)($old[$fileField]??0));
            } elseif ($fileId) {
                $payload[$fileField]=$fileId;
            }
        }
        if (in_array($type,['hardcopy_create','hardcopy_update'],TRUE)) {
            $payload['holder_id']=strcasecmp((string)$user['role'],'Administrator')===0
                ? ($payload['holder_id']?:$user['id'])
                : (int)$user['id'];
            if ($payload['title']==='') throw new DomainException('Hardcopy title is required.');
            // Match the direct hardcopy modal's validation before queuing approval.
            require_once APPPATH.'services/documents/document_service.php';
            (new Document_service())->validate_hardcopy_proposal($payload,$user,$hardcopyId);
        }

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
            if (in_array($r['type'],['softcopy_create','softcopy_revise'],TRUE)) {
                $payload=json_decode($r['payload'],TRUE)?:[];
                $creation=$r['type']==='softcopy_create';
                $file=$this->ci->db->get_where('files',[
                    'id'=>(int)($payload[$creation?'controlled_file_id':'revision_file_id']??0),
                    'document_id'=>$creation?NULL:(int)$r['softcopy_id'],
                    'uploaded_by'=>(int)$r['requested_by'],
                    'status'=>'pending','domain'=>'softcopy',
                    'purpose'=>$creation?'creation':'revision'
                ])->row_array();
                if (!$file) throw new DomainException('Upload a controlled file for review before submitting.');
            }
            if (in_array($r['type'],['hardcopy_create','hardcopy_update'],TRUE)) {
                require_once APPPATH.'services/documents/document_service.php';
                $payload=json_decode($r['payload'],TRUE)?:[];
                $owner=$this->ci->db->select('u.*,r.name AS role')->from('users u')
                    ->join('roles r','r.id=u.role_id')
                    ->where('u.id',$r['requested_by'])->get()->row_array();
                if (!$owner) throw new DomainException('Requester is no longer active.');
                (new Document_service())->validate_hardcopy_proposal($payload,$owner,(int)$r['hardcopy_id']);
            }
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

    // Final workflow approval writes the original domain tables atomically.
    private function applyApproved($request,$actor)
    {
        $payload=json_decode($request['payload'],TRUE)?:[];
        $type=$request['type'];
        $id=(int)$request['id'];
        $owner=(int)$request['requested_by'];
        $soft=(int)$request['softcopy_id'];
        $hard=(int)$request['hardcopy_id'];
        $reason=trim((string)($payload['remarks']??''));
        $title=trim((string)($payload['title']??''));
        $recipient=(int)($payload['recipient_id']??0);
        if ($type==='softcopy_create') {
            require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
            return (new Softcopy_operation_service())->apply(
                $type,$payload,$soft,$actor,$owner,$id
            );
        }
        if ($type==='hardcopy_create') {
            require_once APPPATH.'services/documents/document_service.php';
            return (new Document_service())->apply_hardcopy_request(
                'hardcopy_create',$payload,$owner,$actor,$id,0
            );
        }
        if ($type==='softcopy_revise') {
            require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
            return (new Softcopy_operation_service())->apply(
                $type,$payload,$soft,$actor,$owner,$id
            );
        }
        if ($type==='hardcopy_update') {
            require_once APPPATH.'services/documents/document_service.php';
            return (new Document_service())->apply_hardcopy_request(
                'hardcopy_update',$payload,$owner,$actor,$id,$hard
            );
        }
        if ($type==='softcopy_cancel') {
            require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
            return (new Softcopy_operation_service())->apply(
                $type,$payload,$soft,$actor,$owner,$id
            );
        }
        if ($type==='assignment') {
            if (!$soft || !$recipient) throw new DomainException('Choose a document and assignee.');
            $match=$this->ci->db->get_where('assignments',[
                'softcopy_id'=>$soft,'user_id'=>$recipient
            ])->row_array();
            if ($match) $this->ci->db->where('id',$match['id'])->update('assignments',[
                'active'=>1,'assigned_by'=>$actor,'assigned_at'=>date('Y-m-d H:i:s')
            ]);
            else $this->ci->db->insert('assignments',[
                'softcopy_id'=>$soft,'user_id'=>$recipient,'assigned_by'=>$actor
            ]);
            return ['softcopy_id'=>$soft,'assigned_to'=>$recipient];
        }
        if ($type==='access') {
            if (!$soft || !$recipient || empty($payload['expires_at']))
                throw new DomainException('Document, recipient and expiry are required.');
            $expires=$payload['expires_at'].' 23:59:59';
            if (strtotime($expires)<time()) throw new DomainException('Access expiry has already passed.');
            $this->ci->db->insert('access_grants',[
                'request_id'=>$id,'domain'=>'softcopy','document_id'=>$soft,
                'user_id'=>$recipient,'granted_by'=>$actor,
                'expires_at'=>$expires,'reason'=>$reason
            ]);
            return ['grant_id'=>$this->ci->db->insert_id()];
        }
        if ($type==='transfer') {
            $doc=$this->ci->db->get_where('hardcopy_documents',['id'=>$hard,'status'=>'active'])->row_array();
            $loc=$this->ci->db->get_where('locations',[
                'id'=>(int)($payload['destination_location_id']??0),'active'=>1
            ])->row_array();
            if (!$doc || !$loc || !$recipient) throw new DomainException('Hardcopy, recipient and destination must be active.');
            $destination=[
                'area_id'=>$loc['area_id'],'specific_id'=>$loc['specific_id'],
                'asset_id'=>$loc['asset_id'],'location_id'=>$loc['id'],
                'recipient_id'=>$recipient
            ];
            $this->ci->db->insert('transfers',[
                'request_id'=>$id,'hardcopy_id'=>$hard,
                'origin'=>json_encode($doc,JSON_UNESCAPED_UNICODE),
                'destination'=>json_encode($destination),
                'current_holder_id'=>$doc['holder_id'],'recipient_id'=>$recipient,
                'document_copy_number'=>$payload['document_number']??$doc['sequence_number']??'',
                'reason'=>$reason,'status'=>'for_transfer','recipient_status'=>'pending'
            ]);
            return ['transfer_id'=>$this->ci->db->insert_id()];
        }
        if ($type==='disposal') {
            $doc=$this->ci->db->get_where('hardcopy_documents',['id'=>$hard])->row_array();
            if (!$doc || $doc['status']==='disposed') throw new DomainException('Hardcopy already disposed or missing.');
            $this->ci->db->insert('disposals',[
                'request_id'=>$id,'domain'=>'hardcopy','document_id'=>$hard,
                'previous_status'=>$doc['status'],'previous_state'=>json_encode($doc),
                'disposal_action'=>'dispose','remarks'=>$reason,'disposed_by'=>$actor
            ]);
            $this->ci->db->where('id',$hard)->update('hardcopy_documents',[
                'previous_status'=>$doc['status'],'status'=>'disposed','location_id'=>NULL
            ]);
            $this->ci->db->insert('status_history',[
                'domain'=>'hardcopy','document_id'=>$hard,'previous_status'=>$doc['status'],
                'new_status'=>'disposed','action'=>'disposed','user_id'=>$actor,'remarks'=>$reason
            ]);
            $this->ci->db->where('domain','hardcopy')->where('document_id',$hard)
                ->where('revoked_at IS NULL',NULL,FALSE)->update('access_grants',[
                    'revoked_at'=>date('Y-m-d H:i:s'),'revoked_by'=>$actor,'status'=>'revoked'
                ]);
            return ['disposal'=>'recorded'];
        }
        throw new DomainException('Unsupported request action.');
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
                    $result=$this->applyApproved($r,(int)$user['id']);
                    $status=$r['type']==='transfer'?'approved':'completed';
                    $this->ci->db->where('id',$id)->update('requests',[
                       'status'=>$status,'result'=>json_encode($result),
                       'current_node'=>NULL,
                       'completed_at'=>$status==='completed'?date('Y-m-d H:i:s'):NULL
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
