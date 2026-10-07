<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Compatibility adapter for bookmarked API clients; the UI uses module routes. */
class Api extends CI_Controller
{
    public function index() { Http_gateway::respond(); }
}
