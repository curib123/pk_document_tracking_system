<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class Auth_service
{
    // Kani nga service mao ang business-rule layer; controllers thin ra para easy i-follow.
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    public function session(): array { return ['user'=>$this->ctx->safeUser(),'permissions'=>$this->ctx->id()?$this->ctx->permissions():[]]; }
    public function login(array $input): array
    {
        $username=Rules::text($input,'username',80); $password=(string)($input['password'] ?? '');
        $key=hash('sha256',strtolower($username).'|'.($_SERVER['REMOTE_ADDR'] ?? 'local'));
        // Commit failed-login counters even when authentication fails.
        $result=$this->ctx->model(\Auth_model::class)->transaction(function() use($key,$username,$password) {
            $db=$this->ctx->model(\Auth_model::class);
            $db->ensure_attempt([$key]);
            $attempt=$db->lock_attempt([$key]);
            if (strtotime($attempt['window_started']) < time()-900) { $db->reset_attempt([$key]); $attempt['failures']=0; }
            if ((int)$attempt['failures'] >= 8) return ['error'=>'Too many attempts. Try again after 15 minutes.','status'=>429];
            $user=$db->user_by_username([$username]);
            $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid=password_verify($password,$user['password_hash'] ?? $dummy);
            if (!$valid || !$user || !(int)$user['active'] || !(int)$user['role_active']) {
                $db->increment_attempt([$key]);
                $this->ctx->audit('auth','login_failed',null,null,['attempted_username'=>$username]);
                return ['error'=>'Invalid username or password.','status'=>401];
            }
            $db->clear_attempt([$key]);
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
        $db=$this->ctx->model(\Auth_model::class);
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
