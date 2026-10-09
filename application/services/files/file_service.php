<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    // Pending uploads belong to a request until its complete approval.
    // They are never downloadable while pending.
    public function stage_revision($docId, $actorId, $upload)
    {
        $document = $this->ci->db->get_where('softcopy_documents', [
            'id' => (int) $docId, 'status' => 'active'
        ])->row_array();
        if (!$document) throw new DomainException('Select an active softcopy document.');
        if (!is_array($upload) || (int)($upload['error'] ?? -1) !== UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new DomainException('Attach a valid revision file.');
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 15 * 1024 * 1024) {
            throw new DomainException('Revision attachment must be 15 MB or smaller.');
        }
        $name = preg_replace('/[^a-zA-Z0-9._ -]/', '_',
            basename(str_replace('\\', '/', (string) ($upload['name'] ?? ''))));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allow = [
            'pdf' => ['application/pdf'], 'txt' => ['text/plain'],
            'png' => ['image/png'], 'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'docx' => ['application/zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/zip',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!$name || strlen($name) > 255 || !isset($allow[$extension]) ||
            !in_array($mime, $allow[$extension], TRUE)) {
            throw new DomainException('Revision file type is not supported.');
        }
        $directory = PK_ROOT . '/storage/documents';
        if (!is_dir($directory) && !mkdir($directory, 0700, TRUE)) {
            throw new DomainException('Private storage directory unavailable.');
        }
        $storage = bin2hex(random_bytes(32));
        $path = $directory . '/' . $storage;
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new DomainException('Could not store pending revision file.');
        }
        chmod($path, 0600);
        $saved = $this->ci->db->insert('files', [
            'original_name' => $name, 'storage_name' => $storage,
            'size' => $size, 'mime_type' => $mime,
            'fingerprint' => hash_file('sha256', $path),
            'extension' => $extension,
            'purpose' => 'revision', 'domain' => 'softcopy',
            'document_id' => $docId, 'uploaded_by' => $actorId,
            'status' => 'pending'
        ]);
        if (!$saved) {
            @unlink($path);
            throw new DomainException('Could not record revision attachment.');
        }
        return (int) $this->ci->db->insert_id();
    }

    public function save_revision($docId, $actorId, $upload, $post)
    {
        $document = $this->ci->db->get_where('softcopy_documents', [
            'id' => (int) $docId, 'status' => 'active'
        ])->row_array();
        if (!$document) throw new DomainException('Active softcopy document not found.');

        if (!is_array($upload) || (int) ($upload['error'] ?? -1) !== UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new DomainException('Choose a valid document attachment.');
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 15 * 1024 * 1024) {
            throw new DomainException('File must be no larger than 15 MB.');
        }
        $name = preg_replace('/[^a-zA-Z0-9._ -]/', '_',
            basename(str_replace('\\', '/', (string) ($upload['name'] ?? ''))));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'docx' => ['application/zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/zip',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!$name || strlen($name) > 255 || !isset($allowed[$extension]) ||
            !in_array($mime, $allowed[$extension], TRUE)) {
            throw new DomainException('Unsupported file. Use PDF, TXT, image, DOCX or XLSX.');
        }
        $level = trim((string) ($post['new_revision_level'] ?? ''));
        if ($level === '' || strlen($level) > 30) {
            throw new DomainException('Enter a revision level up to 30 characters.');
        }
        $storageDir = PK_ROOT . '/storage/documents';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0700, TRUE)) {
            throw new DomainException('File storage is unavailable.');
        }
        $storage = bin2hex(random_bytes(32));
        $path = $storageDir . '/' . $storage;
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new DomainException('Unable to save the file.');
        }
        chmod($path, 0600);

        $this->ci->db->trans_begin();
        try {
            if (!$this->ci->db->insert('files', [
                'original_name' => $name, 'storage_name' => $storage,
                'size' => $size, 'mime_type' => $mime,
                'fingerprint' => hash_file('sha256', $path),
                'extension' => $extension, 'purpose' => 'revision',
                'domain' => 'softcopy', 'document_id' => $docId,
                'uploaded_by' => $actorId, 'status' => 'approved',
                'approved_by' => $actorId, 'approved_at' => date('Y-m-d H:i:s')
            ])) throw new DomainException('File metadata could not be saved.');
            $fileId = (int) $this->ci->db->insert_id();
            $maximum = $this->ci->db->select_max('revision_number')
                ->get_where('softcopy_revisions', ['document_id' => $docId])->row_array();
            $revision = (int) ($maximum['revision_number'] ?? 0) + 1;
            $old = $document['current_revision_id']
                ? $this->ci->db->get_where('softcopy_revisions',
                    ['id' => $document['current_revision_id']])->row_array()
                : NULL;
            $today = date('Y-m-d');
            if (!$this->ci->db->insert('softcopy_revisions', [
                'document_id' => $docId, 'revision_number' => $revision,
                'reason' => trim((string) ($post['reason'] ?? '')),
                'effective_date' => $today,
                'page_number' => max(1, (int) ($post['page_number'] ?? 1)),
                'series_number' => $document['series_number'],
                'document_title' => $document['title'],
                'previous_revision_level' => $old['new_revision_level'] ?? NULL,
                'new_revision_level' => $level,
                'previous_effective_date' => $old['new_effective_date'] ?? NULL,
                'new_effective_date' => $today,
                'date_received' => $today, 'date_released' => $today,
                'approval_date' => $today, 'file_id' => $fileId,
                'uploaded_by' => $actorId, 'approved_by' => $actorId
            ])) throw new DomainException('Document revision could not be saved.');
            $revisionId = (int) $this->ci->db->insert_id();
            $this->ci->db->where('id', $docId)->update('softcopy_documents', [
                'current_revision_id' => $revisionId
            ]);
            if ($this->ci->db->trans_status() === FALSE) {
                throw new DomainException('Revision transaction was unsuccessful.');
            }
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            @unlink($path);
            if ($e instanceof DomainException) throw $e;
            log_message('error', 'File revision error: ' . $e->getMessage());
            throw new DomainException('Unable to save revision.');
        }
    }
}
