<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page(null);
    }
    public function metadata()
    {
        $this->endpoint('dashboard/metadata');
    }
    public function lookups()
    {
        $this->endpoint('dashboard/lookups');
    }
    public function data()
    {
        $this->endpoint('dashboard/data');
    }
}
