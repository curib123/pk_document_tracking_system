<?php
declare(strict_types=1);
namespace Pk\Core;
final class UiSchema
{
    public static function field(string $name,string $type='text',bool $required=true,?string $lookup=null): array { $displayName=($type==='lookup' || $type==='upload' || $lookup!==null) ? preg_replace('/_id$/','',$name) : $name; return ['name'=>$name,'label'=>ucwords(str_replace('_',' ',$displayName)),'type'=>$type,'required'=>$required,'lookup'=>$lookup]; }
    public static function modules(): array
    {
        $m=[];
        $add=function(string $key,string $label,string $table,array $columns,array $fields=[],?string $permission=null) use (&$m) { $m[$key]=['key'=>$key,'label'=>$label,'table'=>$table,'columns'=>$columns,'fields'=>$fields,'permission'=>$permission ?? $key.'.view']; };
        $f=static fn(...$args)=>self::field(...$args); $active=$f('active','checkbox',false);
        $add('softcopy','Softcopy documents','softcopy_documents',['document_number','title','status']);
        $add('hardcopy','Hardcopy documents','hardcopy_documents',['title','sequence_number','status']);
        foreach(['requests'=>'Requests','my_requests'=>'My requests','my_tasks'=>'My tasks'] as $key=>$label) $add($key,$label,'requests',['reference','type','status','created_at'],[], 'requests.view');
        $add('transfers','Hardcopy transfers','transfers',['document_copy_number','status','recipient_status','created_at'],[],'transfer.view');
        $add('access','Access grants','access_grants',['domain','status','expires_at']);
        $add('assignments','Assigned documents','assignments',['active','assigned_at'],[],'assignment.view');
        $add('disposals','Disposal records','disposals',['domain','disposal_action','disposed_at'],[],'disposal.view');
        $add('files','Files and attachments','files',['original_name','purpose','domain','status','size','created_at']);
        $add('workflows','Workflow builder','workflows',['name','request_type','active'],[$f('name'),$f('description','textarea',false)]);
        $add('users','Users','users',['username','first_name','last_name','position_title','active','require_password_change'],[$f('username'),$f('first_name'),$f('middle_name','text',false),$f('last_name'),$f('position_title'),$f('role_id','lookup',true,'roles'),$f('leader_id','lookup',false,'users'),$active]);
        $add('roles','Roles','roles',['name','active'],[$f('name'),$active]);
        $add('permissions','Permissions','permissions',['name','module_label','action_label'],[$f('name'),$f('module_key'),$f('module_label'),$f('action_key'),$f('action_label'),$f('description','textarea',false)]);
        $add('areas','Areas','areas',['name','active'],[$f('name'),$active]);
        $add('specifics','Specifics','specifics',['name','active'],[$f('name'),$f('area_id','lookup',true,'areas'),$active]);
        $add('assets','Assets','assets',['asset_number','active'],[$f('asset_number'),$f('specific_id','lookup',true,'specifics'),$active]);
        $add('locations','Locations','locations',['name','code','active','archive_date'],[$f('name'),$f('code'),$f('specific_id','lookup',true,'specifics'),$f('asset_id','lookup',true,'assets'),$active,$f('archive_date','date',false)]);
        $add('categories','Softcopy categories','categories',['name','folder_name','active'],[$f('name'),$f('folder_name'),$f('description','textarea',false),$f('parent_id','lookup',false,'categories'),$active]);
        $add('notifications','Notifications','notifications',['title','message','read_at','created_at']);
        $add('audit','Audit log','audit_logs',['username','module','action','created_at']);
        $add('history','Document status history','status_history',['domain','previous_status','new_status','action','created_at'],[],'audit.view');
        $add('sequences','Sequences','sequences',['sequence_key','value']);
        $add('settings','System settings','settings',['setting_key','value'],[$f('value','json')]);
        return $m;
    }
    public static function documentFields(string $domain): array
    {
        $f=static fn(...$args)=>self::field(...$args);
        if ($domain==='softcopy') return [$f('document_number','text',false),$f('series_number','text',false),$f('title'),$f('category_id','lookup',true,'categories'),$f('file_id','upload'),$f('effective_date','date'),$f('page_number','number'),$f('new_revision_level','text',false),$f('date_received','date',false),$f('date_released','date',false),$f('reason','textarea')];
        return [$f('title'),...self::physicalFields(),$f('holder_id','lookup',true,'users'),$f('sequence_number','text',false),$f('retention_enabled','checkbox',false),$f('retention_start_date','date',false),$f('retention_end_date','date',false),$f('reason','textarea')];
    }
    public static function physicalFields(): array { $f=static fn(...$args)=>self::field(...$args); return [$f('area_id','lookup',true,'areas'),$f('specific_id','lookup',true,'specifics'),$f('asset_id','lookup',true,'assets'),$f('location_id','lookup',true,'locations')]; }
    public static function requestFields(): array
    {
        $f=static fn(...$args)=>self::field(...$args); $reason=$f('reason','textarea');
        return ['softcopy_create'=>self::documentFields('softcopy'),'softcopy_revise'=>self::documentFields('softcopy'),'softcopy_cancel'=>[$reason],
            'hardcopy_create'=>self::documentFields('hardcopy'),'hardcopy_update'=>self::documentFields('hardcopy'),
            'transfer'=>[...self::physicalFields(),$f('recipient_id','lookup',true,'users'),$f('document_copy_number'),$f('sequence_number','text',false),$reason],
            'assignment'=>[$f('user_id','lookup',true,'users'),$reason], 'access'=>[$f('expiration_date','date'),$reason],
            'disposal'=>[$f('disposal_action','disposal_action'),$reason]];
    }
}
