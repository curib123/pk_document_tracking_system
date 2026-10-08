<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// XAMPP-friendly CI3 config; security/session behavior for API requests lives in Security.
$config['base_url'] = pk_base_url();
$config['index_page'] = 'index.php';
$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix'] = '';

$config['language'] = 'english';
$config['charset'] = 'UTF-8';

$config['enable_hooks'] = false;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = false;
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';

$config['enable_query_strings'] = false;
$config['controller_trigger'] = 'c';
$config['function_trigger'] = 'm';
$config['directory_trigger'] = 'd';
$config['allow_get_array'] = true;

$config['log_threshold'] = 1;
$config['log_path'] = PK_ROOT . '/storage/logs/';
$config['log_file_extension'] = 'log';
$config['log_file_permissions'] = 0600;
$config['log_date_format'] = 'Y-m-d H:i:s';

$config['error_views_path'] = APPPATH . 'views/errors/';
$config['cache_path'] = '';
$config['cache_query_string'] = false;
$config['encryption_key'] = '';

$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'pk_dts_ci_session';
$config['sess_expiration'] = 1800;
$config['sess_save_path'] = PK_ROOT . '/storage/sessions';
$config['sess_samesite'] = 'Strict';
$config['sess_match_ip'] = false;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = true;

$config['cookie_prefix'] = '';
$config['cookie_domain'] = '';
$config['cookie_path'] = '/';
$config['cookie_secure'] =
    !empty($_SERVER['HTTPS'])
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$config['cookie_httponly'] = true;
$config['cookie_samesite'] = 'Strict';

$config['standardize_newlines'] = false;
$config['global_xss_filtering'] = false;

// Shared synchronizer token: CI session for browser forms and legacy JSON transport.
$config['csrf_protection'] = false;
$config['csrf_token_name'] = 'unused_ci_csrf';
$config['csrf_cookie_name'] = 'unused_ci_csrf';
$config['csrf_expire'] = 1800;
$config['csrf_regenerate'] = true;
$config['csrf_exclude_uris'] = [];

$config['compress_output'] = false;
$config['time_reference'] = 'local';
$config['rewrite_short_tags'] = false;
$config['proxy_ips'] = '';
