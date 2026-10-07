<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** Request persistence using the native CodeIgniter MySQLi Query Builder. */
class Request_model extends Repository_model
{
public function competing_transfer(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('requests')->where('hardcopy_id',$params[0])->where('type','transfer')
            ->where_in('status',['pending','approved'])->where('id !=',$params[1])->limit(1);
        return $this->first(true);
    }
    public function assignment_for_update(array $params = []): ?array
    {
        $this->db->reset_query()->from('assignments')->where('softcopy_id',$params[0])->where('user_id',$params[1])->limit(1);
        return $this->first(true);
    }
    public function grants_for_update(array $params = []): array
    {
        $this->db->reset_query()->from('access_grants')->where('domain',$params[0])->where('document_id',$params[1])->where('status','access_granted');
        return $this->results(true);
    }
    public function pending_candidates(array $params = []): array
    {
        $this->db->reset_query()->select('candidates')->from('workflow_steps')->where('request_id',$params[0])->where('status','pending');
        return $this->results();
    }
    public function cancel_pending_steps(array $params = []): bool
    {
        $this->db->reset_query()->where('request_id',$params[0])->where('status','pending')
            ->set('status','cancelled')->set('version','version + 1',false);
        return $this->written($this->db->update('workflow_steps'));
    }
}
