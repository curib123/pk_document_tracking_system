<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Disposals extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('disposals');
    }
    public function datatable()
    {
        $this->endpoint('disposals/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('disposals/view', $id===null?[]:['id'=>$id]);
    }
}
