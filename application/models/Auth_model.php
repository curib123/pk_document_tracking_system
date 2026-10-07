<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Problem};

/** Auth persistence using the native CodeIgniter MySQLi Query Builder. */
class Auth_model extends Repository_model
{
public function ensure_attempt(array $params = []): bool
    {
        $this->store->require_transaction();
        $this->db->reset_query()->set(['attempt_key'=>$params[0], 'failures'=>0])->set('window_started','NOW()',false);
        // Atomic insert-if-missing; a SELECT then INSERT would race at first login.
        $sql=$this->db->get_compiled_insert('login_attempts');
        return $this->written($this->db->query($sql.' ON DUPLICATE KEY UPDATE attempt_key=attempt_key'));
    }
    public function lock_attempt(array $params = []): ?array
    {
        $this->db->reset_query()->from('login_attempts')->where('attempt_key',$params[0])->limit(1);
        return $this->first(true);
    }
    public function reset_attempt(array $params = []): bool
    {
        $this->db->reset_query()->where('attempt_key',$params[0])->set('failures',0)->set('window_started','NOW()',false);
        return $this->written($this->db->update('login_attempts'));
    }
    public function user_by_username(array $params = []): ?array
    {
        $this->db->reset_query()->select('u.*, r.active AS role_active')->from('users u')
            ->join('roles r','r.id = u.role_id')->where('u.username',$params[0])->limit(1);
        return $this->first();
    }
    public function increment_attempt(array $params = []): bool
    {
        $this->db->reset_query()->where('attempt_key',$params[0])->set('failures','failures + 1',false);
        return $this->written($this->db->update('login_attempts'));
    }
    public function clear_attempt(array $params = []): bool
    {
        $this->db->reset_query()->where('attempt_key',$params[0]);
        return $this->written($this->db->delete('login_attempts'));
    }
}
