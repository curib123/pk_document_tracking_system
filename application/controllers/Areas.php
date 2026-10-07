<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Areas extends MY_Controller
{
    public function index() { $this->module_page('areas'); }
    public function datatable() { $this->endpoint('areas/datatable'); }
    public function view($id=null) { $this->endpoint('areas/view', $id===null?[]:['id'=>$id]); }
    public function save() { $this->endpoint('areas/save'); }
    public function delete() { $this->endpoint('areas/delete'); }
}
