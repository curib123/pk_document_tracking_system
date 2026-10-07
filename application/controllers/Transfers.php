<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Transfers extends MY_Controller
{
    public function index() { $this->module_page('transfers'); }
    public function datatable() { $this->endpoint('transfers/datatable'); }
    public function view($id=null) { $this->endpoint('transfers/view', $id===null?[]:['id'=>$id]); }
    public function dispatch() { $this->endpoint('transfers/dispatch'); }
    public function receive() { $this->endpoint('transfers/receive'); }
    public function cancel() { $this->endpoint('transfers/cancel'); }
}
