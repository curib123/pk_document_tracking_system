<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Places extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Place_model');
        require_once APPPATH . 'services/places/place_service.php';
    }
    public function home()
    {
        $this->authenticate();
        // Open the first Places tab this account may view.
        // Avoid routing users to Areas when they only have other Places permissions.
        foreach ([
            'area' => 'areas',
            'specific' => 'specifics',
            'asset' => 'assets',
            'location' => 'locations',
            'softcopy-categories' => 'categories',
            'external-categories' => 'external_categories',
            'scanned-categories' => 'scanned_categories',
        ] as $slug => $module) {
            if ($this->can($module, 'view')) {
                redirect("places/{$slug}");
                return;
            }
        }
        show_error('You do not have permission to view Places.', 403);
    }

    public function index($slug)
    {
        $cfg = $this->Place_model->config($slug);
        $this->require_permission($cfg['table'], 'view');
        $state = $this->table_state($cfg['key']);
        [$rows, $total, $state['page']] = $this->Place_model->listing(
            $cfg,
            $state['q'],
            $state['status'],
            $state['page'],
            $state['limit'],
            $state
        );
        $options = [];
        foreach (['areas', 'specifics', 'assets', 'categories', 'external_categories'] as $source) {
            $options[$source] = $this->Place_model->options($source);
        }
        $views = [
            'area' => 'area',
            'specific' => 'specific',
            'asset' => 'asset_number',
            'location' => 'location',
            'softcopy-categories' => 'softcopy_category',
            'external-categories' => 'external_category',
            'scanned-categories' => 'scanned_category',
        ];
        $extra = [];
        if ($slug === 'scanned-categories') {
            require_once APPPATH . 'services/documents/scanned_document_service.php';
            $options['scanned_categories'] = (new Scanned_document_service())->categories();
            $this->load->model('Document_model');
            $options['users'] = $this->Document_model->active_users();
            $paths = array_column($options['scanned_categories'], 'path', 'id');
            $allocations = [];
            foreach ($this->db->select('a.category_id,a.user_id,CONCAT_WS(" ",u.first_name,u.last_name) AS user_name', FALSE)
                ->from('scanned_category_allocations a')->join('users u', 'u.id=a.user_id')->get()->result_array() as $allocation)
                $allocations[(int) $allocation['category_id']][] = $allocation;
            foreach ($rows as &$row) {
                $assigned = $allocations[(int) $row['id']] ?? [];
                $row['path'] = $paths[$row['id']] ?? $row['name'];
                $row['user_ids'] = array_map('intval', array_column($assigned, 'user_id'));
                $row['allocated_users'] = $assigned ? implode(', ', array_column($assigned, 'user_name')) : 'Shared / inherits parent allocation';
            }
            unset($row);
            $this->load->model('Folder_model');
            $folder = trim((string) $this->input->get('folder', TRUE));
            if (strlen($folder) > 100) { show_404(); return; }
            $extra['folder_browser'] = $this->Folder_model->browse('scanned', $folder);
        }
        $this->render(
            "Places · {$cfg['label']}",
            "pages/places/{$views[$slug]}/index",
            ['cfg' => $cfg, 'rows' => $rows, 'total' => $total, 'table' => $state, 'options' => $options] + $extra
        );
    }
    public function save($slug)
    {
        $cfg = $this->Place_model->config($slug);
        if ($slug === 'scanned-categories') $this->require_permission('scanned_categories', 'view');
        else $this->require_permission($cfg['table'], (int) $this->input->post('id') ? 'edit' : 'add');
        $this->confirmed();
        try {
            if ($slug === 'scanned-categories') {
                require_once APPPATH . 'services/places/scanned_category_service.php';
                $savedId = (new Scanned_category_service())->save($this->input->post(), $this->user);
            } else $savedId = (new Place_service())->save($cfg, $this->input->post(), (int) $this->user['id']);
            $this->audit_created($cfg['table'], $savedId);
            $this->notice('Record saved.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        redirect("places/{$slug}");
    }
    public function deactivate($slug)
    {
        $cfg = $this->Place_model->config($slug);
        if (!$cfg['active']) { show_404(); return; }
        $this->require_permission($cfg['table'], 'delete');
        $this->confirmed();
        try {
            (new Place_service())->deactivate($cfg, (int) $this->input->post('id'));
            $this->notice('Record deactivated.');
        } catch (Throwable $e) {
            $this->error_notice($e);
        }
        redirect("places/{$slug}");
    }
}
