<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Administration_service
{
    private $ci;
    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function save_user($post)
    {
        $id = (int) ($post['id'] ?? 0);
        $roleId = (int) ($post['role_id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $username = trim((string) ($post['username'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        if (!$this->ci->db->get_where('roles', ['id' => $roleId])->row_array()) throw new DomainException('Choose a valid role.');
        if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            throw new DomainException('Enter a valid name, username and email.');
        }
        $data = ['name' => $name, 'username' => $username, 'email' => $email,
            'role_id' => $roleId, 'active' => !empty($post['active']) ? 1 : 0];
        $leaderId = (int) ($post['leader_id'] ?? 0);
        if ($leaderId && ($leaderId === $id || !$this->ci->db->get_where('users', ['id' => $leaderId, 'active' => 1])->row_array())) {
            throw new DomainException('Select a valid leader (not the same user).');
        }
        $data['leader_id'] = $leaderId ?: NULL;
        if ($password !== '') {
            if (strlen($password) < 12) throw new DomainException('Password must contain at least 12 characters.');
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        } elseif (!$id) {
            throw new DomainException('Enter an initial password with at least 12 characters.');
        }
        if ($id) {
            if (!$this->ci->db->get_where('users', ['id' => $id])->row_array()) throw new DomainException('User not found.');
            $this->ci->db->where('id', $id)->update('users', $data);
        } else {
            $this->ci->db->insert('users', $data);
        }
        if ($this->ci->db->error()['code']) throw new DomainException('Username or email already exists.');
    }

    public function deactivate_user($id, $actorId)
    {
        if ($id === $actorId) throw new DomainException('You cannot deactivate your own account.');
        if (!$this->ci->db->get_where('users', ['id' => $id])->row_array()) throw new DomainException('User not found.');
        $this->ci->db->where('id', $id)->update('users', ['active' => 0]);
    }

    public function save_role($post)
    {
        $id = (int) ($post['id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        if (!preg_match('/^[a-z][a-z0-9_]{2,79}$/', $name)) throw new DomainException('Role names use lowercase letters, numbers and underscores.');
        $role = $id ? $this->ci->db->get_where('roles', ['id' => $id])->row_array() : NULL;
        if ($id && !$role) throw new DomainException('Role not found.');
        if ($role && $role['is_system'] && $role['name'] !== $name) throw new DomainException('System role names cannot be changed.');
        $permissionIds = array_map('intval', (array) ($post['permissions'] ?? []));
        $permissionIds = array_values(array_unique(array_filter($permissionIds)));
        if ($role && $role['name'] === 'super_admin') throw new DomainException('Super admin permissions are fixed.');
        $this->ci->db->trans_begin();
        $data = ['name' => $name, 'description' => trim((string) ($post['description'] ?? ''))];
        if ($id) $this->ci->db->where('id', $id)->update('roles', $data);
        else { $this->ci->db->insert('roles', $data); $id = $this->ci->db->insert_id(); }
        $this->ci->db->where('role_id', $id)->delete('role_permissions');
        foreach ($permissionIds as $pid) {
            if ($this->ci->db->get_where('permissions', ['id' => $pid])->row_array()) {
                $this->ci->db->insert('role_permissions', ['role_id' => $id, 'permission_id' => $pid]);
            }
        }
        if ($this->ci->db->trans_status() === FALSE) { $this->ci->db->trans_rollback(); throw new DomainException('Role could not be saved.'); }
        $this->ci->db->trans_commit();
    }

    public function save_workflow($post)
    {
        $types = ['softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign'];
        $type = (string) ($post['request_type'] ?? '');
        $name = trim((string) ($post['name'] ?? ''));
        if (!in_array($type, $types, TRUE) || $name === '' || mb_strlen($name) > 160) throw new DomainException('Enter a valid workflow name and type.');
        $id = (int) ($post['id'] ?? 0);
        if (!empty($post['is_default'])) {
            $count = $id ? $this->ci->db->where('workflow_id', $id)->count_all_results('workflow_steps') : 0;
            if (!$count || empty($post['active'])) {
                throw new DomainException('Add approval steps and activate the workflow before making it the default.');
            }
        }
        $this->ci->db->trans_begin();
        $data = ['name' => $name, 'request_type' => $type, 'active' => !empty($post['active']) ? 1 : 0,
            'is_default' => !empty($post['is_default']) ? 1 : 0];
        if ($id) {
            $existing = $this->ci->db->get_where('workflows', ['id' => $id, 'request_type' => $type])->row_array();
            if (!$existing) throw new DomainException('Workflow not found or type cannot change.');
            $this->ci->db->where('id', $id)->update('workflows', $data);
        } else {
            $latest = $this->ci->db->select_max('version')->get_where('workflows', ['request_type' => $type])->row_array();
            $data['version'] = (int) ($latest['version'] ?? 0) + 1;
            $this->ci->db->insert('workflows', $data);
            $id = $this->ci->db->insert_id();
        }
        if ($data['is_default']) {
            $this->ci->db->where('request_type', $type)->where('id !=', $id)
                ->update('workflows', ['is_default' => 0]);
        }
        if ($this->ci->db->trans_status() === FALSE) { $this->ci->db->trans_rollback(); throw new DomainException('Workflow could not be saved.'); }
        $this->ci->db->trans_commit();
    }

    public function clone_workflow($id)
    {
        $source = $this->ci->db->get_where('workflows', ['id' => $id])->row_array();
        if (!$source) throw new DomainException('Workflow version was not found.');
        $steps = $this->ci->db->from('workflow_steps')->where('workflow_id', $id)
            ->order_by('step_order')->get()->result_array();
        if (!$steps) throw new DomainException('Add approval steps before cloning a workflow.');

        $this->ci->db->trans_begin();
        $latest = $this->ci->db->select_max('version')->get_where('workflows', [
            'request_type' => $source['request_type']
        ])->row_array();
        $this->ci->db->insert('workflows', [
            'name' => $source['name'], 'request_type' => $source['request_type'],
            'version' => (int) $latest['version'] + 1,
            'active' => 0, 'is_default' => 0
        ]);
        $newId = (int) $this->ci->db->insert_id();
        foreach ($steps as $step) {
            $this->ci->db->insert('workflow_steps', [
                'workflow_id' => $newId, 'step_order' => $step['step_order'],
                'label' => $step['label'], 'approver_type' => $step['approver_type'],
                'approver_user_id' => $step['approver_user_id'],
                'approver_role_id' => $step['approver_role_id']
            ]);
        }
        if ($this->ci->db->trans_status() === FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Could not clone the workflow version.');
        }
        $this->ci->db->trans_commit();
    }

    public function save_step($post)
    {
        $workflowId = (int) ($post['workflow_id'] ?? 0);
        $workflow = $this->ci->db->get_where('workflows', ['id' => $workflowId])->row_array();
        if (!$workflow) throw new DomainException('Workflow not found.');
        if ($this->ci->db->get_where('requests', ['workflow_id' => $workflowId])->row_array()) {
            throw new DomainException('This workflow version is in use. Create a new version instead.');
        }
        $kind = (string) ($post['approver_type'] ?? '');
        $label = trim((string) ($post['label'] ?? ''));
        $order = (int) ($post['step_order'] ?? 0);
        if (!in_array($kind, ['user','role','requester_leader','requester'], TRUE) || $label === '' || $order < 1) {
            throw new DomainException('Enter a valid step number, label and approver type.');
        }
        $userId = $kind === 'user' ? (int) ($post['approver_user_id'] ?? 0) : 0;
        $roleId = $kind === 'role' ? (int) ($post['approver_role_id'] ?? 0) : 0;
        if ($kind === 'user' && !$this->ci->db->get_where('users', ['id' => $userId, 'active' => 1])->row_array()) throw new DomainException('Choose an active approver.');
        if ($kind === 'role' && !$this->ci->db->get_where('roles', ['id' => $roleId])->row_array()) throw new DomainException('Choose an approver role.');
        $data = ['workflow_id' => $workflowId, 'step_order' => $order, 'label' => $label,
            'approver_type' => $kind, 'approver_user_id' => $userId ?: NULL, 'approver_role_id' => $roleId ?: NULL];
        $id = (int) ($post['id'] ?? 0);
        if ($id) {
            if (!$this->ci->db->get_where('workflow_steps', ['id' => $id, 'workflow_id' => $workflowId])->row_array()) throw new DomainException('Step not found.');
            $this->ci->db->where('id', $id)->update('workflow_steps', $data);
        } else {
            $this->ci->db->insert('workflow_steps', $data);
        }
        if ($this->ci->db->error()['code']) throw new DomainException('Step number already exists in this workflow.');
    }

    public function delete_step($id)
    {
        $step = $this->ci->db->get_where('workflow_steps', ['id' => $id])->row_array();
        if (!$step) throw new DomainException('Step not found.');
        if ($this->ci->db->get_where('requests', ['workflow_id' => $step['workflow_id']])->row_array()) {
            throw new DomainException('This version has requests and cannot be modified.');
        }
        $this->ci->db->where('id', $id)->delete('workflow_steps');
    }
}
