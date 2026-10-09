<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request_model extends CI_Model
{
    public function mine($type, $userId)
    {
        return $this->db->select('r.*, d.code AS document_code, d.title AS document_title, ws.label AS current_step_label, rd.operation, rd.target_place_id, rd.target_user_id, rd.access_expires_at, rd.proposed_code, rd.proposed_title, rd.proposed_version, rd.proposed_description, destination.name AS destination_name, target.name AS target_user_name')
            ->from('requests r')->join('documents d', 'd.id = r.document_id', 'left')
            ->join('workflow_steps ws', 'ws.workflow_id = r.workflow_id AND ws.step_order = r.current_step', 'left')
            ->join('request_details rd', 'rd.request_id = r.id', 'left')
            ->join('places destination', 'destination.id = rd.target_place_id', 'left')
            ->join('users target', 'target.id = rd.target_user_id', 'left')
            ->where('r.request_type', $type)->where('r.requester_id', $userId)
            ->order_by('r.updated_at', 'DESC')->get()->result_array();
    }

    public function tasks($type, $user)
    {
        $sql = "SELECT r.*, d.code AS document_code, d.title AS document_title,
                    creator.name AS requester_name, ws.label AS step_label,
                    rd.operation, rd.target_place_id, rd.target_user_id, rd.access_expires_at,
                    rd.proposed_code, rd.proposed_title, rd.proposed_version, rd.proposed_description,
                    destination.name AS destination_name, target.name AS target_user_name
                FROM requests r
                JOIN users creator ON creator.id = r.requester_id
                JOIN workflow_steps ws ON ws.workflow_id = r.workflow_id
                    AND ws.step_order = r.current_step
                LEFT JOIN documents d ON d.id = r.document_id
                LEFT JOIN request_details rd ON rd.request_id = r.id
                LEFT JOIN places destination ON destination.id = rd.target_place_id
                LEFT JOIN users target ON target.id = rd.target_user_id
                WHERE r.request_type = ? AND r.status = 'pending'
                    AND ((ws.approver_type = 'user' AND ws.approver_user_id = ?)
                      OR (ws.approver_type = 'role' AND ws.approver_role_id = ?)
                      OR (ws.approver_type = 'requester_leader' AND creator.leader_id = ?)
                      OR (ws.approver_type = 'requester' AND creator.id = ?))
                ORDER BY r.updated_at DESC";
        return $this->db->query($sql, [$type, $user['id'], $user['role_id'], $user['id'], $user['id']])->result_array();
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
