<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transfer_model extends CI_Model
{
    private function scope($user)
    {
        $this->db->from('transfers t')
            ->join('requests r', 'r.id=t.request_id')
            ->group_start()
                ->group_start()->where('t.status', 'for_transfer')
                    ->where('r.requested_by', (int) $user['id'])->group_end()
                ->or_group_start()->where('t.status', 'in_transit')
                    ->where('t.recipient_id', (int) $user['id'])->group_end()
            ->group_end();
    }

    public function pending_count($user)
    {
        $this->scope($user);
        return (int) $this->db->count_all_results();
    }

    public function pending($user, $page = 1, $limit = 10)
    {
        $total = $this->pending_count($user);
        $page = min(max(1, (int) $page), max(1, (int) ceil($total / $limit)));
        $this->scope($user);
        $rows = $this->details_query()
            ->order_by('t.created_at', 'DESC')->order_by('t.id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)->get()->result_array();
        return [$rows, $total, $page];
    }

    public function register_query($user, $state)
    {
        $this->scope($user);
        if ($state['status'] !== '')
            $this->db->where('t.status', $state['status']);
        if ($state['from'] !== '')
            $this->db->where('t.created_at >=', $state['from'] . ' 00:00:00');
        if ($state['to'] !== '')
            $this->db->where('t.created_at <=', $state['to'] . ' 23:59:59');
        if ($state['q'] !== '') {
            $this->db->group_start()->like('r.reference', $state['q'])->or_like('r.type', $state['q'])
                ->or_like('d.title', $state['q'])->or_like('t.reason', $state['q'])->group_end();
        }
        return $this->db->select(
            't.id AS task_id, 1 AS handoff, r.id AS request_id, r.reference, r.type, t.status,
             t.created_at, COALESCE(t.transferred_at,t.created_at) AS updated_at,
             CONCAT_WS(" ",requester.first_name,requester.last_name) AS requester_name',
            FALSE
        )->join('hardcopy_documents d', 'd.id=t.hardcopy_id')
            ->join('users requester', 'requester.id=r.requested_by')
            ->get_compiled_select();
    }

    public function register_rows($user, $ids)
    {
        if (!$ids)
            return [];
        $this->scope($user);
        return $this->details_query()->where_in('t.id', $ids)->get()->result_array();
    }

    private function details_query()
    {
        return $this->db->select('t.*, d.title AS document_title,
            r.reference, 1 AS handoff, COALESCE(t.transferred_at,t.created_at) AS updated_at,
            r.requested_by AS requester_id,
            CONCAT_WS(" ",requester.first_name,requester.last_name) AS requester_name,
            CONCAT_WS(" ",recipient.first_name,recipient.last_name) AS recipient_name,
            CONCAT_WS(" ",holder.first_name,holder.last_name) AS holder_name', FALSE)
            ->join('hardcopy_documents d', 'd.id=t.hardcopy_id')
            ->join('users requester', 'requester.id=r.requested_by')
            ->join('users recipient', 'recipient.id=t.recipient_id')
            ->join('users holder', 'holder.id=t.current_holder_id');
    }

    public function find($id)
    {
        return $this->db->get_where('transfers', ['id' => (int) $id])->row_array();
    }
}
