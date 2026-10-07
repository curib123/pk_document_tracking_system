<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Permissions extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('permissions');
    }
    public function datatable()
    {
        $this->endpoint('permissions/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('permissions/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('permissions/save');
    }
    public function delete()
    {
        $this->endpoint('permissions/delete');
    }
}
