<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH.'services/security/user_roles.php';

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
        $roleIds = implode(',',User_roles::ids($user)?:[0]);

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
                ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.value')) AS UNSIGNED) IN (".$roleIds.")", NULL, FALSE)
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

    /**
     * Unread approval notifications, paginated entirely in the database.
     * A notification stays unread until the user explicitly opens it.
     */
    private function unreadQuery(array $user): void
    {
        $uid=(int)$user['id'];
        $roles=implode(',',User_roles::ids($user)?:[0]);
        $this->db->from('workflow_steps s')
            ->join('requests r','r.id=s.request_id AND r.current_node=s.node_key')
            ->join('task_notification_receipts seen',
                'seen.step_id=s.id AND seen.activation=s.version AND seen.user_id='.$uid,'left')
            ->where('s.status','active')->where('r.status','submitted')
            ->where('seen.step_id IS NULL',NULL,FALSE)
            ->group_start()->where('s.assigned_user_id',$uid)
            ->or_group_start()
                ->where("JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.type')) = 'role'",NULL,FALSE)
                ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.value')) AS UNSIGNED) IN ($roles)",NULL,FALSE)
            ->group_end()->group_end();
    }

    public function unread_page(array $user,int $page=1,int $limit=10): array
    {
        $page=max(1,min(100000,$page));
        $limit=in_array($limit,[5,10,20,50],TRUE)?$limit:10;
        if (!$this->db->table_exists('task_notification_receipts'))
            return ['items'=>[],'total'=>0,'page'=>1,'limit'=>$limit,'pages'=>1];
        $this->unreadQuery($user);
        $total=(int)$this->db->count_all_results();
        $pages=max(1,(int)ceil($total/$limit));
        $page=min($page,$pages);
        $this->unreadQuery($user);
        $rows=$this->db->select('r.id AS request_id,r.reference,r.type,s.id AS step_id,s.label,s.version')
            ->order_by('s.id','DESC')->limit($limit,($page-1)*$limit)
            ->get()->result_array();
        $items=[];
        foreach ($rows as $row) {
            $tab=$this->tabForType($row['type']);
            if ($tab===NULL) continue;
            $items[]=[
                'key'=>$row['step_id'].':'.$row['version'],
                'reference'=>$row['reference'],
                'label'=>$row['label'],
                'url'=>'my-tasks/'.$tab.'?'.http_build_query(['q'=>$row['reference']])
            ];
        }
        return ['items'=>$items,'total'=>$total,'page'=>$page,
            'limit'=>$limit,'pages'=>$pages];
    }

    /** Unseen approval occurrences, always scoped to the server-loaded identity. */
    public function pending_approvals(array $user,array $stepIds=[]): array
    {
        if (!$this->db->table_exists('task_notification_receipts')) return [];
        $userId=(int)$user['id'];
        $roles=implode(',',User_roles::ids($user)?:[0]);
        $rows=$this->db->select('r.id AS request_id,r.reference,r.type,s.id AS step_id,s.label,s.version')
            ->from('workflow_steps s')->join('requests r','r.id=s.request_id AND r.current_node=s.node_key')
            ->join('task_notification_receipts seen','seen.step_id=s.id AND seen.activation=s.version AND seen.user_id='.$userId,'left')
            ->where('s.status','active')->where('r.status','submitted')->where('seen.step_id IS NULL',NULL,FALSE)
            ->group_start()->where('s.assigned_user_id',$userId)->or_group_start()
                ->where("JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.type')) = 'role'",NULL,FALSE)
                ->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment, '$.value')) AS UNSIGNED) IN ($roles)",NULL,FALSE)
            ->group_end()->group_end();
        if ($stepIds) $this->db->where_in('s.id',array_values(array_unique(array_map('intval',$stepIds))));
        $rows=$this->db->order_by('s.id')->limit(100)->get()->result_array();
        $out=[];
        foreach ($rows as $row) {
            $tab=$this->tabForType($row['type']);
            if ($tab===NULL) continue;
            $row['key']=$row['step_id'].':'.$row['version'];
            $row['tab']=$tab;
            $out[]=$row;
        }
        return $out;
    }

    /** Atomic claims prevent another tab/device from showing the same occurrence. */
    public function claim_approvals(array $user,array $keys): array
    {
        $selected=array_fill_keys(array_filter(array_slice($keys,0,100),static function($key) {
            return is_string($key) && preg_match('/^[1-9][0-9]*:[1-9][0-9]*$/',$key);
        }),TRUE);
        if (!$selected) return [];
        $requestedIds=array_map(static function($key) {
            return (int)explode(':',$key)[0];
        },array_keys($selected));
        $pending=$this->pending_approvals($user,$requestedIds);$shown=[];
        $roles=implode(',',User_roles::ids($user)?:[0]);
        $this->db->trans_begin();
        try {
            foreach ($pending as $task) {
                if (!isset($selected[$task['key']])) continue;
                // Recheck eligibility at insertion, so a task decided since the
                // last poll is not claimed. The unique key arbitrates duplicates.
                $this->db->query("INSERT IGNORE INTO task_notification_receipts (user_id,step_id,activation)
                    SELECT ?,s.id,s.version FROM workflow_steps s JOIN requests r ON r.id=s.request_id
                    WHERE s.id=? AND s.version=? AND s.status='active' AND r.status='submitted'
                        AND r.current_node=s.node_key AND (s.assigned_user_id=? OR
                        (JSON_UNQUOTE(JSON_EXTRACT(s.assignment,'$.type'))='role' AND
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment,'$.value')) AS UNSIGNED) IN ($roles)))",
                    [(int)$user['id'],(int)$task['step_id'],(int)$task['version'],(int)$user['id']]);
                if ($this->db->affected_rows()>0) $shown[]=$task;
            }
            if ($this->db->trans_status()===FALSE) throw new DomainException('Task notifications could not be saved.');
            $this->db->trans_commit();
            return $shown;
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            throw $error;
        }
    }
}
