<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Softcopy extends MY_Controller
{
    public function index() { $this->module_page('softcopy'); }
    public function datatable() { $this->endpoint('softcopy/datatable'); }
    public function view($id=null) { $this->endpoint('softcopy/view', $id===null?[]:['id'=>$id]); }
    public function direct() { $this->endpoint('softcopy/direct'); }
}
