<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Categories extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('categories');
    }
    public function datatable()
    {
        $this->endpoint('categories/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('categories/view', $id===null?[]:['id'=>$id]);
    }
    public function save()
    {
        $this->endpoint('categories/save');
    }
    public function delete()
    {
        $this->endpoint('categories/delete');
    }
}
