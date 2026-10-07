<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class My_tasks extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('my_tasks');
    }
    public function datatable()
    {
        $this->endpoint('my_tasks/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('my_tasks/view', $id===null?[]:['id'=>$id]);
    }
}
