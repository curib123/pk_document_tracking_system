<?php
declare(strict_types=1);
namespace Pk\Core;
final class Context
{
    public ?array $user = null;
    private array $permissions = [];
    public function __construct(public readonly Database $db) {}
    public function identify(int $id): void
    {
        $user=$this->db->one('SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1 AND r.active=1',[$id]);
        if (!$user) throw new Problem('Your account is inactive or no longer available.',401);
        $this->user=$user;
        $this->permissions=array_column($this->db->all("SELECT CONCAT(p.module_key,'.',p.action_key) capability FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=?",[$user['role_id']]),'capability');
    }
    public function id(): int { return (int)($this->user['id'] ?? 0); }
    public function can(string $permission): bool { return in_array($permission,$this->permissions,true); }
    public function permissions(): array { return $this->permissions; }
    public function require(string $permission): void { if (!$this->id()) throw new Problem('Sign in first.',401); if (!$this->can($permission)) throw new Problem('You do not have permission for this action.',403); }
    public function safeUser(): ?array { if (!$this->user) return null; $u=$this->user; unset($u['password_hash'],$u['session_version']); return $u; }
    public static function name(array $user): string { return trim(($user['first_name'] ?? '').' '.($user['middle_name'] ?? '').' '.($user['last_name'] ?? '')); }
    public static function json(mixed $value): string { return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
    public function audit(string $module,string $action,?int $entity=null,mixed $before=null,mixed $after=null,?string $reason=null,?int $request=null): void
    {
        $redact=function($data) use (&$redact) { if (!is_array($data)) return $data; foreach($data as $k=>$v) { if (preg_match('/password|csrf|session|token|storage_name/i',(string)$k)) unset($data[$k]); else $data[$k]=$redact($v); } return $data; };
        $this->db->insert('audit_logs',['user_id'=>$this->id() ?: null,'username'=>$this->user['username'] ?? 'anonymous','role_name'=>$this->user['role_name'] ?? '', 'module'=>$module,'action'=>$action,'entity_id'=>$entity,'before_state'=>self::json($redact($before)), 'after_state'=>self::json($redact($after)), 'reason'=>$reason,'request_id'=>$request,'http_method'=>substr($_SERVER['REQUEST_METHOD'] ?? 'CLI',0,10),'path'=>substr($_SERVER['REQUEST_URI'] ?? 'CLI',0,255),'ip_address'=>substr($_SERVER['REMOTE_ADDR'] ?? 'local',0,64),'user_agent'=>substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI',0,255)]);
    }
    public function notify(int $user,string $title,string $message,?int $request=null): void { $this->db->insert('notifications',['user_id'=>$user,'title'=>$title,'message'=>$message,'request_id'=>$request]); }
    public function sequence(string $key,string $prefix): string
    {
        $this->db->query('INSERT INTO sequences (sequence_key,value) VALUES (?,1) ON DUPLICATE KEY UPDATE value=value+1',[$key]);
        $row=$this->db->one('SELECT value FROM sequences WHERE sequence_key=? FOR UPDATE',[$key]);
        return $prefix.str_pad((string)$row['value'],6,'0',STR_PAD_LEFT);
    }
    public function active(string $table,int $id): array { $row=$this->db->row($table,$id,true); if (isset($row['active']) && !(int)$row['active']) throw new Problem('Selected '.$table.' record is inactive.'); if ($table==='users' && !(int)$this->db->row('roles',(int)$row['role_id'])['active']) throw new Problem('The selected user has an inactive role.'); return $row; }
    public function status(string $domain,int $id,string $previous,string $next,string $action,string $remarks=''): void { $this->db->insert('status_history',['domain'=>$domain,'document_id'=>$id,'previous_status'=>$previous,'new_status'=>$next,'action'=>$action,'user_id'=>$this->id(),'remarks'=>$remarks]); }
}
