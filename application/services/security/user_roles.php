<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Role IDs come from the server-loaded identity, never an action form. */
final class User_roles
{
    public static function ids(array $user): array
    {
        $ids = array_key_exists('role_ids', $user) ? (array) $user['role_ids'] : [(int) ($user['role_id'] ?? 0)];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
    public static function matches(array $user, int $roleId): bool
    {
        return $roleId > 0 && in_array($roleId, self::ids($user), TRUE);
    }
    public static function available($db): bool
    {
        return method_exists($db, 'table_exists') && $db->table_exists('user_roles') === TRUE;
    }
    public static function assigned($db, int $userId, int $primaryId, bool $activeOnly = TRUE): array
    {
        $ids = [$primaryId];
        if (self::available($db)) {
            $rows = $db->select('role_id')->get_where('user_roles', ['user_id' => $userId])->result_array();
            $ids = array_merge($ids, array_column($rows, 'role_id'));
        }
        $db->select('id,name,active')->from('roles')->where_in('id', array_unique(array_map('intval', $ids)));
        if ($activeOnly)
            $db->where('active', 1);
        return $db->order_by('id')->get()->result_array();
    }
    public static function member_predicate($db, $roleId, $alias = 'u'): string
    {
        $role = (string) $roleId;
        if (
            !preg_match('/^(?:[0-9]+|[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*)$/i', $role) ||
            !preg_match('/^[a-z][a-z0-9_]*$/i', $alias)
        )
            throw new InvalidArgumentException('Invalid role scope.');
        $primary = "{$alias}.role_id={$role}";
        if (!self::available($db))
            return $primary;
        return "({$primary} OR EXISTS (SELECT 1 FROM user_roles role_member WHERE role_member.user_id={$alias}.id AND role_member.role_id={$role}))";
    }
    public static function has_member($db, int $roleId): bool
    {
        return $db->from('users u')->where('u.active', 1)
            ->where(self::member_predicate($db, $roleId), NULL, FALSE)->count_all_results() > 0;
    }
}
