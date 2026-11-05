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
        $description = trim((string) ($post['description'] ?? ''));
        $title = trim((string) ($post['title'] ?? ''));
        $this->validate_metadata($title, $description);
        require_once APPPATH . 'services/files/file_service.php';
        $files = new File_service();
        $fileId = $type === 'external_create'
            ? $files->stage_external_creation($actorId, $attachment)
            : $files->stage_external_revision($documentId, $actorId, $attachment);
        $file = $this->ci->db->get_where('files', ['id' => $fileId])->row_array();

        $this->ci->db->trans_begin();
        try {
            $payload = [
                'title' => $title,
                'description' => $description,
                'external_category_id' => (int) ($post['external_category_id'] ?? 0),
                ($type === 'external_create' ? 'controlled_file_id' : 'revision_file_id') => $fileId
            ];
            $result = $this->apply($type, $payload, $documentId, $actorId, $actorId, NULL);
            if ($this->ci->db->trans_status() === FALSE || $this->ci->db->trans_commit() === FALSE) {
                throw new DomainException('External document could not be saved.');
            }
            return $result;
        } catch (Throwable $error) {
            $this->ci->db->trans_rollback();
            if ($file) {
                $this->ci->db->where('id', $fileId)->where('status', 'pending')->delete('files');
                $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
                if (is_file($path) && !is_link($path)) {
                    @unlink($path);
                }
            }

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
        $this->validate_metadata($title, $description);
        $creation = $type === 'external_create';
        $categoryId = (int) ($payload['external_category_id'] ?? 0);
        $fileId = (int) ($payload[$creation ? 'controlled_file_id' : 'revision_file_id'] ?? 0);
        $file = $this->ci->db->get_where('files', [
            'id' => $fileId,
            'domain' => 'external',
            'document_id' => $creation ? NULL : $documentId,
            'uploaded_by' => $ownerId,
            'purpose' => $creation ? 'creation' : 'revision',
            'status' => 'pending'
        ])->row_array();
        if (!$file) {
            throw new DomainException('A pending external document attachment is required.');
        }
        require_once APPPATH . 'services/files/file_service.php';
        (new File_service())->require_stored_file($file);

        if ($creation) {
            $categoryId = $this->validate_category($categoryId);
            if (!$this->ci->db->insert('external_documents', [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId,
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
            $this->ci->db->where('id', $documentId)->update('external_documents', [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId,
                'version' => (int) $document['version'] + 1
            ]);
            if ($previous) {
                $this->ci->db->where('id', (int) $previous['file_id'])
                    ->where('domain', 'external')->where('status', 'approved')
                    ->update('files', ['status' => 'superseded']);
            }
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
        $this->ci->db->where('id', $documentId)->update('external_documents', [
            'current_revision_id' => $revisionId
        ]);
        $this->ci->db->where('id', $fileId)->where('domain', 'external')->update('files', [
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

}
