<?php
 defined('BASEPATH') OR exit('No direct script access allowed');

// Native CI3 route bridge: all module logic stays in the feature controller.
require_once APPPATH . 'modules/catalog/controllers/Catalog_controller.php';

class Web_catalog extends Catalog_controller
{
}
