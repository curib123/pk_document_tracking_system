<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH.'services/security/user_roles.php';
class Administration_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); }

    private function permission_ids($roleId)
    {
        return array_column($this->ci->db->select('permission_id')->get_where(
            'role_permissions', ['role_id'=>(int)$roleId])->result_array(), 'permission_id');
    }

    private function account_actor($actorId)
    {
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $actor = $this->ci->Identity_model->active_user((int)$actorId);
        if (!$actor) throw new DomainException('Your account is no longer active.');
        return $actor;
    }

    private function ensure_delegation($actor, $role, $permissions = NULL)
    {
        require_once APPPATH.'services/administration/account_policy.php';
        $effective=$this->ci->Permission_model->for_user($actor);
        if (isset($effective['*'])) return;
        $owned=[];
        $catalog=$this->ci->db->select('id,module_key,action_key')->from('permissions')->get()->result_array();
        foreach ($catalog as $permission) {
            if (!empty($effective[$permission['module_key']][$permission['action_key']]))
                $owned[]=(int)$permission['id'];
        }
        if (!Account_policy::can_delegate($actor['role'], $role['name'],
            $owned,
            $permissions ?? $this->permission_ids($role['id']))) {
            throw new DomainException('You cannot delegate actions that none of your assigned roles allows.');
        }
    }

    public function save_user($post, $actorId)
    {
        $actor = $this->account_actor($actorId);
        $id = (int)($post['id'] ?? 0);
        if (!$this->ci->Permission_model->allowed($actor, 'users', $id ? 'edit' : 'add'))
            throw new DomainException('User management permission is required.');
        $data = [
            'username'=>trim((string)($post['username'] ?? '')),
            'first_name'=>trim((string)($post['first_name'] ?? '')),
            'middle_name'=>trim((string)($post['middle_name'] ?? '')) ?: NULL,
            'last_name'=>trim((string)($post['last_name'] ?? '')),
            'position_title'=>trim((string)($post['position_title'] ?? '')),
            'role_id'=>(int)($post['role_id'] ?? 0),
            'leader_id'=>!empty($post['leader_id']) ? (int)$post['leader_id'] : NULL,
            'active'=>!empty($post['active']) ? 1 : 0
        ];
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $data['username']))
            throw new DomainException('Use a username with 3 to 80 letters, numbers, dots, underscores or hyphens.');
        foreach (['first_name'=>100,'middle_name'=>100,'last_name'=>100,'position_title'=>150] as $field=>$max) {
            if (($field !== 'middle_name' && $data[$field] === '') || mb_strlen((string)$data[$field]) > $max)
                throw new DomainException('Enter a valid '.str_replace('_',' ',$field).'.');
        }
        $temporary = NULL;
        $roleIds=array_values(array_unique(array_map('intval',array_merge([$data['role_id']],(array)($post['role_ids']??[])))));
        sort($roleIds,SORT_NUMERIC);
        $this->ci->db->trans_begin();
        try {
            // Serialize role/user changes, including last-administrator checks.
            $this->ci->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE');
            $selectedRoles=$this->ci->db->from('roles')->where_in('id',$roleIds)->where('active',1)->get()->result_array();
            if (count($selectedRoles)!==count($roleIds)) throw new DomainException('Choose active roles.');
            if (count($roleIds)>1 && !User_roles::available($this->ci->db))
                throw new DomainException('Role assignments are not available until the database migration is applied.');
            foreach ($selectedRoles as $role) $this->ensure_delegation($actor,$role);
            $old = $id ? $this->ci->db->query('SELECT * FROM users WHERE id=? FOR UPDATE',[$id])->row_array() : NULL;
            if ($id && !$old) throw new DomainException('User not found.');
            if ($old) {
                $oldRoles=User_roles::assigned($this->ci->db,$id,(int)$old['role_id'],FALSE);
                foreach ($oldRoles as $oldRole) $this->ensure_delegation($actor,$oldRole);
                $oldIds=array_map('intval',array_column($oldRoles,'id'));sort($oldIds,SORT_NUMERIC);
                if ($id === (int)$actorId && (!$data['active'] || (int)$old['role_id'] !== $data['role_id'] || $oldIds!==$roleIds))
                    throw new DomainException('You cannot disable your own account or change your own role.');
                if (isset($post['version']) && (int)$post['version'] !== (int)$old['version'])
                    throw new DomainException('This account changed. Reload it before saving.');
                foreach ($oldRoles as $oldRole) {
                    if (strcasecmp($oldRole['name'],'Administrator')===0 && $oldRole['active'] && $old['active'] &&
                        (!$data['active'] || !in_array((int)$oldRole['id'],$roleIds,TRUE))) {
                        $others=$this->ci->db->from('users u')->where('u.active',1)->where('u.id !=',$id)
                            ->where(User_roles::member_predicate($this->ci->db,(int)$oldRole['id']),NULL,FALSE)->count_all_results();
                        if (!$others) throw new DomainException('Cannot remove the last administrator.');
                    }
                }
            }
            $seen = $id ? [$id=>TRUE] : [];
            $leader = $data['leader_id'];
            while ($leader) {
                if (isset($seen[$leader]) || count($seen) >= 100)
                    throw new DomainException('The reporting line contains a cycle or is too deep.');
                $seen[$leader] = TRUE;
                $row = $this->ci->db->get_where('users',['id'=>$leader,'active'=>1])->row_array();
                if (!$row) throw new DomainException('Choose an active leader.');
                $leader = (int)($row['leader_id'] ?? 0);
            }
            if (!$id || !empty($post['reset_password'])) {
                require_once APPPATH.'services/authentication/authentication_service.php';
                $temporary = Authentication_service::temporary_password();
                $data['password_hash'] = password_hash($temporary, PASSWORD_DEFAULT);
                $data['require_password_change'] = 1;
            }
            if ($id) {
                $data['session_version'] = (int)$old['session_version'] + 1;
                $data['version'] = (int)$old['version'] + 1;
                $this->ci->db->where('id',$id)->update('users',$data);
            } else {
                $this->ci->db->insert('users',$data);
                $id = (int)$this->ci->db->insert_id();
            }
            if (User_roles::available($this->ci->db)) {
                $this->ci->db->where('user_id',$id)->delete('user_roles');
                foreach ($roleIds as $roleId) $this->ci->db->insert('user_roles',['user_id'=>$id,'role_id'=>$roleId]);
            }
            if ($this->ci->db->trans_status() === FALSE)
                throw new DomainException('Could not save user. Check the unique username and relationships.');
            $this->ci->db->trans_commit();
            // Returned only to the authorized POST response, never a log/session/DB plaintext field.
            return ['id'=>$id,'username'=>$data['username'],'temporary_password'=>$temporary];
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            throw new DomainException('Could not save the account.');
        }
    }

    public function deactivate_user($id, $actorId)
    {
        $actor = $this->account_actor($actorId);
        if (!$this->ci->Permission_model->allowed($actor,'users','delete'))
            throw new DomainException('User deactivation permission is required.');
        if ((int)$id === (int)$actorId) throw new DomainException('You cannot deactivate yourself.');
        $this->ci->db->trans_begin();
        try {
            $this->ci->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE');
            $user = $this->ci->db->query('SELECT * FROM users WHERE id=? FOR UPDATE',[(int)$id])->row_array();
            if (!$user) throw new DomainException('User not found.');
            $roles=User_roles::assigned($this->ci->db,(int)$id,(int)$user['role_id'],FALSE);
            foreach ($roles as $role) {
                $this->ensure_delegation($actor,$role);
                if (strcasecmp($role['name'],'Administrator')===0 && $role['active'] && $user['active']) {
                    $others=$this->ci->db->from('users u')->where('u.active',1)->where('u.id !=',(int)$id)
                        ->where(User_roles::member_predicate($this->ci->db,(int)$role['id']),NULL,FALSE)->count_all_results();
                    if (!$others) throw new DomainException('Last administrator cannot be deactivated.');
                }
            }
            $this->ci->db->where('id',(int)$id)->set('active',0)
                ->set('session_version','session_version+1',FALSE)->set('version','version+1',FALSE)->update('users');
            if ($this->ci->db->trans_status() === FALSE) throw new DomainException('Could not deactivate account.');
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            throw new DomainException('Could not deactivate account.');
        }
    }

    private function require_unused_role($role)
    {
        require_once APPPATH.'services/administration/role_usage.php';
        $usage=Role_usage::for_roles($this->ci->db,[(int)$role['id']])[(int)$role['id']];
        if ($usage['user_count'])
            throw new DomainException('This role is assigned to users. Reassign them before deactivating or removing it.');
        if ($usage['workflow_count'])
            throw new DomainException('This role is used by a workflow. Update its approval steps before deactivating or removing it.');
        if ($usage['request_count'])
            throw new DomainException('This role is used by an unfinished request and cannot be deactivated or removed.');
    }

    private function change_unused_role($id,$actorId,$remove)
    {
        $actor=$this->account_actor($actorId);
        if (!$this->ci->Permission_model->allowed($actor,'roles',$remove?'delete':'edit'))
            throw new DomainException('Role management permission is required.');
        $this->ci->db->trans_begin();
        try {
            // Same lock order as account creation/reassignment protects against new user references.
            $this->ci->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE');
            $role=$this->ci->db->get_where('roles',['id'=>(int)$id])->row_array();
            if (!$role) throw new DomainException('Role not found.');
            if (strcasecmp($role['name'],'Administrator')===0)
                throw new DomainException('Administrator privileges are fixed.');
            $this->ensure_delegation($actor,$role);
            $this->require_unused_role($role);
            if ($remove) {
                $this->ci->db->where('role_id',(int)$id)->delete('role_permissions');
                $this->ci->db->where('id',(int)$id)->delete('roles');
            } elseif ($role['active']) {
                $this->ci->db->where('id',(int)$id)->update('roles',[
                    'active'=>0,'version'=>(int)$role['version']+1
                ]);
            }
            if ($this->ci->db->trans_status()===FALSE)
                throw new DomainException('Could not update the role. Refresh and try again.');
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            throw new DomainException('Could not update the role.');
        }
    }

    public function deactivate_role($id,$actorId) { $this->change_unused_role($id,$actorId,FALSE); }
    public function remove_role($id,$actorId) { $this->change_unused_role($id,$actorId,TRUE); }

    public function save_role($post, $actorId)
    {
        $actor = $this->account_actor($actorId);
        $id = (int)($post['id'] ?? 0);
        if (!$this->ci->Permission_model->allowed($actor,'roles',$id ? 'edit' : 'add'))
            throw new DomainException('Role management permission is required.');
        $name = trim((string)($post['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || strcasecmp($name,'Administrator') === 0)
            throw new DomainException('Enter a non-reserved role name, up to 120 characters.');
        $ids = array_values(array_unique(array_filter(array_map('intval',(array)($post['permissions'] ?? [])))));
        $this->ensure_delegation($actor, ['id'=>$id,'name'=>$name], $ids);
        $this->ci->db->trans_begin();
        try {
            $this->ci->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE');
            if ($ids && $this->ci->db->where_in('id',$ids)->count_all_results('permissions') !== count($ids))
                throw new DomainException('One or more selected permissions do not exist.');
            if ($id) {
                $old = $this->ci->db->get_where('roles',['id'=>$id])->row_array();
                if (!$old) throw new DomainException('Role not found.');
                if (strcasecmp($old['name'],'Administrator') === 0)
                    throw new DomainException('Administrator privileges are fixed.');
                $this->ensure_delegation($actor,$old);
                if ($old['active'] && empty($post['active'])) $this->require_unused_role($old);
                $this->ci->db->where('id',$id)->update('roles',[
                    'name'=>$name,'active'=>!empty($post['active'])?1:0,'version'=>(int)$old['version']+1
                ]);
            } else {
                $this->ci->db->insert('roles',['name'=>$name,'active'=>!empty($post['active'])?1:0]);
                $id = (int)$this->ci->db->insert_id();
            }
            $this->ci->db->where('role_id',$id)->delete('role_permissions');
            foreach ($ids as $permission) $this->ci->db->insert('role_permissions',[
                'role_id'=>$id,'permission_id'=>$permission
            ]);
            $this->ci->db->where(User_roles::member_predicate($this->ci->db,$id,'users'),NULL,FALSE)
                ->set('session_version','session_version+1',FALSE)->update('users');
            if ($this->ci->db->trans_status() === FALSE) throw new DomainException('Role update failed. Check duplicate names.');
            $this->ci->db->trans_commit();
            return $id;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            throw new DomainException('Could not save the role.');
        }
    }
    // Workflow definitions are seeded and fixed; only their versioned approval
    // steps may be edited. Never add arbitrary new request-type workflows.
    public function save_workflow($post,$actorId)
    {
        throw new DomainException('Workflow definitions are predefined. Edit approval steps in an existing seeded workflow.');
    }
    // Workflow graph validation and active_request_type publication locking live
    // in one domain service, separate from account and role administration.
    private function workflow_builder()
    {
        require_once APPPATH.'services/workflow/workflow_builder_service.php';
        return new Workflow_builder_service();
    }
    public function save_workflow_step($post) { $this->workflow_builder()->save_workflow_step($post); }
    public function remove_workflow_step($post) { $this->workflow_builder()->remove_workflow_step($post); }
    public function move_workflow_step($post) { $this->workflow_builder()->move_workflow_step($post); }
    public function validate_approval_graph($graph) { $this->workflow_builder()->validate_approval_graph($graph); }
    public function publish_workflow($id,$actorId,$post=[]) { $this->workflow_builder()->publish_workflow($id,$actorId,$post); }
    public function clone_workflow($id,$actorId) { $this->workflow_builder()->clone_workflow($id,$actorId); }
}
