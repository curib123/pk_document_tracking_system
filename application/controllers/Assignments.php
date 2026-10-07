<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Assignments extends MY_Controller
{
    public function index() { $this->module_page('assignments'); }
    public function datatable() { $this->endpoint('assignments/datatable'); }
    public function view($id=null) { $this->endpoint('assignments/view', $id===null?[]:['id'=>$id]); }
    public function remove() { $this->endpoint('assignments/remove'); }
}
