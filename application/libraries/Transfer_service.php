<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class Transfer_service
{
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    public function dispatch(array $input): array
    {
        $this->ctx->require('transfer.view'); $db=$this->ctx->model(\Transfer_model::class); $transfer=$db->lock_transfer(Rules::id($input),Rules::id($input,'version'));
        if ((int)$transfer['current_holder_id']!==$this->ctx->id() && !$this->ctx->can('transfer.manage')) throw new Problem('Only the current holder or transfer manager can record delivery.',403);
        if ($transfer['status']!=='for_transfer') throw new Problem('This transfer is not awaiting dispatch.',409);
        $doc=$db->lock_hardcopy((int)$transfer['hardcopy_id']); $this->unchanged($transfer,$doc);
        $comments=Rules::text($input,'comments',4000);
        $db->update('transfers',(int)$transfer['id'],['status'=>'pending_recipient_acceptance','transferred_by'=>$this->ctx->id(),'transferred_at'=>date('Y-m-d H:i:s'),'comments'=>$comments]);
        (new Workflow_service($this->ctx))->history((int)$transfer['request_id'],null,'physically_transferred',$transfer,['status'=>'pending_recipient_acceptance'],$comments);
        $this->ctx->notify((int)$transfer['recipient_id'],'Receipt confirmation required','Confirm acceptance or refusal of the physical document.',(int)$transfer['request_id']);
        return ['message'=>'Delivery recorded. Current location is unchanged until the recipient accepts.'];
    }
    public function receive(array $input): array
    {
        $this->ctx->require('transfer.view'); $db=$this->ctx->model(\Transfer_model::class); $transfer=$db->lock_transfer(Rules::id($input),Rules::id($input,'version'));
        if ((int)$transfer['recipient_id']!==$this->ctx->id()) throw new Problem('Only the named recipient can accept or refuse this transfer.',403);
        if ($transfer['status']!=='pending_recipient_acceptance' || $transfer['recipient_status']!=='pending') throw new Problem('The transfer is no longer awaiting acceptance.',409);
        $decision=Rules::choice($input,'decision',['accepted','refused']); $comments=Rules::text($input,'comments',4000);
        $doc=$db->lock_hardcopy((int)$transfer['hardcopy_id']); $this->unchanged($transfer,$doc);
        if ($decision==='accepted') {
            $destination=Rules::json($transfer['destination']); $physical=(new Document_service($this->ctx))->physical($destination,(int)$doc['id']);
            $db->update('hardcopy_documents',(int)$doc['id'],[...$physical,'holder_id'=>$this->ctx->id(),'sequence_number'=>$destination['sequence_number'] ?? $doc['sequence_number']]);
            $this->ctx->status('hardcopy',(int)$doc['id'],$doc['status'],$doc['status'],'transfer_received',$comments);
            $this->ctx->audit('hardcopy','location_changed',(int)$doc['id'],$doc,$physical,$comments,(int)$transfer['request_id']);
        }
        $status=$decision==='accepted'?'completed':'returned';
        $db->update('transfers',(int)$transfer['id'],['status'=>$status,'recipient_status'=>$decision,'accepted_by'=>$this->ctx->id(),'accepted_at'=>date('Y-m-d H:i:s'),'comments'=>$comments]);
        $db->update('requests',(int)$transfer['request_id'],['status'=>'completed','completed_at'=>date('Y-m-d H:i:s'),'result'=>Context::json(['transfer_id'=>(int)$transfer['id'],'receipt'=>$decision])]);
        (new Workflow_service($this->ctx))->history((int)$transfer['request_id'],null,'recipient_'.$decision,$transfer,['status'=>$status],$comments);
        $this->ctx->notify((int)$transfer['current_holder_id'],'Transfer '.$status,$comments,(int)$transfer['request_id']);
        return ['message'=>$decision==='accepted'?'Receipt accepted. The current holder and location have been updated.':'Receipt refused. The original holder and location remain unchanged; arrange the physical return.'];
    }
    public function cancel(array $input): array
    {
        $this->ctx->require('transfer.view'); $db=$this->ctx->model(\Transfer_model::class); $transfer=$db->lock_transfer(Rules::id($input),Rules::id($input,'version'));
        if ((int)$transfer['current_holder_id']!==$this->ctx->id() && !$this->ctx->can('transfer.manage')) throw new Problem('You cannot cancel this transfer.',403);
        if ($transfer['status']!=='for_transfer') throw new Problem('Only an undispatched transfer can be cancelled. A delivered copy requires recipient confirmation.',409);
        $reason=Rules::text($input,'reason',4000); $db->update('transfers',(int)$transfer['id'],['status'=>'cancelled','comments'=>$reason]);
        $db->update('requests',(int)$transfer['request_id'],['status'=>'cancelled','completed_at'=>date('Y-m-d H:i:s')]);
        (new Workflow_service($this->ctx))->history((int)$transfer['request_id'],null,'transfer_cancelled',$transfer,null,$reason);
        $this->ctx->notify((int)$transfer['recipient_id'],'Transfer cancelled',$reason,(int)$transfer['request_id']);
        return ['message'=>'Undispatched transfer cancelled.'];
    }
    private function unchanged(array $transfer,array $doc): void
    {
        $origin=Rules::json($transfer['origin']);
        if ($doc['status']!=='active' || (int)$doc['version']!==(int)$origin['version'] || (int)$doc['location_id']!==(int)$origin['location_id'] || (int)$doc['holder_id']!==(int)$origin['holder_id']) throw new Problem('The physical record changed after approval. Resolve this transfer before moving the document.',409);
    }
}
