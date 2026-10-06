<?php
declare(strict_types=1);
namespace Pk\Services;
use Pk\Core\{Context,Problem,Rules};
final class AuthService
{
    public function __construct(private readonly Context $ctx) {}
    public function login(array $input): array
    {
        $username=Rules::text($input,'username',80); $password=(string)($input['password'] ?? '');
        $key=hash('sha256',strtolower($username).'|'.($_SERVER['REMOTE_ADDR'] ?? 'local'));
        // Commit failed-login counters even when authentication fails.
        $result=$this->ctx->db->transaction(function() use($key,$username,$password) {
            $db=$this->ctx->db;
            $db->query('INSERT IGNORE INTO login_attempts(attempt_key,failures,window_started) VALUES (?,0,NOW())',[$key]);
            $attempt=$db->one('SELECT * FROM login_attempts WHERE attempt_key=? FOR UPDATE',[$key]);
            if (strtotime($attempt['window_started']) < time()-900) { $db->query('UPDATE login_attempts SET failures=0,window_started=NOW() WHERE attempt_key=?',[$key]); $attempt['failures']=0; }
            if ((int)$attempt['failures'] >= 8) return ['error'=>'Too many attempts. Try again after 15 minutes.','status'=>429];
            $user=$db->one('SELECT u.*,r.active role_active FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=?',[$username]);
            $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid=password_verify($password,$user['password_hash'] ?? $dummy);
            if (!$valid || !$user || !(int)$user['active'] || !(int)$user['role_active']) {
                $db->query('UPDATE login_attempts SET failures=failures+1 WHERE attempt_key=?',[$key]);
                $this->ctx->audit('auth','login_failed',null,null,['attempted_username'=>$username]);
                return ['error'=>'Invalid username or password.','status'=>401];
            }
            $db->query('DELETE FROM login_attempts WHERE attempt_key=?',[$key]);
            $this->ctx->identify((int)$user['id']);
            $this->ctx->audit('auth','login',(int)$user['id']);
            return ['user'=>$user];
        });
        if (isset($result['error'])) throw new Problem($result['error'],$result['status']);
        session_regenerate_id(true);
        $_SESSION=['user_id'=>(int)$result['user']['id'],'session_version'=>(int)$result['user']['session_version'],'csrf'=>bin2hex(random_bytes(32)),'last_seen'=>time()];
        return ['user'=>$this->ctx->safeUser(),'permissions'=>$this->ctx->permissions()];
    }
    public function changePassword(array $input): array
    {
        if (!$this->ctx->id()) throw new Problem('Sign in first.',401);
        $db=$this->ctx->db;
        $user=$db->lock('users',$this->ctx->id());
        if (!password_verify((string)($input['current_password'] ?? ''),$user['password_hash'])) throw new Problem('Current password is incorrect.');
        $value=(string)($input['new_password'] ?? '');
        if (password_verify($value,$user['password_hash'])) throw new Problem('Choose a different password.');
        $hash=Rules::password($value,(string)($input['confirm_password'] ?? ''));
        $db->update('users',$this->ctx->id(),['password_hash'=>$hash,'require_password_change'=>0,'session_version'=>(int)$user['session_version']+1]);
        $_SESSION['session_version']=(int)$user['session_version']+1;
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        session_regenerate_id(true);
        $this->ctx->audit('auth','password_changed',$this->ctx->id());
        return ['message'=>'Password changed. Other sessions have been invalidated.'];
    }
    public function logout(): array
    {
        $this->ctx->audit('auth','logout',$this->ctx->id());
        $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        return ['message'=>'Signed out.'];
    }
}
