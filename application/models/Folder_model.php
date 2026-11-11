<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Existing relational Places/category folders, not filesystem paths or new tables. */
class Folder_model extends CI_Model
{
    private $trees = [];
    private $levels = [
        'area' => ['table' => 'areas', 'title' => 'name', 'parent' => NULL],
        'specific' => ['table' => 'specifics', 'title' => 'name', 'parent' => 'area'],
        'asset' => ['table' => 'assets', 'title' => 'asset_number', 'parent' => NULL],
        'location' => ['table' => 'locations', 'title' => 'name', 'parent' => NULL]
    ];

    public function tree($domain)
    {
        if (!in_array($domain, ['hardcopy', 'softcopy', 'external'], TRUE))
            show_404();
        if (isset($this->trees[$domain]))
            return $this->trees[$domain];
        require_once APPPATH . 'services/presentation/folder_tree.php';
        $nodes = [];
        if (in_array($domain, ['softcopy', 'external'], TRUE)) {
            $categoryTable = $domain === 'softcopy' ? 'categories' : 'external_categories';
            $rows = $this->db->select('id,parent_id,name,folder_name')
                ->get_where($categoryTable, ['active' => 1])->result_array();
            foreach ($rows as $row)
                $nodes[] = [
                    'key' => 'category:' . $row['id'],
                    'parent' => $row['parent_id'] ? 'category:' . $row['parent_id'] : '',
                    'name' => $row['name'],
                    'meta' => $row['folder_name'],
                    'kind' => 'category'
                ];
        } else {
            // True document repository hierarchy:
            // Area > Specific > Asset > Location > Documents.
            // Optional/missing Asset uses Specific or Area as fallback so that
            // legacy and incomplete Places never disappear.
            // An Asset Number can belong to many Specifics. It appears under each of them; the
            // folder key is asset:<id> for one Specific and asset:<id>:<specific id> for several.
            $assetLinks = [];
            $links = $this->db->select('x.asset_id,x.specific_id')->from('asset_specifics x')
                ->join('specifics s', 's.id=x.specific_id')->where('s.active', 1)
                ->order_by('x.specific_id')->get()->result_array();
            foreach ($links as $link)
                $assetLinks[(int) $link['asset_id']][] = (int) $link['specific_id'];
            $assetKey = static function ($assetId, $specificId) use ($assetLinks) {
                $linked = $assetLinks[$assetId] ?? [];
                if (!$linked)
                    return '';
                if (count($linked) === 1)
                    return 'asset:' . $assetId;
                return 'asset:' . $assetId . ':' . (in_array($specificId, $linked, TRUE) ? $specificId : $linked[0]);
            };
            $specificAreas = [];
            foreach ($this->levels as $kind => $definition) {
                $fields = 'id,' . $definition['title'];
                if ($definition['parent'])
                    $fields .= ',' . $definition['parent'] . '_id';
                if ($kind === 'location')
                    $fields .= ',area_id,specific_id,asset_id,sequence_number';
                $rows = $this->db->select($fields)->get_where($definition['table'], ['active' => 1])->result_array();
                foreach ($rows as $row) {
                    if ($kind === 'specific')
                        $specificAreas[(int) $row['id']] = (int) $row['area_id'];
                    if ($kind === 'asset') {
                        $linked = $assetLinks[(int) $row['id']] ?? [];
                        foreach ($linked as $specificId)
                            $nodes[] = [
                                'key' => $assetKey((int) $row['id'], $specificId),
                                'parent' => 'specific:' . $specificId,
                                'name' => $row[$definition['title']],
                                'meta' => ucfirst($kind),
                                'kind' => $kind
                            ];
                        continue;
                    }

                    $parent = $definition['parent'];
                    $parentId = $parent ? (int) $row[$parent . '_id'] : 0;
                    $parentKey = $parent ? $parent . ':' . $parentId : '';
                    if ($kind === 'location') {
                        $assetId = (int) ($row['asset_id'] ?? 0);
                        $specificId = (int) ($row['specific_id'] ?? 0);
                        $areaId = (int) ($row['area_id'] ?? 0);
                        $assetNode = $assetId ? $assetKey($assetId, $specificId) : '';
                        if ($assetNode !== '') {
                            $parentKey = $assetNode;
                        } elseif ($specificId && isset($specificAreas[$specificId])) {
                            $parentKey = 'specific:' . $specificId;
                        } elseif ($areaId || ($specificId && isset($specificAreas[$specificId]))) {
                            $parentKey = 'area:' . ($areaId ?: $specificAreas[$specificId]);
                        } else {
                            $parentKey = '';
                        }
                    }
                    $nodes[] = [
                        'key' => $kind . ':' . $row['id'],
                        'parent' => $parentKey,
                        'name' => $row[$definition['title']],
                        'meta' => ucfirst($kind),
                        'kind' => $kind
                    ];
                }
            }
        }
        return $this->trees[$domain] = Folder_tree::build($nodes);
    }

