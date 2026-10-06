<?php
declare(strict_types=1);
namespace Pk\Services;
use Pk\Core\{Context,Problem,Rules,UiSchema,WorkflowGraph};
final class ReadService
{
    public function __construct(private readonly Context $ctx) {}
    public function metadata(): array
    {
        $modules=array_filter(UiSchema::modules(),fn($m)=>$this->ctx->can($m['permission']));
        foreach($modules as &$module) unset($module['table']); unset($module);
        return ['modules'=>array_values($modules),'document_fields'=>['softcopy'=>UiSchema::documentFields('softcopy'),'hardcopy'=>UiSchema::documentFields('hardcopy')],'request_fields'=>UiSchema::requestFields(),'request_types'=>RequestService::TYPES,'workflow_template'=>WorkflowGraph::defaults(),'permissions'=>$this->ctx->permissions(),'user'=>$this->ctx->safeUser()];
    }
    private function scope(string $module): array
    {
        $definition=UiSchema::modules()[$module] ?? throw new Problem('Unknown module.',404); $this->ctx->require($definition['permission']);
        $conditions=['1=1']; $params=[]; $id=$this->ctx->id();
        if (in_array($module,['requests','my_requests','my_tasks'],true)) {
            if ($module==='my_requests') { $conditions[]='t.requested_by=?'; $params[]=$id; }
            elseif ($module==='my_tasks') {
                $conditions[]="t.status='pending' AND EXISTS (SELECT 1 FROM workflow_steps s WHERE s.request_id=t.id AND s.status='pending' AND JSON_CONTAINS(s.candidates,JSON_OBJECT('id',CAST(? AS UNSIGNED))))"; $params[]=$id;
            } elseif (!$this->ctx->can('requests.view_all')) {
                $conditions[]="(t.requested_by=? OR EXISTS (SELECT 1 FROM workflow_steps s WHERE s.request_id=t.id AND (s.acting_user_id=? OR JSON_CONTAINS(s.candidates,JSON_OBJECT('id',CAST(? AS UNSIGNED))))) OR EXISTS (SELECT 1 FROM transfers tr WHERE tr.request_id=t.id AND (tr.recipient_id=? OR tr.current_holder_id=?)))";
                $params=[$id,$id,$id,$id,$id];
            }
        }
        if ($module==='transfers' && !$this->ctx->can('transfer.manage') && !$this->ctx->can('transfer.view_all')) { $conditions[]='(t.recipient_id=? OR t.current_holder_id=?)'; $params=[$id,$id]; }
        if ($module==='access' && !$this->ctx->can('access.manage') && !$this->ctx->can('access.view_all')) { $conditions[]='t.user_id=?'; $params[]=$id; }
        if ($module==='assignments' && !$this->ctx->can('assignment.manage') && !$this->ctx->can('assignment.view_all')) { $conditions[]='t.user_id=?'; $params[]=$id; }
        if ($module==='disposals' && !$this->ctx->can('documents.access_all') && !$this->ctx->can('disposal.view_all')) { $conditions[]='EXISTS (SELECT 1 FROM requests r WHERE r.id=t.request_id AND r.requested_by=?)'; $params[]=$id; }
        if ($module==='files' && !$this->ctx->can('files.approve') && !$this->ctx->can('files.view_all')) { $conditions[]='t.uploaded_by=?'; $params[]=$id; }
        if ($module==='notifications') { $conditions[]='t.user_id=?'; $params[]=$id; }
        return [$definition,implode(' AND ',$conditions),$params];
    }
    public function listing(string $module,array $query): array
    {
        [$definition,$where,$params]=$this->scope($module); [$page,$limit]=Rules::page($query); $table=$definition['table'];
        $search=Rules::text($query,'q',150,false);
        if ($search) {
            $parts=[]; foreach($definition['columns'] as $column) { $parts[]="CAST(t.`$column` AS CHAR) LIKE ?"; $params[]='%'.$search.'%'; }
            $where.=' AND ('.implode(' OR ',$parts).')';
        }
        $sort=$query['sort'] ?? $definition['columns'][0]; if (!in_array($sort,$definition['columns'],true)) $sort=$definition['columns'][0];
        $direction=strtolower((string)($query['direction'] ?? 'desc'))==='asc'?'ASC':'DESC'; $offset=($page-1)*$limit;
        $count=(int)$this->ctx->db->one("SELECT COUNT(*) n FROM `$table` t WHERE $where",$params)['n'];
        $select=$module==='users'?'t.id,t.username,t.first_name,t.middle_name,t.last_name,t.position_title,t.role_id,t.leader_id,t.require_password_change,t.active,t.version,t.created_at,t.updated_at':'t.*';
        $rows=$this->ctx->db->all("SELECT $select FROM `$table` t WHERE $where ORDER BY t.`$sort` $direction LIMIT $limit OFFSET $offset",$params);
        $rows=array_map(fn($row)=>$this->safe($row),$rows);
        if ($module==='access') foreach($rows as &$row) if ($row['status']==='access_granted' && strtotime($row['expires_at'])<time()) $row['status']='expired'; unset($row);
        return ['rows'=>$rows,'total'=>$count,'page'=>$page,'limit'=>$limit,'pages'=>max(1,(int)ceil($count/$limit))];
    }
    private function safe(array $row): array
    {
        unset($row['password_hash'],$row['session_version'],$row['storage_name']);
        foreach(['payload','snapshot','result','graph','config','value','origin','destination','candidates','assignment','before_state','after_state','previous_state','approver_config'] as $key) if (isset($row[$key]) && is_string($row[$key])) { $decoded=json_decode($row[$key],true); if (json_last_error()===JSON_ERROR_NONE) $row[$key]=$decoded; }
        return $row;
    }
    public function detail(string $module,int $id): array
    {
        [$definition,$where,$params]=$this->scope($module);
        if ($module==='sequences') throw new Problem('Sequences are read-only counters.');
        $table=$definition['table']; $row=$this->ctx->db->one("SELECT t.* FROM `$table` t WHERE t.id=? AND $where",[$id,...$params]) ?? throw new Problem('Record not found or unavailable to this account.',404);
        $related=[];
        if (in_array($module,['softcopy','hardcopy'],true)) {
            $content=(new DocumentService($this->ctx))->canRead($module,$id);
            if ($content) $related['files']=array_map($this->safe(...),$this->ctx->db->all('SELECT * FROM files WHERE domain=? AND document_id=? ORDER BY id DESC',[$module,$id]));
            if ($module==='softcopy') {
                $related['revisions']=$this->ctx->db->all("SELECT r.*,CASE WHEN r.id=? THEN 'current' ELSE 'historical' END revision_status FROM softcopy_revisions r WHERE r.document_id=? ORDER BY revision_number DESC",[$row['current_revision_id'],$id]);
                if ($content) $related['artifacts']=$this->ctx->db->all('SELECT a.* FROM revision_artifacts a JOIN softcopy_revisions r ON r.id=a.revision_id WHERE r.document_id=? ORDER BY a.id DESC',[$id]);
            }
            $related['disposals']=array_map($this->safe(...),$this->ctx->db->all('SELECT * FROM disposals WHERE domain=? AND document_id=? ORDER BY id DESC',[$module,$id]));
            $related['status_history']=$this->ctx->db->all('SELECT * FROM status_history WHERE domain=? AND document_id=? ORDER BY id DESC',[$module,$id]);
            $config=$this->ctx->db->one('SELECT * FROM document_approvers WHERE domain=? AND document_id=?',[$module,$id]);
            $related['approver_config']=$config?$this->safe($config):null;
            $related['can_read_files']=$content;
        }
        if (in_array($module,['requests','my_requests','my_tasks'],true)) {
            $related['steps']=array_map($this->safe(...),$this->ctx->db->all('SELECT * FROM workflow_steps WHERE request_id=? ORDER BY id',[$id]));
            $related['history']=array_map($this->safe(...),$this->ctx->db->all('SELECT * FROM workflow_history WHERE request_id=? ORDER BY id',[$id]));
            $related['transfer']=$this->ctx->db->one('SELECT id FROM transfers WHERE request_id=?',[$id]);
        }
        if ($module==='workflows') $related['versions']=array_map($this->safe(...),$this->ctx->db->all('SELECT * FROM workflow_versions WHERE workflow_id=? ORDER BY version_number DESC',[$id]));
        if ($module==='roles') {
            $related['permission_ids']=array_map('intval',array_column($this->ctx->db->all('SELECT permission_id FROM role_permissions WHERE role_id=?',[$id]),'permission_id'));
            if ($this->ctx->can('roles.edit')) $related['available_permissions']=$this->ctx->db->all('SELECT id,name,module_label,action_label FROM permissions ORDER BY module_key,action_key');
        }
        return ['row'=>$this->safe($row),'related'=>$related];
    }
    public function lookups(array $query): array
    {
        $kind=Rules::choice($query,'kind',['users','roles','permissions','areas','specifics','assets','locations','categories','softcopy','hardcopy']);
        $table=match($kind){'softcopy'=>'softcopy_documents','hardcopy'=>'hardcopy_documents',default=>$kind};
        if (in_array($kind,['softcopy','hardcopy'],true)) $this->ctx->require($kind.'.view');
        if ($kind==='permissions') $this->ctx->require('permissions.view');
        $label=match($kind){'users'=>"CONCAT(first_name,' ',last_name,' — ',position_title)",'assets'=>'asset_number','softcopy'=>"CONCAT(document_number,' — ',title)",'hardcopy'=>'title','locations'=>"CONCAT(code,' — ',name)",default=>'name'};
        $where=in_array($kind,['softcopy','hardcopy'],true)?"status='active'":($kind==='permissions'?'1=1':'active=1');
        $search=Rules::text($query,'q',100,false) ?? ''; $params=['%'.$search.'%']; $where.=" AND ($label) LIKE ?";
        if (isset($query['selected']) && filter_var($query['selected'],FILTER_VALIDATE_INT)) { $where='('.$where.') OR id=?'; $params[]=(int)$query['selected']; }
        $rows=$this->ctx->db->all("SELECT id,$label label FROM `$table` WHERE $where ORDER BY label LIMIT 101",$params);
        $more=count($rows)>100; return ['options'=>array_slice($rows,0,100),'more'=>$more,'message'=>$more?'More results exist. Type a narrower search.':''];
    }
    public function dashboard(): array
    {
        $this->ctx->require('dashboard.view'); $db=$this->ctx->db; $id=$this->ctx->id(); $result=[];
        foreach(['softcopy'=>'softcopy_documents','hardcopy'=>'hardcopy_documents'] as $module=>$table) if ($this->ctx->can($module.'.view')) $result[$module]=$db->all("SELECT status,COUNT(*) total FROM $table GROUP BY status");
        $result['my_requests']=$db->all('SELECT status,COUNT(*) total FROM requests WHERE requested_by=? GROUP BY status',[$id]);
        $result['unread_notifications']=(int)$db->one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL',[$id])['n'];
        $result['pending_receipts']=(int)$db->one("SELECT COUNT(*) n FROM transfers WHERE recipient_id=? AND status='pending_recipient_acceptance'",[$id])['n'];
        return $result;
    }
    public function readNotification(array $input): array
    {
        $this->ctx->require('notifications.edit'); $db=$this->ctx->db; $row=$db->lock('notifications',Rules::id($input),Rules::id($input,'version'));
        if ((int)$row['user_id']!==$this->ctx->id()) throw new Problem('This notification belongs to another user.',403);
        if ($row['read_at']===null) $db->update('notifications',(int)$row['id'],['read_at'=>date('Y-m-d H:i:s')]);
        return ['message'=>'Notification marked as read.'];
    }
}
