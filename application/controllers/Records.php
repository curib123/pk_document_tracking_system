<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Records extends MY_Controller
{
    private $kinds = ['hardcopy' => 'Hardcopy Documents', 'softcopy' => 'Softcopy Documents'];
    private $places = ['area' => 'Area', 'specific' => 'Specific', 'asset' => 'Asset',
        'location' => 'Location', 'sequence' => 'Sequence', 'softcopy-categories' => 'Softcopy Categories'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Records_model');
        require_once APPPATH . 'services/records/Records_service.php';
    }

    private function valid($value, $options)
    {
        if (!array_key_exists($value, $options)) show_404();
        return $value;
    }

    private function paging()
    {
        $limit = (int) $this->input->get('limit');
        $limit = in_array($limit, [10,25,50,100], TRUE) ? $limit : 10;
        return [max(1, (int) $this->input->get('page')), $limit];
    }

    public function documents($kind)
    {
        $kind = $this->valid($kind, $this->kinds);
        $this->require_permission($kind);
        list($page, $limit) = $this->paging();
        $q = trim((string) $this->input->get('q', TRUE));
        $status = (string) $this->input->get('status', TRUE);
        if (!in_array($status, ['','active','archived','disposed'], TRUE)) $status = '';
        list($rows, $total) = $this->Records_model->documents($kind, $q, $status, $limit, ($page - 1) * $limit);
        $this->render($this->kinds[$kind], 'pages/records/index', [
            'mode' => 'documents', 'module' => $kind, 'current' => $kind,
            'tabs' => $this->kinds, 'rows' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'q' => $q, 'status' => $status,
            'base_path' => 'documents/' . $kind,
            'form_action' => 'documents/' . $kind . '/save',
            'delete_action' => 'documents/' . $kind . '/delete',
            'place_options' => $this->Records_model->place_options('location'),
            'category_options' => $this->Records_model->place_options('softcopy-categories')
        ]);
    }

    public function save_document($kind)
    {
        $kind = $this->valid($kind, $this->kinds);
        $this->require_permission($kind, (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Records_service())->save_document($kind, $this->input->post(), (int) $this->user['id']);
            $this->notice('Document saved successfully.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('documents/' . $kind);
    }

    public function delete_document($kind)
    {
        $kind = $this->valid($kind, $this->kinds);
        $this->require_permission($kind, 'delete');
        $this->confirmed();
        try {
            (new Records_service())->delete_document($kind, (int) $this->input->post('id'));
            $this->notice('Document marked as disposed.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('documents/' . $kind);
    }

    public function places($type)
    {
        $type = $this->valid($type, $this->places);
        $this->require_permission($type);
        list($page, $limit) = $this->paging();
        $q = trim((string) $this->input->get('q', TRUE));
        $status = (string) $this->input->get('status', TRUE);
        if (!in_array($status, ['','0','1'], TRUE)) $status = '';
        list($rows, $total) = $this->Records_model->places($type, $q, $status, $limit, ($page - 1) * $limit);
        $this->render('Places · ' . $this->places[$type], 'pages/records/index', [
            'mode' => 'places', 'module' => $type, 'current' => $type,
            'tabs' => $this->places, 'rows' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'q' => $q, 'status' => $status,
            'base_path' => 'places/' . $type,
            'form_action' => 'places/' . $type . '/save',
            'delete_action' => 'places/' . $type . '/delete'
        ]);
    }

    public function save_place($type)
    {
        $type = $this->valid($type, $this->places);
        $this->require_permission($type, (int) $this->input->post('id') ? 'edit' : 'create');
        $this->confirmed();
        try {
            (new Records_service())->save_place($type, $this->input->post());
            $this->notice('Place saved successfully.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('places/' . $type);
    }

    public function delete_place($type)
    {
        $type = $this->valid($type, $this->places);
        $this->require_permission($type, 'delete');
        $this->confirmed();
        try {
            (new Records_service())->delete_place($type, (int) $this->input->post('id'));
            $this->notice('Place deactivated.');
        } catch (DomainException $e) { $this->notice($e->getMessage(), 'danger'); }
        redirect('places/' . $type);
    }
}
