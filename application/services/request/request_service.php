<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request_service
{
    private $ci;
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Request_model');
    }

    public function save($type, $post, $userId)
    {
        $id = (int) ($post['id'] ?? 0);
        $subject = trim((string) ($post['subject'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 255) throw new DomainException('Enter a subject (up to 255 characters).');
        $documentId = (int) ($post['document_id'] ?? 0);
        if ($documentId && !$this->ci->db->get_where('documents', ['id' => $documentId, 'status' => 'active'])->row_array()) {
            throw new DomainException('Selected document is unavailable.');
        }
        $data = ['request_type' => $type, 'subject' => $subject, 'document_id' => $documentId ?: NULL,
            'remark' => trim((string) ($post['remark'] ?? ''))];
        if ($id) {
            $row = $this->ci->db->get_where('requests', ['id' => $id, 'request_type' => $type, 'requester_id' => $userId])->row_array();
            if (!$row || !in_array($row['status'], ['draft','returned'], TRUE)) throw new DomainException('Only your drafts or returned requests can be edited.');
            $this->ci->db->where('id', $id)->update('requests', $data);
        } else {
            $data['requester_id'] = $userId;
            $this->ci->db->insert('requests', $data);
        }
        if ($this->ci->db->error()['code']) throw new DomainException('Unable to save the request.');
    }

    public function submit($type, $id, $userId)
    {
        $this->ci->db->trans_begin();
        $rows = $this->ci->db->query('SELECT * FROM requests WHERE id = ? AND request_type = ? AND requester_id = ? FOR UPDATE',
            [$id, $type, $userId])->result_array();
        $row = $rows ? $rows[0] : NULL;
        if (!$row || !in_array($row['status'], ['draft','returned'], TRUE)) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Only your drafts or returned requests can be submitted.');
        }
        $workflow = $this->ci->Request_model->available_workflow($type);
        if (!$workflow) {
            $this->ci->db->trans_rollback();
            throw new DomainException('No active default workflow exists for this request type.');
        }
        $first = $this->ci->db->from('workflow_steps')->where('workflow_id', $workflow['id'])
            ->order_by('step_order')->limit(1)->get()->row_array();
        if (!$first) {
            $this->ci->db->trans_rollback();
            throw new DomainException('The selected workflow has no approval steps.');
        }
        $this->ci->db->where('id', $id)->update('requests', [
            'status' => 'pending', 'workflow_id' => $workflow['id'], 'current_step' => $first['step_order']
        ]);
        if ($this->ci->db->trans_status() === FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Request submission failed.');
        }
        $this->ci->db->trans_commit();
    }

    public function delete($type, $id, $userId)
    {
        $row = $this->ci->db->get_where('requests', [
            'id' => $id, 'request_type' => $type, 'requester_id' => $userId
        ])->row_array();
        if (!$row || $row['status'] !== 'draft') throw new DomainException('Only your drafts can be deleted.');
        $this->ci->db->where('id', $id)->delete('requests');
    }

    public function decide($type, $id, $user, $decision, $remark)
    {
        if (!in_array($decision, ['approved','rejected','returned'], TRUE)) throw new DomainException('Invalid decision.');
        $this->ci->db->trans_begin();
        $rows = $this->ci->db->query('SELECT * FROM requests WHERE id = ? AND request_type = ? FOR UPDATE',
            [$id, $type])->result_array();
        $row = $rows ? $rows[0] : NULL;
        if (!$row || $row['status'] !== 'pending' || !$this->ci->Request_model->can_act($row, $user)) {
            $this->ci->db->trans_rollback();
            throw new DomainException('This request is not assigned to you or has already been processed.');
        }
        $this->ci->db->insert('request_decisions', [
            'request_id' => $id, 'step_order' => $row['current_step'],
            'actor_id' => $user['id'], 'decision' => $decision,
            'remark' => trim($remark)
        ]);
        $update = [];
        if ($decision === 'approved') {
            $next = $this->ci->db->from('workflow_steps')->where('workflow_id', $row['workflow_id'])
                ->where('step_order >', $row['current_step'])->order_by('step_order')->limit(1)->get()->row_array();
            $update = $next ? ['current_step' => $next['step_order']] : ['status' => 'approved', 'current_step' => NULL];
        } else {
            $update = ['status' => $decision, 'current_step' => NULL];
        }
        $this->ci->db->where('id', $id)->update('requests', $update);
        if ($this->ci->db->trans_status() === FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Decision could not be saved.');
        }
        $this->ci->db->trans_commit();
    }
}
