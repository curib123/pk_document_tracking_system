<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Workflows extends MY_Controller
{
    public function index() { $this->module_page('workflows'); }
    public function datatable() { $this->endpoint('workflows/datatable'); }
    public function view($id=null) { $this->endpoint('workflows/view', $id===null?[]:['id'=>$id]); }
    public function save() { $this->endpoint('workflows/save'); }
    public function version() { $this->endpoint('workflows/version'); }
    public function publish() { $this->endpoint('workflows/publish'); }
    public function set_default() { $this->endpoint('workflows/set_default'); }
    public function reassign() { $this->endpoint('workflows/reassign'); }
}
