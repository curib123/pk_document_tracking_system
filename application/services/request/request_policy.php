<?php
final class Request_policy
{
    public const REVISION_TEMPLATE = 'softcopy_revision_template';
    public const REVISION_TEMPLATE_ACTION = 'request_revision_template';
    public const REVISION_TEMPLATE_LABEL = 'Request Template for Revision';

    public static function deletable(?array $request, int $actor): bool
    {
        return $request && (int) $request['requested_by'] === $actor &&
            $request['status'] === 'cancelled' && empty($request['submitted_at']) &&
            empty($request['workflow_version_id']) && empty($request['snapshot']) &&
            empty($request['current_node']) && empty($request['result']);
    }

    public static function module(string $type): string
    {
        $modules = [
            'softcopy_create' => 'softcopy',
            'softcopy_revise' => 'softcopy',
            'softcopy_cancel' => 'softcopy',
            self::REVISION_TEMPLATE => 'softcopy',
            'scanned_create' => 'scanned_documents',
            'scanned_update' => 'scanned_documents',
            'scanned_delete' => 'scanned_documents',
            'scanned_category_create' => 'scanned_categories',
            'external_create' => 'external_documents',
            'external_revise' => 'external_documents',
            'hardcopy_create' => 'hardcopy',
            'hardcopy_update' => 'hardcopy',
            'transfer' => 'transfer',
            'assignment' => 'assignment',
            'access' => 'access',
            'disposal' => 'disposal'
        ];
        if (!isset($modules[$type]))
            throw new DomainException('Unsupported request type.');
        return $modules[$type];
    }

    public static function revision_template_dco_role($db, array $graph): int
    {
        $role = $db->select('id')->get_where('roles', [
            'name' => 'Document Control Officer', 'active' => 1
        ])->row_array();
        $roleId = (int) ($role['id'] ?? 0);
        $steps = $graph['steps'] ?? [];
        $approver = $steps[count($steps) - 1]['approver'] ?? [];
        if (!$roleId || ($approver['type'] ?? '') !== 'role' || (int) ($approver['value'] ?? 0) !== $roleId)
            throw new DomainException('Template requests must end with the Document Control Officer role.');
        return $roleId;
    }

    public static function validate_revision_template_expiry($value): string
    {
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) ||
            !($date = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) ||
            $date->format('Y-m-d') !== $value || self::revision_template_expired($value)) {
            throw new DomainException('Choose a valid download expiry date of today or later.');
        }
        return $value;
    }

    public static function revision_template_expired(?string $expiresOn): bool
    {
        // Older approvals have no expiry. New approvals include the whole selected day.
        return $expiresOn !== NULL && $expiresOn < date('Y-m-d');
    }

    public static function editable(?array $request, int $actor, string $type, array $post): void
    {
        if (
            !$request || (int) $request['requested_by'] !== $actor || $request['type'] !== $type ||
            !in_array($request['status'], ['draft', 'returned'], TRUE)
        )
            throw new DomainException('Only your matching draft or returned request can be edited.');
        if (isset($post['version']) && $post['version'] !== '' && (int) $post['version'] !== (int) $request['version'])
            throw new DomainException('This request changed in another session. Refresh before editing.');
    }
}
