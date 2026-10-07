<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Audit extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('audit');
    }
    public function datatable()
    {
        $this->endpoint('audit/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('audit/view', $id===null?[]:['id'=>$id]);
    }
}
