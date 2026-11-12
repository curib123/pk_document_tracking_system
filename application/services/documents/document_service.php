<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Document_service
{
    private $ci;
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Document_model');
    }
    public function save($domain, $post, $actor)
    {
        $cfg = $this->ci->Document_model->config($domain);
        if ($domain !== 'hardcopy')
            throw new DomainException('Use the controlled softcopy action form.');
        $this->ci->load->model('Permission_model');
        if (!$this->ci->Permission_model->allowed($actor, $domain, 'direct'))
            throw new DomainException('Direct document permission is required.');
        $id = (int) ($post['id'] ?? 0);
        $this->ci->db->trans_begin();
        try {
            $old = NULL;
            if ($id) {
                $old = $this->ci->db->query('SELECT * FROM hardcopy_documents WHERE id=? FOR UPDATE', [$id])->row_array();
                if (!$old || !$this->ci->Document_model->manageable($domain, $id, $actor))
                    throw new DomainException('Only the current holder or an Administrator can edit this active hardcopy.');
                if (isset($post['version']) && $post['version'] !== '' && (int) $post['version'] !== (int) $old['version'])
                    throw new DomainException('This document changed in another session. Refresh before editing.');
            }
            $data = $this->validate_hardcopy_proposal($post, $actor, $id);
            if ($old) {
                $data['version'] = (int) $old['version'] + 1;
                $this->ci->db->where('id', $id)->update($cfg['table'], $data);
            } else {
                $data += [
                    'created_by' => (int) $actor['id'],
                    'creation_source' => 'direct',
                    'creation_reason' => trim((string) ($post['creation_reason'] ?? ''))
                ];
                $this->ci->db->insert($cfg['table'], $data);
                $id = (int) $this->ci->db->insert_id();
            }
            $this->ci->db->insert('status_history', [
                'domain' => $domain,
                'document_id' => $id,
                'previous_status' => $old['status'] ?? '',
                'new_status' => 'active',
                'action' => $old ? 'direct_update' : 'direct_create',
                'user_id' => (int) $actor['id'],
                'remarks' => $old ? 'Direct metadata update' : $data['creation_reason']
            ]);
            if ($this->ci->db->trans_status() === FALSE)
                throw new DomainException('Document save failed. Check unique numbers and related records.');
            $this->ci->db->trans_commit();
            return $id;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException)
                throw $e;
            throw new DomainException('Document could not be saved. Refresh and try again.');
        }
    }
    /**
     * Shared validation for direct Hardcopy and Hardcopy Request submissions.
     * The pending request stores a proposal; only the final approver applies it.
     */
    public function validate_hardcopy_proposal(&$payload, $owner, $documentId = 0)
    {
        $ownerId = (int) $owner['id'];
        $this->ci->load->model('Permission_model');
        $admin = $this->ci->Permission_model->allowed($owner, '*', '*');
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255)
            throw new DomainException('Hardcopy title is required (maximum 255 characters).');
        $data = ['title' => $title];
        foreach (['area_id', 'specific_id', 'asset_id', 'location_id'] as $field)
            $data[$field] = !empty($payload[$field]) ? (int) $payload[$field] : NULL;
        $this->validate_hardcopy_location($data, (int) $documentId);
        $data['retention_enabled'] = !empty($payload['retention_enabled']) ? 1 : 0;
        $data['retention_start_date'] = $data['retention_enabled']
            ? $this->valid_date($payload['retention_start_date'] ?? '') : NULL;
        $data['retention_end_date'] = $data['retention_enabled']
            ? $this->valid_date($payload['retention_end_date'] ?? '') : NULL;
        if (
            $data['retention_enabled'] && (!$data['retention_start_date'] ||
                !$data['retention_end_date'] ||
                $data['retention_end_date'] < $data['retention_start_date'])
        ) {
            throw new DomainException('Retention dates must be valid and ordered.');
        }
        $existing = $documentId ? $this->ci->Document_model->find('hardcopy', $documentId) : NULL;
        if ($documentId && (!$existing || $existing['status'] !== 'active'))
            throw new DomainException('Hardcopy is not available for update.');
        if ($existing && !$admin && (int) $existing['holder_id'] !== $ownerId)
            throw new DomainException('Only the current holder may request this update.');
        // The Sequence belongs to the hardcopy document, not to its Location. It is
        // one-to-many: any number of documents may share the same Sequence, so it is
        // never checked for uniqueness. A blank entry takes the next document count.
        $sequence = trim((string) ($payload['sequence_number'] ?? ''));
        $kept = trim((string) ($existing['sequence_number'] ?? ''));
        if ($sequence === '') {
            $sequence = $kept !== '' ? $kept : (string) $this->next_hardcopy_sequence();
        } elseif ($sequence !== $kept && (!ctype_digit($sequence) || (int) $sequence < 1 || (int) $sequence > 1000000)) {
            throw new DomainException('Sequence must be a whole number from 1 to 1,000,000. Several documents may share one Sequence.');
        }
        $data['sequence_number'] = $sequence;
        $holder = $admin
            ? ((int) ($payload['holder_id'] ?? 0) ?: ($existing['holder_id'] ?? $ownerId))
            : $ownerId;
        if (!$this->ci->db->get_where('users', ['id' => $holder, 'active' => 1])->row_array())
            throw new DomainException('Select an active holder.');
        $data['holder_id'] = $holder;
        foreach ($data as $field => $value)
            $payload[$field] = $value;
        return $data;
    }

    public function apply_hardcopy_request($type, $payload, $ownerId, $approverId, $requestId, $documentId)
    {
        if ($documentId)
            $this->ci->db->query('SELECT id FROM hardcopy_documents WHERE id=? FOR UPDATE', [(int) $documentId]);
        $owner = $this->ci->db->select('u.*,r.name AS role')->from('users u')
            ->join('roles r', 'r.id=u.role_id')->where('u.id', $ownerId)->limit(1)
            ->get()->row_array();
        if (!$owner || !$owner['active'])
            throw new DomainException('Requester is no longer active.');
        $data = $this->validate_hardcopy_proposal($payload, $owner, $documentId);
        if ($type === 'hardcopy_create') {
            $data['created_by'] = (int) $ownerId;
            $data['creation_source'] = 'request';
            $data['creation_reason'] = trim((string) ($payload['creation_reason'] ?? $payload['remarks'] ?? ''));
            $data['source_request_id'] = (int) $requestId;
            if (!$this->ci->db->insert('hardcopy_documents', $data))
                throw new DomainException('Cannot create hardcopy from approved request.');
            $newId = (int) $this->ci->db->insert_id();
            $this->ci->db->where('id', $requestId)->update('requests', ['hardcopy_id' => $newId]);
            return ['document_id' => $newId];
        }
        if ($type !== 'hardcopy_update' || !$documentId)
            throw new DomainException('Invalid approved hardcopy action.');
        $this->ci->db->where('id', $documentId)->where('status', 'active')->set('version', 'version+1', FALSE)
            ->update('hardcopy_documents', $data);
        return ['document_id' => (int) $documentId];
    }

    /** The default Sequence for a new hardcopy: the next number after the highest one in use. */
    private function next_hardcopy_sequence()
    {
        $row = $this->ci->db->query(
            "SELECT COALESCE(MAX(CAST(sequence_number AS UNSIGNED)),0)+1 AS next_sequence
             FROM hardcopy_documents WHERE sequence_number REGEXP '^[0-9]+$'"
        )->row_array();
        return max(1, (int) ($row['next_sequence'] ?? 1));
    }

    private function valid_date($value)
    {
        $value = trim((string) $value);
        if ($value === '')
            return NULL;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException('Enter a valid retention date.');
        }
        return $value;
    }

    /**
     * Resolve the active pk_dts Area > Specific > Asset hierarchy from the
     * actual database. A Location may have nullable ancestors in the source
     * schema; any non-null linked ancestors must match the document choices.
     */
    private function validate_hardcopy_location(&$data, $documentId)
    {
        $db = $this->ci->db;
        $area = (int) ($data['area_id'] ?? 0);
        $specific = (int) ($data['specific_id'] ?? 0);
        $asset = (int) ($data['asset_id'] ?? 0);
        $location = (int) ($data['location_id'] ?? 0);
        if ($location) {
            $row = $db->get_where('locations', ['id' => $location, 'active' => 1])->row_array();
            if (!$row)
                throw new DomainException('Choose an active predefined location.');

            foreach (['area_id', 'specific_id', 'asset_id'] as $field) {
                $existing = (int) ($data[$field] ?? 0);
                $linked = (int) ($row[$field] ?? 0);
                if ($linked && $existing && $linked !== $existing) {
                    throw new DomainException('Selected location conflicts with ' . $field . '.');
                }
                if ($linked)
                    $data[$field] = $linked;
            }
            $area = (int) ($data['area_id'] ?? 0);
            $specific = (int) ($data['specific_id'] ?? 0);
            $asset = (int) ($data['asset_id'] ?? 0);
            // A Location may hold multiple hardcopy records. This requires
            // the location index migration on existing MariaDB installations.
        }
        if ($asset) {
            // One Asset Number may be used under several Specifics: the chosen
            // Specific must be one of its links, never a single stored parent.
            require_once APPPATH . 'services/places/asset_specific_policy.php';
            [$specific, $area] = Asset_specific_policy::resolve($db, $asset, $specific, $area);
        }
        if ($specific) {
            $row = $db->select('s.id,s.area_id')->from('specifics s')
                ->join('areas a', 'a.id=s.area_id')
                ->where('s.id', $specific)->where('s.active', 1)
                ->where('a.active', 1)->get()->row_array();
            if (!$row)
                throw new DomainException('Choose an active specific in an active area.');
            if ($area && $area !== (int) $row['area_id']) {
                throw new DomainException('Specific does not belong to the selected area.');
            }
            $area = (int) $row['area_id'];
        }
        if ($area && !$db->get_where('areas', ['id' => $area, 'active' => 1])->row_array()) {
            throw new DomainException('Choose an active area.');
        }
        require_once APPPATH . 'services/places/hardcopy_place_policy.php';
        Hardcopy_place_policy::require_location_path($area, $specific, $location);
        $data['area_id'] = $area;
        $data['specific_id'] = $specific;
        $data['asset_id'] = $asset ?: NULL;
        $data['location_id'] = $location;
    }

    /**
     * Permanently delete a DISPOSED document and its dependent rows and stored files.
     * Administrator only. The disposals and status_history rows are kept as the
     * permanent record that the document existed and was deleted.
     */
    public function hard_delete($domain, $id, $actorId)
    {
        if (!in_array($domain, ['hardcopy', 'softcopy', 'external'], TRUE))
            throw new DomainException('Hard delete is not available for this document type.');
        $cfg = $domain === 'external'
            ? ['table' => 'external_documents'] : $this->ci->Document_model->config($domain);
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $actor = $this->ci->Identity_model->active_user($actorId);
        if (!$actor || !$this->ci->Permission_model->allowed($actor, 'documents', 'hard_delete'))
            throw new DomainException('Hard delete permission is required.');
        $id = (int) $id;
        $db = $this->ci->db;
        $storage = [];
        $db->trans_begin();
        try {
            $row = $db->query('SELECT * FROM ' . $cfg['table'] . ' WHERE id=? FOR UPDATE', [$id])->row_array();
            if (!$row || $row['status'] !== 'disposed')
                throw new DomainException('Only a disposed document can be permanently deleted.');

            if ($domain === 'hardcopy') {
                $db->delete('transfers', ['hardcopy_id' => $id]);
            } elseif ($domain === 'external') {
                $revisions = array_map('intval', array_column(
                    $db->select('id')->get_where('external_revisions', ['document_id' => $id])->result_array(),
                    'id'
                ));
                $fileIds = array_column(
                    $db->select('id')->get_where('files', ['domain' => 'external', 'document_id' => $id])->result_array(),
                    'id'
                );
                if ($revisions) {
                    $fileIds = array_merge(
                        $fileIds,
                        array_column($db->select('file_id')->where_in('id', $revisions)->get('external_revisions')->result_array(), 'file_id'),
                        array_column($db->select('file_id')->where_in('revision_id', $revisions)->get('external_revision_files')->result_array(), 'file_id')
                    );
                }
                $fileIds = array_values(array_unique(array_map('intval', $fileIds)));
                $db->where('id', $id)->update('external_documents', ['current_revision_id' => NULL]);
                if ($revisions) {
                    $db->where_in('revision_id', $revisions)->delete('external_revision_files');
                    $db->where_in('id', $revisions)->delete('external_revisions');
                }
                foreach ($fileIds as $fileId) {
                    $used = $db->where('file_id', $fileId)->count_all_results('softcopy_revisions')
                        + $db->where('file_id', $fileId)->count_all_results('revision_artifacts')
                        + $db->where('file_id', $fileId)->count_all_results('external_revision_files')
                        + $db->where('file_id', $fileId)->count_all_results('external_revisions');
                    if ($used)
                        continue;
                    $file = $db->get_where('files', ['id' => $fileId])->row_array();
                    if ($file) {
                        $storage[] = $file['storage_name'];
                        $db->delete('files', ['id' => $fileId]);
                    }
                }
            } else {
                $revisions = array_map('intval', array_column(
                    $db->select('id')->get_where('softcopy_revisions', ['document_id' => $id])->result_array(),
                    'id'
                ));
                $fileIds = array_column(
                    $db->select('id')->get_where('files', ['domain' => 'softcopy', 'document_id' => $id])->result_array(),
                    'id'
                );
                if ($revisions) {
                    $fileIds = array_merge(
                        $fileIds,
                        array_column($db->select('file_id')->where_in('document_id', [$id])->get('softcopy_revisions')->result_array(), 'file_id'),
                        array_column($db->select('file_id')->where_in('revision_id', $revisions)->get('revision_artifacts')->result_array(), 'file_id')
                    );
                }
                $fileIds = array_values(array_unique(array_map('intval', $fileIds)));
                $db->where('id', $id)->update('softcopy_documents', ['current_revision_id' => NULL]);
                if ($revisions) {
                    $db->where_in('revision_id', $revisions)->delete('revision_artifacts');
                    $db->where_in('id', $revisions)->delete('softcopy_revisions');
                }
                $db->delete('assignments', ['softcopy_id' => $id]);
                // Keep a file row that some other record still uses; remove only unshared ones.
                foreach ($fileIds as $fileId) {
                    $used = $db->where('file_id', $fileId)->count_all_results('softcopy_revisions')
                        + $db->where('file_id', $fileId)->count_all_results('revision_artifacts')
                        + $db->where('file_id', $fileId)->count_all_results('external_revision_files')
                        + $db->where('file_id', $fileId)->count_all_results('external_revisions');
                    if ($used)
                        continue;
                    $file = $db->get_where('files', ['id' => $fileId])->row_array();
                    if ($file) {
                        $storage[] = $file['storage_name'];
                        $db->delete('files', ['id' => $fileId]);
                    }
                }
            }
            $db->delete('access_grants', ['domain' => $domain, 'document_id' => $id]);
            if ($domain !== 'external')
                $db->where($domain . '_id', $id)->update('requests', [$domain . '_id' => NULL]);
            $db->delete($cfg['table'], ['id' => $id]);
            $db->insert('status_history', [
                'domain' => $domain,
                'document_id' => $id,
                'previous_status' => 'disposed',
                'new_status' => 'deleted',
                'action' => 'hard_delete',
                'user_id' => (int) $actorId,
                'remarks' => 'Permanently deleted by an Administrator: ' . (string) ($row['title'] ?? '')
            ]);
            if ($db->trans_status() === FALSE)
                throw new DomainException('The document could not be permanently deleted because related records are still in use.');
            if ($db->trans_commit() === FALSE)
                throw new DomainException('The permanent deletion could not be committed.');
        } catch (Throwable $e) {
            $db->trans_rollback();
            if ($e instanceof DomainException)
                throw $e;
            log_message('error', 'Hard delete failed: ' . $e->getMessage());
            throw new DomainException('The document could not be permanently deleted. Refresh and try again.');
        }
        if ($storage) {
            require_once APPPATH . 'services/files/file_service.php';
            (new File_service())->purge_storage($storage);
        }
        return (string) ($row['title'] ?? '');
    }

    public function dispose($domain, $id, $actorId, $reason = '', $action = 'other')
    {
        $cfg = $this->ci->Document_model->config($domain);
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $actor = $this->ci->Identity_model->active_user($actorId);
        if (!$actor || !$this->ci->Permission_model->allowed($actor, 'disposal', 'direct'))
            throw new DomainException('Direct disposal permission is required.');
        $this->ci->db->trans_begin();
        try {
            $row = $this->ci->db->query('SELECT * FROM ' . $cfg['table'] . ' WHERE id=? FOR UPDATE', [(int) $id])->row_array();
            if (!$row || !$this->ci->Document_model->manageable($domain, $id, $actor))
                throw new DomainException('Active document is not available for disposal.');
            $this->ci->db->where('id', $id)->update($cfg['table'], [
                'previous_status' => $row['status'],
                'status' => 'disposed',
                'version' => (int) $row['version'] + 1
            ]);
            $this->ci->db->insert('disposals', [
                'domain' => $domain,
                'document_id' => $id,
                'previous_status' => $row['status'],
                'previous_state' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'disposal_action' => $action,
                'remarks' => trim($reason),
                'disposed_by' => $actorId
            ]);
            $this->ci->db->insert('status_history', [
                'domain' => $domain,
                'document_id' => $id,
                'previous_status' => $row['status'],
                'new_status' => 'disposed',
                'action' => 'direct_dispose',
                'user_id' => $actorId,
                'remarks' => trim($reason)
            ]);
            if ($this->ci->db->trans_status() === FALSE)
                throw new DomainException('Disposal could not be recorded.');
            if ($domain === 'softcopy') {
                require_once APPPATH . 'services/files/file_service.php';
                (new File_service())->retire_document((int) $id);
            }
            if ($this->ci->db->trans_status() === FALSE || $this->ci->db->trans_commit() === FALSE)
                throw new DomainException('Disposal could not be committed.');
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException)
                throw $e;
            throw new DomainException('Disposal could not be recorded. Refresh and try again.');
        }
        if ($domain === 'softcopy') (new File_service())->cleanup_retired((int) $id);
    }
}
