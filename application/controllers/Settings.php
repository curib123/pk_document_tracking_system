<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Settings extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('settings');
    }
    public function datatable()
    {
        $this->endpoint('settings/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('settings/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('settings/save');
    }
}
