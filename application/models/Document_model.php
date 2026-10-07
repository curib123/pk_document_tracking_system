<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** Document persistence using the native CodeIgniter MySQLi Query Builder. */
class Document_model extends Repository_model
{
public function location_occupant(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('hardcopy_documents')->where('location_id',$params[0])->limit(1);
        return $this->first(true);
    }
    public function active_assignment(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('assignments')->where('softcopy_id',$params[0])->where('user_id',$params[1])->where('active',1)->limit(1);
        return $this->first();
    }
    public function live_access_grant(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('access_grants')->where('domain',$params[0])
            ->where('document_id',$params[1])->where('user_id',$params[2])->where('status','access_granted')
            ->where('revoked_at',null)->where('expires_at > NOW()',null,false)->limit(1);
        return $this->first();
    }
    public function open_transfer(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('transfers')->where('hardcopy_id',$params[0])
            ->where_in('status',['for_transfer','pending_recipient_acceptance'])->limit(1);
        return $this->first(true);
    }
    public function approver_configuration(array $params = []): ?array
    {
        $this->db->reset_query()->from('document_approvers')->where('domain',$params[0])->where('document_id',$params[1])->limit(1);
        return $this->first(true);
    }
}
