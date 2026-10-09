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

    private function valid_details($type, $post)
    {
        $operation = in_array($type, ['softcopy', 'hardcopy'], TRUE)
            ? (string) ($post['operation'] ?? 'create')
            : ['hardcopy-transfer' => 'transfer', 'access-grant' => 'grant', 'document-assign' => 'assign'][$type];

        if (in_array($type, ['softcopy', 'hardcopy'], TRUE) &&
            !in_array($operation, ['create', 'revise', 'dispose'], TRUE)) {
            throw new DomainException('Select a valid document action.');
        }

        $documentId = $operation === 'create' ? 0 : (int) ($post['document_id'] ?? 0);
        $document = NULL;
        if ($operation !== 'create') {
            $document = $this->ci->db->get_where('documents', ['id' => $documentId, 'status' => 'active'])->row_array();
            if (!$document) throw new DomainException('Choose an active document.');
            $expected = in_array($type, ['softcopy','hardcopy'], TRUE) ? $type
                : ($type === 'hardcopy-transfer' ? 'hardcopy' : NULL);
            if ($expected && $document['kind'] !== $expected) throw new DomainException('Document type does not match the request.');
        }

        $details = ['operation' => $operation, 'target_place_id' => NULL,
            'target_user_id' => NULL, 'access_expires_at' => NULL,
            'proposed_code' => NULL, 'proposed_title' => NULL,
            'proposed_version' => NULL, 'proposed_description' => NULL];

        if ($operation === 'create' || $operation === 'revise') {
            $code = trim((string) ($post['proposed_code'] ?? ''));
            $title = trim((string) ($post['proposed_title'] ?? ''));
            $version = trim((string) ($post['proposed_version'] ?? '1'));
            if ($code === '' || mb_strlen($code) > 80 || $title === '' ||
                mb_strlen($title) > 255 || $version === '' || mb_strlen($version) > 30) {
                throw new DomainException('Enter a code, title and version for the proposed document.');
            }
            if ($operation === 'revise' && $version === $document['version']) {
                throw new DomainException('A revision must use a new version.');
            }
            $details['proposed_code'] = $code;
            $details['proposed_title'] = $title;
            $details['proposed_version'] = $version;
            $details['proposed_description'] = trim((string) ($post['proposed_description'] ?? ''));
        }
        if ($operation === 'transfer') {
            $place = (int) ($post['target_place_id'] ?? 0);
            if (!$this->ci->db->get_where('places', ['id' => $place, 'type' => 'location', 'active' => 1])->row_array()) {
                throw new DomainException('Choose an active destination location.');
            }
            if ((int) $document['place_id'] === $place) throw new DomainException('Destination must differ from the current location.');
            $details['target_place_id'] = $place;
        }
        if ($operation === 'assign' || $operation === 'grant') {
            $target = (int) ($post['target_user_id'] ?? 0);
            if (!$this->ci->db->get_where('users', ['id' => $target, 'active' => 1])->row_array()) {
                throw new DomainException('Choose an active user.');
            }
            $details['target_user_id'] = $target;
        }
        if ($operation === 'grant' && trim((string) ($post['access_expires_at'] ?? '')) !== '') {
            $date = (string) $post['access_expires_at'];
            $parsed = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date ||
                $date < date('Y-m-d')) throw new DomainException('Choose a valid future expiration date.');
            $details['access_expires_at'] = $date . ' 23:59:59';
        }
        return [$documentId ?: NULL, $details];
    }

    public function save($type, $post, $userId)
    {
        $id = (int) ($post['id'] ?? 0);
        $subject = trim((string) ($post['subject'] ?? ''));
        if ($subject === '' || mb_strlen($subject) > 255) {
            throw new DomainException('Enter a subject (up to 255 characters).');
        }
        list($documentId, $details) = $this->valid_details($type, $post);
        $data = ['request_type' => $type, 'subject' => $subject, 'document_id' => $documentId,
            'remark' => trim((string) ($post['remark'] ?? ''))];

        $this->ci->db->trans_begin();
        if ($id) {
            $row = $this->ci->db->get_where('requests', [
                'id' => $id, 'request_type' => $type, 'requester_id' => $userId
            ])->row_array();
            if (!$row || !in_array($row['status'], ['draft','returned'], TRUE)) {
                $this->ci->db->trans_rollback();
                throw new DomainException('Only your drafts or returned requests can be edited.');
            }
            $this->ci->db->where('id', $id)->update('requests', $data);
            $this->ci->db->where('request_id', $id)->delete('request_details');
        } else {
            $data['requester_id'] = $userId;
            $this->ci->db->insert('requests', $data);
            $id = (int) $this->ci->db->insert_id();
        }
        $details['request_id'] = $id;
        $this->ci->db->insert('request_details', $details);
        if ($this->ci->db->trans_status() === FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Unable to save the request.');
        }
        $this->ci->db->trans_commit();
        return $id;
    }

    public function submit($type, $id, $userId)
    {
        $this->ci->db->trans_begin();
        $rows = $this->ci->db->query(
            'SELECT * FROM requests WHERE id = ? AND request_type = ? AND requester_id = ? FOR UPDATE',
            [$id, $type, $userId])->result_array();
        $row = $rows ? $rows[0] : NULL;
        if (!$row || !in_array($row['status'], ['draft','returned'], TRUE)) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Only your drafts or returned requests can be submitted.');
        }
        if (!$this->ci->db->get_where('request_details', ['request_id' => $id])->row_array()) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Edit this request to complete its required details before submission.');
        }
        $workflow = $this->ci->Request_model->available_workflow($type);
        $first = $workflow ? $this->ci->db->from('workflow_steps')->where('workflow_id', $workflow['id'])
            ->order_by('step_order')->limit(1)->get()->row_array() : NULL;
        if (!$first) {
            $this->ci->db->trans_rollback();
            throw new DomainException('An active default workflow with approval steps is required.');
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

    private function apply_effect($request, $detail, $actorId)
    {
        $operation = $detail['operation'];
        $documentId = (int) $request['document_id'];
        $document = NULL;
        if ($operation !== 'create') {
            $result = $this->ci->db->query('SELECT * FROM documents WHERE id = ? FOR UPDATE', [$documentId])->result_array();
            $document = $result ? $result[0] : NULL;
            if (!$document || $document['status'] !== 'active') {
                throw new DomainException('Document is no longer active. Return the request for correction.');
            }
        }

        if ($operation === 'create') {
            $kind = $request['request_type'];
            $ok = $this->ci->db->insert('documents', [
                'kind' => $kind, 'code' => $detail['proposed_code'],
                'title' => $detail['proposed_title'], 'version' => $detail['proposed_version'],
                'description' => $detail['proposed_description'],
                'created_by' => $request['requester_id']
            ]);
            if (!$ok) throw new DomainException('Document could not be created. Check for duplicate codes.');
        } elseif ($operation === 'revise') {
            $ok = $this->ci->db->where('id', $documentId)->update('documents', [
                'code' => $detail['proposed_code'], 'title' => $detail['proposed_title'],
                'version' => $detail['proposed_version'], 'description' => $detail['proposed_description']
            ]);
            if (!$ok) throw new DomainException('Document revision failed. Check the document code.');
        } elseif ($operation === 'dispose') {
            $this->ci->db->where('id', $documentId)->update('documents', ['status' => 'disposed']);
        } elseif ($operation === 'transfer') {
            $location = $this->ci->db->get_where('places', [
                'id' => $detail['target_place_id'], 'type' => 'location', 'active' => 1
            ])->row_array();
            if (!$location) throw new DomainException('Destination location is no longer available.');
            $this->ci->db->insert('document_transfers', [
                'document_id' => $documentId, 'from_place_id' => $document['place_id'],
                'to_place_id' => $location['id'],
                'transferred_by' => $actorId, 'request_id' => $request['id']
            ]);
            $this->ci->db->where('id', $documentId)->update('documents', ['place_id' => $location['id']]);
        } elseif ($operation === 'grant' || $operation === 'assign') {
            $target = $this->ci->db->get_where('users', ['id' => $detail['target_user_id'], 'active' => 1])->row_array();
            if (!$target) throw new DomainException('Target user is no longer active.');
            if ($operation === 'grant') {
                if ($detail['access_expires_at'] && $detail['access_expires_at'] < date('Y-m-d H:i:s')) {
                    throw new DomainException('Access expiration has passed. Return the request for correction.');
                }
                $this->ci->db->insert('document_access_grants', [
                    'document_id' => $documentId, 'user_id' => $target['id'],
                    'granted_by' => $actorId, 'request_id' => $request['id'],
                    'expires_at' => $detail['access_expires_at']
                ]);
            } else {
                $this->ci->db->query(
                    'INSERT INTO document_assignments (document_id,user_id,assigned_by,request_id)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), assigned_by=VALUES(assigned_by),
                     request_id=VALUES(request_id), assigned_at=CURRENT_TIMESTAMP',
                    [$documentId, $target['id'], $actorId, $request['id']]
                );
            }
        }
    }

    public function decide($type, $id, $user, $decision, $remark)
    {
        if (!in_array($decision, ['approved','rejected','returned'], TRUE)) {
            throw new DomainException('Invalid decision.');
        }
        $this->ci->db->trans_begin();
        try {
            $rows = $this->ci->db->query(
                'SELECT * FROM requests WHERE id = ? AND request_type = ? FOR UPDATE',
                [$id, $type])->result_array();
            $request = $rows ? $rows[0] : NULL;
            if (!$request || $request['status'] !== 'pending' ||
                !$this->ci->Request_model->can_act($request, $user)) {
                throw new DomainException('This request is not assigned to you or has already been processed.');
            }
            $detail = $this->ci->db->get_where('request_details', ['request_id' => $id])->row_array();
            $this->ci->db->insert('request_decisions', [
                'request_id' => $id, 'step_order' => $request['current_step'],
                'actor_id' => $user['id'], 'decision' => $decision, 'remark' => trim($remark)
            ]);
            if ($decision === 'approved') {
                $next = $this->ci->db->from('workflow_steps')->where('workflow_id', $request['workflow_id'])
                    ->where('step_order >', $request['current_step'])
                    ->order_by('step_order')->limit(1)->get()->row_array();
                if ($next) $update = ['current_step' => $next['step_order']];
                else {
                    if (!$detail) throw new DomainException('Missing action details; return this request for editing.');
                    $this->apply_effect($request, $detail, (int) $user['id']);
                    $update = ['status' => 'approved', 'current_step' => NULL];
                }
            } else {
                $update = ['status' => $decision, 'current_step' => NULL];
            }
            $this->ci->db->where('id', $id)->update('requests', $update);
            if ($this->ci->db->trans_status() === FALSE) {
                throw new DomainException('Decision could not be saved. Check for conflicting document records.');
            }
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error', 'Request decision failure: ' . $e->getMessage());
            throw new DomainException('Decision could not be saved. Please review the request data.');
        }
    }
}
