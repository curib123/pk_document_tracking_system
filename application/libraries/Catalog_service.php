<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class Catalog_service
{
    public const TABLES=['users','roles','permissions','areas','specifics','assets','locations','categories','settings'];
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    public function save(string $module,array $input): array
    {
        if (!in_array($module,self::TABLES,true)) throw new Problem('Unsupported module.',404);
        $id=Rules::id($input,'id',false); $this->ctx->require($module.'.'.($id?'edit':'add'));
        $db=$this->ctx->model(\Catalog_model::class); $before=$id ? $db->lock($module,$id,Rules::id($input,'version')) : null;
        $data=[]; $extra=[];
        if ($module==='users') {
            $data=['username'=>Rules::text($input,'username',80),'first_name'=>Rules::text($input,'first_name',100),'middle_name'=>Rules::text($input,'middle_name',100,false),'last_name'=>Rules::text($input,'last_name',100),'position_title'=>Rules::text($input,'position_title',150),'role_id'=>Rules::id($input,'role_id'),'leader_id'=>Rules::id($input,'leader_id',false),'active'=>Rules::boolean($input['active'] ?? 1)];
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$data['username'])) throw new Problem('Username must be 3–80 letters, numbers, dots, dashes or underscores.');
            $this->ctx->active('roles',$data['role_id']);
            $this->hierarchy('users',$id,$data['leader_id'],'leader_id');
            if ($id===$this->ctx->id() && (!$data['active'] || $data['role_id']!==(int)$before['role_id'])) throw new Problem('You cannot deactivate yourself or change your own role.');
            if ($id && (!$data['active'] || $data['role_id']!==(int)$before['role_id'])) $this->ensureAdministrator($id,null);
            if (!$id) { $password=bin2hex(random_bytes(10)); $data['password_hash']=password_hash($password,PASSWORD_DEFAULT); $data['require_password_change']=1; $extra['initial_password']=$password; $extra['warning']='Copy this password now. It is shown once and is never stored as plaintext.'; }
            elseif ($data['role_id']!==(int)$before['role_id'] || !$data['active']) $data['session_version']=(int)$before['session_version']+1;
        } elseif ($module==='roles') {
            $data=['name'=>Rules::text($input,'name',120),'active'=>Rules::boolean($input['active'] ?? 1)];
            if ($id && !$data['active']) $this->ensureAdministrator(null,$id);
        } elseif ($module==='permissions') {
            foreach(['name','module_key','module_label','action_key','action_label'] as $field) $data[$field]=Rules::text($input,$field,in_array($field,['module_key','action_key'],true)?60:100);
            foreach(['module_key','action_key'] as $field) if (!preg_match('/^[a-z][a-z0-9_]*$/',$data[$field])) throw new Problem('Permission keys must be lowercase identifiers.');
            if ($id && ($data['module_key']!==$before['module_key'] || $data['action_key']!==$before['action_key'])) throw new Problem('Permission keys are immutable. Add a new permission instead.');
            $data['description']=Rules::text($input,'description',4000,false);
        } elseif ($module==='settings') {
            if (!$id) throw new Problem('Only defined system settings can be edited.');
            $value=Rules::json($input['value'] ?? []);
            if ($before['setting_key']==='appearance') { Rules::choice($value,'color_mode',['light','dark','system']); Rules::choice($value,'theme_scope',['global','user']); Rules::text($value,'color_theme',50); }
            $data=['value'=>Context::json($value)];
        } else {
            $data['active']=Rules::boolean($input['active'] ?? 1);
            if ($module==='assets') $data['asset_number']=Rules::text($input,'asset_number',100);
            else $data['name']=Rules::text($input,'name',150);
            if ($module==='specifics') { $data['area_id']=Rules::id($input,'area_id'); $this->ctx->active('areas',$data['area_id']); }
            if (in_array($module,['assets','locations'],true)) { $data['specific_id']=Rules::id($input,'specific_id'); $this->ctx->active('specifics',$data['specific_id']); }
            if ($module==='locations') {
                $data['asset_id']=Rules::id($input,'asset_id'); $asset=$this->ctx->active('assets',$data['asset_id']);
                if ((int)$asset['specific_id']!==$data['specific_id']) throw new Problem('Asset and location must belong to the same Specific.');
                $data['code']=Rules::text($input,'code',100); $data['archive_date']=Rules::date($input,'archive_date',false);
            }
            if ($module==='categories') {
                $data['parent_id']=Rules::id($input,'parent_id',false); $this->hierarchy('categories',$id,$data['parent_id'],'parent_id');
                $data['folder_name']=Rules::text($input,'folder_name',150); $data['description']=Rules::text($input,'description',4000,false);
                if (!$id) $data['created_by']=$this->ctx->id();
            }
            if ($id) $this->guardReferences($module,$id,$before,$data);
        }
        if ($id) $db->update($module,$id,$data); else $id=$db->insert($module,$data);
        $this->ctx->audit($module,$before?'updated':'created',$id,$before,$data);
        return ['id'=>$id,...$extra];
    }
    public function resetPassword(array $input): array
    {
        $this->ctx->require('users.edit'); $id=Rules::id($input); $reason=Rules::text($input,'reason',2000);
        $user=$this->ctx->model(\Catalog_model::class)->lock('users',$id,Rules::id($input,'version'));
        if ($id===$this->ctx->id()) throw new Problem('Use Change password for your own account.');
        $password=bin2hex(random_bytes(10));
        $this->ctx->model(\Catalog_model::class)->update('users',$id,['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'require_password_change'=>1,'session_version'=>(int)$user['session_version']+1]);
        $this->ctx->audit('users','password_reset',$id,null,null,$reason);
        return ['initial_password'=>$password,'warning'=>'Show once. The user must change it at their next login.'];
    }
    public function permissions(array $input): array
    {
        $this->ctx->require('roles.edit'); $id=Rules::id($input); $db=$this->ctx->model(\Catalog_model::class);
        $before=$db->lock('roles',$id,Rules::id($input,'version'));
        $ids=array_values(array_unique(array_map('intval',Rules::json($input['permission_ids'] ?? []))));
        if (count($ids)>500) throw new Problem('Too many permissions.');
        foreach($ids as $permission) $db->row('permissions',$permission);
        $adminPermission=$db->permission_administrator([]);
        if (!in_array((int)$adminPermission['id'],$ids,true)) $this->ensureAdministrator(null,$id);
        // Keep administrative access recoverable: only another admin may change their own role's capabilities.
        if ($id===(int)$this->ctx->user['role_id']) {
            foreach(['roles.edit','users.edit','users.view','roles.view','permissions.view'] as $cap) {
                [$m,$a]=explode('.',$cap); $p=$db->permission_by_key([$m,$a]);
                if (!in_array((int)$p['id'],$ids,true)) throw new Problem('Do not remove your own administrative recovery permissions.');
            }
        }
        $old=array_column($db->role_permission_ids([$id]),'permission_id');
        $db->clear_role_permissions([$id]);
        foreach($ids as $pid) $db->insert('role_permissions',['role_id'=>$id,'permission_id'=>$pid]);
        $db->update('roles',$id,['name'=>$before['name']]);
        $this->ctx->audit('roles','permissions_changed',$id,$old,$ids,Rules::text($input,'reason',2000));
        return ['message'=>'Permissions updated. Changes apply to the next request.'];
    }
    public function delete(string $module,array $input): array
    {
        if (!in_array($module,self::TABLES,true) || $module==='settings') throw new Problem('This module does not allow deletion.',403);
        $this->ctx->require($module.'.delete'); $db=$this->ctx->model(\Catalog_model::class); $id=Rules::id($input);
        $row=$db->lock($module,$id,Rules::id($input,'version')); $reason=Rules::text($input,'reason',2000);
        if ($module==='users') {
            if ($id===$this->ctx->id()) throw new Problem('You cannot deactivate yourself.');
            $this->ensureAdministrator($id,null);
            $db->update('users',$id,['active'=>0,'session_version'=>(int)$row['session_version']+1]);
            $this->ctx->audit('users','deactivated',$id,$row,['active'=>0],$reason);
            return ['message'=>'Account deactivated. Historical references and audit records were retained.'];
        }
        if ($module==='roles') {
            if ($db->assigned_user([$id])) throw new Problem('This role is assigned to users. Reassign them or deactivate the role instead.');
            $db->clear_role_permissions([$id]);
        }
        if ($module==='permissions') {
            if (isset(\Pk\Core\Seed::capabilities()[$row['module_key']]) && in_array($row['action_key'],\Pk\Core\Seed::capabilities()[$row['module_key']],true)) throw new Problem('Built-in capability definitions cannot be deleted. Change role assignments instead.');
            if ($db->assigned_role([$id])) throw new Problem('This permission is assigned to a role. Remove its assignments first.');
        }
        // Foreign keys reject removal of referenced catalogue records; no cascading domain deletions.
        $db->delete_catalog_record($module, [$id]);
        $this->ctx->audit($module,'deleted',$id,$row,null,$reason);
        return ['message'=>'Unused catalogue record deleted. Audit history was retained.'];
    }
    private function hierarchy(string $table,?int $id,?int $parent,string $column): void
    {
        $seen=[];
        while ($parent) {
            if ($parent===$id || isset($seen[$parent])) throw new Problem('This hierarchy would create a cycle.');
            $seen[$parent]=true; $row=$this->ctx->active($table,$parent); $parent=$row[$column] ? (int)$row[$column] : null;
            if (count($seen)>100) throw new Problem('Hierarchy is too deep.');
        }
    }
    private function ensureAdministrator(?int $user,?int $role): void
    {
        $rows=$this->ctx->model(\Catalog_model::class)->active_administrators([]);
        foreach($rows as $row) if (($user===null || (int)$row['id']!==$user) && ($role===null || (int)$row['role_id']!==$role)) return;
        throw new Problem('At least one active permission administrator must remain.');
    }
    private function guardReferences(string $module,int $id,array $before,array $after): void
    {
        $refs=match($module) {
            'areas'=>[['specifics','area_id']], 'specifics'=>[['assets','specific_id'],['locations','specific_id'],['hardcopy_documents','specific_id']],
            'assets'=>[['locations','asset_id'],['hardcopy_documents','asset_id']], 'locations'=>[['hardcopy_documents','location_id']],
            'categories'=>[['categories','parent_id'],['softcopy_documents','category_id']], default=>[],
        };
        $structural=array_intersect(array_keys($after),['area_id','specific_id','asset_id','parent_id']);
        $changed=!$after['active']; foreach($structural as $key) if (($before[$key] ?? null)!=$after[$key]) $changed=true;
        if (!$changed) return;
        foreach($refs as [$table,$column]) if ($this->ctx->model(\Catalog_model::class)->referenced_record($table, $column, [$id])) throw new Problem('This record is in use. Keep its hierarchy and active status; move dependent records through their proper workflow first.');
    }
}
