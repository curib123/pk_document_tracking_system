<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Records_model extends CI_Model
{
    public function places($type, $search, $status, $limit, $offset)
    {
        $this->db->from('places')->where('type', $type);
        if ($search !== '') $this->db->like('name', $search);
        if ($status !== '') $this->db->where('active', (int) $status);
        $total = $this->db->count_all_results('', FALSE);
        $rows = $this->db->order_by('name', 'ASC')->limit($limit, $offset)->get()->result_array();
        return [$rows, $total];
    }

    public function documents($kind, $search, $status, $limit, $offset)
    {
        $this->db->select('d.*, p.name AS place_name, c.name AS category_name, u.name AS creator_name')
            ->from('documents d')
            ->join('places p', 'p.id = d.place_id', 'left')
            ->join('places c', 'c.id = d.category_id', 'left')
            ->join('users u', 'u.id = d.created_by')
            ->where('d.kind', $kind);
        if ($search !== '') {
            $this->db->group_start()->like('d.title', $search)
                ->or_like('d.code', $search)->group_end();
        }
        if ($status !== '') $this->db->where('d.status', $status);
        $total = $this->db->count_all_results('', FALSE);
        $rows = $this->db->order_by('d.updated_at', 'DESC')->limit($limit, $offset)->get()->result_array();
        return [$rows, $total];
    }

    public function place_options($type)
    {
        return $this->db->select('id,name')->from('places')->where('type', $type)
            ->where('active', 1)->order_by('name')->get()->result_array();
    }

    public function document_options()
    {
        return $this->db->select('id,code,title')->from('documents')
            ->where('status', 'active')->order_by('title')->get()->result_array();
    }
}
