<?php
declare(strict_types=1);

use Pk\Core\Context;

/** Identity persistence using the native CodeIgniter MySQLi Query Builder. */
class Identity_model extends Repository_model
{
    // User/role identity queries diri; permission decisions stay sa Context/service.

    public function active_user(array $params = []): ?array
    {
        $this->db
            ->reset_query()
            ->select('u.*, r.name AS role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $params[0])
            ->where('u.active', 1)
            ->where('r.active', 1)
            ->limit(1);

        return $this->first();
    }

    public function role_capabilities(array $params = []): array
    {
        $this->db
            ->reset_query()
            ->select('p.module_key,p.action_key')
            ->from('permissions p')
            ->join(
                'role_permissions rp',
                'rp.permission_id = p.id'
            )
            ->where('rp.role_id', $params[0]);

        return array_map(
            static fn(array $row): array => [
                'capability' =>
                    $row['module_key']
                    . '.'
                    . $row['action_key'],
            ],
            $this->results()
        );
    }

    public function increment_sequence(array $params = []): bool
    {
        $this->store->require_transaction();

        $this->db
            ->reset_query()
            ->set([
                'sequence_key' => $params[0],
                'value' => 1,
            ]);

        $sql = $this->db->get_compiled_insert('sequences');

        // Atomic upsert ni para safe bisan simultaneous requests.
        return $this->written(
            $this->db->query(
                $sql . ' ON DUPLICATE KEY UPDATE \`value\`=\`value\`+1'
            )
        );
    }

    public function sequence_for_update(array $params = []): ?array
    {
        $this->db
            ->reset_query()
            ->select('value')
            ->from('sequences')
            ->where('sequence_key', $params[0])
            ->limit(1);

        return $this->first(true);
    }
}
