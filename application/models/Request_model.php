<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request_model extends CI_Model
{
    // Apply exactly the same scope to COUNT(*) and the paged records query.
    private function scope($type, $user, $task, $q, $status)
    {
        $this->db->from('requests r')
            ->join('documents d', 'd.id = r.document_id', 'left')
            ->join('request_details rd', 'rd.request_id = r.id', 'left')
            ->join('places destination', 'destination.id = rd.target_place_id', 'left')
            ->join('users target', 'target.id = rd.target_user_id', 'left')
            ->join('workflow_steps ws',
                'ws.workflow_id = r.workflow_id AND ws.step_order = r.current_step',
                $task ? 'inner' : 'left')
            ->where('r.request_type', $type);

        if ($task) {
            // Only the currently assigned approver may list this task.
            $this->db->join('users creator', 'creator.id = r.requester_id')
                ->where('r.status', 'pending')
                ->group_start()
                    ->group_start()->where('ws.approver_type', 'user')
                        ->where('ws.approver_user_id', (int) $user['id'])->group_end()
                    ->or_group_start()->where('ws.approver_type', 'role')
                        ->where('ws.approver_role_id', (int) $user['role_id'])->group_end()
                    ->or_group_start()->where('ws.approver_type', 'requester_leader')
                        ->where('creator.leader_id', (int) $user['id'])->group_end()
                    ->or_group_start()->where('ws.approver_type', 'requester')
                        ->where('creator.id', (int) $user['id'])->group_end()
                ->group_end();
        } else {
            $this->db->where('r.requester_id', (int) $user['id']);
        }

        if ($status !== '') $this->db->where('r.status', $status);
        if ($q !== '') {
            $this->db->group_start()
                ->like('r.subject', $q)
                ->or_like('d.code', $q);
            if ($task) $this->db->or_like('creator.name', $q);
            $this->db->group_end();
        }
    }

    public function count_listing($type, $user, $task, $q, $status)
    {
        $this->scope($type, $user, $task, $q, $status);
        return (int) $this->db->count_all_results();
    }

    public function page_listing($type, $user, $task, $q, $status, $limit, $offset, $sort, $dir)
    {
        $this->scope($type, $user, $task, $q, $status);
        $fields = [
            'subject' => 'r.subject', 'document' => 'd.code',
            'status' => 'r.status', 'created' => 'r.created_at',
            'updated' => 'r.updated_at', 'requester' => 'creator.name',
            'step' => 'ws.label'
        ];
        if (!isset($fields[$sort]) || (!$task && in_array($sort, ['requester', 'step'], TRUE))) {
            $sort = 'updated';
        }
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $this->db->select('r.*, d.code AS document_code, d.title AS document_title,
            ws.label AS current_step_label, rd.operation, rd.target_place_id,
            rd.target_user_id, rd.access_expires_at, rd.proposed_code,
            rd.proposed_title, rd.proposed_version, rd.proposed_description,
            destination.name AS destination_name, target.name AS target_user_name');
        if ($task) $this->db->select('creator.name AS requester_name, ws.label AS step_label');
        return $this->db->order_by($fields[$sort], $direction)
            ->order_by('r.id', $direction)
            ->limit($limit, $offset)->get()->result_array();
    }

    public function histories($ids)
    {
        if (!$ids) return [];
        $decisions = $this->db->select('d.request_id,d.step_order,d.decision,d.remark,d.created_at,u.name AS actor')
            ->from('request_decisions d')->join('users u', 'u.id = d.actor_id')
            ->where_in('d.request_id', $ids)
            ->order_by('d.created_at', 'ASC')->order_by('d.id', 'ASC')
            ->get()->result_array();
        $result = [];
        foreach ($decisions as $item) {
            $line = 'Step ' . $item['step_order'] . ': ' . ucfirst($item['decision']) .
                ' by ' . $item['actor'] . ' on ' . $item['created_at'];
            if ($item['remark'] !== '') $line .= ' — ' . $item['remark'];
            $result[$item['request_id']][] = $line;
        }
        return $result;
    }

    public function available_workflow($type)
    {
        return $this->db->from('workflows')->where('request_type', $type)
            ->where('active', 1)->where('is_default', 1)
            ->order_by('version', 'DESC')->limit(1)->get()->row_array();
    }

    public function can_act($request, $user)
    {
        $step = $this->db->get_where('workflow_steps', [
            'workflow_id' => $request['workflow_id'], 'step_order' => $request['current_step']
        ])->row_array();
        if (!$step) return FALSE;
        if ($step['approver_type'] === 'user') return (int) $step['approver_user_id'] === (int) $user['id'];
        if ($step['approver_type'] === 'role') return (int) $step['approver_role_id'] === (int) $user['role_id'];
        if ($step['approver_type'] === 'requester') return (int) $request['requester_id'] === (int) $user['id'];
        $owner = $this->db->select('leader_id')->get_where('users', ['id' => $request['requester_id']])->row_array();
        return $owner && (int) $owner['leader_id'] === (int) $user['id'];
    }
}
