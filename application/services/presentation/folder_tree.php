<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Build a bounded, cycle-safe presentation tree without recursive database queries. */
class Folder_tree
{
    /** Selected folder and descendants, in visible order, with cycle protection. */
    public static function branch(array $tree, string $key): array
    {
        if (!isset($tree['nodes'][$key]))
            throw new DomainException('Choose an active category.');
        $selected = [];
        $pending = [$key];
        while ($pending) {
            $at = array_shift($pending);
            if (isset($selected[$at]) || !isset($tree['nodes'][$at])) continue;
            $selected[$at] = TRUE;
            foreach ($tree['nodes'][$at]['children'] as $child) $pending[] = $child;
        }
        return array_keys($selected);
    }

    public static function build(array $rows)
    {
        $all = [];
        $valid = [];
        foreach ($rows as $row)
            $all[$row['key']] = $row + ['meta' => '', 'children' => []];
        foreach ($all as $key => $node) {
            $seen = [];
            $at = $key;
            while ($at !== '' && isset($all[$at]) && !isset($seen[$at]) && count($seen) < 32) {
                $seen[$at] = TRUE;
                $at = $all[$at]['parent'];
            }
            if ($at === '')
                $valid[$key] = $node;
        }
        $roots = [];
        foreach ($valid as $key => $node) {
            if ($node['parent'] === '')
                $roots[] = $key;
            elseif (isset($valid[$node['parent']]))
                $valid[$node['parent']]['children'][] = $key;
        }
        $compare = static function ($a, $b) use (&$valid) {
            $cmp = strnatcasecmp($valid[$a]['name'], $valid[$b]['name']);
            return $cmp ?: strcmp($a, $b);
        };
        usort($roots, $compare);
        foreach ($valid as &$node)
            usort($node['children'], $compare);
        unset($node);
        return ['roots' => $roots, 'nodes' => $valid, 'unavailable' => count($all) - count($valid)];
    }
}
