<?php
declare(strict_types=1);

namespace Pk\Core;

final class Seed
{
    public static function capabilities(): array
    {
        $map=[];
        foreach (['users','roles','permissions','areas','specifics','assets','locations','categories'] as $module) {
            $map[$module]=['view','add','edit','delete'];
        }

        return array_merge($map,[
            'dashboard'=>['view'],
            'softcopy'=>['view','request','direct'],
            'hardcopy'=>['view','request','direct'],
            'documents'=>['access_all'],
            'files'=>['view','view_all','upload','approve','generate'],
            'requests'=>['view','add','edit','submit','cancel','manage','view_all'],
            'workflows'=>['view','edit','reassign'],
            'transfer'=>['view','view_all','request','manage'],
            'access'=>['view','view_all','request','revoke','manage'],
            'assignment'=>['view','view_all','request','manage'],
            'disposal'=>['view','view_all','request'],
            'notifications'=>['view','edit'],
            'audit'=>['view'],
            'sequences'=>['view'],
            'settings'=>['view','edit'],
        ]);
    }

    public static function defaultAdmin(): array
    {
        return [
            'username'=>'admin',
            'first_name'=>'System',
            'middle_name'=>null,
            'last_name'=>'Administrator',
            'position_title'=>'Administrator',
            'role'=>'Administrator',
            'active'=>1,
            'require_password_change'=>1,
        ];
    }

    public static function rolePermissions(array $permissionIds): array
    {
        $baseline=[
            'dashboard.view',
            'softcopy.view',
            'softcopy.request',
            'hardcopy.view',
            'hardcopy.request',
            'categories.view',
            'files.view',
            'files.upload',
            'requests.view',
            'requests.add',
            'requests.edit',
            'requests.submit',
            'requests.cancel',
            'transfer.view',
            'transfer.request',
            'access.view',
            'access.request',
            'assignment.view',
            'assignment.request',
            'disposal.view',
            'disposal.request',
            'notifications.view',
            'notifications.edit',
        ];

        $catalogPermissions=array_values(array_filter(
            array_keys($permissionIds),
            static function(string $key): bool {
                return (bool)preg_match('/^(areas|specifics|assets|locations|categories)\./',$key);
            }
        ));

        $plantManager=array_merge(
            $baseline,
            ['requests.view_all','documents.access_all']
        );

        $documentControlOfficer=array_merge(
            $baseline,
            [
                'softcopy.direct',
                'hardcopy.direct',
                'documents.access_all',
                'requests.manage',
                'requests.view_all',
                'files.approve',
                'files.generate',
                'transfer.manage',
                'access.manage',
                'access.revoke',
                'assignment.manage',
                'audit.view',
                'sequences.view',
            ],
            $catalogPermissions
        );

        $internalAuditor=[
            'dashboard.view',
            'softcopy.view',
            'hardcopy.view',
            'documents.access_all',
            'requests.view',
            'requests.view_all',
            'transfer.view',
            'transfer.view_all',
            'access.view',
            'access.view_all',
            'assignment.view',
            'assignment.view_all',
            'disposal.view',
            'disposal.view_all',
            'audit.view',
            'notifications.view',
            'notifications.edit',
            'categories.view',
            'files.view',
            'files.view_all',
        ];

        return [
            'Administrator'=>array_keys($permissionIds),
            'Document Control Officer'=>array_values(array_unique($documentControlOfficer)),
            'Plant Manager'=>array_values(array_unique($plantManager)),
            'Internal Auditor'=>array_values(array_unique($internalAuditor)),
            'Staff'=>array_values(array_unique($baseline)),
        ];
    }

    public static function run(Database $db,string $username,string $password): void
    {
        $db->transaction(function() use($db,$username,$password): void {
            if ($db->first($db->builder->reset_query()->select('id')->limit(1)->get('users'))) {
                throw new Problem('Existing accounts found. Seeder will not overwrite them.',409);
            }

            $permissionIds=[];
            foreach (self::capabilities() as $module=>$actions) {
                foreach ($actions as $action) {
                    $moduleLabel=ucwords(str_replace('_',' ',$module));
                    $actionLabel=ucwords(str_replace('_',' ',$action));
                    $permissionIds[$module.'.'.$action]=$db->insert('permissions',[
                        'name'=>$moduleLabel.': '.$actionLabel,
                        'module_key'=>$module,
                        'module_label'=>$moduleLabel,
                        'action_key'=>$action,
                        'action_label'=>$actionLabel,
                        'description'=>'Allows '.$action.' operations for '.$module.'.',
                    ]);
                }
            }

            $roleIds=[];
            foreach (self::rolePermissions($permissionIds) as $name=>$permissions) {
                $roleIds[$name]=$db->insert('roles',[
                    'name'=>$name,
                    'active'=>1,
                ]);

                foreach ($permissions as $permission) {
                    if (!isset($permissionIds[$permission])) {
                        throw new \RuntimeException('Unknown seeded permission: '.$permission);
                    }
                    $db->insert('role_permissions',[
                        'role_id'=>$roleIds[$name],
                        'permission_id'=>$permissionIds[$permission],
                    ]);
                }
            }

            $adminDefaults=self::defaultAdmin();
            $admin=$db->insert('users',[
                'username'=>$username !== '' ? $username : $adminDefaults['username'],
                'first_name'=>$adminDefaults['first_name'],
                'middle_name'=>$adminDefaults['middle_name'],
                'last_name'=>$adminDefaults['last_name'],
                'position_title'=>$adminDefaults['position_title'],
                'role_id'=>$roleIds[$adminDefaults['role']],
                'password_hash'=>Rules::password($password,$password),
                'require_password_change'=>$adminDefaults['require_password_change'],
                'active'=>$adminDefaults['active'],
            ]);

            $ctx=new Context($db);
            $ctx->identify($admin);

            foreach (\Request_service::TYPES as $type) {
                $workflow=$db->insert('workflows',[
                    'workflow_key'=>$type,
                    'name'=>ucwords(str_replace('_',' ',$type)).' approval',
                    'request_type'=>$type,
                    'active'=>1,
                    'created_by'=>$admin,
                ]);
                $db->insert('workflow_versions',[
                    'workflow_id'=>$workflow,
                    'version_number'=>1,
                    'status'=>'published',
                    'is_default'=>1,
                    'graph'=>Context::json(WorkflowGraph::forRole($roleIds['Administrator'])),
                    'created_by'=>$admin,
                    'published_at'=>date('Y-m-d H:i:s'),
                ]);
            }

            $db->insert('settings',[
                'setting_key'=>'appearance',
                'value'=>Context::json([
                    'theme_scope'=>'global',
                    'color_mode'=>'system',
                    'color_theme'=>'default',
                    'styling_enabled'=>false,
                ]),
            ]);

            $ctx->audit('system','installed',$admin,null,[
                'schema_version'=>1,
                'seeded_roles'=>array_keys($roleIds),
                'seeded_permissions'=>count($permissionIds),
            ]);
        });
    }
}
