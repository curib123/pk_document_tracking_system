<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared browser-table state. Every value is allowlisted before it reaches a query.
 * Controllers count first, then clamp the page, then fetch a bounded SQL result.
 */
class Table_pager
{
    public function read($input, $sorts, $defaultSort, $statuses = [], $extraFilters = [], $defaultDir = 'asc')
    {
        // Malformed array-style query params are ignored, not coerced into warnings.
        foreach (['limit', 'page', 'sort', 'dir', 'q', 'status'] as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) unset($input[$key]);
        }
        foreach (array_keys($extraFilters) as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) unset($input[$key]);
        }

        $limit = (int) ($input['limit'] ?? 10);
        if (!in_array($limit, [10, 25, 50, 100], TRUE)) $limit = 10;

        $sort = (string) ($input['sort'] ?? $defaultSort);
        if (!in_array($sort, $sorts, TRUE)) $sort = $defaultSort;
        $dir = strtolower((string) ($input['dir'] ?? $defaultDir));
        if (!in_array($dir, ['asc', 'desc'], TRUE)) $dir = 'asc';

        $status = (string) ($input['status'] ?? '');
        if (!in_array($status, array_merge([''], $statuses), TRUE)) $status = '';
        $state = [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 120),
            'status' => $status, 'sort' => $sort, 'dir' => $dir,
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'limit' => $limit,
            'filters' => ['status' => $status]
        ];
        foreach ($extraFilters as $key => $allowed) {
            $value = (string) ($input[$key] ?? '');
            if (!in_array($value, array_merge([''], $allowed), TRUE)) $value = '';
            $state['filters'][$key] = $value;
        }
        return $state;
    }

    public function clamp(&$state, $total)
    {
        $pages = max(1, (int) ceil((int) $total / $state['limit']));
        $state['page'] = min($state['page'], $pages);
        return ($state['page'] - 1) * $state['limit'];
    }
}
