<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Auth extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page(null);
    }
    public function session()
    {
        $this->endpoint('auth/session');
    }
    public function login()
    {
        $this->endpoint('auth/login');
    }
    public function password()
    {
        $this->endpoint('auth/password');
    }
    public function logout()
    {
        $this->endpoint('auth/logout');
    }
}
