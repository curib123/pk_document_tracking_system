<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
class LogoutRedirect extends RuntimeException {}
function redirect($path){throw new LogoutRedirect($path);}
function site_url($path){return 'http://localhost/'.$path;}
function show_error($message,$status){throw new RuntimeException($message,$status);}
class MY_Controller {
    public $input,$session,$output,$security,$user;
    protected function confirmed(){
        if($this->input->method(TRUE)!=='POST'||$this->input->post('confirmed')!=='yes')
            throw new RuntimeException('Confirmation required',400);
    }
}
class LogoutInput {
    public $ajax=TRUE,$verb='POST',$confirmed='yes',$status=NULL;
    public function is_ajax_request(){return $this->ajax;}
    public function method($upper){return $this->verb;}
    public function post($name){return $this->confirmed;}
    public function get($name,$clean=FALSE){return $this->status;}
}
class LogoutSession {
    public $destroyed=FALSE,$fail=FALSE;
    public function sess_destroy(){if(!$this->fail){session_destroy();$this->destroyed=TRUE;}}
}
class LogoutOutput {
    public $headers=[],$type,$body;
    public function set_header($value){$this->headers[]=$value;return $this;}
    public function set_content_type($value){$this->type=$value;return $this;}
    public function set_output($value){$this->body=$value;return $this;}
}
class LogoutSecurity {
    public function get_csrf_token_name(){return 'pk_csrf_token';}
    public function get_csrf_hash(){return 'fresh-token';}
}
require APPPATH.'controllers/Auth.php';
function logout_check($condition,$message){if(!$condition)throw new RuntimeException($message);}
function logout_fixture(){
    $auth=new Auth();$auth->input=new LogoutInput();$auth->session=new LogoutSession();
    $auth->output=new LogoutOutput();$auth->security=new LogoutSecurity();$auth->user=['id'=>7];
    session_start();$_SESSION=['user_id'=>7];return $auth;
}
$enhanced=logout_fixture();
try{$enhanced->logout();}catch(LogoutRedirect $e){}
logout_check($enhanced->session->destroyed,'Logout must invalidate the session immediately');
logout_check($enhanced->output->type==='application/json','Enhanced logout must return a verifiable result');
logout_check(json_decode($enhanced->output->body,TRUE)===['logged_out'=>TRUE,'login_url'=>'http://localhost/login'],
    'Successful logout response must identify the existing login route');
logout_check($_SESSION===[],'Authenticated request state must be cleared');
logout_check(in_array('Cache-Control: no-store, private',$enhanced->output->headers,TRUE),'Logout results must not be cached');
$native=logout_fixture();$native->input->ajax=FALSE;
try{$native->logout();throw new RuntimeException('Native logout needs its original redirect');}
catch(LogoutRedirect $e){logout_check($e->getMessage()==='login','Native logout routing must remain unchanged');}
$failed=logout_fixture();$failed->session->fail=TRUE;
try{$failed->logout();throw new RuntimeException('Failed invalidation must not claim success');}
catch(RuntimeException $e){logout_check($e->getCode()===500,'Failed invalidation must report an error');}
session_destroy();
$unconfirmed=logout_fixture();$unconfirmed->input->confirmed='no';
try{$unconfirmed->logout();}catch(RuntimeException $e){logout_check($e->getCode()===400,'Confirmation remains mandatory');}
logout_check(!$unconfirmed->session->destroyed,'Unconfirmed requests cannot invalidate sessions');session_destroy();
$probe=logout_fixture();$probe->input->status='1';$probe->input->verb='GET';
$probe->index();$state=json_decode($probe->output->body,TRUE);
logout_check($state['authenticated']===TRUE && $state['csrf']['value']==='fresh-token','Status must reflect current identity and refresh stale CSRF');
logout_check(!$probe->session->destroyed,'Status probes must not invalidate active sessions');
$probe->user=NULL;$probe->index();
logout_check(json_decode($probe->output->body,TRUE)['authenticated']===FALSE,'Expired identity must be reported without a redirect loop');
session_destroy();echo "Logout invalidation, native routing, failures, confirmation and session-status checks passed.\n";
