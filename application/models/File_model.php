<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_model extends CI_Model
{
    /** Controlled request files: owner or actively assigned approver only. */
    public function review_file($requestId,$viewer)
    {
        $r=$this->db->get_where('requests',['id'=>(int)$requestId])->row_array();
        if (!$r || !in_array($r['type'],['softcopy_create','softcopy_revise'],TRUE)) return NULL;
        $allowed=(int)$r['requested_by']===(int)$viewer['id'];
        if (!$allowed && $r['status']==='submitted') {
            $steps=$this->db->get_where('workflow_steps',[
                'request_id'=>(int)$requestId,'status'=>'active'
            ])->result_array();
            foreach ($steps as $step) {
                $a=json_decode($step['assignment'],TRUE)?:[];
                if ((int)($step['assigned_user_id']??0)===(int)$viewer['id'] ||
                   (($a['type']??'')==='role' &&
                    (int)($a['value']??0)===(int)$viewer['role_id'])) {
                    $allowed=TRUE; break;
                }
            }
        }
        if (!$allowed) return NULL;
        $data=json_decode($r['payload'],TRUE)?:[];
        $create=$r['type']==='softcopy_create';
        $fileId=(int)($data[$create?'controlled_file_id':'revision_file_id']??0);
        if (!$fileId) return NULL;
        return $this->db->get_where('files',[
            'id'=>$fileId,'uploaded_by'=>(int)$r['requested_by'],
            'purpose'=>$create?'creation':'revision','domain'=>'softcopy',
            'document_id'=>$create?NULL:(int)$r['softcopy_id'],
            'status'=>'pending'
        ])->row_array();
    }

    public function approved_file($id)
    {
        return $this->db->select('f.*, s.created_by AS owner_id, s.status AS document_status')
            ->from('files f')
            ->join('softcopy_documents s', 's.id = f.document_id AND f.domain = "softcopy"')
            ->where('f.id', (int) $id)
            ->where('f.status', 'approved')
            ->limit(1)->get()->row_array();
    }

    public function allowed($user, $file)
    {
        if (!$user || !$file || $file['document_status']!=='active') return FALSE;
        $scope=$this->file_scope($user);
        return $this->db->from('softcopy_documents d')
            ->where('d.id',(int)$file['document_id'])->where('d.status','active')
            ->where($scope,NULL,FALSE)->count_all_results()>0;
    }

    private function file_scope($user)
    {
        require_once APPPATH.'services/security/document_scope.php';
        $this->load->model('Permission_model');
        $permissions=$this->Permission_model->for_user($user);
        return Document_scope::file_predicate($user['id']??0,$permissions);
    }

    /** One query per page, capped per document; no N+1 permission/history reads. */
    public function histories_for_documents($ids,$user)
    {
        if (!$ids) return [];
        $scope=$this->file_scope($user);
        $sql=$this->db->select('f.id,f.document_id,f.original_name,f.size,f.mime_type,f.created_at,
                f.status,u.first_name,u.last_name,
                ROW_NUMBER() OVER (PARTITION BY f.document_id ORDER BY f.id DESC) AS history_rank',FALSE)
            ->from('files f')->join('softcopy_documents d','d.id=f.document_id')
            ->join('users u','u.id=f.uploaded_by')
            ->where('f.domain','softcopy')->where('f.status','approved')->where('d.status','active')
            ->where_in('f.document_id',array_map('intval',$ids))
            ->where($scope,NULL,FALSE)->get_compiled_select();
        $rows=$this->db->query('SELECT * FROM ('.$sql.') file_history WHERE history_rank<=25 ORDER BY id DESC')->result_array();
        $result=[];
        foreach ($rows as $file) $result[$file['document_id']][]=$file;
        return $result;
    }
}
