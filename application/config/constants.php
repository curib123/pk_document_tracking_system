<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// CI3 runtime constants; private writes use stricter permissions than public reads.
defined('SHOW_DEBUG_BACKTRACE') OR define('SHOW_DEBUG_BACKTRACE', false);
defined('FILE_READ_MODE') OR define('FILE_READ_MODE', 0644);
defined('FILE_WRITE_MODE') OR define('FILE_WRITE_MODE', 0600);
defined('DIR_READ_MODE') OR define('DIR_READ_MODE', 0755);
defined('DIR_WRITE_MODE') OR define('DIR_WRITE_MODE', 0700);

defined('EXIT_SUCCESS') OR define('EXIT_SUCCESS', 0);
defined('EXIT_ERROR') OR define('EXIT_ERROR', 1);
defined('EXIT_CONFIG') OR define('EXIT_CONFIG', 3);
defined('EXIT_UNKNOWN_FILE') OR define('EXIT_UNKNOWN_FILE', 4);
defined('EXIT_UNKNOWN_CLASS') OR define('EXIT_UNKNOWN_CLASS', 5);
defined('EXIT_UNKNOWN_METHOD') OR define('EXIT_UNKNOWN_METHOD', 6);
defined('EXIT_USER_INPUT') OR define('EXIT_USER_INPUT', 7);
defined('EXIT_DATABASE') OR define('EXIT_DATABASE', 8);
defined('EXIT__AUTO_MIN') OR define('EXIT__AUTO_MIN', 9);
defined('EXIT__AUTO_MAX') OR define('EXIT__AUTO_MAX', 125);
