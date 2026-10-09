<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_file_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function upload($documentId, $userId, $input)
    {
        $document = $this->ci->db->get_where('documents', [
            'id' => $documentId, 'kind' => 'softcopy', 'status' => 'active'
        ])->row_array();
        if (!$document) throw new DomainException('Active softcopy document not found.');
        if (!$input || !isset($input['error']) || (int) $input['error'] !== UPLOAD_ERR_OK ||
            empty($input['tmp_name']) || !is_uploaded_file($input['tmp_name'])) {
            throw new DomainException('Select a valid document file.');
        }
        if ((int) $input['size'] <= 0 || (int) $input['size'] > 15 * 1024 * 1024) {
            throw new DomainException('File must be 15 MB or smaller.');
        }

        $filename = trim(basename(str_replace('\\', '/', (string) $input['name'])));
        $filename = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $filename);
        if ($filename === '' || strlen($filename) > 255) throw new DomainException('Invalid filename.');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($input['tmp_name']);
        if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], TRUE)) {
            throw new DomainException('Unsupported file format. Use PDF, Office, TXT, JPEG or PNG.');
        }
        $root = PK_ROOT . '/storage/documents';
        if (!is_dir($root) && !mkdir($root, 0700, TRUE) && !is_dir($root)) {
            throw new DomainException('Upload storage is not writable.');
        }
        $storageName = bin2hex(random_bytes(32));
        $fullPath = $root . '/' . $storageName;
        if (!move_uploaded_file($input['tmp_name'], $fullPath)) {
            throw new DomainException('Could not store the uploaded file.');
        }
        chmod($fullPath, 0600);
        $saved = $this->ci->db->insert('document_files', [
            'document_id' => $documentId, 'uploaded_by' => $userId,
            'original_name' => $filename, 'storage_name' => $storageName,
            'mime_type' => $mime, 'file_size' => (int) $input['size']
        ]);
        if (!$saved) {
            unlink($fullPath);
            throw new DomainException('File metadata could not be saved.');
        }
    }
}
