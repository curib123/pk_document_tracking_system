<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Transfers extends MY_Controller
{
    // Ari ra ang HTTP handoff; business rules naa sa service para simple ang controller.
    public function index()
    {
        $this->module_page('transfers');
    }
    public function datatable()
    {
        $this->endpoint('transfers/datatable');
    }
    public function view($id=null)
    {
        $this->endpoint('transfers/view', $id===null?[]:['id'=>$id]);
    }
    public function direct()
    {
        $this->endpoint('transfers/direct');
    }
    public function dispatch()
    {
        $this->endpoint('transfers/dispatch');
    }
    public function receive()
    {
        $this->endpoint('transfers/receive');
    }
    public function cancel()
    {
        $this->endpoint('transfers/cancel');
    }
}
