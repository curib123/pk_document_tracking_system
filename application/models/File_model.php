<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** File persistence using the native CodeIgniter MySQLi Query Builder. */
class File_model extends Repository_model
{
    // Ari ang DB queries; service layer ang magbuot sa business rules para klaro ang separation.
public function attachment_approvers(array $params = []): array
    {
        $this->db->reset_query()->distinct()->select('u.id')->from('users u')->join('roles r','r.id = u.role_id')
            ->join('role_permissions rp','rp.role_id = r.id')->join('permissions p','p.id = rp.permission_id')
            ->where('u.active',1)->where('r.active',1)->where('p.module_key','files')->where('p.action_key','approve');
        return $this->results();
    }
    public function artifact_revision_state(array $params = []): ?array
    {
        $this->db->reset_query()->select('a.artifact_type,a.revision_id,d.current_revision_id,d.status')->from('revision_artifacts a')
            ->join('softcopy_revisions r','r.id = a.revision_id')->join('softcopy_documents d','d.id = r.document_id')->where('a.file_id',$params[0])->limit(1);
        return $this->first();
    }
    public function pending_file_reviewers(array $params = []): array
    {
        $fileId=(int)$params[0];
        $this->db->reset_query()->select('s.candidates')->from('requests r')->join('workflow_steps s','s.request_id = r.id')
            ->where('r.status','pending')->where('s.status','pending');
        // The JSON expression is fixed; only a validated integer is interpolated.
        $this->db->where("CAST(JSON_UNQUOTE(JSON_EXTRACT(r.payload,'$.file_id')) AS UNSIGNED) = ".$fileId,null,false);
        return $this->results();
    }
    public function matching_artifact(array $params = []): ?array
    {
        $this->db->reset_query()->select('id,file_id')->from('revision_artifacts')->where('revision_id',$params[0])
            ->where('artifact_type',$params[1])->where('source_fingerprint',$params[2])->limit(1);
        return $this->first();
    }
}
