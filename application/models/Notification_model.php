<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Actionable navigation counts, scoped to the signed-in user.
 * Counts are computed once per rendered page (never per sidebar link/tab).
 */
class Notification_model extends CI_Model
{
    private $tabs = [
        'softcopy' => ['softcopy_create', 'softcopy_revise', 'softcopy_cancel'],
        'hardcopy' => ['hardcopy_create', 'hardcopy_update'],
        'hardcopy-transfer' => ['transfer'],
        'hardcopy-disposal' => ['disposal'],
        'access-grant' => ['access'],
        'document-assign' => ['assignment']
    ];

    private function emptyCounts()
    {
        return array_fill_keys(array_keys($this->tabs), 0);
    }

    private function tabForType($type)
    {
        foreach ($this->tabs as $tab => $types) {
            if (in_array($type, $types, TRUE)) return $tab;
        }
        return NULL;
    }

    public function for_user(array $user): array
    {
        $requests = $this->emptyCounts();
        $tasks = $this->emptyCounts();
        $userId = (int)$user['id'];
        $roleId = (int)$user['role_id'];

        // Only returned drafts require action by their original requester.
        $owned = $this->db->select('type,COUNT(*) AS total', FALSE)
            ->from('requests')->where('requested_by', $userId)
            ->where_in('status', ['draft','returned'])->group_by('type')->get()->result_array();
        foreach ($owned as $row) {
            $tab = $this->tabForType($row['type']);
            if ($tab !== NULL) $requests[$tab] += (int)$row['total'];
        }

        // Only current, active approval assignments are actionable tasks.
        // DISTINCT avoids double counting a request for a user matching both
        // an explicit assignment and a role-based assignment.
        $assigned = $this->db->select('r.type,COUNT(DISTINCT r.id) AS total', FALSE)
            ->from('requests r')
            ->join('workflow_steps s', "s.request_id=r.id AND s.status='active'")
            ->where('r.status', 'submitted')
            ->group_start()->where('s.assigned_user_id', $userId)
            ->or_group_start()
                ->where("JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.type')) = 'role'", NULL, FALSE)
                ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.value')) AS UNSIGNED) = ".$roleId, NULL, FALSE)
            ->group_end()->group_end()
            ->group_by('r.type')->get()->result_array();
        foreach ($assigned as $row) {
            $tab = $this->tabForType($row['type']);
            if ($tab !== NULL) $tasks[$tab] += (int)$row['total'];
        }

        // Physical handoffs also live under My Tasks > Hardcopy Transfer.
        $handoffs = $this->db->from('transfers')->group_start()
            ->group_start()->where('status', 'for_transfer')->where('current_holder_id', $userId)->group_end()
            ->or_group_start()->where('status', 'in_transit')->where('recipient_id', $userId)->group_end()
            ->group_end()->count_all_results();
        $tasks['hardcopy-transfer'] += (int)$handoffs;

        return ['requests' => $requests, 'tasks' => $tasks,
            'requests_total' => array_sum($requests), 'tasks_total' => array_sum($tasks)];
    }
}
