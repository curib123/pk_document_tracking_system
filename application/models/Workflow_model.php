<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem};

/**
 * Workflow persistence using the native CodeIgniter MySQLi Query Builder.
 */
class Workflow_model extends Repository_model
{
    // Ari ang DB queries; service layer ang magbuot sa business rules para klaro ang separation.
    public function next_version_number(array $params = []): ?array
    {
        $this->db
            ->reset_query()
            ->select(
                'COALESCE(MAX(version_number),0)+1 AS n',
                false
            )
            ->from('workflow_versions')
            ->where('workflow_id', $params[0]);

        return $this->first();
    }

    public function deactivate_other_definitions(
        array $params = []
    ): bool {
        $this->db
            ->reset_query()
            ->where('request_type', $params[0])
            ->where('id !=', $params[1])
            ->where('active', 1)
            ->set('active', 0)
            ->set(
                'version',
                'version + 1',
                false
            );

        return $this->written(
            $this->db->update('workflows')
        );
    }

    public function default_version(
        array $params = []
    ): ?array {
        $this->db
            ->reset_query()
            ->select('v.*')
            ->from('workflow_versions v')
            ->join(
                'workflows w',
                'w.id = v.workflow_id'
            )
            ->where(
                'w.request_type',
                $params[0]
            )
            ->where('w.active', 1)
            ->where(
                'v.status',
                'published'
            )
            ->where('v.is_default', 1)
            ->limit(1);

        return $this->first(true);
    }

    public function default_version_for_workflow(
        array $params = []
    ): ?array {
        $this->db
            ->reset_query()
            ->from('workflow_versions')
            ->where(
                'workflow_id',
                $params[0]
            )
            ->where('status', 'published')
            ->where('is_default', 1)
            ->limit(1);

        return $this->first();
    }

    public function clear_default_versions(
        array $params = []
    ): bool {
        $this->db
            ->reset_query()
            ->where(
                'workflow_id',
                $params[0]
            )
            ->where('is_default', 1)
            ->set('is_default', 0)
            ->set(
                'version',
                'version + 1',
                false
            );

        return $this->written(
            $this->db->update(
                'workflow_versions'
            )
        );
    }


    public function draft_version_request_reference(
        array $params = []
    ): ?array {
        $this->db
            ->reset_query()
            ->select('id, reference')
            ->from('requests')
            ->where(
                'workflow_version_id',
                $params[0]
            )
            ->limit(1);

        return $this->first();
    }

    public function delete_draft_version(
        array $params = []
    ): bool {
        $this->db
            ->reset_query()
            ->where('id', $params[0]);

        return $this->written(
            $this->db->delete(
                'workflow_versions'
            )
        );
    }

    public function active_user_for_workflow(
        int $id
    ): ?array {
        $this->db
            ->reset_query()
            ->select('u.*')
            ->from('users u')
            ->join(
                'roles r',
                'r.id = u.role_id'
            )
            ->where('u.id', $id)
            ->where('u.active', 1)
            ->where('r.active', 1)
            ->limit(1);

        return $this->first();
    }


    public function previous_approved_step(
        array $params = []
    ): ?array {
        $this->db
            ->reset_query()
            ->from('workflow_steps')
            ->where('request_id', $params[0])
            ->where('id <', $params[1])
            ->where('status', 'completed')
            ->where('decision', 'approve')
            ->order_by('id', 'DESC')
            ->limit(1);

        return $this->first(true);
    }

    public function eligible_approvers(
        string $type,
        mixed $value,
        int $requester
    ): array {
        if (
            !in_array(
                $type,
                ['user', 'role'],
                true
            )
        ) {
            throw new Problem(
                'Invalid workflow assignment.',
                409
            );
        }

        $this->db
            ->reset_query()
            ->distinct()
            ->select('u.*')
            ->from('users u')
            ->join(
                'roles r',
                'r.id = u.role_id'
            )
            ->where('u.active', 1)
            ->where('r.active', 1)
            ->where('u.id !=', $requester);

        if ($type === 'user') {
            $this->db->where(
                'u.id',
                (int) $value
            );
        } else {
            $this->db->where(
                'r.id',
                (int) $value
            );
        }

        return array_map(
            static fn(array $user): array => [
                'id' => (int) $user['id'],
                'name' => Context::name($user),
                'position' =>
                    $user['position_title'],
            ],
            $this->results()
        );
    }
}
