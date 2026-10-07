<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Specifics extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('specifics');
    }
    public function datatable()
    {
        $this->endpoint('specifics/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('specifics/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('specifics/save');
    }
    public function delete()
    {
        $this->endpoint('specifics/delete');
    }
}
