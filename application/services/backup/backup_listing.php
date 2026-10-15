<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Filter and paginate the filesystem catalog using the shared GET state. */
final class Backup_listing
{
    public static function page(array $items, array $state): array
    {
        $query = mb_strtolower(trim((string) ($state['q'] ?? '')));
        $type = (string) ($state['type'] ?? '');
        $items = array_values(array_filter($items, static function ($item) use ($type, $query) {
            return ($type === '' || ($item['kind'] ?? '') === $type) &&
                ($query === '' || mb_strpos(mb_strtolower((string) ($item['name'] ?? '') . ' ' . (string) ($item['kind'] ?? '')), $query) !== FALSE);
        }));
        $sort = in_array(($state['sort'] ?? ''), ['name', 'kind', 'created_at', 'size'], TRUE) ? $state['sort'] : 'created_at';
        $direction = ($state['dir'] ?? 'DESC') === 'ASC' ? 1 : -1;
        usort($items, static function ($left, $right) use ($sort, $direction) {
            $comparison = in_array($sort, ['size', 'created_at'], TRUE)
                ? ($left[$sort] ?? 0) <=> ($right[$sort] ?? 0) : strnatcasecmp((string) ($left[$sort] ?? ''), (string) ($right[$sort] ?? ''));
            return ($comparison ?: strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''))) * $direction;
        });
        $total = count($items);
        $limit = max(1, min(100, (int) ($state['limit'] ?? 10)));
        $page = min(max(1, (int) ($state['page'] ?? 1)), max(1, (int) ceil($total / $limit)));
        return ['rows' => array_slice($items, ($page - 1) * $limit, $limit),
            'total' => $total, 'page' => $page, 'sort' => $sort];
    }
}
