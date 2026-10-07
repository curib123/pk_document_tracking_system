<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Files extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('files');
    }
    public function datatable()
    {
        $this->endpoint('files/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('files/view', $id===null?[]:['id'=>$id]);
    }
    public function download()
    {
        $this->endpoint('files/download');
    }
    public function upload()
    {
        $this->endpoint('files/upload');
    }
    public function attach()
    {
        $this->endpoint('files/attach');
    }
    public function decide()
    {
        $this->endpoint('files/decide');
    }
    public function artifact()
    {
        $this->endpoint('files/artifact');
    }
}
