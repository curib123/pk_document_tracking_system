<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'services/documents/scanned_policy.php';
require_once APPPATH . 'services/security/user_roles.php';
require_once APPPATH . 'services/places/scanned_category_service.php';

class Scanned_document_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); }

    public function global_access(array $user): bool
    {
        $this->ci->load->model('Permission_model');
        return $this->ci->Permission_model->allowed($user, '*', '*') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'access_all') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'view_all');
    }

    public function authorize(string $type, array $payload, array $user): void
    {
        $this->ci->load->model('Permission_model');
        $module = $type === 'scanned_category_create' ? 'scanned_categories' : 'scanned_documents';
        if (!$this->ci->Permission_model->allowed($user, $module, Scanned_policy::action($type)))
            throw new DomainException('Permission for this scanned document action is required.');
        if (in_array($type, ['scanned_update', 'scanned_delete'], TRUE)) {
            $document = $this->ci->db->get_where('scanned_documents', [
                'id' => (int) ($payload['scanned_document_id'] ?? 0), 'status' => 'active'
            ])->row_array();
            if (!$document || ((int) $document['created_by'] !== (int) $user['id'] && !$this->global_access($user)))
                throw new DomainException('The scanned document is unavailable or outside your access.');
        }
    }

    public function categories(?array $user = NULL): array
    {
        $rows = $this->ci->db->order_by('name')->get('scanned_categories')->result_array();
        $byId = array_column($rows, NULL, 'id');
        foreach ($rows as &$row) {
            $parts = [$row['name']]; $parent = (int) $row['parent_id']; $seen = [];
            while ($parent && isset($byId[$parent]) && !isset($seen[$parent])) {
                $seen[$parent] = TRUE; array_unshift($parts, $byId[$parent]['name']);
                $parent = (int) $byId[$parent]['parent_id'];
            }
            $row['path'] = implode(' / ', $parts);
        }
        unset($row);
        if ($user !== NULL) {
            $allowed = array_flip((new Scanned_category_service())->usable_ids($user));
            $rows = array_values(array_filter($rows, static fn($row) => isset($allowed[(int) $row['id']])));
        }
        usort($rows, static fn($a, $b) => strnatcasecmp($a['path'], $b['path']));
        return $rows;
    }

    public function validate(string $type, array $payload, int $owner, bool $attachments = TRUE): void
    {
        Scanned_policy::metadata($type, $payload);
        if ($type === 'scanned_delete') return;
        $categoryId = (int) ($payload[$type === 'scanned_category_create' ? 'parent_id' : 'scanned_category_id'] ?? 0);
        if (($categoryId || $type !== 'scanned_category_create') && !$this->ci->db->get_where(
            'scanned_categories', ['id' => $categoryId])->row_array())
            throw new DomainException('Choose an approved scanned category. Pending folders cannot be used.');
        $this->ci->load->model('Identity_model');
        $ownerUser = $this->ci->Identity_model->active_user($owner);
        if (!$ownerUser) throw new DomainException('The document owner is no longer active.');
        (new Scanned_category_service())->require_usable($categoryId, $ownerUser);
        if ($type === 'scanned_category_create' || !$attachments) return;
        $ids = array_values(array_unique(array_map('intval', (array) ($payload['scanned_file_ids'] ?? []))));
        if (!$ids && $type === 'scanned_update') return; // Metadata-only change keeps the approved files.
        if (!$ids || count($ids) > 20) throw new DomainException('Attach between 1 and 20 files.');
        $rows = $this->ci->db->where_in('id', $ids)->get_where('files', [
            'domain' => 'scanned', 'status' => 'pending', 'uploaded_by' => $owner,
            'purpose' => $type === 'scanned_create' ? 'creation' : 'revision',
            'document_id' => $type === 'scanned_create' ? NULL : (int) $payload['scanned_document_id']
        ])->result_array();
        if (count($rows) !== count($ids)) throw new DomainException('Scanned attachments are unavailable. Upload them again.');
        require_once APPPATH . 'services/files/file_service.php';
        $files = new File_service();
        foreach ($rows as $file) $files->require_stored_file($file);
    }

    public function prepare(string $type, array $post, array $user, $uploads, array &$paths): array
    {
        $this->authorize($type, $post, $user);
        $old = !empty($post['id']) ? $this->ci->db->get_where('requests', ['id' => (int) $post['id']])->row_array() : NULL;
        $oldPayload = $old ? json_decode($old['payload'], TRUE) : [];
        $payload = [
            'document_domain' => 'scanned',
            'description' => trim((string) ($post['description'] ?? $oldPayload['description'] ?? '')),
            'subject' => trim((string) ($post['subject'] ?? '')),
            'title' => trim((string) ($post['title'] ?? '')), 'parent_id' => (int) ($post['parent_id'] ?? 0),
            'scanned_category_id' => (int) ($post['scanned_category_id'] ?? 0),
            'scanned_document_id' => (int) ($post['scanned_document_id'] ?? 0),
            'retention_start_date' => trim((string) ($post['retention_start_date'] ?? '')),
            'retention_end_date' => trim((string) ($post['retention_end_date'] ?? '')),
            'scanned_file_ids' => $oldPayload['scanned_file_ids'] ?? []
        ];
        $this->validate($type, $payload, (int) $user['id'], FALSE);
        if (in_array($type, ['scanned_update', 'scanned_delete'], TRUE)) {
            $doc = $this->ci->db->get_where('scanned_documents', ['id' => $payload['scanned_document_id']])->row_array();
            if (!array_key_exists('description', $post) && !array_key_exists('description', $oldPayload))
                $payload['description'] = $doc['description'] ?? '';
            // Preserve the version the user actually edited, including returned requests.
            $payload['document_version'] = (int) ($post['document_version'] ?? $oldPayload['document_version'] ?? $doc['version']);
            if ($payload['document_version'] !== (int) $doc['version'])
                throw new DomainException('This scanned document changed. Reload it before requesting another change.');
            if ($type === 'scanned_delete') $payload['title'] = $doc['title'];
        }
        if (in_array($type, ['scanned_create', 'scanned_update'], TRUE) && is_array($uploads)) {
            $names = is_array($uploads['name'] ?? NULL) ? $uploads['name'] : [$uploads['name'] ?? ''];
            $newIds = [];
            if (count($names) > 20) throw new DomainException('Attach at most 20 files.');
            require_once APPPATH . 'services/files/file_service.php';
            $files = new File_service();
            foreach ($names as $i => $name) {
                $upload = [];
                foreach (['name', 'tmp_name', 'size', 'error', 'type'] as $field)
                    $upload[$field] = is_array($uploads[$field] ?? NULL) ? ($uploads[$field][$i] ?? NULL) : ($uploads[$field] ?? NULL);
                if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $newIds[] = $files->stage_scanned($type === 'scanned_create' ? NULL : $payload['scanned_document_id'], (int) $user['id'], $upload);
                $paths[] = PK_ROOT . '/storage/documents/' . $files->last_storage;
            }
            if ($newIds) $payload['scanned_file_ids'] = $newIds;
        }
        $this->validate($type, $payload, (int) $user['id']);
        return $payload;
    }

    public function assert_current(string $type, array $payload): void
    {
        if (!in_array($type, ['scanned_update', 'scanned_delete'], TRUE)) return;
        $doc = $this->ci->db->query('SELECT * FROM scanned_documents WHERE id=? FOR UPDATE',
            [(int) ($payload['scanned_document_id'] ?? 0)])->row_array();
        if (!$doc || $doc['status'] !== 'active' || (int) $doc['version'] !== (int) ($payload['document_version'] ?? 0))
            throw new DomainException('The scanned document changed since this proposal. Reload it and create a new request.');
    }

    public function alerts(array $user, ?DateTimeImmutable $now = NULL): array
    {
        $this->ci->load->model('Permission_model');
        if (!$this->ci->Permission_model->allowed($user, 'scanned_documents', 'view')) return [];
        $zone = new DateTimeZone('Asia/Manila');
        $now = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
        $day = $now->format('Y-m-d');
        $daily = $now->format('H:i') >= '16:00';
        if (!$this->global_access($user)) $this->ci->db->where('created_by', (int) $user['id']);
        $rows = $this->ci->db->where('status', 'active')->where('retention_end_date <', $day)
            ->order_by('retention_end_date')->get('scanned_documents')->result_array();
        $items = [];
        foreach ($rows as $doc) {
            $url = site_url('documents/scanned') . '?' . http_build_query(['q' => $doc['title']]);
            if ($doc['retention_end_date'] < $day) $items[] = [
                'key' => 'scanned-expired:' . $doc['id'] . ':' . $doc['retention_end_date'],
                'title' => $doc['title'], 'reference' => 'Retention ended',
                'label' => Scanned_policy::countdown($doc['retention_end_date'], $now), 'url' => $url
            ];
        }
        if ($daily && $this->ci->Permission_model->allowed($user, 'scanned_documents', 'request'))
            array_unshift($items, [
                'key' => 'scan-daily:' . $day . ':guide', 'title' => '4:00 PM scanned document backup reminder',
                'reference' => 'Daily backup check', 'label' => 'Check whether you have scanned documents to upload as a backup.',
                'url' => site_url('documents/scanned'), 'alarm' => TRUE, 'daily' => TRUE
            ]);
        return $items;
    }

    /** Called only within the request engine's locked final-approval transaction. */
    public function apply(array $request, int $actor): array
    {
        $type = $request['type']; $p = json_decode($request['payload'], TRUE) ?: [];
        $this->validate($type, $p, (int) $request['requested_by']);
        $this->assert_current($type, $p);
        if ($type === 'scanned_category_create') {
            // Unique parent_scope/name prevents duplicate siblings, including root folders.
            if (!$this->ci->db->insert('scanned_categories', [
                'name' => $p['title'], 'parent_id' => $p['parent_id'] ?: NULL,
                'created_by' => (int) $request['requested_by'], 'approved_by' => $actor, 'request_id' => (int) $request['id']
            ])) throw new DomainException('An approved category with that name already exists in this folder.');
            $categoryId = (int) $this->ci->db->insert_id();
            // Approved personal folder requests start allocated to the requester.
            $this->ci->db->insert('scanned_category_allocations', [
                'category_id' => $categoryId, 'user_id' => (int) $request['requested_by'], 'allocated_by' => $actor
            ]);
            return ['category_id' => $categoryId];
        }
        $id = (int) $p['scanned_document_id'];
        $data = ['approved_by' => $actor];
        if ($type === 'scanned_delete') $data['status'] = 'deleted';
        else $data += [
            'title' => $p['title'], 'category_id' => $p['scanned_category_id'],
            'retention_start_date' => $p['retention_start_date'], 'retention_end_date' => $p['retention_end_date']
        ];
        if ($type !== 'scanned_delete' && array_key_exists('description', $p))
            $data['description'] = trim((string) $p['description']);
        if ($type === 'scanned_create') {
            $data['created_by'] = (int) $request['requested_by'];
            $this->ci->db->insert('scanned_documents', $data); $id = (int) $this->ci->db->insert_id();
        } else {
            $data['version'] = (int) $p['document_version'] + 1;
            $this->ci->db->where('id', $id)->update('scanned_documents', $data);
        }
        $ids = array_map('intval', (array) ($p['scanned_file_ids'] ?? []));
        if ($ids || $type === 'scanned_delete')
            $this->ci->db->where(['domain' => 'scanned', 'document_id' => $id, 'status' => 'approved'])
                ->update('files', ['status' => $type === 'scanned_delete' ? 'deleted' : 'superseded']);
        if ($ids && $type !== 'scanned_delete')
            $this->ci->db->where_in('id', $ids)->where('domain', 'scanned')->update('files', [
                'document_id' => $id, 'status' => 'approved', 'approved_by' => $actor, 'approved_at' => date('Y-m-d H:i:s')
            ]);
        // Every approval retains its reason and full proposal in requests/workflow_history.
        return ['document_id' => $id];
    }
}
