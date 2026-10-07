<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class My_requests extends MY_Controller
{
    public function index() { $this->module_page('my_requests'); }
    public function datatable() { $this->endpoint('my_requests/datatable'); }
    public function view($id=null) { $this->endpoint('my_requests/view', $id===null?[]:['id'=>$id]); }
}
