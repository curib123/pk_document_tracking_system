<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Administration_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); }

    private function safe_username($value)
    {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$value))
            throw new DomainException('Invalid username.');
        return $value;
    }
    public function save_user($post,$actorId)
    {
        $id=(int)($post['id']??0);
        $data=[
            'first_name'=>trim((string)($post['first_name']??'')),
            'middle_name'=>trim((string)($post['middle_name']??''))?:NULL,
            'last_name'=>trim((string)($post['last_name']??'')),
            'username'=>$this->safe_username(trim((string)($post['username']??''))),
            'position_title'=>trim((string)($post['position_title']??'')),
            'role_id'=>(int)($post['role_id']??0),
            'leader_id'=>!empty($post['leader_id'])?(int)$post['leader_id']:NULL,
            'active'=>!empty($post['active'])?1:0
        ];
        if ($data['first_name']==='' || $data['last_name']==='' || $data['position_title']==='')
            throw new DomainException('First name, last name and position are required.');
        if (!$this->ci->db->get_where('roles',['id'=>$data['role_id'],'active'=>1])->row_array())
            throw new DomainException('Choose an active role.');
        if ($id && $data['leader_id']===$id) throw new DomainException('A user cannot be their own leader.');
        $password=(string)($post['password']??'');
        if ($password!=='') {
            if (strlen($password)<12) throw new DomainException('Password must be at least 12 characters.');
            $data['password_hash']=password_hash($password,PASSWORD_DEFAULT);
            $data['require_password_change']=1;
        } elseif (!$id) throw new DomainException('A new user requires an initial password.');
        $this->ci->db->trans_begin();
        if ($id) {
            $old=$this->ci->db->get_where('users',['id'=>$id])->row_array();
            if (!$old) throw new DomainException('User not found.');
            $admin=$this->ci->db->get_where('roles',['name'=>'Administrator'])->row_array();
            if ($admin && $old['role_id']==$admin['id'] && $old['active'] &&
                ($data['role_id']!=$admin['id'] || !$data['active'])) {
                $remaining=$this->ci->db->from('users')->where('role_id',$admin['id'])
                    ->where('active',1)->where('id !=',$id)->count_all_results();
                if (!$remaining) throw new DomainException('Cannot remove the last administrator.');
            }
            $data['session_version']=(int)$old['session_version']+1;
            $this->ci->db->where('id',$id)->update('users',$data);
        } else $this->ci->db->insert('users',$data);
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();throw new DomainException('Could not save user. Check unique username.');
        }
        $this->ci->db->trans_commit();
    }
    public function deactivate_user($id,$actorId)
    {
        if ($id===$actorId) throw new DomainException('You cannot deactivate yourself.');
        $user=$this->ci->db->get_where('users',['id'=>$id])->row_array();
        if (!$user) throw new DomainException('User not found.');
        $admin=$this->ci->db->get_where('roles',['name'=>'Administrator'])->row_array();
        if ($admin && $user['role_id']==$admin['id']) {
            $others=$this->ci->db->from('users')->where('role_id',$admin['id'])
                ->where('active',1)->where('id !=',$id)->count_all_results();
            if (!$others) throw new DomainException('Last administrator cannot be deactivated.');
        }
        $this->ci->db->where('id',$id)->set('active',0)
            ->set('session_version','session_version+1',FALSE)->update('users');
    }
    public function save_role($post)
    {
        $id=(int)($post['id']??0);
        $name=trim((string)($post['name']??''));
        if ($name==='' || mb_strlen($name)>120) throw new DomainException('Enter a role name.');
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($post['permissions']??[])))));
        $this->ci->db->trans_begin();
        if ($id) {
            $existing=$this->ci->db->get_where('roles',['id'=>$id])->row_array();
            if (!$existing) throw new DomainException('Role not found.');
            if ($existing['name']==='Administrator') throw new DomainException('Administrator privileges are fixed.');
            $this->ci->db->where('id',$id)->update('roles',['name'=>$name,'active'=>!empty($post['active'])?1:0]);
        } else {
            $this->ci->db->insert('roles',['name'=>$name,'active'=>1]);
            $id=(int)$this->ci->db->insert_id();
        }
        $this->ci->db->where('role_id',$id)->delete('role_permissions');
        foreach ($ids as $perm) {
            if ($this->ci->db->get_where('permissions',['id'=>$perm])->row_array())
                $this->ci->db->insert('role_permissions',['role_id'=>$id,'permission_id'=>$perm]);
        }
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();throw new DomainException('Role or permission update failed.');
        }
        $this->ci->db->trans_commit();
    }
    public function save_workflow($post,$actorId)
    {
        $id=(int)($post['id']??0);
        $name=trim((string)($post['name']??''));
        $key=trim((string)($post['workflow_key']??''));
        $type=trim((string)($post['request_type']??''));
        $types=['softcopy_create','softcopy_revise','softcopy_cancel',
            'hardcopy_create','hardcopy_update','transfer','assignment','access','disposal'];
        if (!in_array($type,$types,TRUE) || $name==='' ||
           !preg_match('/^[a-z0-9_]{3,80}$/',$key))
            throw new DomainException('Choose a valid workflow name, key and request type.');
        $data=['workflow_key'=>$key,'name'=>$name,'request_type'=>$type,
            'description'=>trim((string)($post['description']??''))];
        $this->ci->db->trans_begin();
        if ($id) {
            $existing=$this->ci->db->get_where('workflows',['id'=>$id])->row_array();
            if (!$existing) throw new DomainException('Workflow not found.');
            if ($existing['request_type']!==$type &&
                $this->ci->db->from('workflow_versions')->where('workflow_id',$id)
                    ->where('status','published')->count_all_results()>0) {
                throw new DomainException('Published workflow request type is immutable. Create a new workflow.');
            }
            $this->ci->db->where('id',$id)->update('workflows',$data);
        } else {
            $data['created_by']=$actorId;
            $data['active']=0;
            $this->ci->db->insert('workflows',$data);
            $id=(int)$this->ci->db->insert_id();
            $this->ci->db->insert('workflow_versions',[
                'workflow_id'=>$id,'version_number'=>1,'status'=>'draft',
                'is_default'=>0,'graph'=>'{"steps":[]}','created_by'=>$actorId
            ]);
        }
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();throw new DomainException('Workflow save failed.');
        }
        $this->ci->db->trans_commit();
    }
    public function save_workflow_step($post)
    {
        $versionId=(int)($post['workflow_version_id']??0);
        $version=$this->ci->db->get_where('workflow_versions',['id'=>$versionId,'status'=>'draft'])->row_array();
        if (!$version) throw new DomainException('Only draft workflow versions can be edited.');
        $graph=json_decode($version['graph'],TRUE);
        if (!is_array($graph)) $graph=['steps'=>[]];
        $name=trim((string)($post['name']??''));
        $type=(string)($post['approver_type']??'');
        $value=$type==='user'?(int)($post['approver_user_id']??0):
            ($type==='role'?(int)($post['approver_role_id']??0):0);
        if ($name==='' || mb_strlen($name)>120 ||
            !in_array($type,['user','role','requester_leader','requester'],TRUE))
            throw new DomainException('Choose a valid approver type and step name (maximum 120 characters).');
        if ($type==='user' && (!$value || !$this->ci->db->get_where('users',[
            'id'=>$value,'active'=>1
        ])->row_array())) throw new DomainException('Choose an active user approver.');
        if ($type==='role' && (!$value || !$this->ci->db->get_where('roles',[
            'id'=>$value,'active'=>1
        ])->row_array())) throw new DomainException('Choose an active approver role.');
        $key='step_'.(count($graph['steps']??[])+1);
        $target='Requester account';
        if ($type==='user') {
            $assigned=$this->ci->db->select('first_name,last_name')->get_where('users',['id'=>$value])->row_array();
            $target=trim($assigned['first_name'].' '.$assigned['last_name']);
        } elseif ($type==='role') {
            $assigned=$this->ci->db->get_where('roles',['id'=>$value])->row_array();
            $target=$assigned['name'];
        } elseif ($type==='requester_leader') {
            $target='Requester leader';
        }
        $graph['steps'][]=['key'=>$key,'name'=>$name,'approver'=>[
            'type'=>$type,'value'=>in_array($type,['user','role'],TRUE)?$value:NULL,
            'label'=>$target
        ]];
        $this->ci->db->where('id',$versionId)->update('workflow_versions',[
            'graph'=>json_encode($graph,JSON_UNESCAPED_UNICODE)
        ]);
    }
    public function remove_workflow_step($post)
    {
        $versionId=(int)($post['id']??0);
        $key=(string)($post['step_key']??'');
        $version=$this->ci->db->get_where('workflow_versions',[
            'id'=>$versionId,'status'=>'draft'
        ])->row_array();
        if (!$version || !preg_match('/^step_[0-9]+$/',$key))
            throw new DomainException('Select a valid draft workflow step.');
        $graph=json_decode($version['graph'],TRUE);
        if (!is_array($graph) || !isset($graph['steps']) || !is_array($graph['steps']))
            throw new DomainException('Workflow graph is invalid.');
        $original=count($graph['steps']);
        $steps=array_values(array_filter($graph['steps'],function($step)use($key){
            return ($step['key']??'')!==$key;
        }));
        if (count($steps)===$original) throw new DomainException('Approval step was not found.');
        foreach($steps as $i=>&$step) $step['key']='step_'.($i+1);
        unset($step);
        $graph['steps']=$steps;
        $this->ci->db->where('id',$versionId)->update('workflow_versions',[
            'graph'=>json_encode($graph,JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Draft-only step positioning. Routing follows array order, so the current
     * request always passes to the next unresolved approver in this sequence.
     */
    public function move_workflow_step($post)
    {
        $id=(int)($post['id']??0);
        $key=(string)($post['step_key']??'');
        $direction=(string)($post['direction']??'');
        if (!in_array($direction,['up','down'],TRUE) || !preg_match('/^step_[0-9]+$/',$key))
            throw new DomainException('Choose a valid step and direction.');
        $version=$this->ci->db->get_where('workflow_versions',[
            'id'=>$id,'status'=>'draft'
        ])->row_array();
        if (!$version) throw new DomainException('Only draft approval routes can be reordered.');
        $graph=json_decode($version['graph'],TRUE);
        if (!isset($graph['steps']) || !is_array($graph['steps']))
            throw new DomainException('Approval graph is invalid.');
        $index=NULL;
        foreach ($graph['steps'] as $i=>$step) {
            if (($step['key']??'')===$key) {$index=$i;break;}
        }
        if ($index===NULL) throw new DomainException('Approval step was not found.');
        $swap=$index+($direction==='up'?-1:1);
        if ($swap<0 || $swap>=count($graph['steps']))
            throw new DomainException('Approval step is already at the end of the route.');
        [$graph['steps'][$index],$graph['steps'][$swap]]=
            [$graph['steps'][$swap],$graph['steps'][$index]];
        foreach ($graph['steps'] as $i=>&$step) $step['key']='step_'.($i+1);
        unset($step);
        $this->ci->db->where('id',$id)->where('status','draft')
            ->update('workflow_versions',['graph'=>json_encode($graph,JSON_UNESCAPED_UNICODE)]);
    }

    private function validate_approval_graph($graph)
    {
        $steps=$graph['steps']??NULL;
        if (!is_array($steps) || !$steps || count($steps)>30)
            throw new DomainException('An approval route must contain between 1 and 30 steps.');
        foreach ($steps as $i=>$step) {
            if (($step['key']??'')!=='step_'.($i+1) ||
                trim((string)($step['name']??''))==='' ||
                mb_strlen((string)$step['name'])>120) {
                throw new DomainException('Approval steps must have ordered keys and a name.');
            }
            $assignee=$step['approver']??[];
            $type=(string)($assignee['type']??'');
            $value=(int)($assignee['value']??0);
            if (!in_array($type,['user','role','requester_leader','requester'],TRUE))
                throw new DomainException('Every approval step needs a valid approver type.');
            if ($type==='user' &&
                !$this->ci->db->get_where('users',['id'=>$value,'active'=>1])->row_array())
                throw new DomainException('An approver user is no longer active.');
            if ($type==='role') {
                $role=$this->ci->db->get_where('roles',['id'=>$value,'active'=>1])->row_array();
                if (!$role || !$this->ci->db->get_where('users',[
                    'role_id'=>$value,'active'=>1
                ])->row_array()) {
                    throw new DomainException('Every approver role must be active and contain an active user.');
                }
            }
        }
    }

    public function publish_workflow($versionId,$actorId)
    {
        $v=$this->ci->db->get_where('workflow_versions',['id'=>$versionId,'status'=>'draft'])->row_array();
        if (!$v) throw new DomainException('Only draft versions can be published.');
        $this->validate_approval_graph(json_decode($v['graph'],TRUE)?:[]);
        $this->ci->db->trans_begin();
        $workflow=$this->ci->db->get_where('workflows',['id'=>$v['workflow_id']])->row_array();
        if (!$workflow) throw new DomainException('Workflow not found.');
        $related=$this->ci->db->select('id')->get_where('workflows',[
            'request_type'=>$workflow['request_type']
        ])->result_array();
        $ids=array_column($related,'id');
        if ($ids) {
            $this->ci->db->where_in('workflow_id',$ids)
                ->update('workflow_versions',['is_default'=>0]);
            // The source schema enforces UNIQUE(active_request_type) in
            // workflows: deactivate the old route BEFORE activating the new.
            $this->ci->db->where_in('id',$ids)->where('id !=',$v['workflow_id'])
                ->update('workflows',['active'=>0]);
        }
        $this->ci->db->where('id',$versionId)->update('workflow_versions',[
            'status'=>'published','is_default'=>1,'published_at'=>date('Y-m-d H:i:s')
        ]);
        $this->ci->db->where('id',$v['workflow_id'])->update('workflows',['active'=>1]);
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback();throw new DomainException('Could not publish.');
        }
        $this->ci->db->trans_commit();
    }
    public function clone_workflow($workflowId,$actorId)
    {
        $existingDraft=$this->ci->db->get_where('workflow_versions',[
            'workflow_id'=>$workflowId,'status'=>'draft'
        ])->row_array();
        if ($existingDraft) {
            throw new DomainException('An editable draft already exists for this workflow.');
        }
        $v=$this->ci->db->from('workflow_versions')->where('workflow_id',$workflowId)
            ->order_by('version_number','DESC')->limit(1)->get()->row_array();
        if (!$v) throw new DomainException('No workflow version to copy.');
        $this->ci->db->insert('workflow_versions',[
            'workflow_id'=>$workflowId,'version_number'=>(int)$v['version_number']+1,
            'status'=>'draft','is_default'=>0,'graph'=>$v['graph'],'created_by'=>$actorId
        ]);
    }
}
