<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notifications extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('notifications');
    }
    public function datatable()
    {
        $this->endpoint('notifications/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('notifications/view', $id===null?[]:['id'=>$id]);
    }
    public function read()
    {
        $this->endpoint('notifications/read');
    }
}
