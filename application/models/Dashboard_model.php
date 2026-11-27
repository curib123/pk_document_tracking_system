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
        $counts = [];
        $created = 0;
        $userId = (int) $user['id'];
        $documentModules = [
            'hardcopy' => 'hardcopy',
            'softcopy' => 'softcopy',
            'external' => 'external_documents',
            'scanned' => 'scanned_documents'
        ];
        foreach ($documentModules as $domain => $module) {
            if (!$this->Permission_model->allowed($user, $module))
                continue;
            if ($domain === 'external') {
                require_once APPPATH . 'services/documents/external_document_service.php';
                $canViewAll = (new External_document_service())->can_view_all($user);
                $query = $this->db->from('external_documents d');
                if (!$canViewAll)
                    $query->where('d.created_by', $userId);
            } elseif ($domain === 'scanned') {
                require_once APPPATH . 'services/documents/scanned_document_service.php';
                $query = (new Scanned_document_service())->register_query($user);
            } else {
                $table = $this->Document_model->config($domain)['table'];
                $scope = $this->Document_model->scope($domain, $user);
                $query = $this->db->from($table . ' d')->where($scope, NULL, FALSE);
            }
            $totals = $query->select('COUNT(*) AS total,SUM(d.created_by=' . $userId . ') AS mine', FALSE)
                ->get()->row_array();
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
