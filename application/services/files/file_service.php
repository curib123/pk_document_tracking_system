<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    /** A replacement must be usable before the current controlled file is retired. */
    public function require_stored_file(array $file): void
    {
        $path = $this->controlled_path((string) ($file['storage_name'] ?? ''));
        if (
            !$path || !is_readable($path) || (int) filesize($path) !== (int) $file['size'] ||
            !hash_equals((string) $file['fingerprint'], (string) hash_file('sha256', $path))
        )
            throw new DomainException('The controlled revision attachment is unavailable or changed. Upload it again before approval.');
    }

    private function controlled_path(string $storage): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $storage))
            return NULL;
        $directory = realpath(PK_ROOT . '/storage/documents');
        if (!$directory)
            return NULL;
        $candidate = $directory . DIRECTORY_SEPARATOR . $storage;
        if (is_link($candidate))
            return NULL;
        $path = realpath($candidate);
        return $path && is_file($path) && dirname($path) === $directory ? $path : NULL;
    }

    /** Remove superseded bytes only after all database transactions have committed. */
    public function cleanup_superseded(int $documentId): int
    {
        return $documentId > 0 ? $this->cleanup_retired($documentId, 0, ['superseded']) : 0;
    }

    /** Retire metadata inside the caller's transaction; never unlink here. */
    public function retire_document(int $documentId): void
    {
        if ($documentId < 1) return;
        $db = $this->ci->db;
        $document = $db->get_where('softcopy_documents', ['id' => $documentId])->row_array();
        if ($document && !in_array($document['status'], ['disposed', 'cancelled'], TRUE)) return;
        $db->where('domain', 'softcopy')->where('document_id', $documentId)
            ->where_in('status', ['approved', 'pending', 'superseded'])
            ->set('version', 'version + 1', FALSE)->update('files', ['status' => 'deleted']);
    }

    /** A rejected proposal never retires the active document's current file. */
    public function retire_request_attachment(array $request, int $actorId = 0, string $reason = ''): int
    {
        if (!in_array($request['type'], ['softcopy_create', 'softcopy_revise'], TRUE)) return 0;
        $payload = json_decode($request['payload'] ?? '{}', TRUE) ?: [];
        $creation = $request['type'] === 'softcopy_create';
        $id = (int) ($payload[$creation ? 'controlled_file_id' : 'revision_file_id'] ?? 0);
        if (!$id) return 0;
        $data = ['status' => $request['status'] === 'rejected' ? 'rejected' : 'deleted'];
        if ($request['status'] === 'rejected' && $actorId) {
            $data += ['rejected_by' => $actorId, 'rejected_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason];
        }
        $this->ci->db->where([
            'id' => $id, 'domain' => 'softcopy', 'status' => 'pending',
            'document_id' => $creation ? NULL : (int) $request['softcopy_id'],
            'uploaded_by' => (int) $request['requested_by'], 'purpose' => $creation ? 'creation' : 'revision'
        ])->set('version', 'version + 1', FALSE)->update('files', $data);
        return $id;
    }

    /** Called after commit; historical rows and revision links remain available. */
    public function cleanup_retired(int $documentId = 0, int $fileId = 0, array $statuses = ['superseded', 'deleted', 'rejected']): int
    {
        $db = $this->ci->db;
        if ($db->trans_active())
            return 0;
        $removed = 0;
        try {
            $db->where('domain', 'softcopy')->where_in('status', $statuses);
            if ($documentId > 0) $db->where('document_id', $documentId);
            if ($fileId > 0) $db->where('id', $fileId);
            $files = $db->get('files')->result_array();
            foreach ($files as $file) {
                // Imported records may share a storage name. Never remove bytes
                // still used by an approved file or another pending attachment.
                if (
                    $db->where('storage_name', $file['storage_name'])->where_in('status', ['approved', 'pending'])
                        ->count_all_results('files') > 0
                )
                    continue;
                $path = $this->controlled_path((string) $file['storage_name']);
                if (!$path)
                    continue;
                if (@unlink($path))
                    $removed++;
                else
                    log_message('error', 'Retired controlled file cleanup failed for file ' . (int) $file['id'] . '.');
            }
        } catch (Throwable $error) {
            // A committed approval cannot be rolled back after filesystem I/O.
            // Superseded rows remain unavailable for download and eligible for retry.
            log_message('error', 'Retired controlled file cleanup failed for document ' . $documentId . '.');
        }
        return $removed;
    }

    /** Daily reconciliation also covers legacy terminal records and deleted documents. */
    public function cleanup_daily(): void
    {
        if ($this->ci->db->trans_active() || is_file(PK_ROOT . '/storage/.restore-in-progress')) return;
        $day = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        $marker = PK_ROOT . '/storage/.controlled-file-cleanup';
        if (is_file($marker) && trim((string) file_get_contents($marker)) === $day) return;
        $lock = fopen($marker, 'c+');
        if (!$lock) return;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) return;
            if (trim((string) stream_get_contents($lock)) === $day) return;
            $db = $this->ci->db;
            $documents = $db->query('SELECT DISTINCT f.document_id FROM files f
                LEFT JOIN softcopy_documents d ON d.id=f.document_id
                WHERE f.domain="softcopy" AND f.document_id IS NOT NULL
                AND (d.id IS NULL OR d.status IN ("disposed","cancelled"))')->result_array();
            foreach ($documents as $document) $this->retire_document((int) $document['document_id']);
            $requests = $db->where_in('type', ['softcopy_create', 'softcopy_revise'])
                ->where_in('status', ['rejected', 'cancelled'])->get('requests')->result_array();
            foreach ($requests as $request) $this->retire_request_attachment($request);
            $this->cleanup_retired();
            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, $day);
        } catch (Throwable $error) {
            log_message('error', 'Daily controlled file cleanup failed; it will be retried.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    // Controlled files are private while awaiting approval.
    // A new document has no document ID until the final approval is applied.
    public function stage_creation($actorId, $upload)
    {
        return $this->stage_controlled(NULL, $actorId, $upload, 'creation');
    }

    public function stage_revision($docId, $actorId, $upload)
    {
        $document = $this->ci->db->get_where('softcopy_documents', [
            'id' => (int) $docId,
            'status' => 'active'
        ])->row_array();
        if (!$document)
            throw new DomainException('Select an active softcopy document.');
        return $this->stage_controlled((int) $docId, $actorId, $upload, 'revision');
    }

    private function stage_controlled($docId, $actorId, $upload, $purpose)
    {
        if (
            !is_array($upload) || (int) ($upload['error'] ?? -1) !== UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])
        ) {
            throw new DomainException('A controlled file is required for Create and Revise.');
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 15 * 1024 * 1024) {
            throw new DomainException('Controlled file must be between 1 byte and 15 MB.');
        }
        $name = preg_replace(
            '/[^a-zA-Z0-9._ -]/',
            '_',
            basename(str_replace('\\', (string) '/', (string) ($upload['name'] ?? '')))
        );
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (
            !$name || strlen($name) > 255 || !isset($allowed[$ext]) ||
            !in_array($mime, $allowed[$ext], TRUE)
        ) {
            throw new DomainException('Unsupported controlled file format.');
        }
        $directory = PK_ROOT . '/storage/documents';
        if (!is_dir($directory) && !mkdir($directory, 0700, TRUE)) {
            throw new DomainException('Private file storage is unavailable.');
        }
        $storage = bin2hex(random_bytes(32));
        $path = $directory . '/' . $storage;
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new DomainException('Could not store the controlled file.');
        }
        chmod($path, 0600);
        $saved = $this->ci->db->insert('files', [
            'original_name' => $name,
            'storage_name' => $storage,
            'size' => $size,
            'mime_type' => $mime,
            'fingerprint' => hash_file('sha256', $path),
            'extension' => $ext,
            'purpose' => $purpose,
            'domain' => 'softcopy',
            'document_id' => $docId,
            'uploaded_by' => (int) $actorId,
            'status' => 'pending'
        ]);
        if (!$saved) {
            @unlink($path);
            throw new DomainException('Unable to record controlled file.');
        }
        return (int) $this->ci->db->insert_id();
    }

    /** Legacy upload form delegates to the same locked revision transaction as Direct. */
    public function save_revision($docId, $actorId, $upload, $post)
    {
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Document_model');
        $actor = $this->ci->Identity_model->active_user((int) $actorId);
        $document = $actor ? $this->ci->Document_model->manageable('softcopy', (int) $docId, $actor) : NULL;
        if (!$document)
            throw new DomainException('Active softcopy document is not available for direct revision.');
        require_once APPPATH . 'services/softcopy/softcopy_direct_service.php';
        return (new Softcopy_direct_service())->execute([
            'type' => 'softcopy_revise',
            'softcopy_id' => (int) $docId,
            'subject' => 'Revision: ' . $document['title'],
            'remarks' => trim((string) ($post['reason'] ?? '')),
            'title' => $document['title'],
            'document_number' => $document['document_number'],
            'series_number' => $document['series_number'],
            'category_id' => $document['category_id'],
            'new_revision_level' => $post['new_revision_level'] ?? '',
            'page_number' => $post['page_number'] ?? 1,
            'effective_date' => $post['effective_date'] ?? ''
        ], (int) $actorId, $upload);
    }
}
