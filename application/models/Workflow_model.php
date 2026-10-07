<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** Workflow persistence using the native CodeIgniter MySQLi Query Builder. */
class Workflow_model extends Repository_model
{
public function assignment_permission(array $params = []): ?array
    {
        $parts=explode('.',(string)$params[0],2);
        if (count($parts)!==2) return null;
        $this->db->reset_query()->select('id')->from('permissions')->where('module_key',$parts[0])->where('action_key',$parts[1]);
        return $this->first();
    }
    public function next_version_number(array $params = []): ?array
    {
        $this->db->reset_query()->select('COALESCE(MAX(version_number),0)+1 AS n',false)->from('workflow_versions')->where('workflow_id',$params[0]);
        return $this->first();
    }
    public function deactivate_other_definitions(array $params = []): bool
    {
        $this->db->reset_query()->where('request_type',$params[0])->where('id !=',$params[1])->where('active',1)
            ->set('active',0)->set('version','version + 1',false);
        return $this->written($this->db->update('workflows'));
    }
    public function archive_published_versions(array $params = []): bool
    {
        $this->db->reset_query()->where('workflow_id',$params[0])->where('status','published')
            ->set('status','archived')->set('version','version + 1',false);
        return $this->written($this->db->update('workflow_versions'));
    }
    public function published_version(array $params = []): ?array
    {
        $this->db->reset_query()->select('v.*')->from('workflow_versions v')->join('workflows w','w.id = v.workflow_id')
            ->where('w.request_type',$params[0])->where('w.active',1)->where('v.status','published')->order_by('v.version_number','DESC')->limit(1);
        return $this->first(true);
    }
    public function document_approvers(array $params = []): ?array
    {
        $this->db->reset_query()->select('config')->from('document_approvers')->where('domain',$params[0])->where('document_id',$params[1])->limit(1);
        return $this->first();
    }
    public function eligible_approvers(string $type, mixed $value, int $requester): array
    {
        if (!in_array($type,['user','role','permission'],true)) throw new Problem('Invalid workflow assignment.',409);
        $this->db->reset_query()->distinct()->select('u.*')->from('users u')->join('roles r','r.id = u.role_id')
            ->join('role_permissions rp','rp.role_id = r.id')->join('permissions p','p.id = rp.permission_id')
            ->where('u.active',1)->where('r.active',1)->where('p.module_key','requests')->where('p.action_key','approve')->where('u.id !=',$requester);
        if ($type==='user') $this->db->where('u.id',(int)$value);
        elseif ($type==='role') $this->db->where('r.id',(int)$value);
        else {
            $parts=explode('.',(string)$value,2);
            if (count($parts)!==2) { $this->db->reset_query(); return []; }
            $this->db->join('role_permissions rp2','rp2.role_id = r.id')->join('permissions p2','p2.id = rp2.permission_id')
                ->where('p2.module_key',$parts[0])->where('p2.action_key',$parts[1]);
        }
        return array_map(static fn($u)=>['id'=>(int)$u['id'],'name'=>Context::name($u),'position'=>$u['position_title']],$this->results());
    }
}
