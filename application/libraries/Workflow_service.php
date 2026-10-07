<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules,WorkflowGraph};
class Workflow_service
{
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    public function save(array $input): array
    {
        $this->ctx->require('workflows.edit'); $db=$this->ctx->model(\Workflow_model::class); $id=Rules::id($input,'id',false);
        $before=$id ? $db->lock('workflows',$id,Rules::id($input,'version')) : null;
        $data=['name'=>Rules::text($input,'name',150),'description'=>Rules::text($input,'description',4000,false),'active'=>Rules::boolean($input['active'] ?? 1)];
        if ($id) $db->update('workflows',$id,$data);
        else {
            $data['workflow_key']=Rules::text($input,'workflow_key',80); if (!preg_match('/^[a-z][a-z0-9_]+$/',$data['workflow_key'])) throw new Problem('Use a lowercase workflow key.');
            $data['request_type']=Rules::choice($input,'request_type',Request_service::TYPES); $data['active']=0; $data['created_by']=$this->ctx->id();
            $id=$db->insert('workflows',$data); $this->version(['workflow_id'=>$id,'graph'=>WorkflowGraph::defaults()]);
        }
        $this->ctx->audit('workflows',$before?'updated':'created',$id,$before,$data);
        return ['id'=>$id];
    }
    public function version(array $input): array
    {
        $this->ctx->require('workflows.edit'); $db=$this->ctx->model(\Workflow_model::class); $id=Rules::id($input,'id',false);
        $existing=$id ? $db->row('workflow_versions',$id) : null;
        $workflowId=$existing ? (int)$existing['workflow_id'] : Rules::id($input,'workflow_id');
        $db->lock('workflows',$workflowId);
        $before=$id ? $db->lock('workflow_versions',$id,Rules::id($input,'version')) : null;
        if ($before && $before['status']!=='draft') throw new Problem('Published and archived workflow content is immutable. Create a new draft version.',409);
        $graph=WorkflowGraph::validate(Rules::json($input['graph'] ?? []));
        foreach($graph['nodes'] as $node) if ($node['type']==='approval') {
            $a=$node['assignment'];
            if ($a['type']==='user') $this->ctx->active('users',(int)$a['value']);
            if ($a['type']==='role') $this->ctx->active('roles',(int)$a['value']);
            if ($a['type']==='permission' && !$db->assignment_permission([$a['value']])) throw new Problem('Assignment permission does not exist.');
        }
        if ($id) $db->update('workflow_versions',$id,['graph'=>Context::json($graph)]);
        else {
            $n=$db->next_version_number([$workflowId]);
            $id=$db->insert('workflow_versions',['workflow_id'=>$workflowId,'version_number'=>(int)$n['n'],'graph'=>Context::json($graph),'created_by'=>$this->ctx->id()]);
        }
        $this->ctx->audit('workflows','draft_saved',$id,$before,$graph);
        return ['id'=>$id];
    }
    public function publish(array $input): array
    {
        $this->ctx->require('workflows.edit'); $db=$this->ctx->model(\Workflow_model::class); $id=Rules::id($input);
        $meta=$db->row('workflow_versions',$id); $definition=$db->row('workflows',(int)$meta['workflow_id']);
        // Serialize routing changes even when different workflow definitions publish concurrently.
        $this->ctx->sequence('workflow_route_'.$definition['request_type'],'');
        $db->lock('workflows',(int)$meta['workflow_id']);
        $version=$db->lock('workflow_versions',$id,Rules::id($input,'version'));
        if ($version['status']!=='draft') throw new Problem('Only a draft version can be published.',409);
        WorkflowGraph::validate(Rules::json($version['graph']));
        $db->deactivate_other_definitions([$definition['request_type'],$definition['id']]);
        $db->update('workflows',(int)$definition['id'],['active'=>1]);
        $db->archive_published_versions([$version['workflow_id']]);
        $db->update('workflow_versions',$id,['status'=>'published','published_at'=>date('Y-m-d H:i:s')]);
        $this->ctx->audit('workflows','published',$id,$version,['status'=>'published'],Rules::text($input,'reason',2000));
        return ['message'=>'Published for new requests. Existing snapshots are unchanged.'];
    }
    public function begin(array $request,callable $complete): void
    {
        $db=$this->ctx->model(\Workflow_model::class); $id=(int)$request['id'];
        if ($request['snapshot']) $graph=Rules::json($request['snapshot']);
        else {
            $version=$db->published_version([$request['type']]);
            if (!$version) throw new Problem('No published active workflow exists for this request type.',409);
            $graph=Rules::json($version['graph']);
            $config=[]; $domain=$request['softcopy_id']?'softcopy':($request['hardcopy_id']?'hardcopy':null);
            if ($domain) { $d=$db->document_approvers([$domain,$request[$domain.'_id']]); $config=$d?Rules::json($d['config']):[]; }
            $db->update('requests',$id,['workflow_version_id'=>$version['id'],'snapshot'=>Context::json($graph),'approver_config'=>Context::json($config)]);
        }
        $db->update('requests',$id,['status'=>'pending','submitted_at'=>date('Y-m-d H:i:s'),'completed_at'=>null,'result'=>null]);
        $this->history($id,null,'submitted',null,['workflow_version_id'=>$db->row('requests',$id)['workflow_version_id']]);
        $this->advance($db->row('requests',$id),$graph['start'],$complete);
    }
    public function decide(array $request,array $input,callable $complete): void
    {
        $this->ctx->require('requests.approve'); $db=$this->ctx->model(\Workflow_model::class);
        if ($request['status']!=='pending') throw new Problem('This request is no longer awaiting approval.',409);
        $step=$db->lock('workflow_steps',Rules::id($input,'step_id'));
        if ((int)$step['request_id']!==(int)$request['id'] || $step['status']!=='pending' || $step['node_key']!==$request['current_node']) throw new Problem('The task has already changed.',409);
        $candidates=Rules::json($step['candidates']); $ids=array_map('intval',array_column($candidates,'id'));
        if (!in_array($this->ctx->id(),$ids,true) || $this->ctx->id()===(int)$request['requested_by']) throw new Problem('Only an assigned approver other than the requester can decide this task.',403);
        $decision=Rules::choice($input,'decision',['approve','reject','return']); $comments=Rules::text($input,'comments',4000,$decision!=='approve') ?? '';
        $db->update('workflow_steps',(int)$step['id'],['status'=>'completed','decision'=>$decision,'comments'=>$comments,'acting_user_id'=>$this->ctx->id(),'acting_name'=>Context::name($this->ctx->user),'acting_position'=>$this->ctx->user['position_title'],'acted_at'=>date('Y-m-d H:i:s')]);
        $this->history((int)$request['id'],(int)$step['id'],$decision,$step,null,$comments);
        $node=WorkflowGraph::node(Rules::json($request['snapshot']),$request['current_node']);
        $this->advance($request,$node[$decision],$complete);
    }
    private function advance(array $request,string $key,callable $complete): void
    {
        $db=$this->ctx->model(\Workflow_model::class); $graph=Rules::json($request['snapshot']); $payload=Rules::json($request['payload']); $id=(int)$request['id'];
        for($guard=0;$guard<65;++$guard) {
            $node=WorkflowGraph::node($graph,$key);
            if ($node['type']==='start') { $key=$node['next']; continue; }
            if ($node['type']==='condition') { $key=$node[WorkflowGraph::condition($node,$payload)?'true':'false']; continue; }
            if ($node['type']==='end') {
                $outcome=$node['outcome'];
                if ($outcome==='approved') { $complete($request); }
                else $db->update('requests',$id,['status'=>$outcome,'current_node'=>null,'completed_at'=>$outcome==='returned'?null:date('Y-m-d H:i:s')]);
                $this->history($id,null,$outcome,null,null);
                $this->ctx->notify((int)$request['requested_by'],'Request '.$outcome,$request['reference'].' is '.$outcome.'.',$id);
                return;
            }
            $candidates=$this->resolve($node['assignment'],$request);
            if (!$candidates) throw new Problem('No eligible active approver for “'.$node['label'].'”. An administrator must correct account/role assignments before proceeding.',409);
            $single=count($candidates)===1 ? $candidates[0] : null;
            $step=$db->insert('workflow_steps',['request_id'=>$id,'node_key'=>$key,'label'=>$node['label'],'assignment'=>Context::json($node['assignment']),'candidates'=>Context::json($candidates),'assigned_user_id'=>$single['id'] ?? null,'assigned_name'=>$single['name'] ?? null,'assigned_position'=>$single['position'] ?? null]);
            $db->update('requests',$id,['current_node'=>$key,'status'=>'pending']);
            $this->history($id,$step,'assigned',null,$candidates);
            foreach($candidates as $user) $this->ctx->notify((int)$user['id'],'Approval task',$request['reference'].' — '.$node['label'],$id);
            return;
        }
        throw new Problem('Workflow routing exceeded its safety limit.',409);
    }
    private function resolve(array $assignment,array $request): array
    {
        $type=$assignment['type']; $value=$assignment['value'] ?? null; $db=$this->ctx->model(\Workflow_model::class);
        if ($type==='leader') { $requester=$db->row('users',(int)$request['requested_by']); $type='user'; $value=$requester['leader_id']; }
        if ($type==='document') { $config=Rules::json($request['approver_config'] ?? '[]'); $type='user'; $value=$config[$value]['user_id'] ?? null; }
        return $db->eligible_approvers($type,$value,(int)$request['requested_by']);
    }
    public function reassign(array $input): array
    {
        $this->ctx->require('workflows.reassign'); $db=$this->ctx->model(\Workflow_model::class); $step=$db->row('workflow_steps',Rules::id($input,'step_id'));
        $request=$db->lock('requests',(int)$step['request_id'],Rules::id($input,'version')); $step=$db->lock('workflow_steps',(int)$step['id']);
        if ($request['status']!=='pending' || $step['status']!=='pending') throw new Problem('Only active tasks can be reassigned.',409);
        $id=Rules::id($input,'user_id'); $reason=Rules::text($input,'reason',4000);
        $users=$this->resolve(['type'=>'user','value'=>$id],$request);
        if (!$users) throw new Problem('The replacement must be active, authorized to approve, and not the requester.');
        $user=$users[0];
        $db->update('workflow_steps',(int)$step['id'],['candidates'=>Context::json($users),'assigned_user_id'=>$id,'assigned_name'=>$user['name'],'assigned_position'=>$user['position'],'assignment'=>Context::json(['type'=>'user','value'=>$id])]);
        $db->update('requests',(int)$request['id'],['current_node'=>$request['current_node']]);
        $this->history((int)$request['id'],(int)$step['id'],'reassigned',Rules::json($step['candidates']),$users,$reason);
        $this->ctx->audit('workflows','approver_reassigned',(int)$step['id'],Rules::json($step['candidates']),$users,$reason,(int)$request['id']);
        $this->ctx->notify($id,'Task reassigned',$request['reference'].' — '.$step['label'],(int)$request['id']);
        return ['message'=>'Task reassigned with history preserved.'];
    }
    public function history(int $request,?int $step,string $action,mixed $before=null,mixed $after=null,?string $comments=null): void
    {
        $this->ctx->model(\Workflow_model::class)->insert('workflow_history',['request_id'=>$request,'step_id'=>$step,'action'=>$action,'user_id'=>$this->ctx->id(),'user_name'=>Context::name($this->ctx->user),'position_title'=>$this->ctx->user['position_title'],'before_state'=>Context::json($before),'after_state'=>Context::json($after),'comments'=>$comments]);
        $this->ctx->audit('requests',$action,$request,$before,$after,$comments,$request);
    }
}
