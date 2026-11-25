<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'services/places/scanned_category_policy.php';
require_once APPPATH . 'services/places/category_parent_policy.php';

class Scanned_category_service
{
    private $ci;
    public function __construct() {
        $this->ci =& get_instance();
        $this->ci->load->model('Permission_model');
        $this->ci->load->model('Identity_model');
    }

    public function unrestricted(array $user): bool {
        return $this->ci->Permission_model->allowed($user, 'scanned_categories', 'allocate') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'access_all') ||
            $this->ci->Permission_model->allowed($user, 'documents', 'view_all');
    }

    public function usable_ids(array $user, bool $lock = FALSE): array {
        $rows = $this->ci->db->query('SELECT * FROM scanned_categories ORDER BY id' . ($lock ? ' FOR UPDATE' : ''))->result_array();
        if ($this->unrestricted($user)) return array_map('intval', array_column($rows, 'id'));
        return Scanned_category_policy::usable_ids($rows,
            $this->ci->db->query('SELECT * FROM scanned_category_allocations ORDER BY category_id,user_id' . ($lock ? ' FOR UPDATE' : ''))->result_array(), (int) $user['id']);
    }

    public function require_usable(int $category, array $user): void {
        if ($category && !in_array($category, $this->usable_ids($user, TRUE), TRUE))
            throw new DomainException('Choose a scanned folder allocated to you or a shared folder.');
    }

    /** Permissions are checked here as well as in the Master Data UI. */
    public function save(array $post, array $user): int {
        $id = (int) ($post['id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $parent = (int) ($post['parent_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 150) throw new DomainException('Enter a folder name of up to 150 characters.');
        $allocate = array_key_exists('user_ids', $post) || !empty($post['save_allocations']);
        if ($allocate && !$this->ci->Permission_model->allowed($user, 'scanned_categories', 'allocate'))
            throw new DomainException('Scanned category allocation permission is required.');
        $users = array_values(array_unique(array_map('intval', (array) ($post['user_ids'] ?? []))));
        sort($users);
        $this->ci->db->trans_begin();
        try {
            // Serialize folder moves and allocations against category approval.
            $rows = $this->ci->db->query('SELECT *,1 AS active FROM scanned_categories ORDER BY id FOR UPDATE')->result_array();
            $old = array_column($rows, NULL, 'id')[$id] ?? NULL;
            if ($id && !$old) throw new DomainException('Scanned category not found.');
            $changed = !$old || $old['name'] !== $name || (int) $old['parent_id'] !== $parent;
            if ((!$id || $changed) && !$this->ci->Permission_model->allowed($user, 'scanned_categories', $id ? 'edit' : 'add'))
                throw new DomainException('Scanned category creation or editing permission is required.');
            if ($id && !$changed && !$allocate && !$this->ci->Permission_model->allowed($user, 'scanned_categories', 'edit'))
                throw new DomainException('Scanned category editing permission is required.');
            if ($id && (int) ($post['version'] ?? 0) !== (int) $old['version'])
                throw new DomainException('This folder changed. Refresh before saving.');
            Category_parent_policy::validate($rows, $id, $parent);
            if ($allocate) foreach ($users as $target)
                if (!$this->ci->Identity_model->active_user($target)) throw new DomainException('Choose only active users with active roles.');
            $data = ['name' => $name, 'parent_id' => $parent ?: NULL];
            if ($id) $this->ci->db->where('id', $id)->update('scanned_categories', $data + ['version' => (int) $old['version'] + 1]);
            else {
                $this->ci->db->insert('scanned_categories', $data + ['created_by' => (int) $user['id'], 'approved_by' => (int) $user['id']]);
                $id = (int) $this->ci->db->insert_id();
            }
            if ($allocate) {
                $this->ci->db->where('category_id', $id)->delete('scanned_category_allocations');
                foreach ($users as $target) $this->ci->db->insert('scanned_category_allocations', [
                    'category_id' => $id, 'user_id' => $target, 'allocated_by' => (int) $user['id']
                ]);
            }
            if ($this->ci->db->trans_status() === FALSE) throw new DomainException('Could not save. A folder with that name may already exist under the same parent.');
            $this->ci->db->trans_commit();
            return $id;
        } catch (Throwable $error) {
            $this->ci->db->trans_rollback();
            throw $error;
        }
    }
}
