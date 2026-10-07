<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Softcopy extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('softcopy');
    }
    public function datatable()
    {
        $this->endpoint('softcopy/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('softcopy/view', $id===null?[]:['id'=>$id]);
    }
    public function direct()
    {
        $this->endpoint('softcopy/direct');
    }
}
