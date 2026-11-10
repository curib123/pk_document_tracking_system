<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class External_document_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function manageable(int $documentId, array $user): ?array
    {
        $document = $this->ci->db->get_where('external_documents', [
            'id' => $documentId,
            'status' => 'active'
        ])->row_array();
        if (!$document) {
            return NULL;
        }
        $this->ci->load->model('Permission_model');
        if (
            (int) $document['created_by'] === (int) ($user['id'] ?? 0) ||
            $this->ci->Permission_model->allowed($user, '*', '*') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'access_all') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'view_all')
        ) {
            return $document;
        }
        return NULL;
    }

    public function assert_document_unlocked(int $documentId): void
    {
        $locked = $this->ci->db->query(
            'SELECT f.id FROM external_documents d
             JOIN external_revisions r ON r.id=d.current_revision_id
             JOIN files f ON f.id=r.file_id OR f.id IN (
                 SELECT rf.file_id FROM external_revision_files rf WHERE rf.revision_id=r.id
             )
             WHERE d.id=? AND f.locked_at IS NOT NULL LIMIT 1',
            [$documentId]
        )->row_array();
        if ($locked)
            throw new DomainException('This external document file is locked after approval and cannot be replaced.');
    }

    public function execute_direct(array $post, int $actorId, $attachment): array
    {
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $actor = $this->ci->Identity_model->active_user($actorId);
        if (
            !$actor || !empty($actor['require_password_change']) ||
            !$this->ci->Permission_model->allowed($actor, 'external_documents', 'direct')
        ) {
            throw new DomainException('Direct External permission is required.');
        }
        $type = trim((string) ($post['type'] ?? 'external_create'));
        if (!in_array($type, ['external_create', 'external_revise'], TRUE)) {
            throw new DomainException('Choose External Create or External Revise.');
        }
        $documentId = (int) ($post['external_document_id'] ?? 0);
        if ($type === 'external_revise' && !$this->manageable($documentId, $actor)) {
            throw new DomainException('External document is not available for direct revision.');
        }
        if ($type === 'external_revise')
            $this->assert_document_unlocked($documentId);
        $description = trim((string) ($post['description'] ?? ''));
        $title = trim((string) ($post['title'] ?? ''));
        $documentNumber = trim((string) ($post['external_document_number'] ?? ''));
        $this->validate_metadata($title, $description);
        $this->validate_document_number($documentNumber);
        $subsidiaryId = $this->validate_subsidiary((int) ($post['subsidiary_id'] ?? 0));
        require_once APPPATH . 'services/files/file_service.php';
        $files = new File_service();
        $fileIds = $type === 'external_create'
            ? $files->stage_external_creation($actorId, $attachment)
            : $files->stage_external_revision($documentId, $actorId, $attachment);

        $this->ci->db->trans_begin();
        try {
            $payload = [
                'title' => $title,
                'external_document_number' => $documentNumber,
                'description' => $description,
                'external_category_id' => (int) ($post['external_category_id'] ?? 0),
                'subsidiary_id' => $subsidiaryId,
                ($type === 'external_create' ? 'controlled_file_id' : 'revision_file_id') => $fileIds[0] ?? 0,
                ($type === 'external_create' ? 'controlled_file_ids' : 'revision_file_ids') => $fileIds
            ];
            $result = $this->apply($type, $payload, $documentId, $actorId, $actorId, NULL);
            if ($this->ci->db->trans_status() === FALSE || $this->ci->db->trans_commit() === FALSE) {
                throw new DomainException('External document could not be saved.');
            }
            return $result;
        } catch (Throwable $error) {
            $this->ci->db->trans_rollback();
            $files->cleanup_staged($fileIds);

            if ($error instanceof DomainException) {
                throw $error;
            }
            log_message('error', 'Direct external document action failed: ' . $error->getMessage());
            throw new DomainException('Unable to save the external document action.');
        }
    }

    public function validate_category(int $categoryId): int
    {
        $categoryId = $categoryId ?: 1;
        if (!$this->ci->db->get_where('external_categories', [
            'id' => $categoryId,
            'active' => 1
        ])->row_array()) {
            throw new DomainException('Choose an active external category.');
        }
        return $categoryId;
    }

    public function validate_subsidiary(int $subsidiaryId): ?int
    {
        if ($subsidiaryId === 0) {
            return NULL;
        }
        if (!$this->ci->db->get_where('subsidiaries', [
            'id' => $subsidiaryId,
            'active' => 1
        ])->row_array()) {
            throw new DomainException('Choose an active subsidiary.');
        }
        return $subsidiaryId;
    }

    public function dispose_direct(int $documentId, int $actorId, string $reason, string $action): void
    {
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $actor = $this->ci->Identity_model->active_user($actorId);
        if (
            !$actor || !empty($actor['require_password_change']) ||
            !$this->ci->Permission_model->allowed($actor, 'external_documents', 'direct') ||
            !$this->ci->Permission_model->allowed($actor, 'disposal', 'direct')
        ) {
            throw new DomainException('Direct external document disposal permission is required.');
        }

        $this->ci->db->trans_begin();
        try {
            $document = $this->ci->db->query(
                'SELECT * FROM external_documents WHERE id=? FOR UPDATE',
                [$documentId]
            )->row_array();
            if (!$document || $document['status'] !== 'active' || !$this->manageable($documentId, $actor)) {
                throw new DomainException('Active external document is not available for disposal.');
            }
            $this->ci->db->where('id', $documentId)->update('external_documents', [
                'status' => 'disposed',
                'version' => (int) $document['version'] + 1
            ]);
            $this->ci->db->insert('disposals', [
                'domain' => 'external',
                'document_id' => $documentId,
                'previous_status' => $document['status'],
                'previous_state' => json_encode($document, JSON_UNESCAPED_UNICODE),
                'disposal_action' => $action,
                'remarks' => trim($reason),
                'disposed_by' => $actorId
            ]);
            $this->ci->db->insert('status_history', [
                'domain' => 'external',
                'document_id' => $documentId,
                'previous_status' => $document['status'],
                'new_status' => 'disposed',
                'action' => 'direct_dispose',
                'user_id' => $actorId,
                'remarks' => trim($reason)
            ]);
            if ($this->ci->db->trans_status() === FALSE || $this->ci->db->trans_commit() === FALSE) {
                throw new DomainException('External document disposal could not be recorded.');
            }
        } catch (Throwable $error) {
            $this->ci->db->trans_rollback();
            if ($error instanceof DomainException) {
                throw $error;
            }
            log_message('error', 'Direct external document disposal failed: ' . $error->getMessage());
            throw new DomainException('External document disposal could not be recorded. Refresh and try again.');
        }
    }

    public function apply(string $type, array $payload, int $documentId, int $actorId, int $ownerId, ?int $requestId): array
    {
        if (!in_array($type, ['external_create', 'external_revise'], TRUE)) {
            throw new DomainException('Choose External Create or External Revise.');
        }
        $description = trim((string) ($payload['description'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $documentNumber = trim((string) ($payload['external_document_number'] ?? ''));
        $this->validate_metadata($title, $description);
        $this->validate_document_number($documentNumber);
        $creation = $type === 'external_create';
        $categoryId = (int) ($payload['external_category_id'] ?? 0);
        $subsidiaryId = $this->validate_subsidiary((int) ($payload['subsidiary_id'] ?? 0));
        $fileField = $creation ? 'controlled_file_id' : 'revision_file_id';
        $filesField = $creation ? 'controlled_file_ids' : 'revision_file_ids';
        $fileIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($payload[$filesField] ?? [$payload[$fileField] ?? 0])
        ))));
        if (!$fileIds || count($fileIds) > 20) {
            throw new DomainException('Attach between 1 and 20 files to the external document.');
        }
        $fileId = (int) ($payload[$fileField] ?? $fileIds[0]);
        if (!in_array($fileId, $fileIds, TRUE)) {
            throw new DomainException('The primary external document attachment is invalid.');
        }
        $fileIds = array_values(array_unique(array_merge([$fileId], $fileIds)));
        $fileRows = $this->ci->db->where_in('id', $fileIds)->where([
            'domain' => 'external',
            'document_id' => $creation ? NULL : $documentId,
            'uploaded_by' => $ownerId,
            'purpose' => $creation ? 'creation' : 'revision',
            'status' => 'pending'
        ])->get('files')->result_array();
        if (count($fileRows) !== count($fileIds)) {
            throw new DomainException('One or more pending external document attachments are unavailable.');
        }
        require_once APPPATH . 'services/files/file_service.php';
        $files = new File_service();
        $filesById = [];
        foreach ($fileRows as $file) {
            $filesById[(int) $file['id']] = $file;
        }
        foreach ($fileIds as $id) {
            $files->require_stored_file($filesById[$id]);
        }

        if ($creation) {
            $categoryId = $this->validate_category($categoryId);
            if (!$this->ci->db->insert('external_documents', [
                'title' => $title,
                'document_number' => $documentNumber !== '' ? $documentNumber : NULL,
                'description' => $description,
                'category_id' => $categoryId,
                'subsidiary_id' => $subsidiaryId,
                'created_by' => $ownerId
            ])) {
                $databaseError = $this->ci->db->error();
                $message = (string) ($databaseError['message'] ?? 'unknown database error');
                log_message(
                    'error',
                    'External document insert failed (' . (int) ($databaseError['code'] ?? 0) . '): ' . $message
                );
                if (stripos($message, 'retention_') !== FALSE) {
                    throw new DomainException(
                        'The External Documents database still has obsolete retention fields. Apply 20261104_remove_external_retention.sql, then retry.'
                    );
                }
                if (stripos($message, 'category_id') !== FALSE) {
                    throw new DomainException(
                        'External Categories database setup is incomplete. Apply 20261104_external_categories.sql, then retry.'
                    );
                }
                if (stripos($message, 'subsidiary_id') !== FALSE || stripos($message, 'document_number') !== FALSE) {
                    throw new DomainException(
                        'External document number or subsidiary database setup is incomplete. Apply 20261107_external_document_subsidiaries.sql, then retry.'
                    );
                }
                throw new DomainException('Unable to create the external document.');
            }
            $documentId = (int) $this->ci->db->insert_id();
            $revisionNumber = 1;
            $revisionLevel = '00';
        } else {
            $document = $this->ci->db->query(
                'SELECT * FROM external_documents WHERE id=? FOR UPDATE',
                [$documentId]
            )->row_array();
            if (!$document || $document['status'] !== 'active') {
                throw new DomainException('Select an active external document to revise.');
            }
            $this->assert_document_unlocked($documentId);
            $categoryId = $this->validate_category($categoryId ?: (int) $document['category_id']);
            $previous = $document['current_revision_id']
                ? $this->ci->db->get_where('external_revisions', [
                    'id' => (int) $document['current_revision_id'],
                    'document_id' => $documentId
                ])->row_array()
                : NULL;
            $maximum = $this->ci->db->select_max('revision_number')->get_where(
                'external_revisions',
                ['document_id' => $documentId]
            )->row_array();
            $revisionNumber = (int) ($maximum['revision_number'] ?? 0) + 1;
            $revisionLevel = str_pad((string) max(0, $revisionNumber - 1), 2, '0', STR_PAD_LEFT);
            $previousFiles = $previous
                ? $this->ci->db->select('file_id')->get_where('external_revision_files', [
                    'revision_id' => (int) $previous['id']
                ])->result_array()
                : [];
            $previousFileIds = array_map('intval', array_column($previousFiles, 'file_id'));
            if ($previous && !in_array((int) $previous['file_id'], $previousFileIds, TRUE)) {
                $previousFileIds[] = (int) $previous['file_id'];
            }
            if ($previousFileIds) {
                $this->ci->db->where_in('id', $previousFileIds)
                    ->where('domain', 'external')->where('status', 'approved')
                    ->update('files', ['status' => 'superseded']);
            }
            $this->ci->db->where('id', $documentId)->update('external_documents', [
                'title' => $title,
                'document_number' => $documentNumber !== '' ? $documentNumber : NULL,
                'description' => $description,
                'category_id' => $categoryId,
                'subsidiary_id' => $subsidiaryId,
                'version' => (int) $document['version'] + 1
            ]);
        }

        if (!$this->ci->db->insert('external_revisions', [
            'document_id' => $documentId,
            'revision_number' => $revisionNumber,
            'revision_level' => $revisionLevel,
            'description' => $description,
            'file_id' => $fileId,
            'uploaded_by' => $ownerId,
            'approved_by' => $actorId
        ])) {
            throw new DomainException('Unable to register the external document revision.');
        }
        $revisionId = (int) $this->ci->db->insert_id();
        foreach ($fileIds as $attachedFileId) {
            if (!$this->ci->db->insert('external_revision_files', [
                'revision_id' => $revisionId,
                'file_id' => $attachedFileId
            ])) {
                throw new DomainException('Unable to associate all external document attachments.');
            }
        }
        $this->ci->db->where('id', $documentId)->update('external_documents', [
            'current_revision_id' => $revisionId
        ]);
        $this->ci->db->where_in('id', $fileIds)->where('domain', 'external')->update('files', [
            'document_id' => $documentId,
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
        return [
            'document_id' => $documentId,
            'revision_id' => $revisionId,
            'file_id' => $fileId,
            'revision_level' => $revisionLevel,
            'request_id' => $requestId
        ];
    }

    public function validate_metadata(string $title, string $description): void
    {
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('External document title is required (maximum 255 characters).');
        }
        if (mb_strlen($description) > 2000) {
            throw new DomainException('External document description must be 2000 characters or fewer.');
        }
    }

    public function validate_document_number(string $documentNumber): void
    {
        if (mb_strlen($documentNumber) > 150) {
            throw new DomainException('External document number must be 150 characters or fewer.');
        }
    }

}
