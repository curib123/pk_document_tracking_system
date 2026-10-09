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
            if (!$this->ci->db->get_where('workflows',['id'=>$id])->row_array())
                throw new DomainException('Workflow not found.');
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
        $value=(int)($post['approver_value']??0);
        if ($name==='' || !in_array($type,['user','role','requester_leader','requester'],TRUE))
            throw new DomainException('Choose an approver type and step name.');
        if (in_array($type,['user','role'],TRUE) && !$value)
            throw new DomainException('Choose an approver user or role.');
        $key='step_'.(count($graph['steps']??[])+1);
        $graph['steps'][]=['key'=>$key,'name'=>$name,'approver'=>[
            'type'=>$type,'value'=>in_array($type,['user','role'],TRUE)?$value:NULL,'label'=>$name
        ]];
        $this->ci->db->where('id',$versionId)->update('workflow_versions',[
            'graph'=>json_encode($graph,JSON_UNESCAPED_UNICODE)
        ]);
    }
    public function publish_workflow($versionId,$actorId)
    {
        $v=$this->ci->db->get_where('workflow_versions',['id'=>$versionId,'status'=>'draft'])->row_array();
        if (!$v) throw new DomainException('Only draft versions can be published.');
        $steps=(json_decode($v['graph'],TRUE)['steps']??[]);
        if (!$steps) throw new DomainException('Add at least one approval step before publishing.');
        $this->ci->db->trans_begin();
        $this->ci->db->where('workflow_id',$v['workflow_id'])->update('workflow_versions',['is_default'=>0]);
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
        $v=$this->ci->db->from('workflow_versions')->where('workflow_id',$workflowId)
            ->order_by('version_number','DESC')->limit(1)->get()->row_array();
        if (!$v) throw new DomainException('No workflow version to copy.');
        $this->ci->db->insert('workflow_versions',[
            'workflow_id'=>$workflowId,'version_number'=>(int)$v['version_number']+1,
            'status'=>'draft','is_default'=>0,'graph'=>$v['graph'],'created_by'=>$actorId
        ]);
    }
}
