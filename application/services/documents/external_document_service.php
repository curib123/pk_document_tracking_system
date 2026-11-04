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
        $title = trim((string) ($post['title'] ?? ''));
        $retention = trim((string) ($post['retention_period'] ?? ''));
        $this->validate_metadata($title, $retention);
        require_once APPPATH . 'services/files/file_service.php';
        $files = new File_service();
        $fileId = $files->stage_external_creation($actorId, $attachment);
        $file = $this->ci->db->get_where('files', ['id' => $fileId])->row_array();

        $this->ci->db->trans_begin();
        try {
            $result = $this->apply('external_create', [
                'title' => $title,
                'retention_period' => $retention,
                'controlled_file_id' => $fileId
            ], 0, $actorId, $actorId, NULL);
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
            log_message('error', 'Direct external document creation failed: ' . $error->getMessage());
            throw new DomainException('Unable to create the external document.');
        }
    }

    public function apply(string $type, array $payload, int $documentId, int $actorId, int $ownerId, ?int $requestId): array
    {
        if (!in_array($type, ['external_create', 'external_revise'], TRUE)) {
            throw new DomainException('Choose External Create or External Revise.');
        }
        $title = trim((string) ($payload['title'] ?? ''));
        $retention = trim((string) ($payload['retention_period'] ?? ''));
        $this->validate_metadata($title, $retention);
        $creation = $type === 'external_create';
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
            if (!$this->ci->db->insert('external_documents', [
                'title' => $title,
                'retention_period' => $retention,
                'created_by' => $ownerId
            ])) {
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
            $revisionLevel = $previous
                ? $this->next_revision_level((string) $previous['revision_level'])
                : '00';
            $this->ci->db->where('id', $documentId)->update('external_documents', [
                'title' => $title,
                'retention_period' => $retention,
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
            'retention_period' => $retention,
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

    public function validate_metadata(string $title, string $retention): void
    {
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('External document title is required (maximum 255 characters).');
        }
        if ($retention === '' || mb_strlen($retention) > 100) {
            throw new DomainException('Retention period is required (maximum 100 characters).');
        }
    }

    private function next_revision_level(string $previous): string
    {
        if (!ctype_digit($previous)) {
            throw new DomainException('The previous external revision level is not numeric; contact an administrator.');
        }
        $next = (string) ((int) $previous + 1);
        return str_pad($next, max(strlen($previous), strlen($next)), '0', STR_PAD_LEFT);
    }
}
