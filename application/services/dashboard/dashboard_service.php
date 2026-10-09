<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard_service
{
    public function summary($user)
    {
        $ci =& get_instance();
        $ci->load->model('Dashboard_model');
        return $ci->Dashboard_model->summary($user);
    }
}
