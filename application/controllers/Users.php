<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Users extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('users');
    }
    public function datatable()
    {
        $this->endpoint('users/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('users/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('users/save');
    }
    public function delete()
    {
        $this->endpoint('users/delete');
    }
    public function reset_password()
    {
        $this->endpoint('users/reset_password');
    }
}
