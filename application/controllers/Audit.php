<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Audit extends MY_Controller
{
    public function index() { $this->module_page('audit'); }
    public function datatable() { $this->endpoint('audit/datatable'); }
    public function view($id=null) { $this->endpoint('audit/view', $id===null?[]:['id'=>$id]); }
}
