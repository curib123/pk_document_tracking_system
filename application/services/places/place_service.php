<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Server-side Place validation; all writes use native CI3 queries.
 * Never trust the parent hierarchy supplied by a browser form.
 */
class Place_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    private function active_row($table, $id)
    {
        if ($id <= 0) return NULL;
        return $this->ci->db->get_where($table, [
            'id' => (int)$id, 'active' => 1
        ])->row_array();
    }

    private function optional_id($post, $field)
    {
        $value = trim((string)($post[$field] ?? ''));
        if ($value === '') return NULL;
        if (!ctype_digit($value) || (int)$value <= 0) {
            throw new DomainException('Please select a valid '.str_replace('_id','',$field).'.');
        }
        return (int)$value;
    }

    private function required_text($post, $field, $limit)
    {
        $value = trim((string)($post[$field] ?? ''));
        if ($value === '' || mb_strlen($value) > $limit) {
            throw new DomainException(ucwords(str_replace('_', ' ', $field)).
                ' is required (maximum '.$limit.' characters).');
        }
        return $value;
    }

    private function valid_date($value)
    {
        $value = trim((string)$value);
        if ($value === '') return NULL;

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException('Archive date must be a valid calendar date.');
        }
        return $value;
    }

    /**
     * Find the true ancestor records for a Location.
     * A selected asset determines its specific and area; a selected
     * specific determines its area. Conflicting posted IDs are rejected.
     */
    private function prepare_location($post)
    {
        $areaId = $this->optional_id($post, 'area_id');
        $specificId = $this->optional_id($post, 'specific_id');
        $assetId = $this->optional_id($post, 'asset_id');

        if ($assetId) {
            $asset = $this->ci->db->select('b.id,b.specific_id,s.area_id')
                ->from('assets b')
                ->join('specifics s','s.id=b.specific_id')
                ->join('areas a','a.id=s.area_id')
                ->where('b.id',$assetId)
                ->where('b.active',1)->where('s.active',1)->where('a.active',1)
                ->get()->row_array();
            if (!$asset) throw new DomainException('Choose an active predefined asset.');
            if ($specificId && $specificId !== (int)$asset['specific_id']) {
                throw new DomainException('The asset belongs to another specific.');
            }
            if ($areaId && $areaId !== (int)$asset['area_id']) {
                throw new DomainException('The asset belongs to another area.');
            }
            $specificId = (int)$asset['specific_id'];
            $areaId = (int)$asset['area_id'];
        }

        if ($specificId) {
            $specific = $this->ci->db->select('s.id,s.area_id')
                ->from('specifics s')->join('areas a','a.id=s.area_id')
                ->where('s.id',$specificId)
                ->where('s.active',1)->where('a.active',1)
                ->get()->row_array();
            if (!$specific) throw new DomainException('Choose an active predefined specific.');
            if ($areaId && $areaId !== (int)$specific['area_id']) {
                throw new DomainException('The specific belongs to another area.');
            }
            $areaId = (int)$specific['area_id'];
        }

        if ($areaId && !$this->active_row('areas', $areaId)) {
            throw new DomainException('Choose an active predefined area.');
        }

        return [
            'area_id' => $areaId, 'specific_id' => $specificId,
            'asset_id' => $assetId
        ];
    }

    private function validate_parent_category($parentId, $currentId)
    {
        if (!$parentId) return;
        $seen = [];
        for ($id = $parentId; $id; ) {
            if ($id === $currentId || isset($seen[$id])) {
                throw new DomainException('A category cannot contain itself or a parent cycle.');
            }
            $seen[$id] = TRUE;
            $parent = $this->active_row('categories',$id);
            if (!$parent) throw new DomainException('Select an active parent category.');
            $id = (int)($parent['parent_id'] ?? 0);
        }
    }

    public function save($cfg, $post, $actorId)
    {
        if (!empty($cfg['read_only'])) {
            throw new DomainException('System sequences are read-only.');
        }

        $slug = (string)($cfg['slug'] ?? '');
        $id = $this->optional_id($post, 'id') ?: 0;
        $table = (string)($cfg['table'] ?? '');
        $tables = [
            'area' => 'areas', 'specific' => 'specifics', 'asset' => 'assets',
            'location' => 'locations', 'softcopy-categories' => 'categories'
        ];
        if (!isset($tables[$slug]) || $table !== $tables[$slug]) {
            throw new DomainException('Unsupported Places operation.');
        }

        $existing = $id ? $this->ci->db->get_where($table,['id'=>$id])->row_array() : NULL;
        if ($id && !$existing) throw new DomainException('Reference item not found.');

        $data = ['active' => !empty($post['active']) ? 1 : 0];

        if ($slug === 'area') {
            $data['name'] = $this->required_text($post,'name',150);
        } elseif ($slug === 'specific') {
            $data['name'] = $this->required_text($post,'name',150);
            $data['area_id'] = $this->optional_id($post,'area_id');
            if (!$this->active_row('areas',(int)$data['area_id'])) {
                throw new DomainException('Specific requires an active area.');
            }
        } elseif ($slug === 'asset') {
            $data['asset_number'] = $this->required_text($post,'asset_number',100);
            $data['specific_id'] = $this->optional_id($post,'specific_id');
            $specific = $this->ci->db->select('s.id')
                ->from('specifics s')->join('areas a','a.id=s.area_id')
                ->where('s.id',(int)$data['specific_id'])
                ->where('s.active',1)->where('a.active',1)->get()->row_array();
            if (!$specific) throw new DomainException('Asset requires an active specific and area.');
        } elseif ($slug === 'location') {
            $data['name'] = $this->required_text($post,'name',150);
            $data['code'] = $this->required_text($post,'code',100);
            $data = array_merge($data, $this->prepare_location($post));
            $data['archive_date'] = $this->valid_date($post['archive_date'] ?? '');
        } else {
            $data['name'] = $this->required_text($post,'name',150);
            $data['folder_name'] = $this->required_text($post,'folder_name',150);
            $description = trim((string)($post['description'] ?? ''));
            $data['description'] = $description === '' ? NULL : $description;
            $data['parent_id'] = $this->optional_id($post,'parent_id');
            $this->validate_parent_category($data['parent_id'], $id);
            if (!$id) $data['created_by'] = (int)$actorId;
        }

        // Show a helpful error rather than a database exception on unique codes.
        if ($slug === 'location') {
            $duplicate = $this->ci->db->select('id')->from('locations')
                ->where('code',$data['code'])->where('id !=',(int)$id)
                ->limit(1)->get()->row_array();
            if ($duplicate) throw new DomainException('Location code already exists.');
        }

        $this->ci->db->trans_begin();
        if ($id) {
            $this->ci->db->where('id',$id)->set('version','version+1',FALSE);
            $ok = $this->ci->db->update($table,$data);
        } else {
            $ok = $this->ci->db->insert($table,$data);
        }
        if (!$ok || $this->ci->db->trans_status() === FALSE) {
            $this->ci->db->trans_rollback();
            throw new DomainException('Could not save this reference. Check names and selected parents.');
        }
        $this->ci->db->trans_commit();
    }

    public function deactivate($cfg, $id)
    {
        if (!empty($cfg['read_only'])) {
            throw new DomainException('System sequences are read-only.');
        }
        if ($id <= 0) throw new DomainException('Select a valid reference.');
        $table = (string)($cfg['table'] ?? '');
        if (!in_array($table,['areas','specifics','assets','locations','categories'],TRUE)) {
            throw new DomainException('Unsupported Places operation.');
        }
        $record = $this->ci->db->get_where($table,['id'=>$id])->row_array();
        if (!$record) throw new DomainException('Reference item not found.');
        if (!(int)$record['active']) return;

        $this->ci->db->where('id',$id)
            ->set('active',0)
            ->set('version','version+1',FALSE);
        if (!$this->ci->db->update($table)) {
            throw new DomainException('Reference could not be deactivated.');
        }
    }
}
