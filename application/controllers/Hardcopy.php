<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Hardcopy extends MY_Controller
{
    public function index() { $this->module_page('hardcopy'); }
    public function datatable() { $this->endpoint('hardcopy/datatable'); }
    public function view($id=null) { $this->endpoint('hardcopy/view', $id===null?[]:['id'=>$id]); }
    public function direct() { $this->endpoint('hardcopy/direct'); }
}
