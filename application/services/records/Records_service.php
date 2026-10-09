<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Records_service
{
    private $ci;
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Records_model');
    }

    public function save_place($type, $input)
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) throw new DomainException('Enter a name (up to 180 characters).');
        $data = ['type' => $type, 'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'active' => !empty($input['active']) ? 1 : 0];
        $id = (int) ($input['id'] ?? 0);
        if ($id) {
            $row = $this->ci->db->get_where('places', ['id' => $id, 'type' => $type])->row_array();
            if (!$row) throw new DomainException('Place was not found.');
            $this->ci->db->where('id', $id)->update('places', $data);
        } else {
            $this->ci->db->insert('places', $data);
        }
        if ($this->ci->db->error()['code']) throw new DomainException('This place already exists or could not be saved.');
    }

    public function delete_place($type, $id)
    {
        $row = $this->ci->db->get_where('places', ['id' => $id, 'type' => $type])->row_array();
        if (!$row) throw new DomainException('Place was not found.');
        // Existing document references are preserved. No accidental cascades.
        $this->ci->db->where('id', $id)->update('places', ['active' => 0]);
    }

    public function save_document($kind, $input, $userId)
    {
        $code = trim((string) ($input['code'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        if ($code === '' || mb_strlen($code) > 80 || $title === '' || mb_strlen($title) > 255) {
            throw new DomainException('A document code and title are required.');
        }
        $status = (string) ($input['status'] ?? 'active');
        if (!in_array($status, ['active','archived','disposed'], TRUE)) throw new DomainException('Invalid status.');
        $placeId = (int) ($input['place_id'] ?? 0);
        $categoryId = (int) ($input['category_id'] ?? 0);
        if ($placeId && !$this->ci->db->get_where('places', ['id' => $placeId, 'active' => 1])->row_array()) {
            throw new DomainException('Select a valid place.');
        }
        if ($categoryId && !$this->ci->db->get_where('places', ['id' => $categoryId, 'type' => 'softcopy-categories', 'active' => 1])->row_array()) {
            throw new DomainException('Select a valid softcopy category.');
        }
        $data = ['kind' => $kind, 'code' => $code, 'title' => $title,
            'description' => trim((string) ($input['description'] ?? '')),
            'version' => trim((string) ($input['version'] ?? '1')) ?: '1',
            'status' => $status, 'place_id' => $placeId ?: NULL,
            'category_id' => $kind === 'softcopy' ? ($categoryId ?: NULL) : NULL];
        $id = (int) ($input['id'] ?? 0);
        if ($id) {
            if (!$this->ci->db->get_where('documents', ['id' => $id, 'kind' => $kind])->row_array()) {
                throw new DomainException('Document was not found.');
            }
            $this->ci->db->where('id', $id)->update('documents', $data);
        } else {
            $data['created_by'] = $userId;
            $this->ci->db->insert('documents', $data);
        }
        if ($this->ci->db->error()['code']) throw new DomainException('Document code already exists or save failed.');
    }

    public function delete_document($kind, $id)
    {
        if (!$this->ci->db->get_where('documents', ['id' => $id, 'kind' => $kind])->row_array()) {
            throw new DomainException('Document was not found.');
        }
        // Preserve documents referenced by requests and audit history.
        $this->ci->db->where('id', $id)->update('documents', ['status' => 'disposed']);
    }
}
