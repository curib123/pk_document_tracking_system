<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH.'services/workflow/workflow_graph.php';

/** Mutate draft routes only; serialize publication against edits and cloning. */
class Workflow_builder_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
    }

    private function transaction(callable $operation, $actorId=NULL)
    {
        $actor=$this->ci->Identity_model->active_user($actorId??(int)$this->ci->session->userdata('user_id'));
        if (!$actor || !$this->ci->Permission_model->allowed($actor,'workflows','edit'))
            throw new DomainException('Workflow editing permission is required.');
        $this->ci->db->trans_begin();
        try {
            // There are nine predefined workflow definitions. One consistent
            // lock order prevents clone/edit/publish races and duplicate drafts.
            $this->ci->db->query('SELECT id FROM workflows ORDER BY id FOR UPDATE');
            $result=$operation();
            if ($this->ci->db->trans_status()===FALSE)
                throw new DomainException('Workflow changes could not be saved. Refresh and try again.');
            $this->ci->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error','Workflow transaction failed.');
            throw new DomainException('Workflow changes could not be saved. Refresh and try again.');
        }
    }

    private function draft($id, array $post=[]): array
    {
        $version=$this->ci->db->query('SELECT * FROM workflow_versions WHERE id=? FOR UPDATE',[(int)$id])->row_array();
        if (!$version || $version['status']!=='draft')
            throw new DomainException('Only draft workflow versions can be edited.');
        // Older integrations omit the token; every current editing form sends
        // it to reject stale browser tabs before interpreting positional keys.
        if (isset($post['graph_hash']) && $post['graph_hash']!=='' &&
            !hash_equals(hash('sha256',$version['graph']),(string)$post['graph_hash']))
            throw new DomainException('This workflow changed in another session. Refresh before editing.');
        $graph=json_decode($version['graph'],TRUE);
        if (!is_array($graph)) throw new DomainException('Workflow graph is invalid.');
        Workflow_graph::validate($graph,FALSE);
        $version['decoded']=$graph;
        return $version;
    }

    private function persist(array $version, array $graph): void
    {
        $graph['steps']=Workflow_graph::renumber($graph['steps']);
        Workflow_graph::validate($graph,FALSE);
        $this->ci->db->where('id',(int)$version['id'])->where('status','draft')
            ->update('workflow_versions',['graph'=>json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }

    private function resolved_approver(string $type, int $value): array
    {
        $label=$type==='requester_leader'?"Requester's leader":'Requester account';
        if ($type==='user') {
            $user=$this->ci->Identity_model->active_user($value);
            if (!$user) throw new DomainException('Select an active user with an active role.');
            $label=$user['name'];
        } elseif ($type==='role') {
            $role=$this->ci->db->get_where('roles',['id'=>$value,'active'=>1])->row_array();
            if (!$role || !$this->ci->db->get_where('users',['role_id'=>$value,'active'=>1])->row_array())
                throw new DomainException('The approver role must contain an active user.');
            $label=$role['name'];
        }
        return ['type'=>$type,'value'=>in_array($type,['user','role'],TRUE)?$value:NULL,'label'=>$label];
    }

    public function save_workflow_step(array $post): void
    {
        $this->transaction(function() use($post) {
            $version=$this->draft($post['workflow_version_id']??0,$post);
            $graph=$version['decoded'];
            $key=trim((string)($post['step_key']??''));
            $type=(string)($post['approver_type']??'');
            $value=(int)($post[$type==='user'?'approver_user_id':'approver_role_id']??0);
            $step=['key'=>'step_1','name'=>trim((string)($post['name']??'')),
                'approver'=>['type'=>$type,'value'=>$value]];
            Workflow_graph::validate(['steps'=>[$step]],TRUE);
            $step['approver']=$this->resolved_approver($type,$value);
            if ($key==='') {
                if (count($graph['steps'])>=30) throw new DomainException('A workflow supports at most 30 steps.');
                $graph['steps'][]=$step;
            } else {
                $index=array_search($key,array_column($graph['steps'],'key'),TRUE);
                if ($index===FALSE) throw new DomainException('Draft approval step was not found.');
                $graph['steps'][$index]=$step;
            }
            $this->persist($version,$graph);
        });
    }

    public function remove_workflow_step(array $post): void
    {
        $this->transaction(function() use($post) {
            $version=$this->draft($post['id']??0,$post);
            $graph=$version['decoded'];
            $index=array_search((string)($post['step_key']??''),array_column($graph['steps'],'key'),TRUE);
            if ($index===FALSE) throw new DomainException('Draft approval step was not found.');
            array_splice($graph['steps'],$index,1);
            $this->persist($version,$graph);
        });
    }

    public function move_workflow_step(array $post): void
    {
        $this->transaction(function() use($post) {
            $version=$this->draft($post['id']??0,$post);
            $graph=$version['decoded'];
            $index=array_search((string)($post['step_key']??''),array_column($graph['steps'],'key'),TRUE);
            $direction=(string)($post['direction']??'');
            if ($index===FALSE || !in_array($direction,['up','down'],TRUE))
                throw new DomainException('Select a step and an ordering direction.');
            $swap=$index+($direction==='up'?-1:1);
            if (!isset($graph['steps'][$swap])) throw new DomainException('The step is already at the end of the route.');
            [$graph['steps'][$index],$graph['steps'][$swap]]=[$graph['steps'][$swap],$graph['steps'][$index]];
            $this->persist($version,$graph);
        });
    }

    public function validate_approval_graph(array $graph): void
    {
        Workflow_graph::validate($graph,TRUE);
        foreach ($graph['steps'] as $step)
            $this->resolved_approver($step['approver']['type'],(int)($step['approver']['value']??0));
    }

    public function publish_workflow($versionId,$actorId,array $post=[]): void
    {
        $this->transaction(function() use($versionId,$post) {
            $version=$this->draft($versionId,$post);
            $this->validate_approval_graph($version['decoded']);
            $workflow=$this->ci->db->get_where('workflows',['id'=>$version['workflow_id']])->row_array();
            if (!$workflow) throw new DomainException('Workflow not found.');
            $ids=array_column($this->ci->db->select('id')->get_where('workflows',[
                'request_type'=>$workflow['request_type']])->result_array(),'id');
            $this->ci->db->where_in('workflow_id',$ids)->update('workflow_versions',['is_default'=>0]);
            // Preserve the original UNIQUE(active_request_type) constraint.
            $this->ci->db->where_in('id',$ids)->where('id !=',$version['workflow_id'])
                ->update('workflows',['active'=>0]);
            $this->ci->db->where('id',$versionId)->where('status','draft')->update('workflow_versions',[
                'status'=>'published','is_default'=>1,'published_at'=>date('Y-m-d H:i:s')]);
            $this->ci->db->where('id',$version['workflow_id'])->update('workflows',['active'=>1]);
        },$actorId);
    }

    public function clone_workflow($workflowId,$actorId): void
    {
        $this->transaction(function() use($workflowId,$actorId) {
            if ($this->ci->db->get_where('workflow_versions',[
                'workflow_id'=>(int)$workflowId,'status'=>'draft'])->row_array())
                throw new DomainException('An editable draft already exists for this workflow.');
            $version=$this->ci->db->from('workflow_versions')->where('workflow_id',(int)$workflowId)
                ->order_by('version_number','DESC')->limit(1)->get()->row_array();
            if (!$version) throw new DomainException('No workflow version is available to copy.');
            $this->ci->db->insert('workflow_versions',[
                'workflow_id'=>(int)$workflowId,'version_number'=>(int)$version['version_number']+1,
                'status'=>'draft','is_default'=>0,'graph'=>$version['graph'],'created_by'=>(int)$actorId]);
        },$actorId);
    }
}
