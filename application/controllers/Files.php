<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Files extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('File_model');
        require_once APPPATH . 'services/files/file_service.php';
    }

    public function upload()
    {
        $this->require_permission('files', 'upload');
        $this->require_permission('softcopy', 'direct');
        $this->confirmed();
        try {
            (new File_service())->save_revision(
                (int) $this->input->post('document_id'),
                (int) $this->user['id'],
                $_FILES['attachment'] ?? NULL, $this->input->post()
            );
            $this->notice('Controlled revision uploaded.');
        } catch (DomainException $e) {
            $this->notice($e->getMessage(), 'danger');
        }
        redirect('documents/softcopy');
    }

    public function download($id)
    {
        $this->authenticate();
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('File download is not permitted.', 403);
            return;
        }
        $filePath = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($filePath)) {
            show_404();
            return;
        }
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: attachment; filename="' .
            str_replace(['"', "\r", "\n"], '_', $file['original_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}
