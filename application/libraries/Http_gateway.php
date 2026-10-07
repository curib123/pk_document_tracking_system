<?php
declare(strict_types=1);
use Pk\Core\{Context,Database,Database_error,Problem,Rules,Security};
class Http_gateway
{
    // Kani nga service mao ang business-rule layer; controllers thin ra para easy i-follow.

    private Context $ctx;
    public function __construct()
    {
        Security::startSession(); Security::headers();
        $this->ctx=new Context(Database::connect());
        if (!empty($_SESSION['user_id'])) {
            try {
                if (Security::expired($_SESSION,time())) throw new Problem('Your session expired.',401);
                $this->ctx->identify((int)$_SESSION['user_id']);
                if ((int)$this->ctx->user['session_version']!==(int)($_SESSION['session_version'] ?? 0)) throw new Problem('Sign in again.',401);
                $_SESSION['last_seen']=time();
            } catch (Problem $e) {
                if ($e->status!==401) throw $e;
                $this->ctx->user=null; $_SESSION=['csrf'=>bin2hex(random_bytes(32))]; session_regenerate_id(true);
            }
        }
    }
    public function execute(array $definition,string $method,array $input,array $uploads=[]): array
    {
        $mutation=$definition['method']==='POST'; $operation=$definition['op'];
        Security::method($method,$mutation);
        if ($mutation) Security::csrf($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $input=array_replace($input,$definition['fixed']);
        if (!in_array($operation,['session','auth.login'],true) && !$this->ctx->id()) throw new Problem('Your session ended. Sign in again.',401);
        if ($operation==='auth.login' && $this->ctx->id()) throw new Problem('Sign out before signing in as another user.',409);
        if ($this->ctx->id() && (int)$this->ctx->user['require_password_change'] && !in_array($operation,['session','auth.password','auth.logout'],true)) throw new Problem('Change your initial password before using the system.',423);
        $handle=function() use($definition,$input,$uploads): array {
            $class=$definition['service'];
            if (function_exists('get_instance')) {
                $CI=get_instance(); $alias='dts_'.strtolower($class);
                $CI->load->library($class,['context'=>$this->ctx],$alias); $service=$CI->$alias;
            } else $service=new $class($this->ctx);
            $arguments=match($definition['shape']) {
                'none'=>[], 'input'=>[$input],
                'module_input'=>[Rules::text($input,'module',50),$input],
                'module_id'=>[Rules::text($input,'module',50),Rules::id($input)],
                'domain_input'=>[Rules::choice($input,'domain',['softcopy','hardcopy']),$input],
                'upload'=>[$uploads['file'] ?? []], 'id'=>[Rules::id($input)],
                default=>throw new \LogicException('Invalid endpoint configuration.'),
            };
            return $service->{$definition['handler']}(...$arguments);
        };
        // Authentication owns its transaction so failed-login counters are committed.
        if ($operation==='auth.login') return $handle();
        return ($mutation || $operation==='files.download')?$this->ctx->db->transaction($handle):$handle();
    }
    public static function respond(string $path,array $routeParameters=[]): void
    {
        Security::startSession(); Security::headers();
        try {
            $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method==='POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '','application/json')) {
                if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>1024*1024) throw new Problem('Form payload is too large.',413);
                $raw=file_get_contents('php://input',false,null,0,1024*1024+1);
                if (strlen($raw)>1024*1024) throw new Problem('Form payload is too large.',413);
                $input=Rules::json($raw);
            } else $input=$method==='POST'?$_POST:$_GET;
            $registry=new Endpoint_registry();
            $definition=$registry->byPath($path);
            $input=array_replace($input,$definition['fixed'],$routeParameters);
            $operation=$definition['op'];
            Security::method($method,$definition['method']==='POST');
            if ($definition['method']==='POST') Security::csrf($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            $data=(new self())->execute($definition,$method,$input,$_FILES);
            if ($operation==='files.download') {
                session_write_close(); header('Content-Type: '.$data['mime']); header('Content-Length: '.$data['size']);
                header("Content-Disposition: attachment; filename=\"document\"; filename*=UTF-8''".rawurlencode($data['name']));
                readfile($data['path']); return;
            }
            self::json(['ok'=>true,'data'=>$data,'csrf'=>$_SESSION['csrf']]);
        } catch(Problem $e) {
            http_response_code($e->status); self::json(['ok'=>false,'error'=>['message'=>$e->getMessage(),'fields'=>$e->fields],'csrf'=>$_SESSION['csrf'] ?? '']);
        } catch(Database_error $e) {
            $conflict=$e->isConflict();
            error_log('PK database error '.$e->getMessage());
            http_response_code($conflict?409:503);
            self::json(['ok'=>false,'error'=>['message'=>$conflict?'A duplicate, referenced record, or concurrent change prevented this action. Refresh and check your selections.':'Database unavailable or not installed. Ask the administrator to check the private server logs and installation.','fields'=>[]],'csrf'=>$_SESSION['csrf'] ?? '']);
        } catch(\Throwable $e) {
            $reference=bin2hex(random_bytes(5)); error_log('PK error '.$reference.': '.$e);
            http_response_code(500); self::json(['ok'=>false,'error'=>['message'=>'The operation could not be completed. Reference: '.$reference,'fields'=>[]],'csrf'=>$_SESSION['csrf'] ?? '']);
        }
    }
    private static function json(array $data): void { header('Content-Type: application/json; charset=utf-8'); echo Context::json($data); }
}
