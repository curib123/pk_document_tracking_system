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
          'category_id'=>(int)($post['category_id']??0),
          'recipient_id'=>(int)($post['recipient_id']??0),
          'expires_at'=>trim((string)($post['expires_at']??'')),
          'destination_location_id'=>(int)($post['destination_location_id']??0),
          'new_revision_level'=>trim((string)($post['new_revision_level']??'')),
          'effective_date'=>trim((string)($post['effective_date']??'')),
          'date_received'=>trim((string)($post['date_received']??'')),
          'date_released'=>trim((string)($post['date_released']??'')),
          'page_number'=>max(1,(int)($post['page_number']??1))
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
        $revisionFile=NULL;
        if ($type==='softcopy_revise') {
            if (!$this->ci->db->get_where('softcopy_documents',[
                'id'=>$softcopyId, 'status'=>'active'
            ])->row_array()) throw new DomainException('Choose an active softcopy to revise.');
            if ($payload['new_revision_level']==='' ||
                strlen($payload['new_revision_level'])>30)
                throw new DomainException('Enter the proposed revision level.');
            foreach (['effective_date','date_received','date_released'] as $key) {
                $date=$payload[$key];
                $parsed=DateTime::createFromFormat('!Y-m-d',$date);
                if (!$parsed || $parsed->format('Y-m-d')!==$date)
                    throw new DomainException('Enter valid revision dates.');
            }
            if (!empty($attachment['name'])) {
                require_once APPPATH.'services/files/file_service.php';
                $revisionFile=(new File_service())->stage_revision(
                    $softcopyId,(int)$user['id'],$attachment
                );
            }
        }
        if ($id && $type==='softcopy_revise') {
            $existing=$this->ci->Request_model->request($id);
            $old=json_decode($existing['payload']??'{}',TRUE)?:[];
            $payload['revision_file_id']=$revisionFile ?:
                (int)($old['revision_file_id']??0);
        } elseif ($revisionFile) {
            $payload['revision_file_id']=$revisionFile;
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
            if ($r['type']==='softcopy_revise') {
                $payload=json_decode($r['payload'],TRUE)?:[];
                $file=$this->ci->db->get_where('files',[
                    'id'=>(int)($payload['revision_file_id']??0),
                    'document_id'=>(int)$r['softcopy_id'],
                    'uploaded_by'=>(int)$r['requested_by'],
                    'status'=>'pending', 'purpose'=>'revision'
                ])->row_array();
                if (!$file) throw new DomainException('Upload a revision attachment before submitting.');
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
            if ($title==='' || empty($payload['document_number']) || empty($payload['category_id']))
                throw new DomainException('Title, number and category are required to create a softcopy.');
            $this->ci->db->insert('softcopy_documents',[
                'title'=>$title,'document_number'=>$payload['document_number'],
                'category_id'=>(int)$payload['category_id'],'created_by'=>$owner,
                'creation_source'=>'request','creation_reason'=>$reason,'source_request_id'=>$id
            ]);
            $newId=(int)$this->ci->db->insert_id();
            $this->ci->db->where('id',$id)->update('requests',['softcopy_id'=>$newId]);
            return ['document_id'=>$newId];
        }
        if ($type==='hardcopy_create') {
            if ($title==='') throw new DomainException('A hardcopy title is required.');
            $this->ci->db->insert('hardcopy_documents',[
                'title'=>$title,'created_by'=>$owner,'holder_id'=>$owner,
                'creation_source'=>'request','creation_reason'=>$reason,'source_request_id'=>$id
            ]);
            $newId=(int)$this->ci->db->insert_id();
            $this->ci->db->where('id',$id)->update('requests',['hardcopy_id'=>$newId]);
            return ['document_id'=>$newId];
        }
        if ($type==='softcopy_revise') {
            $rows=$this->ci->db->query(
                'SELECT * FROM softcopy_documents WHERE id=? FOR UPDATE',[$soft]
            )->result_array();
            $doc=$rows[0]??NULL;
            $file=$this->ci->db->get_where('files',[
                'id'=>(int)($payload['revision_file_id']??0),
                'document_id'=>$soft, 'domain'=>'softcopy',
                'uploaded_by'=>$owner, 'purpose'=>'revision', 'status'=>'pending'
            ])->row_array();
            if (!$doc || $doc['status']!=='active' || !$file)
                throw new DomainException('Revision document or pending attachment is unavailable.');
            $numberRow=$this->ci->db->select_max('revision_number')
                ->get_where('softcopy_revisions',['document_id'=>$soft])->row_array();
            $old=$doc['current_revision_id']?$this->ci->db->get_where(
                'softcopy_revisions',['id'=>$doc['current_revision_id']])->row_array():NULL;
            $level=trim((string)($payload['new_revision_level']??''));
            if (!$level || ($old && $level===$old['new_revision_level']))
                throw new DomainException('New revision level must differ from current revision.');
            foreach (['effective_date','date_received','date_released'] as $key) {
                if (!isset($payload[$key]) ||
                    !DateTime::createFromFormat('!Y-m-d',(string)$payload[$key]))
                    throw new DomainException('Revision effective, received and released dates are required.');
            }
            $date=date('Y-m-d');
            $this->ci->db->insert('softcopy_revisions',[
                'document_id'=>$soft,'revision_number'=>(int)($numberRow['revision_number']??0)+1,
                'reason'=>$reason,'effective_date'=>$payload['effective_date'],
                'page_number'=>max(1,(int)($payload['page_number']??1)),
                'series_number'=>$doc['series_number'],
                'document_title'=>$title?:$doc['title'],
                'previous_revision_level'=>$old['new_revision_level']??NULL,
                'new_revision_level'=>$level,
                'previous_effective_date'=>$old['new_effective_date']??NULL,
                'new_effective_date'=>$payload['effective_date'],
                'date_received'=>$payload['date_received'],
                'date_released'=>$payload['date_released'],
                'approval_date'=>$date,'file_id'=>$file['id'],
                'uploaded_by'=>$owner,'approved_by'=>$actor
            ]);
            $revisionId=(int)$this->ci->db->insert_id();
            $this->ci->db->where('id',$soft)->update('softcopy_documents',[
                'current_revision_id'=>$revisionId,
                'title'=>$title?:$doc['title']
            ]);
            $this->ci->db->where('id',$file['id'])->update('files',[
                'status'=>'approved', 'approved_by'=>$actor,
                'approved_at'=>date('Y-m-d H:i:s')
            ]);
            return ['document_id'=>$soft,'revision_id'=>$revisionId,'file_id'=>$file['id']];
        }
        if ($type==='hardcopy_update') {
            if ($title==='') throw new DomainException('Updated hardcopy title is required.');
            $this->ci->db->where('id',$hard)->where('status','active')
                ->update('hardcopy_documents',['title'=>$title]);
            if (!$this->ci->db->affected_rows())
                throw new DomainException('Hardcopy was not updated; it may have changed.');
            return ['document_id'=>$hard];
        }
        if ($type==='softcopy_cancel') {
            $doc=$this->ci->db->get_where('softcopy_documents',['id'=>$soft])->row_array();
            if (!$doc || $doc['status']==='disposed') throw new DomainException('Softcopy is unavailable.');
            $this->ci->db->where('id',$soft)->update('softcopy_documents',[
                'previous_status'=>$doc['status'],'status'=>'cancelled'
            ]);
            $this->ci->db->insert('status_history',[
                'domain'=>'softcopy','document_id'=>$soft,'previous_status'=>$doc['status'],
                'new_status'=>'cancelled','action'=>'cancelled','user_id'=>$actor,'remarks'=>$reason
            ]);
            return ['document_id'=>$soft];
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
