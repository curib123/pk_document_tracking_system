<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Request_service
{
    private $ci;
    private $stagedPaths=[];
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Request_model');
        $this->ci->load->model('Document_model');
        $this->ci->load->model('Permission_model');
        $this->ci->load->model('Identity_model');
        require_once APPPATH.'services/request/request_policy.php';
    }

    private function authorize($type,$softcopyId,$hardcopyId,array $payload,array $user): void
    {
        if (!$this->ci->Permission_model->allowed($user,Request_policy::module($type),'request'))
            throw new DomainException('Your role cannot request this document action.');
        if (in_array($type,['softcopy_create','hardcopy_create'],TRUE)) return;
        $catalog=in_array($type,['access','assignment'],TRUE);
        $domain=$catalog?($payload['document_domain']??'softcopy'):
            (str_starts_with($type,'softcopy_')?'softcopy':'hardcopy');
        if (!in_array($domain,['softcopy','hardcopy'],TRUE)) throw new DomainException('Choose a document domain.');
        $document=$this->ci->Document_model->visible($domain,$domain==='softcopy'?$softcopyId:$hardcopyId,$user,$catalog);
        if (!$document || $document['status']!=='active')
            throw new DomainException('Selected document is unavailable in your authorized scope.');
    }

    public function save($tab,$post,$user,$attachment=NULL)
    {
        $type=(string)($post['type']??'');
        if (!in_array($type,$this->ci->Request_model->types($tab),TRUE))
            throw new DomainException('Request type does not match this tab.');
        $id=(int)($post['id']??0);
        if (!$this->ci->Permission_model->allowed($user,'requests',$id?'edit':'add'))
            throw new DomainException('Request editing permission is required.');
        $this->stagedPaths=[];
        $this->ci->db->trans_begin();
        try {
            if ($id) {
                $request=$this->ci->db->query('SELECT * FROM requests WHERE id=? FOR UPDATE',[$id])->row_array();
                Request_policy::editable($request,(int)$user['id'],$type,$post);
            }
            // Validate ownership and action permissions before accepting upload bytes.
            $this->authorize($type,(int)($post['softcopy_id']??0),(int)($post['hardcopy_id']??0),$post,$user);
            $result=$this->save_draft($tab,$post,$user,$attachment);
            if ($this->ci->db->trans_status()===FALSE) throw new DomainException('Draft could not be saved.');
            $this->ci->db->trans_commit();
            $this->stagedPaths=[];
            return $result;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            foreach ($this->stagedPaths as $path) if (is_file($path)) @unlink($path);
            $this->stagedPaths=[];
            if ($e instanceof DomainException) throw $e;
            log_message('error','Request draft transaction failed.');
            throw new DomainException('Request could not be saved. Refresh and try again.');
        }
    }

    private function save_draft($tab,$post,$user,$attachment=NULL)
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
          'destination_area_id'=>(int)($post['destination_area_id']??0),
          'destination_specific_id'=>(int)($post['destination_specific_id']??0),
          'destination_asset_id'=>(int)($post['destination_asset_id']??0),
          'new_revision_level'=>trim((string)($post['new_revision_level']??'')),
          'effective_date'=>trim((string)($post['effective_date']??'')),
          'date_received'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d'),
          'date_released'=>'',
          'page_number'=>max(1,(int)($post['page_number']??1)),
          'document_domain'=>trim((string)($post['document_domain']??'softcopy')),
          'disposal_reason'=>trim((string)($post['disposal_reason']??'')),
          'disposal_other'=>trim((string)($post['disposal_other']??'')),
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
        $domain=$payload['document_domain'];
        // Unrelated posted IDs must never attach a request to another document.
        if (str_starts_with($type,'softcopy_')) {$hardcopyId=0;if ($type==='softcopy_create') $softcopyId=0;}
        elseif (in_array($type,['hardcopy_create','hardcopy_update','transfer','disposal'],TRUE)) {
            $softcopyId=0;if ($type==='hardcopy_create') $hardcopyId=0;
        } elseif ($domain==='softcopy') $hardcopyId=0;
        else $softcopyId=0;
        if (in_array($type,['assignment','access'],TRUE)) {
            if (!in_array($domain,['softcopy','hardcopy'],TRUE))
                throw new DomainException('Choose Softcopy or Hardcopy.');
            if (($domain==='softcopy' && !$softcopyId) ||
                ($domain==='hardcopy' && !$hardcopyId))
                throw new DomainException('Select a document in the chosen domain.');
            $table=$domain==='softcopy'?'softcopy_documents':'hardcopy_documents';
            $docId=$domain==='softcopy'?$softcopyId:$hardcopyId;
            if (!$this->ci->db->get_where($table,['id'=>$docId,'status'=>'active'])->row_array())
                throw new DomainException('Selected document is inactive or missing.');
        }
        if (in_array($type,['softcopy_revise','softcopy_cancel'],TRUE) &&
            !$softcopyId) throw new DomainException('Select a softcopy document.');
        if (in_array($type,['hardcopy_update','transfer','disposal'],TRUE) &&
            !$hardcopyId) throw new DomainException('Select a hardcopy document.');
        if (in_array($type,['assignment','access','transfer'],TRUE) && !$payload['recipient_id'])
            throw new DomainException('Select a recipient or assigned user.');
        if (in_array($type,['assignment','access','transfer'],TRUE) &&
            !$this->ci->Identity_model->active_user($payload['recipient_id']))
            throw new DomainException('Select an active recipient with an active role.');
        if ($type==='access') {
            $expiry=$payload['expires_at'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry) ||
                !($parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$expiry)) ||
                $parsed->format('Y-m-d')!==$expiry || $expiry<=date('Y-m-d'))
                throw new DomainException('Choose a valid access expiry date after today.');
        }
        if ($type==='disposal') {
            require_once APPPATH.'services/documents/disposal_service.php';
            $payload['disposal_description']=(new Disposal_service())->reason($payload);
        }
        if ($type==='transfer') $this->validate_transfer_destination($hardcopyId,$payload);
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
                $staged=$this->ci->db->select('storage_name')->get_where('files',['id'=>$fileId])->row_array();
                if ($staged && preg_match('/^[a-f0-9]{64}$/',$staged['storage_name']))
                    $this->stagedPaths[]=PK_ROOT.'/storage/documents/'.$staged['storage_name'];
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
            $data['version']=(int)$existing['version']+1;
            $this->ci->db->where('id',$id)->where_in('status',['draft','returned'])
                ->where('version',(int)$existing['version'])->update('requests',$data);
            if ($this->ci->db->error()['code']) throw new DomainException('Could not update draft.');
            return $id;
        }
        $data+=['reference'=>'REQ-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),
            'status'=>'draft','requested_by'=>$user['id']];
        if (!$this->ci->db->insert('requests',$data)) throw new DomainException('Could not create request.');
        return (int)$this->ci->db->insert_id();
    }

    // Original metadata is never accepted from the form as authoritative.
    // Destination must be a valid active place, distinct from current location.
    private function validate_transfer_destination($hardcopyId,$payload)
    {
        $document=$this->ci->db->get_where('hardcopy_documents',[
            'id'=>(int)$hardcopyId,'status'=>'active'
        ])->row_array();
        $destination=(int)($payload['destination_location_id']??0);
        if (!$document || !$destination)
            throw new DomainException('Choose an active hardcopy and a predefined destination.');
        $location=$this->ci->db->get_where('locations',[
            'id'=>$destination,'active'=>1
        ])->row_array();
        if (!$location) throw new DomainException('Destination is unavailable.');
        if ($this->ci->db->from('hardcopy_documents')->where('location_id',$destination)
            ->where('id !=',(int)$hardcopyId)->count_all_results()>0)
            throw new DomainException('The destination is already occupied by another hardcopy.');
        if ((int)($document['location_id']??0)===$destination)
            throw new DomainException('Destination must differ from the original location.');
        foreach (['area','specific','asset'] as $level) {
            $given=(int)($payload['destination_'.$level.'_id']??0);
            $actual=(int)($location[$level.'_id']??0);
            if ($given && $given!==$actual) {
                throw new DomainException('Destination does not match the selected '.$level.'.');
            }
        }
        if (!empty($location['asset_id'])) {
            $asset=$this->ci->db->select('b.specific_id,s.area_id')
                ->from('assets b')->join('specifics s','s.id=b.specific_id')
                ->join('areas a','a.id=s.area_id')
                ->where('b.id',$location['asset_id'])->where('b.active',1)
                ->where('s.active',1)->where('a.active',1)->get()->row_array();
            if (!$asset || (int)$asset['specific_id']!==(int)$location['specific_id'] ||
                (int)$asset['area_id']!==(int)$location['area_id']) {
                throw new DomainException('Destination location has an invalid hierarchy.');
            }
        } elseif (!empty($location['specific_id'])) {
            $specific=$this->ci->db->get_where('specifics',[
                'id'=>$location['specific_id'],'active'=>1
            ])->row_array();
            if (!$specific || (!empty($location['area_id']) &&
                (int)$specific['area_id']!==(int)$location['area_id'])) {
                throw new DomainException('Destination specific is no longer valid.');
            }
        }
        if (!empty($location['area_id']) &&
            !$this->ci->db->get_where('areas',[
                'id'=>$location['area_id'],'active'=>1
            ])->row_array()) throw new DomainException('Destination area is inactive.');
        if (!$this->ci->db->get_where('users',[
            'id'=>(int)($payload['recipient_id']??0),'active'=>1
        ])->row_array()) throw new DomainException('Choose an active receiving user.');
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
            if (!$this->ci->Permission_model->allowed($user,'requests','submit'))
                throw new DomainException('Request submission permission is required.');
            $currentPayload=json_decode($r['payload'],TRUE)?:[];
            $this->authorize($r['type'],(int)$r['softcopy_id'],(int)$r['hardcopy_id'],$currentPayload,$user);
            if ($r['type']==='transfer') $this->validate_transfer_destination((int)$r['hardcopy_id'],$currentPayload);
            if (in_array($r['type'],['assignment','access','transfer'],TRUE) &&
                !$this->ci->Identity_model->active_user((int)($currentPayload['recipient_id']??0)))
                throw new DomainException('The recipient is no longer active.');
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
            require_once APPPATH.'services/workflow/workflow_graph.php';
            Workflow_graph::validate(is_array($graph)?$graph:[],TRUE);
            // Resubmissions do not overwrite old step rows: workflow_history
            // holds foreign keys to those decisions. Only unfinished old steps
            // become superseded, preserving the exact past approval sequence.
            $this->ci->db->where('request_id',$id)
                ->where_in('status',['pending','active'])
                ->update('workflow_steps',['status'=>'superseded']);
            foreach ($steps as $i=>$step) {
                $approver=$step['approver']??[];
                $type=$approver['type']??'';
                $value=(int)($approver['value']??0);
                if (!in_array($type,['user','role','requester_leader','requester'],TRUE))
                    throw new DomainException('Workflow contains an unsupported approver type.');
                $assigned=NULL;
                $name=NULL;
                $position=NULL;
                if ($type==='user') $assigned=$value;
                if ($type==='requester') $assigned=(int)$r['requested_by'];
                if ($type==='requester_leader') {
                    $owner=$this->ci->db->get_where('users',['id'=>$r['requested_by']])->row_array();
                    $assigned=(int)($owner['leader_id']??0);
                }
                if ($type==='role') {
                    $role=$this->ci->db->get_where('roles',['id'=>$value,'active'=>1])->row_array();
                    if (!$role || !$this->ci->db->get_where('users',[
                        'role_id'=>$value,'active'=>1
                    ])->row_array()) {
                        throw new DomainException('Approver role needs an active user.');
                    }
                } else {
                    $resolved=$this->ci->Identity_model->active_user((int)$assigned);
                    if (!$resolved) throw new DomainException('An approver account or leader is no longer active.');
                    $name=trim($resolved['first_name'].' '.$resolved['last_name']);
                    $position=$resolved['position_title'];
                }
                $this->ci->db->insert('workflow_steps',[
                    'request_id'=>$id,'node_key'=>(string)($step['key']??'step_'.($i+1)),
                    'label'=>(string)($step['name']??'Review'),
                    'assignment'=>json_encode($approver),
                    'candidates'=>'[]','assigned_user_id'=>$assigned,
                    'assigned_name'=>$name,'assigned_position'=>$position,
                    'status'=>$i===0?'active':'pending'
                ]);
            }
            $this->ci->db->where('id',$id)->update('requests',[
              'workflow_version_id'=>$workflow['id'],'snapshot'=>$workflow['graph'],
              'current_node'=>$steps[0]['key'],'status'=>'submitted',
              'submitted_at'=>date('Y-m-d H:i:s'),'completed_at'=>NULL,'version'=>(int)$r['version']+1
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
        if ($type==='assignment' || $type==='access') {
            require_once APPPATH.'services/documents/document_access_service.php';
            return (new Document_access_service())->apply(
                $type,$payload,$soft,$hard,$recipient,$actor,$id
            );
        }
        if ($type==='transfer') {
            $doc=$this->ci->db->get_where('hardcopy_documents',['id'=>$hard,'status'=>'active'])->row_array();
            $loc=$this->ci->db->get_where('locations',[
                'id'=>(int)($payload['destination_location_id']??0),'active'=>1
            ])->row_array();
            $this->validate_transfer_destination($hard,$payload);
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
                'disposal_action'=>$payload['disposal_reason'],
                'remarks'=>$payload['disposal_description'],'disposed_by'=>$actor
            ]);
            $this->ci->db->where('id',$hard)->update('hardcopy_documents',[
                'previous_status'=>$doc['status'],'status'=>'disposed','location_id'=>NULL
            ]);
            $this->ci->db->insert('status_history',[
                'domain'=>'hardcopy','document_id'=>$hard,'previous_status'=>$doc['status'],
                'new_status'=>'disposed','action'=>'disposed','user_id'=>$actor,
                'remarks'=>$payload['disposal_description']
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
        $this->ci->db->where('id',$id)->where('requested_by',(int)$user['id'])
            ->where('status','draft')->where('version',(int)$r['version'])
            ->update('requests',['status'=>'cancelled','version'=>(int)$r['version']+1]);
        if ($this->ci->db->affected_rows()!==1) throw new DomainException('Request changed before cancellation. Refresh and try again.');
    }
}
