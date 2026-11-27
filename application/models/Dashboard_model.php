<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard_model extends CI_Model
{
    public function summary($user)
    {
        $this->load->model('Document_model');
        $this->load->model('Permission_model');
        $this->load->model('Request_model');
        $this->load->model('Transfer_model');
        $stats = $this->Permission_model->allowed($user, 'requests') ? $this->Request_model->status_counts($user) : [];
        $counts = ['hardcopy' => 0, 'softcopy' => 0];
        $created = 0;
        foreach (array_keys($counts) as $domain) {
            if (!$this->Permission_model->allowed($user, $domain))
                continue;
            $scope = $this->Document_model->scope($domain, $user);
            $table = $this->Document_model->config($domain)['table'];
            $totals = $this->db->select('COUNT(*) AS total,SUM(d.created_by=' . (int) $user['id'] . ') AS mine', FALSE)
                ->from($table . ' d')->where($scope, NULL, FALSE)->get()->row_array();
            $counts[$domain] = (int) $totals['total'];
            $created += (int) $totals['mine'];
        }
        $finished = ($stats['completed'] ?? 0) + ($stats['approved'] ?? 0);
        $resolved = $finished + ($stats['rejected'] ?? 0);
        return [
            'stats' => $stats,
            'document_counts' => $counts,
            'metrics' => [
                'documents' => array_sum($counts),
                'created' => $created,
                'pending_tasks' => array_sum($this->Request_model->approval_counts($user)),
                'transfers' => $this->Transfer_model->pending_count($user),
                'decided_requests' => $resolved,
                'completion_rate' => $resolved ? (int) round(100 * $finished / $resolved) : 0
            ]
        ];
    }
}
