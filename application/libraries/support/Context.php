<?php
declare(strict_types=1);
namespace Pk\Core;
final class Context
{
    // Mao ni ang request context: current user, permissions, DB, audit, ug notifications in one place.
    public ?array $user = null;
    private array $models = [];
    private array $permissions = [];
    public Database $db;
    public function __construct(Database $db) { $this->db=$db; }
    /** A single context owns every model and the transaction connection. */
    public function model(string $class): \Repository_model
    {
        if (!is_subclass_of($class, \Repository_model::class)) throw new \LogicException('Invalid domain model.');
        if (!isset($this->models[$class])) {
            if (function_exists('get_instance')) {
                $CI = get_instance(); $alias = 'dts_'.strtolower($class);
                $CI->load->model($class, $alias); $model = $CI->$alias;
            } else $model = new $class();
            $model->initialize($this); $this->models[$class] = $model;
        }
        return $this->models[$class];
    }
    public static function fromOptions(self|array|null $options): self
    {
        if ($options instanceof self) return $options;
        if (is_array($options) && ($options['context'] ?? null) instanceof self) return $options['context'];
        throw new \LogicException('Load the service with the current request context.');
    }
    public function identify(int $id): void
    {
        $user=$this->model(\Identity_model::class)->active_user([$id]);
        if (!$user) throw new Problem('Your account is inactive or no longer available.',401);
        $this->user=$user;
        $this->permissions=array_column($this->model(\Identity_model::class)->role_capabilities([$user['role_id']]),'capability');
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
        $this->model(\Identity_model::class)->insert('audit_logs',['user_id'=>$this->id() ?: null,'username'=>$this->user['username'] ?? 'anonymous','role_name'=>$this->user['role_name'] ?? '', 'module'=>$module,'action'=>$action,'entity_id'=>$entity,'before_state'=>self::json($redact($before)), 'after_state'=>self::json($redact($after)), 'reason'=>$reason,'request_id'=>$request,'http_method'=>substr($_SERVER['REQUEST_METHOD'] ?? 'CLI',0,10),'path'=>substr($_SERVER['REQUEST_URI'] ?? 'CLI',0,255),'ip_address'=>substr($_SERVER['REMOTE_ADDR'] ?? 'local',0,64),'user_agent'=>substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI',0,255)]);
    }
    public function notify(int $user,string $title,string $message,?int $request=null): void { $this->model(\Identity_model::class)->insert('notifications',['user_id'=>$user,'title'=>$title,'message'=>$message,'request_id'=>$request]); }
    public function sequence(string $key,string $prefix): string
    {
        $this->model(\Identity_model::class)->increment_sequence([$key]);
        $row=$this->model(\Identity_model::class)->sequence_for_update([$key]);
        return $prefix.str_pad((string)$row['value'],6,'0',STR_PAD_LEFT);
    }
    public function active(string $table,int $id): array { $row=$this->db->row($table,$id,true); if (isset($row['active']) && !(int)$row['active']) throw new Problem('Selected '.$table.' record is inactive.'); if ($table==='users' && !(int)$this->db->row('roles',(int)$row['role_id'])['active']) throw new Problem('The selected user has an inactive role.'); return $row; }
    public function status(string $domain,int $id,string $previous,string $next,string $action,string $remarks=''): void { $this->model(\Identity_model::class)->insert('status_history',['domain'=>$domain,'document_id'=>$id,'previous_status'=>$previous,'new_status'=>$next,'action'=>$action,'user_id'=>$this->id(),'remarks'=>$remarks]); }
}
