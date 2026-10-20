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

    public function pending($user, $page = 1, $limit = 10)
    {
        $this->scope($user);
        $total = (int) $this->db->count_all_results();
        $page = min(max(1, (int) $page), max(1, (int) ceil($total / $limit)));
        $this->scope($user);
        $rows = $this->db->select('t.*, d.title AS document_title,
            r.requested_by AS requester_id,
            CONCAT_WS(" ",requester.first_name,requester.last_name) AS requester_name,
            CONCAT_WS(" ",recipient.first_name,recipient.last_name) AS recipient_name,
            CONCAT_WS(" ",holder.first_name,holder.last_name) AS holder_name', FALSE)
            ->join('hardcopy_documents d', 'd.id=t.hardcopy_id')
            ->join('users requester', 'requester.id=r.requested_by')
            ->join('users recipient', 'recipient.id=t.recipient_id')
            ->join('users holder', 'holder.id=t.current_holder_id')
            ->order_by('t.created_at', 'DESC')->order_by('t.id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)->get()->result_array();
        return [$rows, $total, $page];
    }

    public function find($id)
    {
        return $this->db->get_where('transfers', ['id' => (int) $id])->row_array();
    }
}
