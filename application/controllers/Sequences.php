<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Sequences extends MY_Controller
{
    public function index() { $this->module_page('sequences'); }
    public function datatable() { $this->endpoint('sequences/datatable'); }
}
