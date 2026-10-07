<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$route['default_controller']='app';
$route['api']='api/index';
$route['404_override']='';
$route['translate_uri_dashes']=false;

$route['login']='auth/index';
