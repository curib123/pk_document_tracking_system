<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Locations extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('locations');
    }
    public function datatable()
    {
        $this->endpoint('locations/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('locations/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('locations/save');
    }
    public function delete()
    {
        $this->endpoint('locations/delete');
    }
}
