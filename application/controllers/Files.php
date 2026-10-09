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
            $this->load->model('Document_model');
            if (!$this->Document_model->manageable('softcopy',(int)$this->input->post('document_id'),$this->user)) {
                throw new DomainException('Document is not available in your authorized scope.');
            }
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

    public function review($requestId)
    {
        $this->authenticate();
        $file=$this->File_model->review_file((int)$requestId,$this->user);
        if (!$file) { show_error('Controlled file is not available for your review.',403); return; }
        if (!preg_match('/^[a-f0-9]{64}$/',(string)$file['storage_name'])) {
            show_404(); return;
        }
        $path=PK_ROOT.'/storage/documents/'.$file['storage_name'];
        if (!is_file($path)) { show_404(); return; }
        $inline=in_array($file['mime_type'],['application/pdf','image/png','image/jpeg','text/plain'],TRUE);
        header('Content-Type: '.($inline?$file['mime_type']:'application/octet-stream'));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.
            str_replace(['"', "\r", "\n"],'_',$file['original_name']).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }

    public function download($id)
    {
        $this->authenticate();
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('File download is not permitted.', 403);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/',(string)$file['storage_name'])) { show_404(); return; }
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
