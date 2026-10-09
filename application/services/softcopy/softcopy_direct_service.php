<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Softcopy Direct: same create/revise/cancel fields and domain effects as
 * requests, but a staff member with softcopy.direct approves immediately.
 * Does not insert requests, workflow_steps, or workflow_history.
 */
class Softcopy_direct_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function execute($post,$actorId,$attachment=NULL)
    {
        require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
        $service=new Softcopy_operation_service();
        $type=trim((string)($post['type']??''));
        $service->validate_type($type);
        $subject=trim((string)($post['subject']??''));
        if ($subject==='' || mb_strlen($subject)>255) {
            throw new DomainException('Subject / reason is required (maximum 255 characters).');
        }

        $docId=(int)($post['softcopy_id']??0);
        $payload=[
            'subject'=>$subject,
            'remarks'=>trim((string)($post['remarks']??'')),
            'title'=>trim((string)($post['title']??'')),
            'document_number'=>trim((string)($post['document_number']??'')),
            'series_number'=>trim((string)($post['series_number']??'')),
            'category_id'=>(int)($post['category_id']??0),
            'new_revision_level'=>trim((string)($post['new_revision_level']??'')),
            'effective_date'=>trim((string)($post['effective_date']??'')),
            'date_received'=>trim((string)($post['date_received']??'')),
            'date_released'=>trim((string)($post['date_released']??'')),
            'page_number'=>(int)($post['page_number']??0)
        ];
        $stagedFileId=NULL;
        if ($type!=='softcopy_create' && $docId<=0) {
            throw new DomainException('Select an existing softcopy document.');
        }
        if (in_array($type,['softcopy_create','softcopy_revise'],TRUE)) {
            if ($type==='softcopy_revise' &&
                ($payload['title']==='' || mb_strlen($payload['title'])>255)) {
                throw new DomainException('Proposed document title is required for a revision.');
            }
            if ($type==='softcopy_revise') $service->validate_revision($payload);
            require_once APPPATH.'services/files/file_service.php';
            $files=new File_service();
            $stagedFileId=$type==='softcopy_create'
                ? $files->stage_creation($actorId,$attachment)
                : $files->stage_revision($docId,$actorId,$attachment);
            $payload[$type==='softcopy_create'?'controlled_file_id':'revision_file_id']=$stagedFileId;
        }

        $this->ci->db->trans_begin();
        try {
            $result=$service->apply($type,$payload,$docId,$actorId,$actorId,NULL);
            if ($this->ci->db->trans_status()===FALSE) {
                throw new DomainException('Direct approval failed: database transaction was not saved.');
            }
            $this->ci->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            // A staged file was stored before the DB transaction started.
            // Clean up its DB row and physical bytes on failure.
            if ($stagedFileId) {
                $file=$this->ci->db->get_where('files',[
                    'id'=>$stagedFileId,'status'=>'pending','uploaded_by'=>$actorId
                ])->row_array();
                if ($file) {
                    $this->ci->db->where('id',$stagedFileId)->delete('files');
                    @unlink(PK_ROOT.'/storage/documents/'.$file['storage_name']);
                }
            }
            if ($e instanceof DomainException) throw $e;
            log_message('error', 'Softcopy direct approval failed: '.$e->getMessage());
            throw new DomainException('Unable to approve this softcopy action directly.');
        }
    }
}
