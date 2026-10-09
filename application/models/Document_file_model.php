<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_file_model extends CI_Model
{
    public function by_id($id, $kind)
    {
        return $this->db->select('f.*, d.kind, d.status, d.created_by')
            ->from('document_files f')
            ->join('documents d', 'd.id = f.document_id')
            ->where('f.id', $id)->where('d.kind', $kind)->get()->row_array();
    }

    public function authorized($user, $document, $canEdit = FALSE)
    {
        if ($canEdit || $user['role'] === 'super_admin' ||
            in_array($user['role'], ['document_control_officer', 'internal_audit'], TRUE) ||
            (int) $document['created_by'] === (int) $user['id']) return TRUE;

        $assigned = $this->db->from('document_assignments')
            ->where('document_id', $document['document_id'] ?? $document['id'])
            ->where('user_id', $user['id'])->count_all_results() > 0;
        if ($assigned) return TRUE;

        return $this->db->from('document_access_grants')
            ->where('document_id', $document['document_id'] ?? $document['id'])
            ->where('user_id', $user['id'])
            ->where('revoked_at IS NULL', NULL, FALSE)
            ->group_start()->where('expires_at IS NULL', NULL, FALSE)
                ->or_where('expires_at >=', date('Y-m-d H:i:s'))->group_end()
            ->count_all_results() > 0;
    }

    public function authorized_document_ids($user, $documents, $canEdit)
    {
        $allowed = [];
        if (!$documents) return $allowed;
        $ids = array_map('intval', array_column($documents, 'id'));
        if ($canEdit || in_array($user['role'], ['super_admin','document_control_officer','internal_audit'], TRUE)) {
            return array_fill_keys($ids, TRUE);
        }
        foreach ($documents as $document) {
            if ((int) $document['created_by'] === (int) $user['id']) $allowed[$document['id']] = TRUE;
        }
        $assigned = $this->db->select('document_id')->from('document_assignments')
            ->where_in('document_id', $ids)->where('user_id', $user['id'])->get()->result_array();
        foreach ($assigned as $row) $allowed[$row['document_id']] = TRUE;

        $grants = $this->db->select('document_id')->from('document_access_grants')
            ->where_in('document_id', $ids)->where('user_id', $user['id'])
            ->where('revoked_at IS NULL', NULL, FALSE)
            ->group_start()->where('expires_at IS NULL', NULL, FALSE)
                ->or_where('expires_at >=', date('Y-m-d H:i:s'))->group_end()
            ->get()->result_array();
        foreach ($grants as $row) $allowed[$row['document_id']] = TRUE;
        return $allowed;
    }
}
