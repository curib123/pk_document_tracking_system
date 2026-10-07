<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Shared HTTP mechanics only. Domain decisions belong to service libraries. */
class MY_Controller extends CI_Controller
{
    // Shared HTTP helper ra ni; ayaw diri ibutang ang domain/business rules.
    protected function endpoint(string $path,array $parameters=[]): void
    {
        Http_gateway::respond($path,$parameters);
    }
    protected function module_page(?string $module=null): void
    {
        \Pk\Core\Security::headers();
        $this->load->helper('ui');
        $data=['initial_module'=>$module ?? '','page_title'=>'PK Document Tracking System'];
        $this->load->view('templates/header',$data);
        $this->load->view('modules/index',$data);
        $this->load->view('templates/footer',$data);
    }
}
