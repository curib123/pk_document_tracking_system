<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Documents extends MY_Controller
{
    public function approvers() { $this->endpoint('documents/approvers'); }
}
