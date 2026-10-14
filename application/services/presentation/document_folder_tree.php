<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure presentation helper. No new API, queries or permission bypass.
 * Receives ONLY rows already authorized and paginated by Document_model.
 * Active Places/category relationships come from Folder_model::tree().
 */
final class Document_folder_tree
{
    public static function folder_key(array $row, array $nodes, string $domain): string
    {
        if ($domain === 'hardcopy' && !empty($nodes['location:' . (int) ($row['location_id'] ?? 0)]['contextual_alias'])) {
            require_once __DIR__ . '/hardcopy_folder_tree.php';
            return Hardcopy_folder_tree::folder_key($row, $nodes);
        }
        $levels = $domain === 'softcopy'
            ? ['category' => 'category_id']
            : [
                'location' => 'location_id',
                'asset' => 'asset_id',
                'specific' => 'specific_id',
                'area' => 'area_id'
            ];
        foreach ($levels as $kind => $field) {
            $id = (int) ($row[$field] ?? 0);
            $key = $kind . ':' . $id;
            if ($id > 0 && isset($nodes[$key]))
                return $key;
        }
        return 'unfiled';
    }

    public static function distribute(array $rows, array $tree, string $domain): array
    {
        $byFolder = ['unfiled' => []];
        $counts = [];
        $nodes = $tree['nodes'] ?? [];
        foreach ($rows as $row) {
            $key = (string) ($row['folder_key'] ?? '');
            if ($key === '' || ($key !== 'unfiled' && !isset($nodes[$key]))) {
                $key = self::folder_key($row['record_path'] ?? [], $nodes, $domain);
            }
            $byFolder[$key][] = $row;
            $at = $key;
            $seen = [];
            while (
                $at !== 'unfiled' && $at !== '' && isset($nodes[$at]) && !isset($seen[$at])
                && count($seen) < 32
            ) {
                $seen[$at] = TRUE;
                $counts[$at] = ($counts[$at] ?? 0) + 1;
                $at = (string) $nodes[$at]['parent'];
            }
        }
        return ['by_folder' => $byFolder, 'counts' => $counts];
    }
}
