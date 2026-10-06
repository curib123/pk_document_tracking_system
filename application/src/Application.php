<?php
declare(strict_types=1);
namespace Pk;
use Pk\Core\{Context,Database,Problem,Rules,Security};
use Pk\Services\{AuthService,CatalogService,DocumentService,FileService,ReadService,RequestService,TransferService,WorkflowService};
final class Application
{
    private const READS=['session','metadata','list','detail','lookups','dashboard','files.download'];
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
    public function dispatch(string $operation,string $method,array $input,array $uploads=[]): array
    {
        $mutation=!in_array($operation,self::READS,true); Security::method($method,$mutation);
        if ($mutation) Security::csrf($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($operation==='session') return ['user'=>$this->ctx->safeUser(),'permissions'=>$this->ctx->id()?$this->ctx->permissions():[]];
        if ($operation==='auth.login') {
            if ($this->ctx->id()) throw new Problem('Sign out before signing in as another user.',409);
            return (new AuthService($this->ctx))->login($input); // Own transaction preserves failed-login counters.
        }
        if (!$this->ctx->id()) throw new Problem('Your session ended. Sign in again.',401);
        if ((int)$this->ctx->user['require_password_change'] && !in_array($operation,['auth.password','auth.logout'],true)) throw new Problem('Change your initial password before using the system.',423);
        $handle=function() use($operation,$input,$uploads): array {
            $read=new ReadService($this->ctx);
            return match($operation) {
                'metadata'=>$read->metadata(),
                'list'=>$read->listing(Rules::text($input,'module',50),$input),
                'detail'=>$read->detail(Rules::text($input,'module',50),Rules::id($input)),
                'lookups'=>$read->lookups($input),
                'dashboard'=>$read->dashboard(),
                'auth.password'=>(new AuthService($this->ctx))->changePassword($input),
                'auth.logout'=>(new AuthService($this->ctx))->logout(),
                'catalog.save'=>(new CatalogService($this->ctx))->save(Rules::text($input,'module',50),$input),
                'catalog.delete'=>(new CatalogService($this->ctx))->delete(Rules::text($input,'module',50),$input),
                'users.reset_password'=>(new CatalogService($this->ctx))->resetPassword($input),
                'roles.permissions'=>(new CatalogService($this->ctx))->permissions($input),
                'documents.direct'=>(new DocumentService($this->ctx))->direct(Rules::choice($input,'domain',['softcopy','hardcopy']),$input),
                'documents.approvers'=>(new DocumentService($this->ctx))->configureApprovers($input),
                'requests.save'=>(new RequestService($this->ctx))->save($input),
                'requests.submit'=>(new RequestService($this->ctx))->submit($input),
                'requests.decide'=>(new RequestService($this->ctx))->decide($input),
                'requests.cancel'=>(new RequestService($this->ctx))->cancel($input),
                'workflows.save'=>(new WorkflowService($this->ctx))->save($input),
                'workflows.version'=>(new WorkflowService($this->ctx))->version($input),
                'workflows.publish'=>(new WorkflowService($this->ctx))->publish($input),
                'workflows.reassign'=>(new WorkflowService($this->ctx))->reassign($input),
                'transfers.dispatch'=>(new TransferService($this->ctx))->dispatch($input),
                'transfers.receive'=>(new TransferService($this->ctx))->receive($input),
                'transfers.cancel'=>(new TransferService($this->ctx))->cancel($input),
                'access.revoke'=>(new RequestService($this->ctx))->revoke($input),
                'assignments.remove'=>(new RequestService($this->ctx))->unassign($input),
                'files.upload'=>(new FileService($this->ctx))->upload($uploads['file'] ?? []),
                'files.attach'=>(new FileService($this->ctx))->attach($input),
                'files.decide'=>(new FileService($this->ctx))->decide($input),
                'files.artifact'=>(new FileService($this->ctx))->artifact($input),
                'files.download'=>(new FileService($this->ctx))->download(Rules::id($input)),
                'notifications.read'=>$read->readNotification($input),
                default=>throw new Problem('Unknown operation.',404),
            };
        };
        return ($mutation || $operation==='files.download')?$this->ctx->db->transaction($handle):$handle();
    }
    public static function respond(): void
    {
        Security::startSession(); Security::headers();
        try {
            $method=$_SERVER['REQUEST_METHOD'] ?? 'GET'; $operation=Rules::text($_GET,'op',80);
            if ($method==='POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '','application/json')) {
                if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>1024*1024) throw new Problem('Form payload is too large.',413);
                $raw=file_get_contents('php://input',false,null,0,1024*1024+1);
                if (strlen($raw)>1024*1024) throw new Problem('Form payload is too large.',413);
                $input=Rules::json($raw);
            } else $input=$method==='POST'?$_POST:$_GET;
            $data=(new self())->dispatch($operation,$method,$input,$_FILES);
            if ($operation==='files.download') {
                session_write_close(); header('Content-Type: '.$data['mime']); header('Content-Length: '.$data['size']);
                header("Content-Disposition: attachment; filename=\"document\"; filename*=UTF-8''".rawurlencode($data['name']));
                readfile($data['path']); return;
            }
            self::json(['ok'=>true,'data'=>$data,'csrf'=>$_SESSION['csrf']]);
        } catch(Problem $e) {
            http_response_code($e->status); self::json(['ok'=>false,'error'=>['message'=>$e->getMessage(),'fields'=>$e->fields],'csrf'=>$_SESSION['csrf'] ?? '']);
        } catch(\PDOException $e) {
            $conflict=in_array((string)$e->getCode(),['23000','40001'],true);
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
