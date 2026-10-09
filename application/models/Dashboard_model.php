<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard_model extends CI_Model
{
    public function counts($user)
    {
        $stats=['draft'=>0,'submitted'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,
            'completed'=>0,'returned'=>0,'cancelled'=>0];
        $rows=$this->db->select('status,COUNT(*) AS total')->from('requests')
            ->where('requested_by',(int)$user['id'])->group_by('status')->get()->result_array();
        foreach ($rows as $row) $stats[$row['status']]=(int)$row['total'];
        return $stats;
    }

    public function summary($user)
    {
        $this->load->model('Document_model');
        $this->load->model('Permission_model');
        $this->load->model('Transfer_model');
        $permissions=$this->Permission_model->for_user($user);
        $stats=$this->counts($user);
        $counts=['hardcopy'=>0,'softcopy'=>0];
        $created=0; $activities=[];
        foreach (array_keys($counts) as $domain) {
            if (!isset($permissions['*']) && empty($permissions[$domain]['view'])) continue;
            $scope=$this->Document_model->scope($domain,$user);
            $table=$this->Document_model->config($domain)['table'];
            $totals=$this->db->select('COUNT(*) AS total,SUM(d.created_by='.(int)$user['id'].') AS mine',FALSE)
                ->from($table.' d')->where($scope,NULL,FALSE)->get()->row_array();
            $counts[$domain]=(int)$totals['total'];
            $created+=(int)$totals['mine'];
            $rows=$this->db->select('h.action,h.created_at,d.title')
                ->from('status_history h')->join($table.' d','d.id=h.document_id')
                ->where('h.domain',$domain)->where($scope,NULL,FALSE)
                ->order_by('h.created_at','DESC')->order_by('h.id','DESC')
                ->limit(10)->get()->result_array();
            foreach ($rows as $row) $activities[]=$row+['domain'=>$domain];
        }
        usort($activities,static function($a,$b) {return strcmp($b['created_at'],$a['created_at']);});
        $tasks=$this->db->query("SELECT COUNT(DISTINCT r.id) AS total FROM requests r
            JOIN workflow_steps s ON s.request_id=r.id AND s.status='active'
            WHERE r.status='submitted' AND (s.assigned_user_id=? OR
              (JSON_UNQUOTE(JSON_EXTRACT(s.assignment,'$.type'))='role' AND
               CAST(JSON_UNQUOTE(JSON_EXTRACT(s.assignment,'$.value')) AS UNSIGNED)=?))",
            [(int)$user['id'],(int)$user['role_id']])->row_array();
        $transfers=0;
        if (isset($permissions['*']) || !empty($permissions['transfer']['view'])) {
            list($unused,$transfers)=$this->Transfer_model->pending($user,1,1);
        }
        $finished=$stats['completed']+$stats['approved'];
        $resolved=$finished+$stats['rejected'];
        return [
            'stats'=>$stats,'document_counts'=>$counts,
            'metrics'=>['documents'=>array_sum($counts),'created'=>$created,
                'pending_tasks'=>(int)$tasks['total'],'transfers'=>(int)$transfers,
                'completion_rate'=>$resolved?(int)round(100*$finished/$resolved):0],
            'recent_activities'=>array_slice($activities,0,10)
        ];
    }
}
