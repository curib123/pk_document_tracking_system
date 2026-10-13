<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');

class LoginRedirect extends RuntimeException {}
function redirect($path) { throw new LoginRedirect($path); }
class MY_Controller {
    public $input; public $db; public $session; public $Identity_model; public $user;
    public $notices=[];
    protected function notice($message,$type='success') { $this->notices[]=[$message,$type]; }
}
class LoginInput {
    public $password='test-password';
    public function method($upper) { return 'POST'; }
    public function post($key,$clean=FALSE) { return $key==='login'?'test-user':$this->password; }
    public function ip_address() { return '127.0.0.1'; }
}
class LoginDB {
    public $attempt=NULL;
    public function get_where($table,$where) { return $this; }
    public function row_array() { return $this->attempt; }
    public function where($key,$value) { return $this; }
    public function set($key,$value,$escape=TRUE) { return $this; }
    public function delete($table) {}
    public function replace($table,$values) {}
    public function update($table) {}
}
class LoginSession {
    public $data=[]; public $regenerated=FALSE;
    public function sess_regenerate($destroy) { $this->regenerated=$destroy; }
    public function set_userdata($values) { $this->data=array_merge($this->data,$values); }
    public function set_flashdata($key,$value) { $this->data[$key]=$value; }
}
class LoginIdentity {
    public $record;
    public function find_login($username) { return $this->record; }
}
require_once APPPATH.'controllers/Auth.php';
function loginCheck($passed,$message) { if (!$passed) throw new RuntimeException($message); }
function loginFixture(): Auth {
    $auth=new Auth();
    $auth->input=new LoginInput(); $auth->db=new LoginDB(); $auth->session=new LoginSession();
    $auth->Identity_model=new LoginIdentity();
    $auth->Identity_model->record=['id'=>8,'session_version'=>2,
        'require_password_change'=>0,'password_hash'=>password_hash('test-password',PASSWORD_DEFAULT)];
    return $auth;
}
function runLogin(Auth $auth,string $target): void {
    try { $auth->login(); } catch (LoginRedirect $redirect) {
        loginCheck($redirect->getMessage()===$target,'Authentication redirect must remain '.$target);
        return;
    }
    throw new RuntimeException('Existing redirect must terminate login');
}
$success=loginFixture();runLogin($success,'dashboard');
loginCheck(($success->session->data['login_transition']??FALSE)===TRUE,
    'Successful authentication must arm the one-time dashboard transition');
loginCheck($success->session->regenerated && $success->session->data['user_id']===8 &&
    $success->session->data['session_version']===2,'Existing authenticated session behavior must be preserved');
$failure=loginFixture();$failure->input->password='incorrect';runLogin($failure,'login');
loginCheck(!isset($failure->session->data['login_transition']) && !$failure->session->regenerated,
    'Incorrect credentials must never arm an animation or authenticated session');
$throttled=loginFixture();$throttled->db->attempt=['window_started'=>date('Y-m-d H:i:s'),'failures'=>10];
runLogin($throttled,'login');
loginCheck(!isset($throttled->session->data['login_transition']),'Throttled login must never arm the transition');
$setup=loginFixture();$setup->Identity_model->record['require_password_change']=1;runLogin($setup,'change-password');
loginCheck(!isset($setup->session->data['login_transition']),'Account setup must retain its existing immediate routing');
echo "Successful-only login handoff, failed credentials, throttling and setup routing passed.\n";
