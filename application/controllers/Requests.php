<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Requests extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Request_model');
        $this->load->model('Document_model');
        $this->load->model('Transfer_model');
        require_once APPPATH . 'services/transfer/transfer_service.php';
        $this->load->model('Place_model');
        require_once APPPATH . 'services/request/request_service.php';
    }
    private function listing($task, $legacyTab = NULL)
    {
        if ($task) {
            // Tasks are scoped to the user's approval assignments and physical handoffs.
            $this->authenticate();
        } else {
            $this->require_permission('requests', 'view');
        }

        $path = $task ? 'my-tasks' : 'my-requests';
        if ($legacyTab !== NULL) {
            $this->Request_model->types($legacyTab);
            $query = $this->input->get() ?: [];
            $query['request_category'] = $legacyTab;
            unset($query['handoff_page']);
            redirect($path . '?' . http_build_query($query));
            return;
        }

        $category = $this->input->get('request_category', TRUE) ?? '';
        $categories = [
            'softcopy' => 'Softcopy Documents',
            'external' => 'External Documents',
            'scanned' => 'Scanned Documents',
            'hardcopy' => 'Hardcopy Documents',
            'hardcopy-transfer' => 'Hardcopy Transfers',
            'hardcopy-disposal' => 'Disposal',
            'access-grant' => 'Document Access',
            'document-assign' => 'Document Assignment'
        ];
        if (!is_string($category) || ($category !== '' && !isset($categories[$category]))) {
            show_404();
            return;
        }

        $state = $this->table_state('updated_at');
        if ($state['sort'] === 'updated_at')
            $state['sort'] = 'updated';
        $state['request_category'] = $category;
        list($rows, $total, $state['page']) = $task
            ? $this->Request_model->task_listing($category, $this->user, $state)
            : $this->Request_model->listing(
                $category, $this->user, FALSE, $state['q'], $state['status'], $state['page'], $state['limit'], $state
            );
        $typeTabs = [];
        foreach ($this->Request_model->tabs() as $tab)
            foreach ($this->Request_model->types($tab) as $type)
                $typeTabs[$type] = $tab;
        $requestTypes = $task ? [] : $this->allowed_request_types();
        $requestIds = array_column(array_filter($rows, static fn($row) => empty($row['handoff'])), 'id');
        $tableOnly = $this->input->get_request_header('X-PK-Table') === '1';
        $data = [
            'task' => $task,
            'path' => $path,
            'categories' => $categories,
            'type_tabs' => $typeTabs,
            'allowed_request_types' => $requestTypes,
            'rows' => $rows,
            'total' => $total,
            'table' => $state,
            'history' => $this->Request_model->histories($requestIds),
            'approval_routes' => $this->Request_model->approval_routes($requestIds),
            'show_request_modal' => !$tableOnly && $requestTypes &&
                ($this->can('requests', 'add') || $this->can('requests', 'edit'))
        ];
        if ($data['show_request_modal'])
            $data += $this->request_form_data($requestTypes);

        $this->render(
            $task ? 'My Tasks' : 'My Requests',
            $task ? 'pages/request/approving_assign_request/index' : 'pages/request/user_own_all_request/index',
            $data
        );
    }

    private function allowed_request_types()
    {
        $modules = [
            'softcopy_create' => 'softcopy',
            'softcopy_revise' => 'softcopy',
            Request_policy::REVISION_TEMPLATE => 'softcopy',
            'scanned_create' => 'scanned_documents',
            'scanned_update' => 'scanned_documents',
            'scanned_delete' => 'scanned_documents',
            'scanned_category_create' => 'scanned_categories',
            'external_create' => 'external_documents',
            'external_revise' => 'external_documents',
            'transfer' => 'transfer',
            'disposal' => 'disposal',
            'assignment' => 'assignment',
            'access' => 'access'
        ];
        $actions = [
            Request_policy::REVISION_TEMPLATE => Request_policy::REVISION_TEMPLATE_ACTION,
            'scanned_update' => 'edit',
            'scanned_delete' => 'delete'
        ];
        return array_keys(array_filter($modules, fn($module, $type) =>
            $this->can($module, $actions[$type] ?? 'request'), ARRAY_FILTER_USE_BOTH));
    }

    private function request_form_data($types)
    {
        $data = [];
        if (array_intersect($types, $this->Request_model->types('scanned'))) {
            require_once APPPATH . 'services/documents/scanned_document_service.php';
            $service = new Scanned_document_service();
            if (!$service->global_access($this->user))
                $this->db->where('created_by', (int) $this->user['id']);
            $data['scanned_options'] = $this->db->order_by('title')
                ->get_where('scanned_documents', ['status' => 'active'])->result_array();
            $data['scanned_categories'] = $service->categories($this->user);
        }
        if (array_intersect($types, $this->Request_model->types('softcopy'))) {
            $categories = $this->Place_model->options('numbered_categories');
            $data['softcopy_options'] = $this->Document_model->options('softcopy', $this->user);
            $data['numbered_category_options'] = $categories;
            $data['numbering_options'] = $this->Document_model->softcopy_numbering_catalog($this->user);
            $data['number_previews'] = $this->Document_model->softcopy_number_previews($categories);
        }
        if (array_intersect($types, $this->Request_model->types('external'))) {
            $data['external_category_options'] = $this->Place_model->options('external_categories');
            require_once APPPATH . 'services/documents/external_document_service.php';
            $data['subsidiary_options'] = (new External_document_service())->subsidiary_names();
        }
        if (array_intersect($types, ['external_create', 'external_revise', 'disposal']))
            $data['external_options'] = $this->external_document_options();
        if (in_array('disposal', $types, TRUE))
            $data['disposal_hardcopy_options'] = $this->Document_model->options('hardcopy', $this->user);
        if (in_array('transfer', $types, TRUE)) {
            $data['transfer_sources'] = $this->Document_model->transfer_sources($this->user);
            $data['transfer_options'] = [
                'areas' => $this->Place_model->options('areas'),
                'specifics' => $this->Place_model->options('specifics'),
                'assets' => $this->Place_model->options('assets'),
                'locations' => $this->Document_model->hardcopy_locations(),
                'users' => $this->Document_model->active_users()
            ];
        }
        if (array_intersect($types, ['access', 'assignment'])) {
            $data['catalog_softcopy_options'] = $this->Document_model->options('softcopy', $this->user, TRUE);
            $data['catalog_hardcopy_options'] = $this->Document_model->options('hardcopy', $this->user, TRUE);
            $data['category_options'] = $this->Place_model->options('categories');
            $data['location_options'] = $this->Place_model->options('locations');
        }
        return $data;
    }

    private function external_document_options()
    {
        $canViewAll = $this->can('*', '*') || $this->can('documents', 'access_all') ||
            $this->can('documents', 'view_all');
        $this->db->select(
            'd.id,d.title,d.description,d.document_number AS external_document_number,
            s.name AS subsidiary_name,d.category_id,r.revision_level,
            EXISTS(SELECT 1 FROM external_revision_files rf JOIN files f ON f.id=rf.file_id
                WHERE rf.revision_id=d.current_revision_id AND f.locked_at IS NOT NULL) AS has_locked_file',
            FALSE
        )
            ->from('external_documents d')
            ->join('subsidiaries s', 's.id=d.subsidiary_id', 'left')
            ->join('external_revisions r', 'r.id=d.current_revision_id', 'left')
            ->where('d.status', 'active');
        if (!$canViewAll)
            $this->db->where('d.created_by', (int) $this->user['id']);
        return $this->db->order_by('d.title')->get()->result_array();
    }

    public function mine($tab = NULL)
    {
        $this->listing(FALSE, $tab);
    }
    public function tasks($tab = NULL)
    {
        $this->listing(TRUE, $tab);
    }
    private function return_to_register($path)
    {
        parse_str((string) $this->input->post('return_query'), $submitted);
        $filters = array_intersect_key($submitted, array_flip([
            'q', 'status', 'type', 'request_category', 'period', 'from', 'to', 'page', 'limit', 'sort', 'dir'
        ]));
        $filters = array_filter($filters, 'is_string');
        redirect($path . ($filters ? '?' . http_build_query($filters) : ''));
    }

    public function save($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests', (int) $this->input->post('id') ? 'edit' : 'add');
        $this->confirmed();
        try {
            if (in_array((string) $this->input->post('type'), ['hardcopy_create', 'hardcopy_update'], TRUE)) {
                throw new DomainException('Hardcopy create and update requests are no longer available. Use Direct Hardcopy for document management.');
            }
            $savedId = (new Request_service())->save(
                $tab,
                $this->input->post(),
                $this->user,
                $_FILES['scanned_attachment'] ?? ($_FILES['external_attachment'] ?? ($_FILES['revision_attachment'] ?? NULL))
            );
            $this->audit_created('requests', $savedId);
            $this->notice('Request draft saved.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-requests');
    }
    public function submit($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests', 'submit');
        $this->confirmed();
        try {
            (new Request_service())->submit($tab, (int) $this->input->post('id'), $this->user);
            $this->notice('Request submitted for approval.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-requests');
    }
    public function decide($tab)
    {
        $this->Request_model->types($tab);
        // The service verifies the locked request's active approver step.
        $this->authenticate();
        $this->confirmed();
        try {
            $lockedFiles = (new Request_service())->decide(
                $tab,
                (int) $this->input->post('id'),
                $this->user,
                (string) $this->input->post('decision'),
                (string) $this->input->post('remark'),
                $_FILES['final_file'] ?? NULL
            );
            foreach ($lockedFiles as $file) {
                $this->audit_event('files/document-lock', 'files:' . $file['file_id'], [
                    'request_id' => $file['request_id'],
                    'designated_step_key' => $file['step_key'],
                    'designated_step' => $file['step_name'],
                    'designated_approver' => $file['designated_approver'],
                    'approved_by_id' => $file['approver_id'],
                    'approved_by' => $file['approver_name'],
                    'approval_timestamp' => $file['approved_at'],
                    'file_extension' => $file['extension']
                ], $file['approver_id']);
            }
            $this->notice('Approval decision recorded.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-tasks');
    }
    public function dispatch_transfer()
    {
        // The transfer service enforces exact requester ownership.
        $this->authenticate();
        $this->confirmed();
        try {
            (new Transfer_service())->dispatch((int) $this->input->post('id'), $this->user);
            $this->notice('Hardcopy dispatched. Awaiting recipient acceptance.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-tasks');
    }

    public function accept_transfer()
    {
        // The transfer service enforces exact recipient ownership.
        $this->authenticate();
        $this->confirmed();
        try {
            (new Transfer_service())->accept((int) $this->input->post('id'), $this->user);
            $this->notice('Hardcopy received. Physical handoff is complete.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-tasks');
    }

    public function delete($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests', 'cancel');
        $this->confirmed();
        try {
            (new Request_service())->delete($tab, (int) $this->input->post('id'), $this->user);
            $this->notice('Cancelled draft permanently deleted.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-requests');
    }

    public function cancel($tab)
    {
        $this->Request_model->types($tab);
        $this->require_permission('requests', 'cancel');
        $this->confirmed();
        try {
            (new Request_service())->cancel($tab, (int) $this->input->post('id'), $this->user);
            $this->notice('Draft cancelled.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        $this->return_to_register('my-requests');
    }
}
