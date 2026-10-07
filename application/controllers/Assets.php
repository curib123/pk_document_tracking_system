<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Assets extends MY_Controller
{
    public function index() { $this->module_page('assets'); }
    public function datatable() { $this->endpoint('assets/datatable'); }
    public function view($id=null) { $this->endpoint('assets/view', $id===null?[]:['id'=>$id]); }
    public function save() { $this->endpoint('assets/save'); }
    public function delete() { $this->endpoint('assets/delete'); }
}
