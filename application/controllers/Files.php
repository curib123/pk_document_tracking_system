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

    /** Prevent response-splitting and broken Content-Disposition headers. */
    private function safe_download_name($name): string
    {
        $name = basename(str_replace(chr(92), '/', (string) $name));
        $name = str_replace(['"', chr(92)], '_', $name);
        $name = preg_replace('/[[:cntrl:]]/', '_', $name);
        return $name !== '' && $name !== '.' ? $name : 'document';
    }

    private function clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
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
        $file = $this->File_model->review_file(
            (int) $requestId,
            $this->user,
            (int) $this->input->get('file_id')
        );
        if (!$file) {
            show_error('Document Fileis not available for your review.', 403);
            return;
        }
        $canBypassLock = empty($file['locked_at']) || $this->can('files', 'download_locked');
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path) || is_link($path)) {
            show_404();
            return;
        }
        $wantsDownload = $this->input->get('download') === '1';
        if ($wantsDownload && !$canBypassLock) {
            show_error('This file is locked after Plant Manager approval and is read-only. Ask Document Control for a full download.', 403);
            return;
        }
        $extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
        if (in_array($extension, ['docx', 'xlsx'], TRUE) && !$wantsDownload) {
            require_once APPPATH . 'services/files/office_review_ticket.php';
            $ticket = (new Office_review_ticket())->issue($this->user, (int) $requestId, (int) $file['id']);
            $contentUrl = site_url('files/review-content/' . (int) $requestId . '/' . $ticket . '/review.' . $extension);
            $this->audit_event('files/review-open', 'requests:' . (int) $requestId, ['file_id' => (int) $file['id']]);
            header('Cache-Control: private, no-store');
            header('Referrer-Policy: no-referrer');
            $this->load->view('pages/files/review', ['file' => $file, 'request_id' => (int) $requestId,
                'app' => $extension === 'xlsx' ? 'Excel' : 'Word',
                'office_url' => ($extension === 'xlsx' ? 'ms-excel' : 'ms-word') . ':ofv|u|' . $contentUrl,
                'wps_url' => 'pk-dts-office://wps/' . rtrim(strtr(base64_encode($contentUrl), '+/', '-_'), '='),
                'download_url' => $canBypassLock ? site_url('files/review/' . (int) $requestId) . '?download=1' : NULL]);
            return;
        }
        $inline = in_array($file['mime_type'], ['application/pdf', 'image/png', 'image/jpeg', 'text/plain'], TRUE);
        $this->audit_event('files/review', 'requests:' . (int) $requestId, ['file_id' => (int) $file['id']]);
        $this->clear_output_buffers();
        header('Content-Type: ' . ($inline ? $file['mime_type'] : 'application/octet-stream'));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' .
            $this->safe_download_name($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Native apps have no browser session: validate the short-lived capability and current access again. */
    public function review_content($requestId, $ticket, $filename = '')
    {
        require_once APPPATH . 'services/files/office_review_ticket.php';
        try { $claims = (new Office_review_ticket())->verify((string) $ticket, (int) $requestId); }
        catch (DomainException $error) { show_error($error->getMessage(), 403); return; }
        $viewer = $this->Identity_model->active_user((int) $claims['user_id']);
        if (!$viewer || (int) $viewer['session_version'] !== (int) $claims['session_version'] || !empty($viewer['require_password_change'])) {
            show_error('Review access has expired. Reopen Review File.', 403); return;
        }
        $file = $this->File_model->review_file((int) $requestId, $viewer, (int) $claims['file_id']);
        if (!$file || (int) $file['id'] !== (int) $claims['file_id']) { show_error('Review access is no longer available.', 403); return; }
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) $file['storage_name'])) { show_404(); return; }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path) || is_link($path)) { show_404(); return; }
        $extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
        $mime = ['docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$extension] ?? NULL;
        if (!$mime) { show_404(); return; }
        $this->audit_event('files/review-content', 'requests:' . (int) $requestId,
            ['file_id'=>(int)$file['id']], (int)$viewer['id']);
        $this->clear_output_buffers();
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $this->safe_download_name($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($path);
        }
        exit;
    }

    public function office_setup()
    {
        $this->authenticate();
        foreach (['install.ps1','open-office.ps1','README.txt'] as $name) {
            if (!is_readable(PK_ROOT . '/tools/office-opener/' . $name)) {
                show_error('Setup package is unavailable. Contact your administrator.', 503); return;
            }
        }
        $path = tempnam(PK_ROOT . '/storage', '.office-setup-');
        $zip = new ZipArchive();
        if (!$path || $zip->open($path, ZipArchive::OVERWRITE) !== TRUE) {
            if ($path) unlink($path);
            show_error('Setup package is unavailable.', 503); return;
        }
        foreach (['install.ps1','open-office.ps1','README.txt'] as $name)
            $zip->addFile(PK_ROOT . '/tools/office-opener/' . $name, $name);
        $zip->addFromString('config.json', json_encode(['BaseUrl'=>base_url()]));
        $zip->close();
        $this->audit_event('files/office-setup');
        header('Content-Type: application/zip');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="PK-DTS-WPS-Opener.zip"');
        header('Content-Length: ' . filesize($path));
        try { readfile($path); } finally { unlink($path); }
        exit;
    }

    /** Locked files are read-only except for roles holding Download Locked Files. */
    private function locked_for_user(array $file): bool
    {
        return !empty($file['locked_at']) && !$this->can('files', 'download_locked');
    }

    /**
     * What a viewer or printer receives for a file: the original bytes for PDF/image/text,
     * or a LibreOffice-rendered PDF for Word/Excel. The original upload is never altered.
     *
     * @return array{path:string,mime:string,name:string}|null NULL after an error response was sent.
     */
    private function display_source(array $file): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return NULL;
        }
        $original = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($original) || is_link($original)) {
            show_404();
            return NULL;
        }
        require_once APPPATH . 'services/files/pdf_preview_service.php';
        if (Pdf_preview_service::office_extension($file) !== '') {
            try {
                $pdf = (new Pdf_preview_service())->pdf_path($file);
            } catch (DomainException $error) {
                show_error($error->getMessage(), 503);
                return NULL;
            }
            return ['path' => $pdf, 'mime' => 'application/pdf',
                'name' => pathinfo((string) $file['original_name'], PATHINFO_FILENAME) . '.pdf'];
        }
        return ['path' => $original, 'mime' => (string) $file['mime_type'], 'name' => (string) $file['original_name']];
    }

    /** Accessible print viewer; every file response repeats the access check. */
    public function print_file($id)
    {
        $this->require_permission('files', 'print');
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('Document Fileprinting is not permitted.', 403);
            return;
        }
        if ($this->locked_for_user($file)) {
            show_error('This file is locked after approval and is read-only. Printing is limited to Document Control.', 403);
            return;
        }
        $source = $this->display_source($file);
        if (!$source) {
            return;
        }
        if (!in_array($source['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], TRUE)) {
            show_error('Download this file to print it in its native application.', 415);
            return;
        }
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'");
        $this->audit_event('files/print', 'files:' . (int) $id);
        $this->load->view('pages/files/print', [
            'file' => $file,
            'print_mime' => $source['mime'],
            'text' => $source['mime'] === 'text/plain' ? file_get_contents($source['path']) : NULL,
            'download_url' => $this->can('files', 'download') ? site_url('files/download/' . (int) $file['id']) : NULL
        ]);
    }

    public function print_content($id)
    {
        $this->require_permission('files', 'print');
        $file = $this->File_model->approved_file((int) $id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('Document Fileprinting is not permitted.', 403);
            return;
        }
        if ($this->locked_for_user($file)) {
            show_error('This file is locked after approval and is read-only. Printing is limited to Document Control.', 403);
            return;
        }
        $source = $this->display_source($file);
        if (!$source) {
            return;
        }
        if (!in_array($source['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], TRUE)) {
            show_error('Download this file to print it in its native application.', 415);
            return;
        }
        $this->clear_output_buffers();
        header('Content-Type: ' . $source['mime']);
        $this->audit_event('files/print-content', 'files:' . (int) $id);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        // Native PDF viewers use a browser plugin, which CSP sandbox disables.
        // Text and image responses keep the sandbox; PDFs remain MIME-scoped.
        header('Content-Security-Policy: ' . ($source['mime'] === 'application/pdf'
            ? "frame-ancestors 'self'"
            : "sandbox allow-same-origin allow-modals; frame-ancestors 'self'"));
        header('Content-Disposition: inline; filename="' .
            $this->safe_download_name($source['name']) . '"');
        header('Content-Length: ' . filesize($source['path']));
        readfile($source['path']);
        exit;
    }

    /** Read-only inline preview. Word/Excel are shown as a PDF rendered by LibreOffice. */
    public function preview($id)
    {
        $this->require_permission('files', 'view');
        $id = (int) $id;
        $file = $this->File_model->approved_file($id);
        if (!$file || !$this->File_model->allowed($this->user, $file)) {
            show_error('File preview is not permitted.', 403);
            return;
        }
        $source = $this->display_source($file);
        if (!$source) {
            return;
        }
        if ($source['mime'] !== 'application/pdf' && $source['mime'] !== 'text/plain' &&
            strpos($source['mime'], 'image/') !== 0) {
            show_error('Preview is available for PDF, image, text, Word, and Excel attachments only.', 415);
            return;
        }
        $this->clear_output_buffers();
        $this->audit_event('files/preview', 'files:' . $id);
        header('Content-Type: ' . $source['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Security-Policy: ' . ($source['mime'] === 'application/pdf'
            ? "frame-ancestors 'self'"
            : "sandbox allow-same-origin; frame-ancestors 'self'"));
        header('Content-Disposition: inline; filename="' . $this->safe_download_name($source['name']) . '"');
        header('Content-Length: ' . filesize($source['path']));
        readfile($source['path']);
        exit;
    }

    /** Native apps have no browser session: validate the short-lived capability and current access again. */
    public function preview_content($id, $ticket, $filename = '')
    {
        $id = (int) $id;
        require_once APPPATH . 'services/files/locked_file_ticket.php';
        try { $claims = (new Locked_file_ticket())->verify((string) $ticket, $id); }
        catch (DomainException $error) { show_error($error->getMessage(), 403); return; }
        $viewer = $this->Identity_model->active_user((int) $claims['user_id']);
        if (!$viewer || (int) $viewer['session_version'] !== (int) $claims['session_version'] || !empty($viewer['require_password_change'])) {
            show_error('Preview access has expired. Reopen Preview.', 403); return;
        }
        $file = $this->File_model->approved_file($id);
        if (!$file || !$this->File_model->allowed($viewer, $file)) { show_error('Preview access is no longer available.', 403); return; }
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) $file['storage_name'])) { show_404(); return; }
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($path) || is_link($path)) { show_404(); return; }
        $extension = strtolower(pathinfo((string) $file['original_name'], PATHINFO_EXTENSION));
        $mime = ['docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$extension] ?? NULL;
        if (!$mime) { show_404(); return; }
        $this->audit_event('files/preview-content', 'files:' . $id, [], (int) $viewer['id']);
        $this->clear_output_buffers();
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $this->safe_download_name($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($path);
        }
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
        if (!empty($file['locked_at']) && !$this->can('files', 'download_locked')) {
            show_error('This file is locked after approval and is read-only. Use Preview to view it, or ask Document Control for a full download.', 403);
            return;
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $file['storage_name'])) {
            show_404();
            return;
        }
        $filePath = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        if (!is_file($filePath) || is_link($filePath)) {
            show_404();
            return;
        }
        // File delivery must not depend on maintenance cleanup succeeding.
        // Run deferred cleanup independently, not during a user's download.
        $this->clear_output_buffers();
        header('Content-Type: application/octet-stream');
        $this->audit_event('files/download', 'files:' . (int) $id);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: attachment; filename="' .
            $this->safe_download_name($file['original_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function download_external($documentId)
    {
        $this->require_permission('files', 'download');
        $documentId = (int) $documentId;
        $document = $this->db->select('id,title,status')
            ->get_where('external_documents', ['id' => $documentId])
            ->row_array();
        if (!$document || $document['status'] !== 'active') {
            show_404();
            return;
        }

        $filesByDocument = $this->File_model->current_external_files([$documentId]);
        $files = $filesByDocument[$documentId] ?? [];
        if (!$files) {
            show_404();
            return;
        }

        $downloadFiles = [];
        foreach ($files as $listedFile) {
            $file = $this->File_model->approved_file((int) $listedFile['id']);
            if (!$file || !$this->File_model->allowed($this->user, $file)) {
                show_error('File download is not permitted.', 403);
                return;
            }
            if (!empty($file['locked_at']) && !$this->can('files', 'download_locked')) {
                show_error('One or more files are locked after approval and read-only. Preview them individually, or ask Document Control for a full download.', 403);
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
            $downloadFiles[] = ['path' => $path, 'name' => $this->safe_download_name($file['original_name'])];
        }

        if (!class_exists('ZipArchive')) {
            show_error('ZIP downloads are unavailable on this server.', 503);
            return;
        }
        $archivePath = tempnam(PK_ROOT . '/storage', '.external-download-');
        if ($archivePath === FALSE) {
            show_error('The external document archive could not be created.', 500);
            return;
        }
        $archive = new ZipArchive();
        $opened = $archive->open($archivePath, ZipArchive::OVERWRITE);
        if ($opened !== TRUE) {
            unlink($archivePath);
            show_error('The external document archive could not be created.', 500);
            return;
        }

        $archiveOpen = TRUE;
        $usedNames = [];
        $archiveError = NULL;
        try {
            foreach ($downloadFiles as $file) {
                $name = $file['name'];
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $stem = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
                $candidate = $name;
                $suffix = 2;
                while (isset($usedNames[strtolower($candidate)])) {
                    $candidate = $stem . ' (' . $suffix++ . ')' . ($extension === '' ? '' : '.' . $extension);
                }
                $usedNames[strtolower($candidate)] = TRUE;
                if (!$archive->addFile($file['path'], $candidate)) {
                    $archiveError = 'An attachment could not be added to the archive.';
                    break;
                }
            }
            if ($archiveError === NULL && !$archive->close()) {
                $archiveOpen = FALSE;
                $archiveError = 'The external document archive could not be finalized.';
            } elseif ($archiveError === NULL) {
                $archiveOpen = FALSE;
            }
            if ($archiveError === NULL) {
                $this->audit_event('files/download-external', 'external_documents:' . $documentId, [
                    'file_ids' => array_column($files, 'id')
                ]);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/zip');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store, max-age=0');
                header('Content-Disposition: attachment; filename="' .
                    $this->safe_download_name($document['title']) . '-attachments.zip"');
                header('Content-Length: ' . filesize($archivePath));
                readfile($archivePath);
            }
        } finally {
            if ($archiveOpen) {
                $archive->close();
            }
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
        if ($archiveError !== NULL) {
            show_error($archiveError, 500);
            return;
        }
        exit;
    }
}
