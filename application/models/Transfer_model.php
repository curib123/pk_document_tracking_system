<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transfer_model extends CI_Model
{
    private function scope($user)
    {
        $this->db->from('transfers t')
            ->where_in('t.status', ['for_transfer', 'in_transit'])
            ->group_start()->where('t.current_holder_id', (int)$user['id'])
            ->or_where('t.recipient_id', (int)$user['id'])->group_end();
    }

    public function pending($user, $page = 1, $limit = 10)
    {
        $this->scope($user);
        $total=(int)$this->db->count_all_results();
        $page=min(max(1,(int)$page),max(1,(int)ceil($total/$limit)));
        $this->scope($user);
        $rows=$this->db->select('t.*, d.title AS document_title,
            CONCAT_WS(" ",recipient.first_name,recipient.last_name) AS recipient_name,
            CONCAT_WS(" ",holder.first_name,holder.last_name) AS holder_name',FALSE)
            ->join('hardcopy_documents d', 'd.id=t.hardcopy_id')
            ->join('users recipient','recipient.id=t.recipient_id')
            ->join('users holder','holder.id=t.current_holder_id')
            ->order_by('t.created_at','DESC')->order_by('t.id','DESC')
            ->limit($limit,($page-1)*$limit)->get()->result_array();
        return [$rows,$total,$page];
    }

    public function find($id)
    {
        return $this->db->get_where('transfers',['id'=>(int)$id])->row_array();
    }
}
