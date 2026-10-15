<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Filter and paginate the filesystem catalog using the shared GET state. */
final class Backup_listing
{
    public static function page(array $items, array $state): array
    {
        $query = mb_strtolower($state['q']);
        $items = array_values(array_filter($items, static function ($item) use ($state, $query) {
            return ($state['type'] === '' || $item['kind'] === $state['type']) &&
                ($query === '' || mb_strpos(mb_strtolower($item['name'] . ' ' . $item['kind']), $query) !== FALSE);
        }));
        $sort = in_array($state['sort'], ['name', 'kind', 'created_at', 'size'], TRUE) ? $state['sort'] : 'created_at';
        $direction = $state['dir'] === 'ASC' ? 1 : -1;
        usort($items, static function ($left, $right) use ($sort, $direction) {
            $comparison = in_array($sort, ['size', 'created_at'], TRUE)
                ? $left[$sort] <=> $right[$sort] : strnatcasecmp($left[$sort], $right[$sort]);
            return ($comparison ?: strcmp($left['name'], $right['name'])) * $direction;
        });
        $total = count($items);
        $page = min(max(1, $state['page']), max(1, (int) ceil($total / $state['limit'])));
        return ['rows' => array_slice($items, ($page - 1) * $state['limit'], $state['limit']),
            'total' => $total, 'page' => $page, 'sort' => $sort];
    }
}
