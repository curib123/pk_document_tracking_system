<?php
declare(strict_types=1);
namespace Pk\Core;
use Pk\Services\RequestService;
final class Seed
{
    public static function capabilities(): array
    {
        $map=[];
        foreach(['users','roles','permissions','areas','specifics','assets','locations','categories'] as $module) $map[$module]=['view','add','edit','delete'];
        return [...$map,'dashboard'=>['view'],'softcopy'=>['view','request','direct'],'hardcopy'=>['view','request','direct'],
            'documents'=>['access_all'],'files'=>['view','view_all','upload','approve','generate'],'requests'=>['view','add','edit','submit','cancel','approve','manage','view_all'],
            'workflows'=>['view','edit','reassign'],'transfer'=>['view','view_all','request','manage'],'access'=>['view','view_all','request','revoke','manage'],
            'assignment'=>['view','view_all','request','manage'],'disposal'=>['view','view_all','request'],'notifications'=>['view','edit'],'audit'=>['view'],'sequences'=>['view'],'settings'=>['view','edit']];
    }
    public static function run(Database $db,string $username,string $password): void
    {
        $db->transaction(function() use($db,$username,$password) {
            if ($db->first($db->builder->reset_query()->select('id')->limit(1)->get('users'))) throw new Problem('Existing accounts found. Installer will not overwrite them.',409);
            $ids=[];
            foreach(self::capabilities() as $module=>$actions) foreach($actions as $action) {
                $label=ucwords(str_replace('_',' ',$module)); $actionLabel=ucwords(str_replace('_',' ',$action));
                $ids[$module.'.'.$action]=$db->insert('permissions',['name'=>"$label: $actionLabel",'module_key'=>$module,'module_label'=>$label,'action_key'=>$action,'action_label'=>$actionLabel,'description'=>"Allows $action operations for $module."]);
            }
            $baseline=['dashboard.view','softcopy.view','softcopy.request','hardcopy.view','hardcopy.request','categories.view','files.view','files.upload','requests.view','requests.add','requests.edit','requests.submit','requests.cancel','transfer.view','transfer.request','access.view','access.request','assignment.view','assignment.request','disposal.view','disposal.request','notifications.view','notifications.edit'];
            $roles=[
                'Administrator'=>array_keys($ids),
                'Staff'=>$baseline,
                'Plant Manager'=>[...$baseline,'requests.approve','requests.view_all','documents.access_all'],
                'Document Control Officer'=>[...$baseline,'softcopy.direct','hardcopy.direct','documents.access_all','requests.approve','requests.manage','requests.view_all','files.approve','files.generate','transfer.manage','access.manage','access.revoke','assignment.manage','audit.view','sequences.view',...array_filter(array_keys($ids),fn($key)=>preg_match('/^(areas|specifics|assets|locations|categories)\./',$key))],
                'Internal Auditor'=>['dashboard.view','softcopy.view','hardcopy.view','documents.access_all','requests.view','requests.view_all','transfer.view','transfer.manage','access.view','access.manage','assignment.view','assignment.manage','disposal.view','audit.view','notifications.view','notifications.edit','categories.view'],
            ];
            // Read-all capabilities are distinct from mutation capabilities. Auditors do not receive transfer.manage/assignment.manage.
            $roles['Internal Auditor']=[...array_values(array_diff($roles['Internal Auditor'],['transfer.manage','assignment.manage','access.manage'])),'transfer.view_all','assignment.view_all','access.view_all','disposal.view_all','files.view','files.view_all'];
            $roleIds=[];
            foreach($roles as $name=>$permissions) {
                $roleIds[$name]=$db->insert('roles',['name'=>$name]);
                foreach(array_unique($permissions) as $permission) $db->insert('role_permissions',['role_id'=>$roleIds[$name],'permission_id'=>$ids[$permission]]);
            }
            $admin=$db->insert('users',['username'=>$username,'first_name'=>'System','last_name'=>'Administrator','position_title'=>'Administrator','role_id'=>$roleIds['Administrator'],'password_hash'=>Rules::password($password,$password),'require_password_change'=>1]);
            $ctx=new Context($db); $ctx->identify($admin);
            foreach(RequestService::TYPES as $type) {
                $workflow=$db->insert('workflows',['workflow_key'=>$type,'name'=>ucwords(str_replace('_',' ',$type)).' approval','request_type'=>$type,'active'=>1,'created_by'=>$admin]);
                $db->insert('workflow_versions',['workflow_id'=>$workflow,'version_number'=>1,'status'=>'published','graph'=>Context::json(WorkflowGraph::defaults()),'created_by'=>$admin,'published_at'=>date('Y-m-d H:i:s')]);
            }
            $db->insert('settings',['setting_key'=>'appearance','value'=>Context::json(['theme_scope'=>'global','color_mode'=>'system','color_theme'=>'default','styling_enabled'=>false])]);
            $ctx->audit('system','installed',$admin,null,['schema_version'=>1]);
        });
    }
}
