<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard extends MY_Controller
{
    public function index() { $this->module_page(null); }
    public function metadata() { $this->endpoint('dashboard/metadata'); }
    public function lookups() { $this->endpoint('dashboard/lookups'); }
    public function data() { $this->endpoint('dashboard/data'); }
}
