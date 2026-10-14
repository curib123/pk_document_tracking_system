<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Match the already-authorized folder tree; keep document permissions and pagination authoritative. */
final class Folder_search
{
    private static function descendants(array $nodes, array $keys): array
    {
        $found = [];
        while ($keys) {
            $key = array_pop($keys);
            if (!isset($nodes[$key]) || isset($found[$key]))
                continue;
            $found[$key] = TRUE;
            foreach ($nodes[$key]['children'] ?? [] as $child)
                $keys[] = $child;
        }
        return $found;
    }

    public static function for_browser(array $browser, string $query, string $domain): array
    {
        $tree = $browser['tree'];
        $nodes = $tree['nodes'] ?? [];
        $matched = [];
        foreach ($nodes as $key => $node) {
            if (!empty($node['contextual_alias']))
                continue;
            if (
                $query !== '' && (mb_stripos((string) $node['name'], $query) !== FALSE ||
                    mb_stripos((string) ($node['meta'] ?? ''), $query) !== FALSE)
            )
                $matched[] = $key;
        }
        $matched = self::descendants($nodes, $matched);
        $scoped = !in_array($browser['folder'], ['', 'all'], TRUE);
        $scope = $scoped ? self::descendants($nodes, $browser['selected_folders'] ?? [$browser['folder']]) : $nodes;
        $matched = array_intersect_key($matched, $scope);
        $visible = $matched;
        foreach (array_keys($matched) as $key) {
            $seen = [];
            while (isset($nodes[$key]) && !isset($seen[$key])) {
                $seen[$key] = TRUE;
                $visible[$key] = TRUE;
                $key = (string) $nodes[$key]['parent'];
            }
        }
        $result = ['visible' => $visible, 'paths' => [], 'categories' => []];
        foreach ($tree['document_paths'] ?? [] as $path)
            if (isset($matched[$path['folder']]))
                $result['paths'][] = $path['fields'];
        if ($domain === 'softcopy') {
            foreach (array_keys($matched) as $key)
                if ($nodes[$key]['kind'] === 'category')
                    $result['categories'][] = (int) explode(':', $key)[1];
            sort($result['categories']);
            if ($scoped) {
                $result['scope_categories'] = [];
                foreach (array_keys($scope) as $key)
                    if ($nodes[$key]['kind'] === 'category')
                        $result['scope_categories'][] = (int) explode(':', $key)[1];
                sort($result['scope_categories']);
            }
        }
        return $result;
    }
}
