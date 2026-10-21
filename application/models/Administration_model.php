<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'services/security/user_roles.php';
class Administration_model extends CI_Model
{
    private function count_and_page($table, $select, $joins, $filter, $q, $searchFields, $page, $limit, $sort, $direction = 'ASC')
    {
        $apply = function () use ($table, $joins, $filter, $q, $searchFields) {
            $this->db->from($table);
            foreach ($joins as $join)
                $this->db->join($join[0], $join[1], $join[2] ?? 'inner');
            foreach ($filter as $col => $val)
                $this->db->where($col, $val, $val !== NULL);
            if ($q !== '') {
                $this->db->group_start();
                foreach ($searchFields as $i => $field) {
                    if ($i === 0)
                        $this->db->like($field, $q);
                    else
                        $this->db->or_like($field, $q);
                }
                $this->db->group_end();
            }
        };
        $apply();
        $total = (int) $this->db->count_all_results();
        $page = min(max(1, $page), max(1, (int) ceil($total / $limit)));
        $apply();
        $rows = $this->db->select($select, FALSE)->order_by($sort, $direction)
            ->limit($limit, ($page - 1) * $limit)->get()->result_array();
        return [$rows, $total, $page];
    }
    public function users($q, $status, $page, $limit, $state = [])
    {
        $sorts = [
            'username' => 'u.username',
            'name' => 'u.first_name',
            'position' => 'u.position_title',
            'role' => 'r.name',
            'active' => 'u.active'
        ];
        $filter = in_array($status, ['0', '1'], TRUE) ? ['u.active' => (int) $status] : [];
        if (!empty($state['role']))
            $filter[User_roles::member_predicate($this->db, (int) $state['role'])] = NULL;
        $result = $this->count_and_page(
            'users u',
            'u.id,u.username,u.first_name,u.middle_name,u.last_name,u.position_title,u.role_id,
             u.leader_id,u.active,u.require_password_change,u.version,
             r.name AS role_name,
             CONCAT_WS(" ",l.first_name,l.last_name) AS leader_name',
            [['roles r', 'r.id=u.role_id'], ['users l', 'l.id=u.leader_id', 'left']],
            $filter,
            $q,
            ['u.username', 'u.first_name', 'u.last_name', 'u.position_title', 'r.name'],
            $page,
            $limit,
            $sorts[$state['sort'] ?? ''] ?? 'u.username',
            ($state['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC'
        );
        $assigned = [];
        if ($result[0] && User_roles::available($this->db)) {
            $rows = $this->db->select('ur.user_id,r.id,r.name')->from('user_roles ur')
                ->join('roles r', 'r.id=ur.role_id')->where_in('ur.user_id', array_column($result[0], 'id'))
                ->order_by('r.id')->get()->result_array();
            foreach ($rows as $row)
                $assigned[(int) $row['user_id']][(int) $row['id']] = $row['name'];
        }
        foreach ($result[0] as &$row) {
            $roles = $assigned[(int) $row['id']] ?? [];
            $roles[(int) $row['role_id']] = $row['role_name'];
            ksort($roles, SORT_NUMERIC);
            $row['role_ids'] = array_keys($roles);
            $row['roles_label'] = implode(' · ', array_values($roles));
            $row['has_administrator'] = count(array_filter($roles, static function ($name) {
                return strcasecmp($name, 'Administrator') === 0;
            })) > 0;
        }
        unset($row);
        return $result;
    }
    public function roles($q, $status, $page, $limit, $state = [])
    {
        $result = $this->count_and_page(
            'roles r',
            'r.*',
            [],
            in_array($status, ['0', '1'], TRUE) ? ['r.active' => (int) $status] : [],
            $q,
            ['r.name'],
            $page,
            $limit,
            ($state['sort'] ?? '') === 'active' ? 'r.active' : 'r.name',
            ($state['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC'
        );
        require_once APPPATH . 'services/administration/role_usage.php';
        $usage = Role_usage::for_roles($this->db, array_column($result[0], 'id'));
        foreach ($result[0] as &$row)
            $row = array_merge($row, $usage[(int) $row['id']]);
        unset($row);
        return $result;
    }
    public function workflows($q, $status, $page, $limit, $state = [])
    {
        $filter = in_array($status, ['0', '1'], TRUE) ? ['w.active' => (int) $status] : [];
        if (!empty($state['type']))
            $filter['w.request_type'] = $state['type'];
        if (in_array($state['publication'] ?? '', ['draft', 'published'], TRUE)) {
            $publication = $this->db->escape($state['publication']);
            $filter["EXISTS (SELECT 1 FROM workflow_versions pv WHERE pv.workflow_id=w.id AND pv.status=$publication)"] = NULL;
        }
        $sorts = ['name' => 'w.name', 'type' => 'w.request_type', 'active' => 'w.active'];
        $rows = $this->count_and_page(
            'workflows w',
            'w.id,w.workflow_key,w.name,w.description,w.request_type,w.active,w.version',
            [],
            $filter,
            $q,
            ['w.name', 'w.request_type'],
            $page,
            $limit,
            $sorts[$state['sort'] ?? ''] ?? 'w.name',
            ($state['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC'
        );
        $ids = array_column($rows[0], 'id');
        if ($ids) {
            $versions = $this->db->from('workflow_versions')->where_in('workflow_id', $ids)
                ->order_by('version_number', 'DESC')->order_by('id', 'DESC')->get()->result_array();
            $byWorkflow = [];
            foreach ($versions as $version)
                $byWorkflow[$version['workflow_id']][] = $version;
            foreach ($rows[0] as &$workflow) {
                $workflow['versions'] = $byWorkflow[$workflow['id']] ?? [];
                $latest = $workflow['versions'][0] ?? [];
                $workflow['latest_version_id'] = $latest['id'] ?? NULL;
                $workflow['version_number'] = $latest['version_number'] ?? 0;
                $workflow['version_status'] = $latest['status'] ?? 'not configured';
                $workflow['is_default'] = $latest['is_default'] ?? 0;
                $workflow['graph'] = $latest['graph'] ?? '{"steps":[]}';
            }
            unset($workflow);
        }
        return $rows;
    }
    public function document_assignments($domain, $browser, $state = [])
    {
        // Flat assignment records are scoped by the selected source folder.
        // No separate assignment/location table is introduced.
        $this->load->model('Folder_model');
        if ($domain === 'softcopy') {
            $this->db->select("'softcopy' AS domain,d.id AS document_id,
                a.user_id AS recipient_id,d.document_number AS code,
                d.title,CONCAT_WS(' ',u.first_name,u.last_name) AS assignee,
                a.assigned_at AS assigned_date", FALSE)
                ->from('assignments a')
                ->join('softcopy_documents d', 'd.id=a.softcopy_id')
                ->join('users u', 'u.id=a.user_id')
                ->where('a.active', 1)->where('d.status', 'active');
        } else {
            $this->db->select("'hardcopy' AS domain,d.id AS document_id,
                d.holder_id AS recipient_id,d.sequence_number AS code,
                d.title,CONCAT_WS(' ',u.first_name,u.last_name) AS assignee,
                d.updated_at AS assigned_date", FALSE)
                ->from('hardcopy_documents d')
                ->join('users u', 'u.id=d.holder_id')
                ->where('d.status', 'active');
        }
        $this->Folder_model->scope_documents($domain, $browser, 'd');
        if (!empty($state['q']))
            $this->db->group_start()->like('d.title', $state['q'])
                ->or_like($domain === 'softcopy' ? 'd.document_number' : 'd.sequence_number', $state['q'])
                ->or_like('u.first_name', $state['q'])->or_like('u.last_name', $state['q'])
                ->or_like("CONCAT_WS(' ',u.first_name,u.last_name)", $state['q'])->group_end();
        if (!empty($state['owner']))
            $this->db->where('u.id', (int) $state['owner']);
        $query = $this->db->get_compiled_select();
        $total = (int) $this->db->query('SELECT COUNT(*) AS total FROM (' . $query . ') scoped_assignments')->row()->total;
        $limit = in_array((int) ($state['limit'] ?? 10), [10, 25, 50, 100], TRUE) ? (int) ($state['limit'] ?? 10) : 10;
        $page = min(max(1, (int) ($state['page'] ?? 1)), max(1, (int) ceil($total / $limit)));
        $sort = in_array($state['sort'] ?? '', ['title', 'code', 'assignee', 'assigned_date'], TRUE) ? $state['sort'] : 'assigned_date';
        $direction = ($state['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $rows = $this->db->query('SELECT * FROM (' . $query . ') scoped_assignments ORDER BY ' . $sort . ' ' . $direction .
            ',document_id ' . $direction . ',recipient_id ' . $direction . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit))->result_array();
        return [$rows, $total, $page];
    }

    /** Current assignments across every folder; fetched only when a user's modal opens. */
    public function user_assigned_documents($userId)
    {
        $hardcopy = $this->db->select('d.id,d.title,d.sequence_number AS code,l.name AS location_name,l.sequence_number AS location_sequence')
            ->from('hardcopy_documents d')->join('locations l', 'l.id=d.location_id', 'left')
            ->where('d.holder_id', (int) $userId)
            ->where('d.status', 'active')->order_by('d.title')->order_by('d.id')->get()->result_array();
        $softcopy = $this->db->select('d.id,d.title,d.document_number AS code,a.assigned_at')
            ->from('assignments a')->join('softcopy_documents d', 'd.id=a.softcopy_id')
            ->where('a.user_id', (int) $userId)->where('a.active', 1)->where('d.status', 'active')
            ->order_by('d.title')->order_by('d.id')->get()->result_array();
        return ['hardcopy' => $hardcopy, 'softcopy' => $softcopy];
    }

    public function role_options()
    {
        return $this->db->select('id,name')->from('roles')->where('active', 1)
            ->order_by('name')->get()->result_array();
    }
    public function user_options()
    {
        return $this->db->select('id,CONCAT_WS(" ",first_name,last_name) AS name', FALSE)
            ->from('users')->where('active', 1)->order_by('first_name')->get()->result_array();
    }
    public function permissions()
    {
        return $this->db->select('id,module_key,module_label,action_key,action_label')
            ->from('permissions')->order_by('module_label')->order_by('action_label')
            ->get()->result_array();
    }
    public function grants($roleIds)
    {
        if (!$roleIds)
            return [];
        $out = [];
        $rows = $this->db->from('role_permissions')->where_in('role_id', $roleIds)->get()->result_array();
        foreach ($rows as $r)
            $out[$r['role_id']][] = (int) $r['permission_id'];
        return $out;
    }
}
