<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Roles extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('roles');
    }
    public function datatable()
    {
        $this->endpoint('roles/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('roles/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('roles/save');
    }
    public function delete()
    {
        $this->endpoint('roles/delete');
    }
    public function permissions()
    {
        $this->endpoint('roles/permissions');
    }
}