    public function browse($domain, $folder = '', array $tree = NULL)
    {
        $tree = $tree ?? $this->tree($domain);
        // Virtual, permission-scoped directory for records with any/missing
        // folder assignment. It does not add a new table or bypass document scope.
        if ($folder === 'all')
            return [
                'folder' => 'all',
                'level' => 'all',
                'breadcrumbs' => [
                    ['name' => 'Folders', 'folder' => ''],
                    ['name' => 'All Documents', 'folder' => 'all']
                ],
                'children' => [],
                'filter' => NULL,
                'tree' => $tree
            ];
        if (
            $folder !== '' && (!preg_match('/^(?:(area|specific|asset|location|category):[1-9][0-9]*|asset:[1-9][0-9]*:[1-9][0-9]*|location:[1-9][0-9]*:(area|specific|asset):[1-9][0-9]*)$/', $folder) ||
                !isset($tree['nodes'][$folder]))
        )
            show_404();
        $result = [
            'folder' => $folder,
            'level' => 'root',
            'breadcrumbs' => [['name' => 'All Documents', 'folder' => '']],
            'children' => [],
            'filter' => NULL,
            'tree' => $tree
        ];
        $children = $tree['roots'];
        if ($folder !== '') {
            $node = $tree['nodes'][$folder];
            $result['level'] = $node['kind'];
            $result['label'] = $node['name'];
            $parts = explode(':', $folder);
            $result['filter'] = [$node['kind'] . '_id' => (int) $parts[1]];
            // An asset folder under one of several Specifics lists only that Specific's records.
            if ($node['kind'] === 'asset' && isset($parts[2]))
                $result['filter']['specific_id'] = (int) $parts[2];
            $children = $node['children'];
            $chain = [];
            $at = $folder;
            if (!empty($node['contextual_alias'])) {
                $result['selected_folders'] = array_keys(array_filter(
                    $tree['nodes'],
                    static function ($candidate) use ($folder) {
                        return strpos($candidate['key'], $folder . ':') === 0;
                    }
                ));
                $at = count($result['selected_folders']) === 1 ? $result['selected_folders'][0] : $folder;
                if ($at === $folder)
                    $chain = [['name' => $node['name'], 'folder' => $folder]];
                if ($at === $folder)
                    $at = '';
            }
            while ($at !== '') {
                $ancestor = $tree['nodes'][$at];
                array_unshift($chain, ['name' => $ancestor['name'], 'folder' => $at]);
                $at = $ancestor['parent'];
            }
            $result['breadcrumbs'] = array_merge($result['breadcrumbs'], $chain);
            if (isset($tree['document_paths']) && empty($node['contextual_alias'])) {
                $result['document_paths'] = [];
                foreach ($tree['document_paths'] as $path) {
                    $at = $path['folder'];
                    $seen = [];
                    while (isset($tree['nodes'][$at]) && !isset($seen[$at]) && count($seen) < 32) {
                        if ($at === $folder) {
                            $result['document_paths'][] = $path['fields'];
                            break;
                        }
                        $seen[$at] = TRUE;
                        $at = (string) $tree['nodes'][$at]['parent'];
                    }
                }
            }
        }
        foreach ($children as $key) {
            $node = $tree['nodes'][$key];
            $result['children'][] = ['folder' => $key, 'name' => $node['name'], 'meta' => $node['meta'], 'kind' => $node['kind']];
        }
        return $result;
    }

    public function scope_documents($domain, $browser, $alias = 'd')
    {
        if ($domain === 'softcopy' && !empty($browser['filter']['category_id']) &&
            !empty($browser['tree']['nodes'])) {
            // A folder-only category is a parent of nested folders and numbered
            // categories. Browsing it includes descendants instead of showing
            // an empty folder simply because documents live in child categories.
            require_once APPPATH . 'services/presentation/folder_tree.php';
            $folder = 'category:' . (int) $browser['filter']['category_id'];
            $keys = Folder_tree::branch($browser['tree'], $folder);
            $ids = array_map(static function ($key) {
                return (int) substr($key, strlen('category:'));
            }, $keys);
            $this->db->where_in($alias . '.category_id', $ids);
            return;
        }
        if ($domain === 'hardcopy' && array_key_exists('document_paths', $browser)) {
            if (!$browser['document_paths']) {
                $this->db->where('1 = 0');
                return;
            }
            $this->db->group_start();
            foreach ($browser['document_paths'] as $index => $path) {
                if ($index === 0)
                    $this->db->group_start();
                else
                    $this->db->or_group_start();
                foreach (['area_id', 'specific_id', 'asset_id', 'location_id'] as $column)
                    $this->db->where($alias . '.' . $column, $path[$column]);
                $this->db->group_end();
            }
            $this->db->group_end();
            return;
        }
        if (!empty($browser['filter'])) {
            foreach ($browser['filter'] as $column => $value)
                $this->db->where($alias . '.' . $column, (int) $value);
        }
    }
}
