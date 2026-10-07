<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** Catalog persistence using the native CodeIgniter MySQLi Query Builder. */
class Catalog_model extends Repository_model
{
public function permission_administrator(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('permissions')->where('module_key','roles')->where('action_key','edit');
        return $this->first();
    }
    public function permission_by_key(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('permissions')->where('module_key',$params[0])->where('action_key',$params[1]);
        return $this->first();
    }
    public function role_permission_ids(array $params = []): array
    {
        $this->db->reset_query()->select('permission_id')->from('role_permissions')->where('role_id',$params[0]);
        return $this->results();
    }
    public function clear_role_permissions(array $params = []): bool
    {
        $this->db->reset_query()->where('role_id',$params[0]);
        return $this->written($this->db->delete('role_permissions'));
    }
    public function assigned_user(array $params = []): ?array
    {
        $this->db->reset_query()->select('id')->from('users')->where('role_id',$params[0])->limit(1);
        return $this->first();
    }
    public function assigned_role(array $params = []): ?array
    {
        $this->db->reset_query()->select('role_id')->from('role_permissions')->where('permission_id',$params[0])->limit(1);
        return $this->first();
    }
    public function delete_catalog_record(string $module, array $params = []): bool
    {
        if (!in_array($module,['roles','permissions','areas','specifics','assets','locations','categories'],true)) throw new LogicException('Not a deletable catalogue.');
        $this->db->reset_query()->where('id',$params[0]);
        return $this->written($this->db->delete($module));
    }
    public function active_administrators(array $params = []): array
    {
        $this->db->reset_query()->select('u.id,u.role_id')->from('users u')->join('roles r','r.id = u.role_id')
            ->join('role_permissions rp','rp.role_id = r.id')->join('permissions p','p.id = rp.permission_id')
            ->where('u.active',1)->where('r.active',1)->where('p.module_key','roles')->where('p.action_key','edit');
        return $this->results(true);
    }
    public function referenced_record(string $table, string $column, array $params = []): ?array
    {
        Database::identifier($table); Database::identifier($column);
        $this->db->reset_query()->select('id')->from($table)->where($column,$params[0])->limit(1);
        return $this->first();
    }
}
