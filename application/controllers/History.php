<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class History extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('history');
    }
    public function datatable()
    {
        $this->endpoint('history/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('history/view', $id===null?[]:['id'=>$id]);
    }
}
