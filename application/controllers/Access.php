<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Access extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('access');
    }
    public function datatable()
    {
        $this->endpoint('access/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('access/view', $id===null?[]:['id'=>$id]);
    }
    public function revoke()
    {
        $this->endpoint('access/revoke');
    }
}
