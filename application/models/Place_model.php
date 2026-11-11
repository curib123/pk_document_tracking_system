<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Place_model extends CI_Model
{
    private $definitions = [
        'area' => ['table' => 'areas', 'label' => 'Areas', 'key' => 'name', 'fields' => ['name']],
        'specific' => ['table' => 'specifics', 'label' => 'Specifics', 'key' => 'name', 'fields' => ['name', 'area_id']],
        'asset' => ['table' => 'assets', 'label' => 'Assets', 'key' => 'asset_number', 'fields' => ['asset_number']],
        'location' => [
            'table' => 'locations',
            'label' => 'Locations',
            'key' => 'name',
            'fields' => ['name', 'sequence_number', 'area_id', 'specific_id', 'asset_id', 'archive_date']
        ],
        'softcopy-categories' => [
            'table' => 'categories',
            'label' => 'Softcopy Categories',
            'key' => 'name',
            'fields' => ['name', 'category_type', 'folder_name', 'code_pattern', 'description', 'parent_id']
        ],
        'external-categories' => [
            'table' => 'external_categories',
            'label' => 'External Categories',
            'key' => 'name',
            'fields' => ['name', 'folder_name', 'description', 'parent_id']
        ],
        'subsidiary' => [
            'table' => 'subsidiaries',
            'label' => 'Subsidiaries',
            'key' => 'name',
            'fields' => ['name']
        ],
    ];
    public function config($slug)
    {
        if (!isset($this->definitions[$slug]))
            show_404();
        $c = $this->definitions[$slug];
        $c['slug'] = $slug;
        $c['active'] = TRUE;
        $c['read_only'] = FALSE;
        return $c;
    }
    private function scope($c, $q, $status, $state = [])
    {
        $this->db->from($c['table'] . ' p');
        if ($q !== '') {
            $this->db->group_start()->like('p.' . $c['key'], $q);
            foreach (['folder_name', 'description'] as $field) {
                if (in_array($field, $c['fields'], TRUE))
                    $this->db->or_like('p.' . $field, $q);
            }
            $this->db->group_end();
        }
        $parents = [
            'specific' => 'area_id',
            'location' => 'area_id',
            'softcopy-categories' => 'parent_id',
            'external-categories' => 'parent_id'
        ];
        if (!empty($state['parent']) && $c['slug'] === 'asset') {
            // An Asset Number can belong to many Specifics.
            $this->db->where('p.id IN (SELECT asset_id FROM asset_specifics WHERE specific_id=' . (int) $state['parent'] . ')', NULL, FALSE);
        } elseif (!empty($state['parent']) && isset($parents[$c['slug']])) {
            $this->db->where('p.' . $parents[$c['slug']], (int) $state['parent']);
        }
        if ($c['active'] && $status !== '' && in_array($status, ['0', '1'], TRUE)) {
            $this->db->where('p.active', (int) $status);
        }
    }
    public function listing($c, $q, $status, $page, $limit, $state = [])
    {
        $this->scope($c, $q, $status, $state);
        $total = (int) $this->db->count_all_results();
        $page = min(max(1, $page), max(1, (int) ceil($total / $limit)));
        $this->scope($c, $q, $status, $state);
        $this->db->select('p.*');
        $joins = [
            'specific' => [['areas a', 'a.id=p.area_id', 'a.name AS area_name']],
            'location' => [
                ['areas a', 'a.id=p.area_id', 'a.name AS area_name'],
                ['specifics s', 's.id=p.specific_id', 's.name AS specific_name'],
                ['assets b', 'b.id=p.asset_id', 'b.asset_number AS asset_name']
            ],
            'softcopy-categories' => [['categories par', 'par.id=p.parent_id', 'par.name AS parent_name']],
            'external-categories' => [['external_categories par', 'par.id=p.parent_id', 'par.name AS parent_name']]
        ];
        foreach ($joins[$c['slug']] ?? [] as $join) {
            $this->db->join($join[0], $join[1], 'left')->select($join[2]);
        }
        if ($c['slug'] === 'asset') {
            $this->db->select('(SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") FROM asset_specifics x
                JOIN specifics s ON s.id=x.specific_id WHERE x.asset_id=p.id) AS specific_name,
                (SELECT GROUP_CONCAT(x.specific_id ORDER BY x.specific_id) FROM asset_specifics x
                WHERE x.asset_id=p.id) AS specific_ids', FALSE);
        }
        $sorts = array_intersect($c['fields'], ['name', 'sequence_number', 'asset_number', 'folder_name']);
        if ($c['active'])
            $sorts[] = 'active';
        $sort = in_array($state['sort'] ?? '', $sorts, TRUE) ? $state['sort'] : ($c['slug'] === 'location' ? 'sequence_number' : $c['key']);
        $dir = ($state['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $rows = $this->db->order_by('p.' . $sort, $dir)->order_by('p.' . $c['key'], 'ASC')
            ->limit($limit, ($page - 1) * $limit)->get()->result_array();
        return [$rows, $total, $page];
    }
    public function options($table)
    {
        $defs = [
            'areas' => 'name',
            'specifics' => 'name',
            'assets' => 'asset_number',
            'locations' => 'name',
            'categories' => 'name',
            'numbered_categories' => 'name',
            'external_categories' => 'name',
            'subsidiaries' => 'name'
        ];
        if (!isset($defs[$table]))
            throw new DomainException('Unknown option list.');

        // Location Upsert: preload the real Area -> Specific -> Asset relationships.
        // No artificial presets, extra tables, APIs or invented identifiers.
        if ($table === 'specifics') {
            return $this->db->select('s.id,s.name,s.area_id,a.name AS area_name')
                ->from('specifics s')
                ->join('areas a', 'a.id=s.area_id')
                ->where('s.active', 1)->where('a.active', 1)
                ->order_by('s.name')->order_by('s.id')->get()->result_array();
        }
        if ($table === 'assets') {
            // One row per Asset Number; the comma lists hold every active Specific and Area it is used under.
            return $this->db->select('b.id,b.asset_number AS name,
                GROUP_CONCAT(DISTINCT s.id ORDER BY s.id) AS specific_ids,
                GROUP_CONCAT(DISTINCT s.area_id ORDER BY s.area_id) AS area_ids', FALSE)
                ->from('assets b')->join('asset_specifics x', 'x.asset_id=b.id')
                ->join('specifics s', 's.id=x.specific_id')
                ->join('areas a', 'a.id=s.area_id')
                ->where('b.active', 1)->where('s.active', 1)->where('a.active', 1)
                ->group_by('b.id,b.asset_number')
                ->order_by('b.asset_number')->order_by('b.id')->get()->result_array();
        }
        if ($table === 'categories') {
            // All active folder and number-pattern categories remain available
            // for hierarchy/assignment selectors. Add full paths so folders
            // with identical names in different branches are distinguishable.
            $rows = $this->db->select('id,name,code_pattern,category_type,parent_id')->from('categories')
                ->where('active', 1)->order_by('name')->get()->result_array();
            $byId = [];
            foreach ($rows as $row)
                $byId[(int) $row['id']] = $row;
            foreach ($rows as &$row) {
                $parts = [(string) $row['name']];
                $parent = (int) ($row['parent_id'] ?? 0);
                $seen = [(int) $row['id'] => TRUE];
                while ($parent && !isset($seen[$parent]) && isset($byId[$parent]) && count($seen) < 32) {
                    $seen[$parent] = TRUE;
                    array_unshift($parts, (string) $byId[$parent]['name']);
                    $parent = (int) ($byId[$parent]['parent_id'] ?? 0);
                }
                $row['display_path'] = implode(' / ', $parts);
            }
            unset($row);
            return $rows;
        }
        if ($table === 'numbered_categories') {
            // Creation only: a folder-only category cannot allocate a Document No.
            return $this->db->select('id,name,code_pattern,parent_id')->from('categories')
                ->where('active', 1)->where('category_type', 'pattern')
                ->order_by('name')->get()->result_array();
        }
        if ($table === 'external_categories') {
            return $this->db->select('id,name')->from('external_categories')
                ->where('active', 1)->order_by('name')->get()->result_array();
        }
        return $this->db->select('id,' . $defs[$table] . ' AS name')->from($table)
            ->where('active', 1)->order_by($defs[$table])->get()->result_array();
    }
    public function find($c, $id)
    {
        return $this->db->get_where($c['table'], ['id' => (int) $id])->row_array();
    }
}
