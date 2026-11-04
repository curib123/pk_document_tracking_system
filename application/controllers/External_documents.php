<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class External_documents extends MY_Controller
{
    public function index()
    {
        $this->require_permission('external_documents', 'view');
        $this->load->model('Permission_model');
        $state = $this->table_state('updated_at');
        $canViewAll = $this->Permission_model->allowed($this->user, '*', '*') ||
            $this->Permission_model->allowed($this->user, 'documents', 'access_all') ||
            $this->Permission_model->allowed($this->user, 'documents', 'view_all');
        $applyFilters = function () use ($state, $canViewAll) {
            $this->db->where('d.status', 'active');
            if (!$canViewAll) {
                $this->db->where('d.created_by', (int) $this->user['id']);
            }
            if ($state['q'] !== '') {
                $this->db->group_start()
                    ->like('d.title', $state['q'])
                    ->or_like('d.description', $state['q'])
                    ->or_like('r.revision_level', $state['q'])
                    ->or_like('f.original_name', $state['q'])
                    ->group_end();
            }
        };
        $joinCurrent = function () {
            $this->db->from('external_documents d')
                ->join('external_revisions r', 'r.id=d.current_revision_id', 'left')
                ->join('files f', 'f.id=r.file_id AND f.domain="external" AND f.status="approved"', 'left');
        };
        $joinCurrent();
        $applyFilters();
        $total = $this->db->count_all_results();
        $state['page'] = max(1, min($state['page'], max(1, (int) ceil($total / $state['limit']))));

        $sortColumns = [
            'title' => 'd.title',
            'description' => 'd.description',
            'revision_level' => 'r.revision_level',
            'updated_at' => 'd.updated_at'
        ];
        $sort = isset($sortColumns[$state['sort']]) ? $sortColumns[$state['sort']] : 'd.updated_at';
        $joinCurrent();
        $applyFilters();
        $rows = $this->db->select('d.id,d.title,d.description,
                d.created_by,d.created_at,d.updated_at,
                r.revision_level,f.id AS file_id,f.original_name', FALSE)
            ->order_by($sort, $state['dir'])
            ->limit($state['limit'], ($state['page'] - 1) * $state['limit'])
            ->get()->result_array();

        $this->render('External Documents', 'pages/external_document/index', [
            'rows' => $rows,
            'table' => $state,
            'total' => $total,
            'can_direct' => $this->Permission_model->allowed($this->user, 'external_documents', 'direct'),
            'can_request' => $this->Permission_model->allowed($this->user, 'requests', 'view') &&
                $this->Permission_model->allowed($this->user, 'requests', 'add') &&
                $this->Permission_model->allowed($this->user, 'external_documents', 'request'),
            'can_download' => $this->Permission_model->allowed($this->user, 'files', 'download')
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
            $this->notice('External document saved directly.');
        } catch (Throwable $error) {
            $this->error_notice($error);
        }
        redirect('documents/external');
    }
}
