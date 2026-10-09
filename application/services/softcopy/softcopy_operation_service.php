<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The request-approval path and Softcopy Direct use the exact same
 * database effects. The caller owns the transaction; this service does not
 * create requests, steps, or workflow history on direct operations.
 */
class Softcopy_operation_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function validate_type($type)
    {
        if (!in_array($type, ['softcopy_create','softcopy_revise','softcopy_cancel'], TRUE)) {
            throw new DomainException('Choose Create, Revise or Cancel Softcopy.');
        }
    }

    public function validate_revision($payload)
    {
        $level=trim((string)($payload['new_revision_level']??''));
        if ($level==='' || mb_strlen($level)>30) {
            throw new DomainException('New revision level must contain 1 to 30 characters.');
        }
        foreach (['effective_date','date_received','date_released'] as $field) {
            $date=(string)($payload[$field]??'');
            $parsed=DateTime::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d')!==$date) {
                throw new DomainException('Provide a valid '.$field.' for the revision.');
            }
        }
        if ((int)($payload['page_number']??0)<1) {
            throw new DomainException('Number of pages must be at least one.');
        }
    }

    public function apply($type,$payload,$softcopyId,$actorId,$ownerId,$requestId=NULL)
    {
        $this->validate_type($type);
        $db=$this->ci->db;
        $reason=trim((string)($payload['remarks']??''));
        $title=trim((string)($payload['title']??''));
        if ($type==='softcopy_create') {
            $number=trim((string)($payload['document_number']??''));
            $categoryId=(int)($payload['category_id']??0);
            $series=trim((string)($payload['series_number']??''));
            if ($title==='' || mb_strlen($title)>255 || $number==='' || mb_strlen($number)>100 ||
                mb_strlen($series)>100 || !$categoryId) {
                throw new DomainException('Document title, number and category are required.');
            }
            if (!$db->get_where('categories',['id'=>$categoryId,'active'=>1])->row_array()) {
                throw new DomainException('Choose an active softcopy category.');
            }
            if (!$db->insert('softcopy_documents',[
                'title'=>$title,'document_number'=>$number,'series_number'=>$series?:NULL,
                'category_id'=>$categoryId,'created_by'=>$ownerId,
                'creation_source'=>$requestId===NULL?'direct':'request',
                'creation_reason'=>$reason?:trim((string)($payload['subject']??'')),
                'source_request_id'=>$requestId
            ])) throw new DomainException('Unable to create the softcopy document.');
            $newId=(int)$db->insert_id();
            if ($requestId!==NULL) {
                $db->where('id',$requestId)->update('requests',['softcopy_id'=>$newId]);
            }
            if ($requestId===NULL) {
                $this->audit($newId,'new','active','direct_create',$actorId,$reason);
            }
            return ['document_id'=>$newId];
        }

        $rows=$db->query('SELECT * FROM softcopy_documents WHERE id=? FOR UPDATE',[(int)$softcopyId])
            ->result_array();
        $doc=$rows[0]??NULL;
        if (!$doc || $doc['status']!=='active') {
            throw new DomainException('Select an active softcopy document.');
        }

        if ($type==='softcopy_revise') {
            $this->validate_revision($payload);
            $fileId=(int)($payload['revision_file_id']??0);
            $file=$db->get_where('files',[
                'id'=>$fileId,'document_id'=>(int)$softcopyId,'domain'=>'softcopy',
                'uploaded_by'=>(int)$ownerId,'purpose'=>'revision','status'=>'pending'
            ])->row_array();
            if (!$file) throw new DomainException('A pending controlled revision attachment is required.');
            $max=$db->select_max('revision_number')
                ->get_where('softcopy_revisions',['document_id'=>$softcopyId])->row_array();
            $previous=$doc['current_revision_id']?
                $db->get_where('softcopy_revisions',['id'=>$doc['current_revision_id']])->row_array():NULL;
            $level=trim((string)$payload['new_revision_level']);
            if ($previous && $level===$previous['new_revision_level']) {
                throw new DomainException('New revision level must differ from the current revision.');
            }
            if ($title!=='' && mb_strlen($title)>255) {
                throw new DomainException('Document title is too long.');
            }
            $date=date('Y-m-d');
            if (!$db->insert('softcopy_revisions',[
                'document_id'=>(int)$softcopyId,
                'revision_number'=>(int)($max['revision_number']??0)+1,
                'reason'=>$reason,'effective_date'=>$payload['effective_date'],
                'page_number'=>(int)$payload['page_number'],
                'series_number'=>$doc['series_number'],
                'document_title'=>$title?:$doc['title'],
                'previous_revision_level'=>$previous['new_revision_level']??NULL,
                'new_revision_level'=>$level,
                'previous_effective_date'=>$previous['new_effective_date']??NULL,
                'new_effective_date'=>$payload['effective_date'],
                'date_received'=>$payload['date_received'],
                'date_released'=>$payload['date_released'],
                'approval_date'=>$date,'file_id'=>$fileId,
                'uploaded_by'=>$ownerId,'approved_by'=>$actorId
            ])) throw new DomainException('Unable to save softcopy revision.');
            $revisionId=(int)$db->insert_id();
            $db->where('id',$softcopyId)->update('softcopy_documents',[
                'current_revision_id'=>$revisionId,'title'=>$title?:$doc['title']
            ]);
            $db->where('id',$fileId)->update('files',[
                'status'=>'approved','approved_by'=>$actorId,
                'approved_at'=>date('Y-m-d H:i:s')
            ]);
            if ($requestId===NULL) {
                $this->audit($softcopyId,'active','active','direct_revise',$actorId,$reason);
            }
            return ['document_id'=>(int)$softcopyId,'revision_id'=>$revisionId,'file_id'=>$fileId];
        }

        $db->where('id',$softcopyId)->update('softcopy_documents',[
            'previous_status'=>$doc['status'],'status'=>'cancelled'
        ]);
        $this->audit($softcopyId,$doc['status'],'cancelled',
            $requestId===NULL?'direct_cancel':'cancelled',$actorId,$reason);
        return ['document_id'=>(int)$softcopyId];
    }

    private function audit($id,$previous,$current,$action,$actor,$remarks)
    {
        $this->ci->db->insert('status_history',[
            'domain'=>'softcopy','document_id'=>$id,
            'previous_status'=>$previous,'new_status'=>$current,
            'action'=>$action,'user_id'=>$actor,'remarks'=>$remarks
        ]);
    }
}
