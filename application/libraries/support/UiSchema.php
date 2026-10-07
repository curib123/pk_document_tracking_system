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
        $add('softcopy','Softcopy documents','softcopy_documents',['id','document_number','title','status','version']);
        $add('hardcopy','Hardcopy documents','hardcopy_documents',['id','title','area_id','location_id','holder_id','status','version']);
        foreach(['requests'=>'Requests','my_requests'=>'My requests','my_tasks'=>'My tasks'] as $key=>$label) $add($key,$label,'requests',['id','reference','type','status','current_node','requested_by','created_at'],[], 'requests.view');
        $add('transfers','Hardcopy transfers','transfers',['id','request_id','hardcopy_id','recipient_id','status','recipient_status'],[],'transfer.view');
        $add('access','Access grants','access_grants',['id','request_id','domain','document_id','user_id','status','expires_at']);
        $add('assignments','Assigned documents','assignments',['id','softcopy_id','user_id','active','assigned_at'],[],'assignment.view');
        $add('disposals','Disposal records','disposals',['id','domain','document_id','disposal_action','disposed_by','disposed_at'],[],'disposal.view');
        $add('files','Files and attachments','files',['id','original_name','purpose','domain','document_id','status','size','created_at']);
        $add('workflows','Workflow builder','workflows',['id','name','request_type','active','version'],[$f('name'),$f('description','textarea',false)]);
        $add('users','Users','users',['id','username','first_name','last_name','role_id','active','require_password_change'],[$f('username'),$f('first_name'),$f('middle_name','text',false),$f('last_name'),$f('position_title'),$f('role_id','lookup',true,'roles'),$f('leader_id','lookup',false,'users'),$active]);
        $add('roles','Roles','roles',['id','name','active','version'],[$f('name'),$active]);
        $add('permissions','Permissions','permissions',['id','name','module_key','action_key'],[$f('name'),$f('module_key'),$f('module_label'),$f('action_key'),$f('action_label'),$f('description','textarea',false)]);
        $add('areas','Areas','areas',['id','name','active'],[$f('name'),$active]);
        $add('specifics','Specifics','specifics',['id','name','area_id','active'],[$f('name'),$f('area_id','lookup',true,'areas'),$active]);
        $add('assets','Assets','assets',['id','asset_number','specific_id','active'],[$f('asset_number'),$f('specific_id','lookup',true,'specifics'),$active]);
        $add('locations','Locations','locations',['id','name','code','specific_id','asset_id','active','archive_date'],[$f('name'),$f('code'),$f('specific_id','lookup',true,'specifics'),$f('asset_id','lookup',true,'assets'),$active,$f('archive_date','date',false)]);
        $add('categories','Softcopy categories','categories',['id','name','folder_name','parent_id','active'],[$f('name'),$f('folder_name'),$f('description','textarea',false),$f('parent_id','lookup',false,'categories'),$active]);
        $add('notifications','Notifications','notifications',['id','title','message','read_at','created_at']);
        $add('audit','Audit log','audit_logs',['id','username','module','action','entity_id','created_at']);
        $add('history','Document status history','status_history',['id','domain','document_id','previous_status','new_status','action','created_at'],[],'audit.view');
        $add('sequences','Sequences','sequences',['sequence_key','value']);
        $add('settings','System settings','settings',['id','setting_key','value','version'],[$f('value','json')]);
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
