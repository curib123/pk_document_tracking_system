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
        $this->load->library('Table_pager');
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
        $sorts = $task
            ? ['subject','requester','document','step','status','updated']
            : ['subject','document','status','created','updated'];
        $params = $this->table_pager->read(
            (array) $this->input->get(NULL, TRUE), $sorts, 'updated',
            $task ? ['pending'] : ['draft','pending','returned','approved','rejected'],
            [], 'desc'
        );
        $total = $this->Request_model->count_listing(
            $type, $this->user, $task, $params['q'], $params['status']
        );
        $offset = $this->table_pager->clamp($params, $total);
        $visibleRows = $this->Request_model->page_listing(
            $type, $this->user, $task, $params['q'], $params['status'],
            $params['limit'], $offset, $params['sort'], $params['dir']
        );
        $histories = $this->Request_model->histories(array_column($visibleRows, 'id'));
        foreach ($visibleRows as &$item) {
            $item['history_text'] = implode("\n", $histories[$item['id']] ?? []);
        }
        unset($item);
        $this->render(($task ? 'My Tasks' : 'My Requests') . ' · ' . $this->types[$type], 'pages/request/index', [
            'task' => $task, 'module' => $task ? 'tasks' : 'requests',
            'tabs' => $this->types, 'current' => $type,
            'rows' => $visibleRows,
            'total' => $total, 'page' => $params['page'], 'limit' => $params['limit'],
            'q' => $params['q'], 'status' => $params['status'],
            'sort' => $params['sort'], 'dir' => $params['dir'],
            'filter_values' => $params['filters'],
            'base_path' => ($task ? 'my-tasks/' : 'my-requests/') . $type,
            'form_action' => 'my-requests/' . $type . '/save',
            'document_options' => $this->Records_model->document_options(
                in_array($type, ['hardcopy','softcopy'], TRUE) ? $type :
                ($type === 'hardcopy-transfer' ? 'hardcopy' : NULL)
            ),
            'user_options' => $this->Records_model->user_options(),
            'location_options' => $this->Records_model->place_options('location')
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
