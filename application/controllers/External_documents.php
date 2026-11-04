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
        $ownerOptions = [];
        if ($canViewAll) {
            $this->load->model('Document_model');
            $ownerOptions = $this->Document_model->active_users();
        }
        $applyFilters = function () use ($state, $canViewAll) {
            $this->db->where('d.status', 'active');
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
            'created_at' => 'd.created_at',
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
            'owner_options' => $ownerOptions,
            'can_direct' => $this->Permission_model->allowed($this->user, 'external_documents', 'direct'),
            'can_dispose' => $this->Permission_model->allowed($this->user, 'external_documents', 'direct') &&
                $this->Permission_model->allowed($this->user, 'disposal', 'direct'),
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
