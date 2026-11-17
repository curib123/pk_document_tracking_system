<?php
defined('BASEPATH') OR exit('No direct script access allowed');

final class Scanned_category_policy
{
    /** The nearest allocated folder controls its branch; no allocation means shared. */
    public static function usable_ids(array $categories, array $allocations, int $user): array
    {
        $byId = array_column($categories, NULL, 'id');
        $assigned = [];
        foreach ($allocations as $row) $assigned[(int) $row['category_id']][(int) $row['user_id']] = TRUE;
        $allowed = [];
        foreach ($categories as $row) {
            $at = (int) $row['id']; $seen = [];
            while ($at && isset($byId[$at]) && !isset($seen[$at]) && count($seen) < 32) {
                $seen[$at] = TRUE;
                if (isset($assigned[$at])) {
                    if (isset($assigned[$at][$user])) $allowed[] = (int) $row['id'];
                    continue 2;
                }
                $at = (int) $byId[$at]['parent_id'];
            }
            if (!$at) $allowed[] = (int) $row['id'];
        }
        return $allowed;
    }
}
