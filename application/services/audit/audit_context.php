<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Audit only approved metadata; never serialize whole POST bodies or DB rows. */
class Audit_context
{
    private $db;
    private $input;
    private $route;
    private $targets = [];
    private $before;
    private $truncated = FALSE;
    private const FIELDS = ['id','title','name','username','asset_number','folder_name','version','status','active',
        'holder_id','area_id','specific_id','asset_id','location_id','category_id','parent_id',
        'role_id','leader_id','position_title','document_id','softcopy_id','hardcopy_id','user_id',
        'recipient_id','current_holder_id','destination','recipient_status','transferred_by','accepted_by',
        'request_id','requested_by','type','current_node','current_revision_id','revision_number',
        'file_id','purpose','workflow_id','workflow_version_id','node_key','label','assignment','assigned_user_id','graph',
        'assigned_by','assigned_at','permission_id','version_number','is_default','expires_at','revoked_at',
        'created_at','updated_at','submitted_at','completed_at','transferred_at','accepted_at',
        'document_number','sequence_number','series_number','retention_enabled','retention_start_date','retention_end_date',
        'archive_date','previous_status','new_revision_level','new_effective_date','effective_date','date_received','date_released','page_number',
        'first_name','middle_name','last_name','require_password_change','domain','original_name','extension','mime_type','size','fingerprint',
        'approved_by','approved_at','rejected_by','rejected_at','rejection_reason','uploaded_by','created_by','source_request_id',
        'granted_by','granted_at','revoked_by','disposal_action','disposed_by','disposed_at'];

    public function __construct($db, string $route, array $post)
    {
        $this->db = $db;
        $this->route = $route;
        $keys = ['id','version','document_id','softcopy_id','hardcopy_id','request_id','document_domain',
            'recipient_id','location_id','category_id','workflow_id','version_id','step_id','type','decision','direction','backup_name'];
        $this->input = array_filter(array_intersect_key($post, array_flip($keys)), 'is_scalar');
        $parts = explode('/', $route);
        $id = (int) ($post['id'] ?? 0);
        $table = NULL;
        if ($route === 'change-password') $table = 'users';
        if (($parts[0] ?? '') === 'documents') $table = ($parts[1] ?? '') === 'hardcopy' ? 'hardcopy_documents' : 'softcopy_documents';
        if (in_array($parts[0] ?? '', ['my-requests','my-tasks'], TRUE))
            $table = in_array($parts[2] ?? '', ['accept','dispatch'], TRUE) ? 'transfers' : 'requests';
        if (($parts[0] ?? '') === 'places') $table = ['area'=>'areas','specific'=>'specifics','asset'=>'assets','location'=>'locations','softcopy-categories'=>'categories'][$parts[1] ?? ''] ?? NULL;
        if (($parts[0] ?? '') === 'admin') $table = ['users'=>'users','roles'=>'roles','workflows'=>'workflow_versions'][$parts[1] ?? ''] ?? NULL;
        if ($table && $id) $this->add($table, $id);
        foreach (['hardcopy_id'=>'hardcopy_documents','softcopy_id'=>'softcopy_documents','document_id'=>'softcopy_documents','request_id'=>'requests','workflow_id'=>'workflows','version_id'=>'workflow_versions','step_id'=>'workflow_steps'] as $key=>$target)
            if (!empty($post[$key])) $this->add($target, (int) $post[$key]);
        $this->before = $this->snapshot();
    }

    public function add(string $table, int $id): void
    {
        if ($id > 0) $this->targets[$table][$id] = $id;
    }

    private function rows(string $table, string $key, array $ids, bool $bounded = TRUE): array
    {
        if (!$ids) return [];
        $this->db->where_in($key, array_values($ids));
        if ($bounded) $this->db->limit(2001);
        $query = $this->db->get($table);
        $rows = $query ? $query->result_array() : [];
        if ($bounded && count($rows) === 2001) $this->truncated = TRUE;
        return $rows;
    }

