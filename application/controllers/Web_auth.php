<?php
 defined('BASEPATH') OR exit('No direct script access allowed');

// Native CI3 route bridge: all module logic stays in the feature controller.
require_once APPPATH . 'modules/auth/controllers/Auth_controller.php';

class Web_auth extends Auth_controller
{
}
