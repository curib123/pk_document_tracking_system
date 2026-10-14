<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Administration extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Administration_model');
        require_once APPPATH . 'services/administration/administration_service.php';
    }
    private function listing($kind)
    {
        $map = ['users' => 'users', 'roles' => 'roles', 'workflows' => 'workflows'];
        $module = $map[$kind];
        $this->require_permission($module, 'view');
        $t = $this->table_state($kind === 'users' ? 'username' : 'name');
        list($rows, $total, $t['page']) = $this->Administration_model->$kind(
            $t['q'],
            $t['status'],
            $t['page'],
            $t['limit'],
            $t
        );
        $views = [
            'users' => 'pages/user/index',
            'roles' => 'pages/roles_and_permission/index',
            'workflows' => 'pages/workflow_builder/index'
        ];
        $this->render(ucwords($kind), $views[$kind], [
            'kind' => $kind,
            'rows' => $rows,
            'total' => $total,
            'table' => $t,
            'roles' => $this->Administration_model->role_options(),
            'users' => $this->Administration_model->user_options(),
            'permission_rows' => $kind === 'roles' ? $this->Administration_model->permissions() : [],
            'grants' => $kind === 'roles' ? $this->Administration_model->grants(array_column($rows, 'id')) : []
        ]);
    }
    /** Administrative direct assignment. Approval requests remain in My Requests. */
    public function document_assignments()
    {
        $this->require_permission('assignment', 'direct');
        $this->load->model('Document_model');
        $this->load->model('Folder_model');
        $domain = (string) ($this->input->get('domain', TRUE) ?: 'hardcopy');
        if (!in_array($domain, ['hardcopy', 'softcopy'], TRUE))
            show_404();
        $folder = trim((string) $this->input->get('folder', TRUE));
        if (strlen($folder) > 100)
            show_404();
        $browser = $this->Folder_model->browse($domain, $folder);
        $table = $this->table_state('assigned_date');
        list($assignmentRows, $total, $table['page']) = $this->Administration_model->document_assignments($domain, $browser, $table);
        $this->render('Assign Documents', 'pages/administration/document_assignments', [
            'selected_domain' => $domain,
            'folder_value' => $browser['folder'],
            'folder_browser' => $browser,
            'folder_base' => 'admin/document-assignments',
            'folder_params' => array_merge($table, ['domain' => $domain]),
            'table' => $table,
            'total' => $total,
            'softcopy_options' => $domain === 'softcopy' ?
                $this->Document_model->options_in_folder('softcopy', $browser, $table['q']) : [],
            'hardcopy_options' => $domain === 'hardcopy' ?
                $this->Document_model->options_in_folder('hardcopy', $browser, $table['q']) : [],
            'location_options' => $domain === 'hardcopy' ? $this->Document_model->hardcopy_locations() : [],
            'users_list' => $this->Administration_model->user_options(),
            'assignment_rows' => $assignmentRows
        ]);
    }
    public function save_document_assignment()
    {
        $this->require_permission('assignment', 'direct');
        $this->confirmed();
        $domain = (string) $this->input->post('document_domain', TRUE);
        $payload = ['document_domain' => $domain];
        $soft = (int) $this->input->post('softcopy_id');
        $hard = (int) $this->input->post('hardcopy_id');
        $recipient = (int) $this->input->post('recipient_id');
        $folder = trim((string) $this->input->post('folder', TRUE));
        try {
            $this->load->model('Folder_model');
            $this->load->model('Document_model');
            $browser = $this->Folder_model->browse($domain, $folder);
            $documentId = $domain === 'softcopy' ? $soft : $hard;
            if (!$this->Document_model->available_in_folder($domain, $documentId, $browser))
                throw new DomainException('The document is not available in the selected folder.');
            require_once APPPATH . 'services/documents/document_access_service.php';
            $this->db->trans_begin();
            (new Document_access_service())->apply(
                'assignment',
                $payload,
                $soft,
                $hard,
                $recipient,
                (int) $this->user['id'],
                NULL
            );
            if ($this->db->trans_status() === FALSE)
                throw new DomainException('Assignment transaction failed.');
            $this->db->trans_commit();
            $this->notice('Document assigned successfully.');
        } catch (DomainException $e) {
            $this->db->trans_rollback();
            $this->notice($e->getMessage(), 'danger');
        }
        $query = ['domain' => in_array($domain, ['hardcopy', 'softcopy'], TRUE) ? $domain : 'hardcopy'];
        if ($folder !== '')
            $query['folder'] = $folder;
        redirect('admin/document-assignments?' . http_build_query($query));
    }
    /** Direct assignment of every active Hardcopy currently stored at one location. */
    public function assign_location()
    {
        $this->require_permission('assignment', 'direct');
        $this->confirmed();
        $locationId = (int) $this->input->post('location_id');
        $recipientId = (int) $this->input->post('recipient_id');
        try {
            $this->load->model('Document_model');
            $location = $this->db->get_where('locations', ['id' => $locationId, 'active' => 1])->row_array();
            $recipient = $this->db->get_where('users', ['id' => $recipientId, 'active' => 1])->row_array();
            if (!$location || !$recipient)
                throw new DomainException('Choose an active Location and assigned user.');
            require_once APPPATH . 'services/documents/document_access_service.php';
            $this->db->trans_begin();
            // Lock the complete location set, not only the visible page.
            $rows = $this->db->query(
                'SELECT id FROM hardcopy_documents WHERE location_id=? AND status=? ORDER BY id FOR UPDATE',
                [$locationId, 'active']
            )->result_array();

            $ids = array_map('intval', array_column($rows, 'id'));
            $openTransfers = $this->db
                ->where_in('hardcopy_id', $ids)
                ->where_in('status', ['for_transfer', 'in_transit'])
                ->limit(1)->get('transfers')->row_array();
            if ($openTransfers) {
                throw new DomainException(
                    'Some documents at this Location have active transfers. ' .
                    'Complete or cancel those handoffs before reassigning.'
                );
            }
            if (!$rows)
                throw new DomainException('No active Hardcopy documents in this Location.');
            if (count($rows) > 2000)
                throw new DomainException('More than 2000 documents at this Location. Divide into smaller assignments.');
            $service = new Document_access_service();
            foreach ($rows as $row) {
                $service->apply(
                    'assignment',
                    ['document_domain' => 'hardcopy'],
                    0,
                    (int) $row['id'],
                    $recipientId,
                    (int) $this->user['id'],
                    NULL
                );
            }
            if ($this->db->trans_status() === FALSE)
                throw new DomainException('Location assignment transaction failed.');
            $this->db->trans_commit();
            
            $this->load->library('Audit_file');
            $written = $this->audit_file->append(
                (int) $this->user['id'],
                'hardcopy_location_bulk_assigned',
                'location:' . $locationId,
                [
                    'location_id' => $locationId,
                    'recipient_id' => $recipientId,
                    'document_ids' => array_map('intval', array_column($rows, 'id')),
                    'count' => count($rows)
                ],
                'success'
            );
            if (!$written) {
                log_message('error', 'Bulk Location assignment committed without a durable audit entry.');
                $this->notice(
                    'Documents were assigned, but audit logging failed. Contact your administrator.',
                    'warning'
                );
            } else {
                $this->notice(count($rows) . ' Hardcopy documents assigned successfully.');
            }
          
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->notice($e instanceof DomainException ? $e->getMessage() :
                'Location assignment failed. No changes were saved.', 'danger');
        }
        redirect('admin/document-assignments?domain=hardcopy&folder=location:' . $locationId);
    }

    public function users()
    {
        $this->listing('users');
    }
    public function roles()
    {
        $this->listing('roles');
    }
    public function workflows()
    {
        $this->listing('workflows');
    }

    private function mutate($module, $action, $callback, $redirect)
    {
        $this->require_permission($module, $action);
        $this->confirmed();
        try {
            $callback();
            $this->notice('Changes saved.');
        } catch (DomainException $e) {
            $this->notice($e->getMessage(), 'danger');
        }
        // Preserve the selected workflow after a form POST so an editor does
        // not lose context when changing steps or publishing a new version.
        if ($module === 'workflows' && $redirect === 'admin/workflows') {
            $workflowId = (int) $this->input->post('workflow_id');
            if ($workflowId > 0)
                $redirect .= '?workflow=' . $workflowId;
        }
        redirect($redirect);
    }
    public function save_user()
    {
        $this->require_permission('users', (int) $this->input->post('id') ? 'edit' : 'add');
        $this->confirmed();
        try {
            $result = (new Administration_service())->save_user($this->input->post(), $this->user['id']);
            $this->notice('Account saved.');
            if ($result['temporary_password'] !== NULL) {
                $this->render(
                    'Temporary Account Credentials',
                    'pages/user/temporary_credentials',
                    ['account_result' => $result]
                );
                return;
            }
        } catch (DomainException $e) {
            $this->notice($e->getMessage(), 'danger');
        }
        redirect('admin/users');
    }
    public function deactivate_user()
    {
        $this->mutate('users', 'delete', function () {
            (new Administration_service())->deactivate_user((int) $this->input->post('id'), $this->user['id']);
        }, 'admin/users');
    }
    public function save_role()
    {
        $this->mutate('roles', (int) $this->input->post('id') ? 'edit' : 'add', function () {
            (new Administration_service())->save_role($this->input->post(), $this->user['id']);
        }, 'admin/roles');
    }
    public function deactivate_role()
    {
        $this->mutate('roles', 'edit', function () {
            (new Administration_service())->deactivate_role((int) $this->input->post('id'), $this->user['id']);
        }, 'admin/roles');
    }
    public function remove_role()
    {
        $this->mutate('roles', 'delete', function () {
            (new Administration_service())->remove_role((int) $this->input->post('id'), $this->user['id']);
        }, 'admin/roles');
    }
    public function save_workflow_step()
    {
        $this->mutate('workflows', 'edit', function () {
            (new Administration_service())->save_workflow_step($this->input->post());
        }, 'admin/workflows');
    }
    public function remove_workflow_step()
    {
        $this->mutate('workflows', 'edit', function () {
            (new Administration_service())->remove_workflow_step($this->input->post());
        }, 'admin/workflows');
    }
    public function move_workflow_step()
    {
        $this->mutate('workflows', 'edit', function () {
            (new Administration_service())->move_workflow_step($this->input->post());
        }, 'admin/workflows');
    }
    public function publish_workflow()
    {
        $this->mutate('workflows', 'edit', function () {
            (new Administration_service())->publish_workflow((int) $this->input->post('id'), $this->user['id'], $this->input->post());
        }, 'admin/workflows');
    }
    public function clone_workflow()
    {
        $this->mutate('workflows', 'edit', function () {
            (new Administration_service())->clone_workflow((int) $this->input->post('id'), $this->user['id']);
        }, 'admin/workflows');
    }
}
