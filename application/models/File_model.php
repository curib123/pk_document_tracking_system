<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_model extends CI_Model
{
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
        if (!$user || !$file || $file['document_status'] !== 'active') return FALSE;
        $ci =& get_instance();
        if ($ci->Permission_model->allowed($user, 'files', 'view_all')) return TRUE;
        if ((int) $file['owner_id'] === (int) $user['id']) return TRUE;

        $assigned = $this->db->from('assignments')
            ->where('softcopy_id', $file['document_id'])
            ->where('user_id', $user['id'])
            ->where('active', 1)->count_all_results();
        if ($assigned > 0) return TRUE;

        return $this->db->from('access_grants')
            ->where('domain', 'softcopy')
            ->where('document_id', $file['document_id'])
            ->where('user_id', $user['id'])
            ->where('status', 'access_granted')
            ->where('revoked_at IS NULL', NULL, FALSE)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->count_all_results() > 0;
    }

    public function latest_for_documents($ids)
    {
        if (!$ids) return [];
        $rows = $this->db->select('id, document_id, original_name, status, created_at')
            ->from('files')->where('domain', 'softcopy')->where('status', 'approved')
            ->where_in('document_id', $ids)
            ->order_by('id', 'DESC')->get()->result_array();
        $latest = [];
        foreach ($rows as $row) {
            if (!isset($latest[$row['document_id']])) $latest[$row['document_id']] = $row;
        }
        return $latest;
    }

    public function history($docId)
    {
        return $this->db->select('f.id, f.original_name, f.size, f.created_at,
                f.status, u.first_name, u.last_name')
            ->from('files f')->join('users u', 'u.id = f.uploaded_by')
            ->where('f.domain', 'softcopy')->where('f.document_id', (int) $docId)
            ->where('f.status', 'approved')->order_by('f.id', 'DESC')
            ->get()->result_array();
    }
}
