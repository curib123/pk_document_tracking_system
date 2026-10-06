<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class App extends CI_Controller
{
    public function index()
    {
        \Pk\Core\Security::headers();
        $this->load->view('app');
    }
}
