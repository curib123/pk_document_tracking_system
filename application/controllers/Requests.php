<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Requests extends MY_Controller
{
    public function index() { $this->module_page('requests'); }
    public function datatable() { $this->endpoint('requests/datatable'); }
    public function view($id=null) { $this->endpoint('requests/view', $id===null?[]:['id'=>$id]); }
    public function save() { $this->endpoint('requests/save'); }
    public function submit() { $this->endpoint('requests/submit'); }
    public function decide() { $this->endpoint('requests/decide'); }
    public function cancel() { $this->endpoint('requests/cancel'); }
}
