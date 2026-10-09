<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Requests extends MY_Controller
{
    private $types = [
        'softcopy' => 'Softcopy Request',
        'hardcopy' => 'Hardcopy Request',
        'hardcopy-transfer' => 'Hardcopy Transfer',
        'access-grant' => 'Document Access Grant',
        'document-assign' => 'Document Assign'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Request_model');
        $this->load->model('Records_model');
        require_once APPPATH . 'services/request/request_service.php';
    }

    private function valid($type)
    {
        if (!array_key_exists($type, $this->types)) show_404();
        return $type;
    }

    private function listing($type, $task)
    {
        $type = $this->valid($type);
        $this->require_permission($task ? 'tasks' : 'requests');
        $rows = $task ? $this->Request_model->tasks($type, $this->user)
            : $this->Request_model->mine($type, $this->user['id']);
        $q = trim((string) $this->input->get('q', TRUE));
        $status = trim((string) $this->input->get('status', TRUE));
        $rows = array_values(array_filter($rows, function ($row) use ($q, $status) {
            return ($q === '' || stripos($row['subject'], $q) !== FALSE
                    || stripos($row['document_code'] ?? '', $q) !== FALSE)
                && ($status === '' || $row['status'] === $status);
        }));
        $limit = (int) $this->input->get('limit');
        if (!in_array($limit, [10,25,50,100], TRUE)) $limit = 10;
        $page = max(1, (int) $this->input->get('page'));
        $total = count($rows);
        $this->render(($task ? 'My Tasks' : 'My Requests') . ' · ' . $this->types[$type], 'pages/request/index', [
            'task' => $task, 'module' => $task ? 'tasks' : 'requests',
            'tabs' => $this->types, 'current' => $type,
            'rows' => array_slice($rows, ($page - 1) * $limit, $limit),
            'total' => $total, 'page' => $page, 'limit' => $limit, 'q' => $q,
            'status' => $status,
            'base_path' => ($task ? 'my-tasks/' : 'my-requests/') . $type,
            'form_action' => 'my-requests/' . $type . '/save',
            'document_options' => $this->Records_model->document_options()
        ]);
    }

    public function mine($type) { $this->listing($type, FALSE); }
    public function tasks($type) { $this->listing($type, TRUE); }

    public function save($type)
    {
        $type = $this->valid($type);
        $this->require_permission('requests', (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Request_service())->save($type, $this->input->post(), (int) $this->user['id']);
            $this->notice('Request draft saved.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('my-requests/' . $type);
    }

    public function submit($type)
    {
        $type = $this->valid($type);
        $this->require_permission('requests', 'submit');
        $this->confirmed();
        try {
            (new Request_service())->submit($type, (int) $this->input->post('id'), (int) $this->user['id']);
            $this->notice('Request submitted for approval.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('my-requests/' . $type);
    }

    public function delete($type)
    {
        $type = $this->valid($type);
        $this->require_permission('requests', 'delete');
        $this->confirmed();
        try {
            (new Request_service())->delete($type, (int) $this->input->post('id'), (int) $this->user['id']);
            $this->notice('Draft deleted.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('my-requests/' . $type);
    }

    public function decide($type)
    {
        $type = $this->valid($type);
        $decision = (string) $this->input->post('decision');
        $actions = ['approved' => 'approve', 'rejected' => 'reject', 'returned' => 'return'];
        if (!isset($actions[$decision])) show_error('Invalid decision.', 400);
        $this->require_permission('tasks', $actions[$decision]);
        $this->confirmed();
        try {
            (new Request_service())->decide($type, (int) $this->input->post('id'),
                $this->user, $decision, (string) $this->input->post('remark'));
            $this->notice('Decision recorded.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('my-tasks/' . $type);
    }
}
