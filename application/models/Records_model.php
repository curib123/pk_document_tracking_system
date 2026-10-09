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
        $this->db->select('d.*, p.name AS place_name, c.name AS category_name, u.name AS creator_name,
                 df.id AS latest_file_id, df.original_name AS latest_file_name, df.file_size AS latest_file_size')
            ->from('documents d')
            ->join('places p', 'p.id = d.place_id', 'left')
            ->join('places c', 'c.id = d.category_id', 'left')
            ->join('users u', 'u.id = d.created_by')
            ->join('document_files df',
                'df.id = (SELECT MAX(f.id) FROM document_files f WHERE f.document_id = d.id)', 'left')
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

    public function document_options($kind = NULL)
    {
        $this->db->select('id,code,title,kind')->from('documents')->where('status', 'active');
        if ($kind) $this->db->where('kind', $kind);
        return $this->db->order_by('title')->get()->result_array();
    }

    public function file_histories($documentIds)
    {
        if (!$documentIds) return [];
        $rows = $this->db->select('f.id,f.document_id,f.original_name,f.file_size,f.created_at,u.name AS uploaded_by')
            ->from('document_files f')->join('users u', 'u.id = f.uploaded_by')
            ->where_in('f.document_id', $documentIds)
            ->order_by('f.created_at', 'DESC')->order_by('f.id', 'DESC')
            ->get()->result_array();
        $histories = [];
        foreach ($rows as $row) $histories[$row['document_id']][] = $row;
        return $histories;
    }

    public function active_grants($documentIds)
    {
        if (!$documentIds) return [];
        $rows = $this->db->select('g.id,g.document_id,g.expires_at,g.created_at,u.name AS recipient')
            ->from('document_access_grants g')
            ->join('users u', 'u.id = g.user_id')
            ->where_in('g.document_id', $documentIds)
            ->where('g.revoked_at IS NULL', NULL, FALSE)
            ->group_start()->where('g.expires_at IS NULL', NULL, FALSE)
                ->or_where('g.expires_at >=', date('Y-m-d H:i:s'))->group_end()
            ->order_by('u.name')->get()->result_array();
        $byDocument = [];
        foreach ($rows as $row) $byDocument[$row['document_id']][] = $row;
        return $byDocument;
    }

    public function user_options()
    {
        return $this->db->select('id,name')->from('users')->where('active', 1)
            ->order_by('name')->get()->result_array();
    }
}
