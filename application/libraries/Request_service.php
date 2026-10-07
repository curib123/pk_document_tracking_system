<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class Request_service
{
    public const TYPES=['softcopy_create','softcopy_revise','softcopy_cancel','hardcopy_create','hardcopy_update','transfer','assignment','access','disposal'];
    private Document_service $documents;
    private Workflow_service $workflow;
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options); $this->documents=new Document_service($ctx); $this->workflow=new Workflow_service($ctx); }
    private function permission(string $type): void { if (str_starts_with($type,'softcopy_')) $this->ctx->require('softcopy.request'); elseif (str_starts_with($type,'hardcopy_')) $this->ctx->require('hardcopy.request'); else $this->ctx->require($type.'.request'); }
    public function save(array $input): array
    {
        $id=Rules::id($input,'id',false); $this->ctx->require($id?'requests.edit':'requests.add'); $db=$this->ctx->model(\Request_model::class);
        $before=$id ? $db->lock('requests',$id,Rules::id($input,'version')) : null;
        if ($before && ((int)$before['requested_by']!==$this->ctx->id() || !in_array($before['status'],['draft','returned'],true))) throw new Problem('Only your own draft or returned requests may be edited.',403);
        $type=Rules::choice($input,'type',self::TYPES); $this->permission($type);
        $soft=Rules::id($input,'softcopy_id',false); $hard=Rules::id($input,'hardcopy_id',false);
        if ($before && ($type!==$before['type'] || $soft!=($before['softcopy_id'] ?: null) || $hard!=($before['hardcopy_id'] ?: null))) throw new Problem('Request type and target are immutable; create another draft.');
        $payload=$this->normalize($type,$soft,$hard,Rules::json($input['payload'] ?? []),$this->ctx->id(),true);
        $data=['type'=>$type,'softcopy_id'=>$soft,'hardcopy_id'=>$hard,'payload'=>Context::json($payload)];
        if ($id) $db->update('requests',$id,$data);
        else $id=$db->insert('requests',array_merge($data,['reference'=>$this->ctx->sequence('request_'.date('Y'),'REQ-'.date('Y').'-'),'requested_by'=>$this->ctx->id()]));
        $this->workflow->history($id,null,$before?'draft_updated':'draft_created',$before,$data);
        return ['id'=>$id,'message'=>'Draft saved. Submit it from the request dialog when ready.'];
    }
    private function normalize(string $type,?int $soft,?int $hard,array $payload,int $owner,bool $checkRights): array
    {
        if ($soft && $hard) throw new Problem('A request can reference only one document domain.');
        $create=in_array($type,['softcopy_create','hardcopy_create'],true);
        if ($create && ($soft || $hard)) throw new Problem('Creation requests must not reference an existing document.');
        if (!$create && !$soft && !$hard) throw new Problem('Choose an existing target document.');
        if ((str_starts_with($type,'softcopy_') || $type==='assignment') && $hard) throw new Problem('This request requires a softcopy document.');
        if ((str_starts_with($type,'hardcopy_') || $type==='transfer') && $soft) throw new Problem('This request requires a hardcopy document.');
        $domain=$soft?'softcopy':($hard?'hardcopy':(str_starts_with($type,'softcopy')?'softcopy':'hardcopy'));
        $target=$soft ?? $hard; $doc=$target ? $this->ctx->model(\Request_model::class)->lock(Document_service::table($domain),$target) : null;
        if ($doc && !in_array($doc['status'],['active'],true)) throw new Problem('The target document is not active.');
        if ($checkRights) $this->ctx->require($domain.'.view');
        if ($checkRights && $doc && $type!=='access' && !$this->documents->canRead($domain,$target)) throw new Problem('You need document access before requesting this operation.',403);
        if ($checkRights && $type==='transfer' && (int)$doc['holder_id']!==$owner && !$this->ctx->can('transfer.manage')) throw new Problem('Only the current holder or transfer manager may request a transfer.',403);
        $data=['reason'=>Rules::text($payload,'reason',4000)];
        if (in_array($type,['softcopy_create','softcopy_revise','hardcopy_create','hardcopy_update'],true)) $data=$this->documents->validate($domain,$payload,$target,$owner);
        elseif ($type==='transfer') {
            $this->documents->noOpenTransfer($hard);
            $data=array_merge($data,$this->documents->physical($payload,$hard),['recipient_id'=>Rules::id($payload,'recipient_id'),'document_copy_number'=>Rules::text($payload,'document_copy_number',100),'sequence_number'=>Rules::text($payload,'sequence_number',100,false)]);
            $this->ctx->active('users',$data['recipient_id']);
            if ($data['location_id']===(int)$doc['location_id'] && $data['recipient_id']===(int)$doc['holder_id']) throw new Problem('A transfer must change the location or holder.');
        } elseif ($type==='assignment') { $data['user_id']=Rules::id($payload,'user_id'); $this->ctx->active('users',$data['user_id']); }
        elseif ($type==='access') {
            $data['expiration_date']=Rules::date($payload,'expiration_date');
            if ($data['expiration_date']<date('Y-m-d') || $data['expiration_date']>date('Y-m-d',strtotime('+2 years'))) throw new Problem('Access expiry must be today or within the next two years.');
        } elseif ($type==='disposal') {
            $data['disposal_action']=Rules::choice($payload,'disposal_action',['shred','scratch','reuse','other']);
            if ($domain==='hardcopy') {
                $this->documents->noOpenTransfer($hard);
                if ((int)$doc['retention_enabled'] && $doc['retention_end_date']>=date('Y-m-d')) throw new Problem('The retention period has not ended; this hardcopy cannot be disposed yet.');
            }
        }
        if ($doc) $data['base_document_version']=(int)$doc['version'];
        return $data;
    }
    public function submit(array $input): array
    {
        $this->ctx->require('requests.submit'); $db=$this->ctx->model(\Request_model::class); $request=$db->lock('requests',Rules::id($input),Rules::id($input,'version'));
        if ((int)$request['requested_by']!==$this->ctx->id() || !in_array($request['status'],['draft','returned'],true)) throw new Problem('Only your draft or corrected returned request can be submitted.',409);
        $this->permission($request['type']); $payload=Rules::json($request['payload']);
        $fresh=$this->normalize($request['type'],$request['softcopy_id']?(int)$request['softcopy_id']:null,$request['hardcopy_id']?(int)$request['hardcopy_id']:null,$payload,$this->ctx->id(),true);
        $this->unchanged($payload,$fresh);
        if ($request['type']==='transfer' && $db->competing_transfer([$request['hardcopy_id'],$request['id']])) throw new Problem('Another transfer request is already in progress.');
        $this->workflow->begin($request,fn(array $completedRequest)=>$this->complete($completedRequest));
        return ['id'=>(int)$request['id'],'message'=>'Request submitted using its pinned workflow version.'];
    }
    private function unchanged(array $old,array $fresh): void { if (isset($old['base_document_version']) && $old['base_document_version']!==($fresh['base_document_version'] ?? null)) throw new Problem('The document changed after this request was prepared. Return it for correction, then edit and resubmit.',409); }
    public function decide(array $input): array
    {
        if (!$this->ctx->id()) throw new Problem('Sign in first.',401); $request=$this->ctx->model(\Request_model::class)->lock('requests',Rules::id($input),Rules::id($input,'version'));
        $this->workflow->decide($request,$input,fn(array $completedRequest)=>$this->complete($completedRequest));
        return ['message'=>'Decision recorded.'];
    }
    private function complete(array $request): void
    {
        $db=$this->ctx->model(\Request_model::class); $payload=Rules::json($request['payload']); $type=$request['type']; $id=(int)$request['id']; $owner=(int)$request['requested_by'];
        $soft=$request['softcopy_id']?(int)$request['softcopy_id']:null; $hard=$request['hardcopy_id']?(int)$request['hardcopy_id']:null;
        $fresh=$this->normalize($type,$soft,$hard,$payload,$owner,false); $this->unchanged($payload,$fresh);
        $domain=$soft?'softcopy':($hard?'hardcopy':(str_starts_with($type,'softcopy')?'softcopy':'hardcopy')); $target=$soft ?? $hard;
        $result=[]; $status='completed';
        if (in_array($type,['softcopy_create','softcopy_revise','hardcopy_create','hardcopy_update'],true)) {
            $result=$this->documents->apply($domain,$fresh,$target,'request',$id,$owner); $target=$result['id'];
            $db->update('requests',$id,[$domain.'_id'=>$target]);
        } elseif ($type==='softcopy_cancel') {
            $doc=$db->lock('softcopy_documents',$soft); $db->update('softcopy_documents',$soft,['status'=>'cancelled','previous_status'=>$doc['status']]);
            $this->ctx->status('softcopy',$soft,$doc['status'],'cancelled','cancelled',$payload['reason']);
        } elseif ($type==='transfer') {
            $doc=$db->lock('hardcopy_documents',$hard);
            $transfer=$db->insert('transfers',['request_id'=>$id,'hardcopy_id'=>$hard,'origin'=>Context::json($doc),'destination'=>Context::json($fresh),'current_holder_id'=>$doc['holder_id'],'recipient_id'=>$fresh['recipient_id'],'document_copy_number'=>$fresh['document_copy_number'],'reason'=>$fresh['reason']]);
            $result=['transfer_id'=>$transfer]; $status='approved';
            $this->ctx->notify((int)$doc['holder_id'],'Transfer approved','Record the physical delivery before the recipient accepts.',$id);
        } elseif ($type==='assignment') {
            $existing=$db->assignment_for_update([$soft,$fresh['user_id']]);
            if ($existing) $db->update('assignments',(int)$existing['id'],['active'=>1,'assigned_by'=>$this->ctx->id(),'assigned_at'=>date('Y-m-d H:i:s')]);
            else $db->insert('assignments',['softcopy_id'=>$soft,'user_id'=>$fresh['user_id'],'assigned_by'=>$this->ctx->id()]);
            $this->ctx->notify($fresh['user_id'],'Document assigned','A controlled softcopy document has been assigned to you.',$id);
        } elseif ($type==='access') {
            $grant=$db->insert('access_grants',['request_id'=>$id,'domain'=>$domain,'document_id'=>$target,'user_id'=>$owner,'granted_by'=>$this->ctx->id(),'expires_at'=>$fresh['expiration_date'].' 23:59:59','reason'=>$fresh['reason']]);
            $result=['grant_id'=>$grant]; $this->ctx->notify($owner,'Access granted','Access expires '.$fresh['expiration_date'].'.',$id);
        } elseif ($type==='disposal') {
            $table=Document_service::table($domain); $doc=$db->lock($table,$target);
            $disposal=$db->insert('disposals',['request_id'=>$id,'domain'=>$domain,'document_id'=>$target,'previous_status'=>$doc['status'],'previous_state'=>Context::json($doc),'disposal_action'=>$fresh['disposal_action'],'remarks'=>$fresh['reason'],'disposed_by'=>$this->ctx->id()]);
            $db->update($table,$target,array_merge(['previous_status'=>$doc['status'],'status'=>'disposed'],$domain==='hardcopy'?['location_id'=>null]:[]));
            $this->ctx->status($domain,$target,$doc['status'],'disposed','disposed',$fresh['reason']);
            foreach($db->grants_for_update([$domain,$target]) as $grant) {
                $db->update('access_grants',(int)$grant['id'],['status'=>'revoked','revoked_at'=>date('Y-m-d H:i:s'),'revoked_by'=>$this->ctx->id()]);
                $this->ctx->notify((int)$grant['user_id'],'Access revoked','The document has been disposed.',(int)$grant['request_id']);
                $this->ctx->audit('access','revoked',(int)$grant['id'],$grant,null,'Document disposed',$id);
            }
            $result=['disposal_id'=>$disposal];
        }
        $db->update('requests',$id,['status'=>$status,'current_node'=>null,'result'=>Context::json($result),'completed_at'=>$status==='completed'?date('Y-m-d H:i:s'):null]);
        $this->ctx->audit($type,'applied',$target,null,$result,$payload['reason'],$id);
    }
    public function cancel(array $input): array
    {
        $this->ctx->require('requests.cancel'); $db=$this->ctx->model(\Request_model::class); $request=$db->lock('requests',Rules::id($input),Rules::id($input,'version'));
        if ((int)$request['requested_by']!==$this->ctx->id() && !$this->ctx->can('requests.manage')) throw new Problem('Only the requester or request manager can cancel.',403);
        if (!in_array($request['status'],['draft','pending','returned'],true)) throw new Problem('This request cannot be cancelled in its current state.',409);
        $reason=Rules::text($input,'reason',4000);
        foreach($db->pending_candidates([$request['id']]) as $step) foreach(Rules::json($step['candidates']) as $user) $this->ctx->notify((int)$user['id'],'Request cancelled',$request['reference'],(int)$request['id']);
        $db->cancel_pending_steps([$request['id']]);
        $db->update('requests',(int)$request['id'],['status'=>'cancelled','current_node'=>null,'completed_at'=>date('Y-m-d H:i:s')]);
        $this->workflow->history((int)$request['id'],null,'cancelled',$request,null,$reason);
        return ['message'=>'Request cancelled. History was retained.'];
    }
    public function revoke(array $input): array
    {
        $db=$this->ctx->model(\Request_model::class); $grant=$db->lock('access_grants',Rules::id($input),Rules::id($input,'version'));
        $own=(int)$grant['user_id']===$this->ctx->id(); if (!$own) $this->ctx->require('access.revoke');
        if (!$own && !$this->ctx->id()) throw new Problem('Sign in first.',401);
        if ($grant['status']!=='access_granted') throw new Problem('Access is no longer granted.',409);
        $reason=Rules::text($input,'reason',4000); $status=$own?'returned':'revoked';
        $db->update('access_grants',(int)$grant['id'],['status'=>$status,'revoked_at'=>date('Y-m-d H:i:s'),'revoked_by'=>$this->ctx->id()]);
        $this->workflow->history((int)$grant['request_id'],null,'access_'.$status,$grant,null,$reason);
        $this->ctx->notify((int)$grant['user_id'],'Access '.$status,$reason,(int)$grant['request_id']);
        return ['message'=>'Access '.$status.'.'];
    }
    public function unassign(array $input): array
    {
        $this->ctx->require('assignment.manage'); $assignment=$this->ctx->model(\Request_model::class)->lock('assignments',Rules::id($input),Rules::id($input,'version'));
        if (!(int)$assignment['active']) throw new Problem('Assignment is already inactive.',409);
        $reason=Rules::text($input,'reason',4000); $this->ctx->model(\Request_model::class)->update('assignments',(int)$assignment['id'],['active'=>0]);
        $this->ctx->audit('assignment','removed',(int)$assignment['id'],$assignment,null,$reason);
        $this->ctx->notify((int)$assignment['user_id'],'Assignment removed',$reason);
        return ['message'=>'Assignment removed; its history was retained.'];
    }
}
