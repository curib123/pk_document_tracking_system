<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Softcopy Direct: same create/revise/cancel fields and domain effects as
 * requests, but a staff member with softcopy.direct approves immediately.
 * Does not insert requests, workflow_steps, or workflow_history.
 */
class Softcopy_direct_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function execute($post, $actorId, $attachment = NULL)
    {
        require_once APPPATH . 'services/softcopy/softcopy_operation_service.php';
        $service = new Softcopy_operation_service();
        $type = trim((string) ($post['type'] ?? ''));
        if ($type === 'softcopy_correct') {
            return $this->correct($post, (int) $actorId);
        }
        $service->validate_type($type);
        if ($type === 'softcopy_cancel') {
            throw new DomainException('Direct softcopy cancellation is not available.');
        }
        $subject = trim((string) ($post['subject'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 255) {
            throw new DomainException('Subject / reason is required (maximum 255 characters).');
        }

        $docId = (int) ($post['softcopy_id'] ?? 0);
        $payload = [
            'subject' => $subject,
            'remarks' => trim((string) ($post['remarks'] ?? '')),
            'title' => trim((string) ($post['title'] ?? '')),
            'document_number' => trim((string) ($post['document_number'] ?? '')),
            'generated_document_number' => trim((string) ($post['generated_document_number'] ?? '')),
            'series_number' => array_key_exists('series_number', $post)
                ? trim((string) $post['series_number']) : NULL,
            // The Series No. is typed in manually (no code pattern).
            'generated_series_number' => trim((string) ($post['generated_series_number'] ?? '')),
            'series_number_checker' => !empty($post['series_number_checker']) ? 1 : 0,
            'category_id' => (int) ($post['category_id'] ?? 0),
            'effective_date' => trim((string) ($post['effective_date'] ?? '')),
            'new_revision_level' => trim((string) ($post['new_revision_level'] ?? '')),
            'date_received' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'),
            'date_released' => '',
            'page_number' => (int) ($post['page_number'] ?? 0)
        ];
        if (!$payload['series_number_checker']) {
            $payload['document_number'] = '';
        }
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $this->ci->load->model('Document_model');
        $actor = $this->ci->Identity_model->active_user((int) $actorId);
        if (
            !$actor || !empty($actor['require_password_change']) ||
            !$this->ci->Permission_model->allowed($actor, 'softcopy', 'direct')
        ) {
            throw new DomainException('Direct softcopy permission is required.');
        }
        if ($type !== 'softcopy_create' && !$this->ci->Document_model->manageable('softcopy', $docId, $actor)) {
            throw new DomainException('Document is not available for direct modification.');
        }
        $stagedFileId = NULL;
        if ($type !== 'softcopy_create' && $docId <= 0) {
            throw new DomainException('Select an existing softcopy document.');
        }
        if (in_array($type, ['softcopy_create', 'softcopy_revise'], TRUE)) {
            $service->validate_revision_level($payload);
            if (
                $type === 'softcopy_revise' &&
                ($payload['title'] === '' || mb_strlen($payload['title']) > 255)
            ) {
                throw new DomainException('Proposed document title is required for a revision.');
            }
            if ($type === 'softcopy_create')
                $service->validate_effective_date($payload);
            elseif ($type === 'softcopy_revise')
                $service->validate_revision($payload);
            require_once APPPATH . 'services/files/file_service.php';
            $files = new File_service();
            $stagedFileId = $type === 'softcopy_create'
                ? $files->stage_creation($actorId, $attachment)
                : $files->stage_revision($docId, $actorId, $attachment);
            $payload[$type === 'softcopy_create' ? 'controlled_file_id' : 'revision_file_id'] = $stagedFileId;
        }

        $this->ci->db->trans_begin();
        try {
            $result = $service->apply($type, $payload, $docId, $actorId, $actorId, NULL);
            if ($this->ci->db->trans_status() === FALSE) {
                throw new DomainException('Direct approval failed: database transaction was not saved.');
            }
            if ($this->ci->db->trans_commit() === FALSE)
                throw new DomainException('Direct approval could not be committed.');
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            // A staged file was stored before the DB transaction started.
            // Clean up its DB row and physical bytes on failure.
            if ($stagedFileId) {
                $file = $this->ci->db->get_where('files', [
                    'id' => $stagedFileId,
                    'status' => 'pending',
                    'uploaded_by' => $actorId
                ])->row_array();
                if ($file) {
                    $this->ci->db->where('id', $stagedFileId)->delete('files');
                    @unlink(PK_ROOT . '/storage/documents/' . $file['storage_name']);
                }
            }
            if ($e instanceof DomainException)
                throw $e;
            log_message('error', 'Softcopy direct approval failed: ' . $e->getMessage());
            throw new DomainException('Unable to approve this softcopy action directly.');
        }
        if ($type === 'softcopy_revise') {
            (new File_service())->cleanup_superseded($docId);
        }
        return $result;
    }

    /**
     * Typo correction of an existing softcopy's details. It never touches the
     * file, the effective date or the revision history: only the title, Series
     * No., revision level and page count of the current record are rewritten.
     */
    private function correct($post, int $actorId)
    {
        $this->ci->load->model('Identity_model');
        $this->ci->load->model('Permission_model');
        $this->ci->load->model('Document_model');
        $actor = $this->ci->Identity_model->active_user($actorId);
        if (
            !$actor || !empty($actor['require_password_change']) ||
            !$this->ci->Permission_model->allowed($actor, 'softcopy', 'direct')
        ) {
            throw new DomainException('Direct softcopy permission is required.');
        }
        $docId = (int) ($post['softcopy_id'] ?? 0);
        if ($docId <= 0 || !$this->ci->Document_model->manageable('softcopy', $docId, $actor)) {
            throw new DomainException('Document is not available for direct modification.');
        }
        $title = trim((string) ($post['title'] ?? ''));
        $series = trim((string) ($post['series_number'] ?? ''));
        $level = trim((string) ($post['new_revision_level'] ?? ''));
        $pages = (int) ($post['page_number'] ?? 0);
        $reason = trim((string) ($post['subject'] ?? ''));
        $remarks = trim((string) ($post['remarks'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Document title is required (maximum 255 characters).');
        }
        if (mb_strlen($series) > 100) {
            throw new DomainException('Series No. must be 100 characters or fewer.');
        }
        if ($level === '' || mb_strlen($level) > 30) {
            throw new DomainException('Revision level is required and must be no longer than 30 characters.');
        }
        if ($pages < 1) {
            throw new DomainException('Number of pages must be at least one.');
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $doc = $db->query('SELECT * FROM softcopy_documents WHERE id=? FOR UPDATE', [$docId])->row_array();
            if (!$doc || $doc['status'] !== 'active') {
                throw new DomainException('Select an active softcopy document.');
            }
            if ($series !== '' && $db->where('document_number', $doc['document_number'])
                ->where('series_number', $series)->where('id !=', $docId)
                ->limit(1)->get('softcopy_documents')->row_array()) {
                throw new DomainException('That document number and series number are already in use.');
            }
            $db->set('version', 'version + 1', FALSE)->where('id', $docId)->update('softcopy_documents', [
                'title' => $title,
                'series_number' => $series !== '' ? $series : NULL
            ]);
            if (!empty($doc['current_revision_id'])) {
                $db->where('id', (int) $doc['current_revision_id'])->update('softcopy_revisions', [
                    'document_title' => $title,
                    'series_number' => $series !== '' ? $series : NULL,
                    'new_revision_level' => $level,
                    'page_number' => $pages
                ]);
            }
            $db->insert('status_history', [
                'domain' => 'softcopy',
                'document_id' => $docId,
                'previous_status' => 'active',
                'new_status' => 'active',
                'action' => 'direct_correct',
                'user_id' => $actorId,
                'remarks' => $remarks !== '' ? $remarks : $reason
            ]);
            if ($db->trans_status() === FALSE) {
                throw new DomainException('The correction could not be saved.');
            }
            if ($db->trans_commit() === FALSE) {
                throw new DomainException('The correction could not be committed.');
            }
        } catch (Throwable $e) {
            $db->trans_rollback();
            if ($e instanceof DomainException) {
                throw $e;
            }
            log_message('error', 'Softcopy correction failed: ' . $e->getMessage());
            throw new DomainException('Unable to correct this softcopy.');
        }
        return ['document_id' => $docId, 'document_number' => (string) $doc['document_number'], 'corrected' => TRUE];
    }
}
