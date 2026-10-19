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
            'softcopy-categories' => 'categories'
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
        foreach (['areas', 'specifics', 'assets', 'categories'] as $source) {
            $options[$source] = $this->Place_model->options($source);
        }
        $views = [
            'area' => 'area',
            'specific' => 'specific',
            'asset' => 'asset_number',
            'location' => 'location',
            'softcopy-categories' => 'softcopy_category'
        ];
        $this->render(
            "Places · {$cfg['label']}",
            "pages/places/{$views[$slug]}/index",
            ['cfg' => $cfg, 'rows' => $rows, 'total' => $total, 'table' => $state, 'options' => $options]
        );
    }
    public function save($slug)
    {
        $cfg = $this->Place_model->config($slug);
        $this->require_permission($cfg['table'], (int) $this->input->post('id') ? 'edit' : 'add');
        $this->confirmed();
        try {
            $savedId = (new Place_service())->save($cfg, $this->input->post(), (int) $this->user['id']);
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