    private function snapshot(): array
    {
        $out = [];
        // Bulk assignments cover the whole selection, irrespective of table paging.
        if ($this->route === 'admin/document-assignments/location' && !empty($this->input['location_id'])) {
            $this->db->where('status', 'active');
            foreach ($this->rows('hardcopy_documents', 'location_id', [(int) $this->input['location_id']]) as $row)
                if ($row['status'] === 'active') $this->add('hardcopy_documents', (int) $row['id']);
        }
        if ($this->route === 'admin/document-assignments/category' && !empty($this->input['category_id'])) {
            require_once __DIR__ . '/../presentation/folder_tree.php';
            $nodes = [];
            foreach ($this->db->where('active', 1)->get('categories')->result_array() as $row)
                $nodes[] = ['key'=>'category:'.$row['id'],'parent'=>$row['parent_id'] ? 'category:'.$row['parent_id'] : '', 'name'=>$row['name']];
            $tree = Folder_tree::build($nodes);
            $key = 'category:' . $this->input['category_id'];
            $ids = isset($tree['nodes'][$key]) ? array_map(static function($key){return (int)substr($key,9);}, Folder_tree::branch($tree,$key)) : [];
            if ($ids) $this->db->where('status', 'active');
            foreach ($this->rows('softcopy_documents', 'category_id', $ids) as $row)
                if ($row['status'] === 'active') $this->add('softcopy_documents', (int) $row['id']);
        }
        foreach (['transfers','requests'] as $table) {
            foreach ($this->rows($table, 'id', $this->targets[$table] ?? []) as $row) {
                if (!empty($row['hardcopy_id'])) $this->add('hardcopy_documents', (int)$row['hardcopy_id']);
                if (!empty($row['softcopy_id'])) $this->add('softcopy_documents', (int)$row['softcopy_id']);
                if ($table === 'transfers' && !empty($row['request_id'])) $this->add('requests',(int)$row['request_id']);
                if ($table === 'requests') {
                    $payload = json_decode((string)($row['payload'] ?? ''), TRUE);
                    foreach (['controlled_file_id','revision_file_id'] as $key)
                        if (!empty($payload[$key])) $this->add('files',(int)$payload[$key]);
                }
            }
        }
        foreach ($this->targets as $table=>$ids)
            foreach ($this->rows($table, 'id', $ids) as $row)
                $out[$table . ':' . $row['id']] = array_intersect_key($row, array_flip(self::FIELDS));
        $related = [
            ['assignments','softcopy_id',$this->targets['softcopy_documents'] ?? []],
            ['files','document_id',$this->targets['softcopy_documents'] ?? []],
            ['softcopy_revisions','document_id',$this->targets['softcopy_documents'] ?? []],
            ['access_grants','request_id',$this->targets['requests'] ?? []],
            ['workflow_steps','request_id',$this->targets['requests'] ?? []],
            ['workflow_versions','workflow_id',$this->targets['workflows'] ?? []],
            ['transfers','request_id',$this->targets['requests'] ?? []],
            ['user_roles','user_id',$this->targets['users'] ?? []],
            ['role_permissions','role_id',$this->targets['roles'] ?? []]
        ];
        foreach ($related as [$table,$key,$ids])
            foreach ($this->rows($table,$key,$ids,FALSE) as $index=>$row)
                $out[$table . ':' . ($row['id'] ?? ($row[$key] . ':' . $index))] = array_intersect_key($row,array_flip(self::FIELDS));
        foreach (['hardcopy','softcopy'] as $domain) {
            $ids = $this->targets[$domain . '_documents'] ?? [];
            if (!$ids) continue;
            $this->db->where('domain',$domain);
            foreach ($this->rows('access_grants','document_id',$ids,FALSE) as $row)
                $out['access_grants:' . $row['id']] = array_intersect_key($row,array_flip(self::FIELDS));
        }
        return $out;
    }

    public function finish(): array
    {
        $after = $this->snapshot();
        return ['input'=>$this->input, 'before'=>$this->before, 'after'=>$after, 'truncated'=>$this->truncated];
    }
}
