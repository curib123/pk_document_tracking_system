<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Sequences extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('sequences');
    }
    public function datatable()
    {
        $this->endpoint('sequences/datatable');
    }
}
