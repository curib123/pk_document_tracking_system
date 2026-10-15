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
            if (!$this->Document_model->manageable('softcopy', (int) $this->input->post('document_id'), $this->user)) {
                throw new DomainException('Document is not available in your authorized scope.');
            }
            (new File_service())->save_revision(
                (int) $this->input->post('document_id'),
                (int) $this->user['id'],
                $_FILES['attachment'] ?? NULL,
                $this->input->post()
            );
            $this->notice('Controlled revision uploaded.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        redirect('documents/softcopy');
    }

    public function review($requestId)
    {
        $this->authenticate();
        $file = $this->File_model->review_file((int) $requestId, $this->user);
        if (!$file) {
            show_error('Controlled file is not available for your review.', 403);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path)) {
            show_404();
            return;
        }
        $inline = in_array($file['mime_type'], ['application/pdf', 'image/png', 'image/jpeg', 'text/plain'], TRUE);
        header('Content-Type: ' . ($inline ? $file['mime_type'] : 'application/octet-stream'));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' .
            str_replace(['"', "\r", "\n"], '_', $file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Accessible print viewer; every file response repeats the access check. */
    public function print_file($id)
    {
        $this->require_permission('files', 'print');
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('Controlled file printing is not permitted.', 403);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path) || is_link($path)) {
            show_404();
            return;
        }
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'");
        $this->load->view('pages/files/print', [
            'file' => $file,
            'text' => $file['mime_type'] === 'text/plain' ? file_get_contents($path) : NULL
        ]);
    }

    public function print_content($id)
    {
        $this->require_permission('files', 'print');
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('Controlled file printing is not permitted.', 403);
            return;
        }
        if (!in_array($file['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], TRUE)) {
            show_error('Download this file to print it in its native application.', 415);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path) || is_link($path)) {
            show_404();
            return;
        }
        header('Content-Type: ' . $file['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        // Native PDF viewers use a browser plugin, which CSP sandbox disables.
        // Text and image responses keep the sandbox; PDFs remain MIME-scoped.
        header('Content-Security-Policy: ' . ($file['mime_type'] === 'application/pdf'
            ? "frame-ancestors 'self'"
            : "sandbox allow-same-origin allow-modals; frame-ancestors 'self'"));
        header('Content-Disposition: inline; filename="' .
            str_replace(['"', "\r", "\n"], '_', $file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function download($id)
    {
        $this->require_permission('files', 'download');
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('File download is not permitted.', 403);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $filePath = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($filePath)) {
            show_404();
            return;
        }
        // Retry any old-file cleanup deferred by a temporary filesystem lock.
        (new File_service())->cleanup_superseded((int) $file['document_id']);
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
