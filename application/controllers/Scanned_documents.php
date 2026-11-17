<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'services/documents/scanned_document_service.php';

class Scanned_documents extends MY_Controller
{
    public function index()
    {
        $this->require_permission('scanned_documents', 'view');
        $service = new Scanned_document_service();
        $this->load->model('Folder_model');
        $state = $this->table_state('updated_at');
        $category = max(0, (int) $this->input->get('category'));
        $status = in_array($state['status'], ['active', 'deleted'], TRUE) ? $state['status'] : 'active';
        $folder = trim((string) $this->input->get('folder', TRUE));
        if (strlen($folder) > 100) { show_404(); return; }
        // Keep allocated upload folders and paths to the user's existing documents.
        $tree = $this->Folder_model->tree('scanned');
        if (!(new Scanned_category_service())->unrestricted($this->user)) {
            require_once APPPATH . 'services/presentation/folder_visibility.php';
            $paths = array_map(static fn($id) => ['category_id' => $id], (new Scanned_category_service())->usable_ids($this->user));
            $paths = array_merge($paths, $this->db->select('category_id')->get_where('scanned_documents', ['created_by' => (int) $this->user['id']])->result_array());
            $tree = Folder_visibility::restrict($tree, $paths, 'scanned');
            if ($folder !== '' && $folder !== 'all' && !isset($tree['nodes'][$folder])) {
                show_error('This folder is outside your category access.', 403); return;
            }
        }
        $browser = $this->Folder_model->browse('scanned', $folder, $tree);
        if ($state['layout'] === 'folder' && $state['q'] !== '') {
            require_once APPPATH . 'services/presentation/folder_search.php';
            $browser['search'] = Folder_search::for_browser($browser, $state['q'], 'scanned');
        }
        $filter = function () use ($service, $state, $category, $status, $browser) {
            $this->db->from('scanned_documents d')->join('scanned_categories c', 'c.id=d.category_id')
                ->where('d.status', $status);
            if (!$service->global_access($this->user)) $this->db->where('d.created_by', (int) $this->user['id']);
            if ($category) $this->db->where('d.category_id', $category);
            if ($state['q'] !== '') {
                $this->db->group_start()->like('d.title', $state['q'])->or_like('d.description', $state['q'])->or_like('c.name', $state['q']);
                if (!empty($browser['search']['categories'])) $this->db->or_where_in('d.category_id', $browser['search']['categories']);
                $this->db->group_end();
            }
            $this->Folder_model->scope_documents('scanned', $browser, 'd');
        };
        $filter(); $total = $this->db->count_all_results();
        $state['page'] = min($state['page'], max(1, (int) ceil($total / $state['limit'])));
        $sort = ['title' => 'd.title', 'created_at' => 'd.created_at', 'updated_at' => 'd.updated_at',
            'retention_end_date' => 'd.retention_end_date'][$state['sort']] ?? 'd.updated_at';
        $filter();
        $rows = $this->db->select('d.*,c.name AS category_name')->order_by($sort, $state['dir'])
            ->limit($state['limit'], ($state['page'] - 1) * $state['limit'])->get()->result_array();
        $files = [];
        if ($rows) {
            foreach ($this->db->where('domain', 'scanned')->where('status', 'approved')
                ->where_in('document_id', array_column($rows, 'id'))->get('files')->result_array() as $file)
                $files[$file['document_id']][] = $file;
        }
        $state['status'] = $status;
        $this->render('Scanned Documents', 'pages/scanned_document/index', [
            'rows' => $rows, 'table' => $state, 'total' => $total, 'files' => $files,
            'categories' => $service->categories(), 'selected_category' => $category, 'folder_browser' => $browser
        ]);
    }

    /** Current approved files or the requester's/active approver's proposal only. */
    public function file($id)
    {
        $this->authenticate();
        $file = $this->db->get_where('files', ['id' => (int) $id, 'domain' => 'scanned'])->row_array();
        if (!$file) { show_404(); return; }
        $allowed = FALSE;
        if ($file['status'] === 'approved' && $this->can('scanned_documents', 'view')) {
            $doc = $this->db->get_where('scanned_documents', ['id' => (int) $file['document_id'], 'status' => 'active'])->row_array();
            $allowed = $doc && ((int) $doc['created_by'] === (int) $this->user['id'] ||
                (new Scanned_document_service())->global_access($this->user));
        } elseif ($file['status'] === 'pending') {
            $requestId = (int) $this->input->get('request_id');
            $request = $this->db->get_where('requests', ['id' => $requestId])->row_array();
            $p = $request ? json_decode($request['payload'], TRUE) : [];
            if ($request && in_array($request['type'], ['scanned_create', 'scanned_update'], TRUE) &&
                in_array($request['status'], ['draft', 'returned', 'submitted'], TRUE) &&
                (int) $file['uploaded_by'] === (int) $request['requested_by'] &&
                in_array((int) $file['id'], array_map('intval', $p['scanned_file_ids'] ?? []), TRUE)) {
                $allowed = (int) $request['requested_by'] === (int) $this->user['id'];
                if (!$allowed && $request['status'] === 'submitted') {
                    $step = $this->db->get_where('workflow_steps', [
                        'request_id' => $requestId, 'node_key' => $request['current_node'], 'status' => 'active'
                    ])->row_array();
                    $a = $step ? json_decode($step['assignment'], TRUE) : [];
                    $allowed = $step && ((int) $step['assigned_user_id'] === (int) $this->user['id'] ||
                        (($a['type'] ?? '') === 'role' && User_roles::matches($this->user, (int) ($a['value'] ?? 0))));
                }
            }
        }
        if (!$allowed) { show_error('This file is outside your access.', 403); return; }
        require_once APPPATH . 'services/files/file_service.php';
        try { (new File_service())->require_stored_file($file); }
        catch (DomainException $e) { show_error('The stored file is unavailable.', 404); return; }
        $preview = $this->input->get('preview') === '1';
        $path = PK_ROOT . '/storage/documents/' . $file['storage_name'];
        $mime = $file['mime_type'];
        $name = $file['original_name'];
        if ($preview) {
            require_once APPPATH . 'services/files/pdf_preview_service.php';
            if (Pdf_preview_service::office_extension($file) !== '') {
                try { $path = (new Pdf_preview_service())->pdf_path($file); }
                catch (DomainException $error) { show_error($error->getMessage(), 503); return; }
                $mime = 'application/pdf';
                $name = pathinfo($name, PATHINFO_FILENAME) . '.pdf';
            }
            if (!in_array($mime, ['application/pdf', 'text/plain', 'image/png', 'image/jpeg'], TRUE)) {
                show_error('Preview is unavailable for this file type.', 415); return;
            }
        }
        $name = preg_replace('/[[:cntrl:]"\\\\]/', '_', basename(str_replace(chr(92), '/', $name)));
        $this->output->set_header('Cache-Control: no-store, private')->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Content-Security-Policy: ' . ($preview && $mime === 'application/pdf'
                ? "frame-ancestors 'self'" : "sandbox allow-same-origin; frame-ancestors 'self'"))
            ->set_content_type($mime)
            ->set_header('Content-Disposition: ' . ($preview ? 'inline' : 'attachment') . '; filename="' . $name . '"')
            ->set_output(file_get_contents($path));
    }
}
