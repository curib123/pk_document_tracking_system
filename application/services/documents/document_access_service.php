<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Schema-aware document permissions:
 * access_grants is polymorphic (domain + document_id).
 * assignments only has softcopy_id; a hardcopy assignment changes holder_id.
 */
class Document_access_service
{
    private $ci;

    public function __construct() { $this->ci =& get_instance(); }

    public function apply($type,$payload,$softcopyId,$hardcopyId,$recipientId,$actorId,$requestId=NULL)
    {
        if (!in_array($type,['assignment','access'],TRUE))
            throw new DomainException('Unsupported document action.');
        $domain=(string)($payload['document_domain']??'softcopy');
        if (!in_array($domain,['softcopy','hardcopy'],TRUE))
            throw new DomainException('Choose a valid document type.');
        $docId=$domain==='softcopy'?(int)$softcopyId:(int)$hardcopyId;
        if (!$docId || !(int)$recipientId)
            throw new DomainException('Choose an existing document and an assignee.');
        $table=$domain==='softcopy'?'softcopy_documents':'hardcopy_documents';
        $doc=$this->ci->db->get_where($table,['id'=>$docId,'status'=>'active'])->row_array();
        $user=$this->ci->db->get_where('users',['id'=>(int)$recipientId,'active'=>1])->row_array();
        if (!$doc || !$user)
            throw new DomainException('The selected document or recipient is no longer active.');
        if ($type==='assignment') {
            if ($domain==='softcopy') {
                $existing=$this->ci->db->get_where('assignments',[
                    'softcopy_id'=>$docId,'user_id'=>(int)$recipientId
                ])->row_array();
                $data=['active'=>1,'assigned_by'=>(int)$actorId,
                    'assigned_at'=>date('Y-m-d H:i:s')];
                if ($existing) $this->ci->db->where('id',$existing['id'])
                    ->update('assignments',$data);
                else $this->ci->db->insert('assignments',[
                    'softcopy_id'=>$docId,'user_id'=>(int)$recipientId
                ]+$data);
            } else {
                // Physical custody is represented in pk_dts by holder_id,
                // NOT by a synthetic hardcopy_id column in assignments.
                $this->ci->db->where('id',$docId)->where('status','active')
                    ->update('hardcopy_documents',['holder_id'=>(int)$recipientId]);
            }
            return ['domain'=>$domain,'document_id'=>$docId,'assigned_to'=>(int)$recipientId];
        }
        $date=(string)($payload['expires_at']??'');
        $parsed=DateTime::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date)
            throw new DomainException('Enter a valid access expiry date.');
        $expires=$date.' 23:59:59';
        if (strtotime($expires)<time()) throw new DomainException('Access expiry must be in the future.');
        $reason=trim((string)($payload['remarks']??''));
        if ($reason==='') $reason='Authorized document access';
        $this->ci->db->insert('access_grants',[
            'request_id'=>$requestId,'domain'=>$domain,'document_id'=>$docId,
            'user_id'=>(int)$recipientId,'granted_by'=>(int)$actorId,
            'expires_at'=>$expires,'reason'=>$reason
        ]);
        return ['domain'=>$domain,'document_id'=>$docId,
            'grant_id'=>(int)$this->ci->db->insert_id()];
    }
}
