<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class External_documents extends MY_Controller
{
    public function index()
    {
        $this->require_permission('external_documents', 'view');
        $this->load->model('Permission_model');
        $this->load->model('Folder_model');
        $this->load->model('Place_model');
        $this->load->model('File_model');
        $state = $this->table_state('updated_at');
        if ($state['status'] === '') {
            $state['status'] = 'active';
        } elseif (!in_array($state['status'], ['active', 'disposed', 'all'], TRUE)) {
            show_error('Invalid external document status filter.', 400);
            return;
        }
        $canViewAll = $this->Permission_model->allowed($this->user, '*', '*') ||
            $this->Permission_model->allowed($this->user, 'documents', 'access_all') ||
            $this->Permission_model->allowed($this->user, 'documents', 'view_all');
        $ownerOptions = [];
        if ($canViewAll) {
            $this->load->model('Document_model');
            $ownerOptions = $this->Document_model->active_users();
        }
        $folder = trim((string) $this->input->get('folder', TRUE));
        if (strlen($folder) > 100) {
            show_404();
            return;
        }
        $tree = $this->Folder_model->tree('external');
        $restricted = !$canViewAll;
        if ($restricted) {
            $paths = $this->db->distinct()->select('category_id')->from('external_documents')
                ->where('created_by', (int) $this->user['id'])->get()->result_array();
            require_once APPPATH . 'services/presentation/folder_visibility.php';
            $allowed = Folder_visibility::allowed_nodes($tree, $paths, 'external');
            if ($folder !== '' && $folder !== 'all' && !isset($allowed[$folder])) {
                show_error('This folder is outside your document access.', 403);
                return;
            }
            $tree = Folder_visibility::restrict($tree, $paths, 'external');
        }
        $browser = $this->Folder_model->browse('external', $folder, $tree);
        if ($state['layout'] === 'folder' && $state['q'] !== '') {
            require_once APPPATH . 'services/presentation/folder_search.php';
            $browser['search'] = Folder_search::for_browser($browser, $state['q'], 'external');
        }
        $applyFilters = function () use ($state, $canViewAll, $browser) {
            if ($state['status'] !== 'all') {
                $this->db->where('d.status', $state['status']);
            }
            if ($state['category'] > 0) {
                $this->db->where('d.category_id', $state['category']);
            }
            if (!$canViewAll) {
                $this->db->where('d.created_by', (int) $this->user['id']);
            } elseif ($state['owner'] > 0) {
                $this->db->where('d.created_by', $state['owner']);
            }
            if ($state['from'] !== '') {
                $this->db->where('d.created_at >=', $state['from'] . ' 00:00:00');
            }
            if ($state['to'] !== '') {
                $this->db->where('d.created_at <=', $state['to'] . ' 23:59:59');
            }
            if ($state['q'] !== '') {
                $this->db->group_start()
                    ->like('d.title', $state['q'])
                    ->or_like('d.document_number', $state['q'])
                    ->or_like('s.name', $state['q'])
                    ->or_like('d.description', $state['q'])
                    ->or_like('r.revision_level', $state['q'])
                    ->or_like('f.original_name', $state['q'])
                    ;
                if (!empty($browser['search']['categories'])) {
                    $this->db->or_where_in('d.category_id', $browser['search']['categories']);
                }
                $this->db->group_end();
            }
            $this->Folder_model->scope_documents('external', $browser, 'd');
        };
        $joinCurrent = function () {
            $this->db->from('external_documents d')
                ->join('external_revisions r', 'r.id=d.current_revision_id', 'left')
                ->join('files f', 'f.id=r.file_id AND f.domain="external" AND f.status="approved"', 'left')
                ->join('external_categories c', 'c.id=d.category_id', 'left')
                ->join('subsidiaries s', 's.id=d.subsidiary_id', 'left');
        };
        $joinCurrent();
        $applyFilters();
        $total = $this->db->count_all_results();
        $state['page'] = max(1, min($state['page'], max(1, (int) ceil($total / $state['limit']))));

        $sortColumns = [
            'title' => 'd.title',
            'document_number' => 'd.document_number',
            'subsidiary_name' => 's.name',
            'category_name' => 'c.name',
            'description' => 'd.description',
            'revision_level' => 'r.revision_level',
            'created_at' => 'd.created_at',
            'updated_at' => 'd.updated_at'
        ];
        $sort = isset($sortColumns[$state['sort']]) ? $sortColumns[$state['sort']] : 'd.updated_at';
        $joinCurrent();
        $applyFilters();
        $rows = $this->db->select('d.id,d.title,d.document_number,d.subsidiary_id,d.description,d.status,
                d.category_id,d.created_by,d.created_at,d.updated_at,c.name AS category_name,s.name AS subsidiary_name,
                r.revision_level,f.id AS file_id', FALSE)
            ->order_by($sort, $state['dir'])
            ->limit($state['limit'], ($state['page'] - 1) * $state['limit'])
            ->get()->result_array();
        $attachments = $this->File_model->current_external_files(array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $row['attachments'] = $attachments[(int) $row['id']] ?? [];
        }
        unset($row);

        $this->render('External Documents', 'pages/external_document/index', [
            'rows' => $rows,
            'table' => $state,
            'total' => $total,
            'owner_options' => $ownerOptions,
            'external_category_options' => $this->Place_model->options('external_categories'),
            'subsidiary_options' => $this->Place_model->options('subsidiaries'),
            'folder_browser' => $browser,
            'folder_params' => [
                'q' => $state['q'],
                'status' => $state['status'],
                'limit' => $state['limit'],
                'sort' => $state['sort'],
                'dir' => strtolower($state['dir']),
                'from' => $state['from'],
                'to' => $state['to'],
                'period' => $state['period'],
                'owner' => $state['owner'],
                'category' => $state['category'],
                'layout' => $state['layout']
            ],
            'folder_value' => $browser['folder'],
            'is_administrator' => $this->Permission_model->allowed($this->user, '*', '*'),
            'can_direct' => $this->Permission_model->allowed($this->user, 'external_documents', 'direct'),
            'can_dispose' => $this->Permission_model->allowed($this->user, 'external_documents', 'direct') &&
                $this->Permission_model->allowed($this->user, 'disposal', 'direct'),
            'can_request' => $this->Permission_model->allowed($this->user, 'requests', 'view') &&
                $this->Permission_model->allowed($this->user, 'requests', 'add') &&
                $this->Permission_model->allowed($this->user, 'external_documents', 'request'),
            'can_download' => $this->Permission_model->allowed($this->user, 'files', 'download'),
            'can_preview' => $this->Permission_model->allowed($this->user, 'files', 'view'),
            'can_bypass_lock' => $this->Permission_model->allowed($this->user, 'files', 'download_locked')
        ]);
    }

    public function direct()
    {
        $this->require_permission('external_documents', 'direct');
        $this->confirmed();
        try {
            require_once APPPATH . 'services/documents/external_document_service.php';
            $result = (new External_document_service())->execute_direct(
                $this->input->post(),
                (int) $this->user['id'],
                $_FILES['external_attachment'] ?? NULL
            );
            $this->audit_created('external_documents', $result['document_id']);
            $this->notice(
                ($this->input->post('type') === 'external_revise'
                    ? 'External document revised directly.'
                    : 'External document saved directly.')
            );
        } catch (Throwable $error) {
            $this->error_notice($error);
        }
        redirect('documents/external');
    }

    /** Administrator-only permanent deletion of an already disposed external document. */
    public function hard_delete()
    {
        $this->authenticate();
        if (!$this->can('*', '*'))
            show_error('Only an Administrator can permanently delete documents.', 403);
        $this->confirmed();
        $id = (int) $this->input->post('id');
        try {
            $this->load->model('Document_model');
            require_once APPPATH . 'services/documents/document_service.php';
            $title = (new Document_service())->hard_delete('external', $id, (int) $this->user['id']);
            $this->audit_event('documents/external/hard-delete', 'external_documents:' . $id, ['title' => $title]);
            $this->notice('External document permanently deleted.');
        } catch (Throwable $error) {
            $this->error_notice($error);
        }
        // Stay on the same view (status filter, layout, folder).
        $allowed = ['q', 'page', 'limit', 'sort', 'dir', 'folder', 'layout', 'status', 'owner', 'category', 'period', 'from', 'to'];
        $query = [];
        parse_str((string) $this->input->post('return_query'), $query);
        $query = array_filter(
            array_intersect_key($query, array_flip($allowed)),
            static function ($value) {
                return is_string($value) && strlen($value) <= 100; }
        );
        redirect('documents/external' . ($query ? '?' . http_build_query($query) : ''));
    }

    public function dispose()
    {
        $this->require_permission('external_documents', 'direct');
        $this->require_permission('disposal', 'direct');
        $this->confirmed();
        try {
            require_once APPPATH . 'services/documents/disposal_service.php';
            require_once APPPATH . 'services/documents/external_document_service.php';
            $payload = [
                'disposal_reason' => $this->input->post('disposal_reason', TRUE),
                'disposal_other' => $this->input->post('disposal_other', TRUE)
            ];
            $reason = (new Disposal_service())->reason($payload);
            (new External_document_service())->dispose_direct(
                (int) $this->input->post('id'),
                (int) $this->user['id'],
                $reason,
                $payload['disposal_reason']
            );
            $this->notice('External document disposed and history recorded.');
        } catch (Throwable $error) {
            $this->error_notice($error);
        }
        redirect('documents/external');
    }
}
