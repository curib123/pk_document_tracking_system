<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
class MY_Controller {
    protected $user = ['id' => 2];
    public $denied = TRUE;
    public $checks = [];
    protected function authenticate() {}
    protected function require_permission($module, $action = 'view') {
        $this->checks[] = [$module, $action];
        if ($this->denied) show_error('Permission denied.', 403);
    }
}
function show_error($message, $status = 500) { throw new RuntimeException($message, $status); }
require APPPATH . 'controllers/Files.php';
$controller = (new ReflectionClass(Files::class))->newInstanceWithoutConstructor();
$controller->File_model = new class {
    public $reads = 0;
    function approved_file($id) { $this->reads++; return NULL; }
};
foreach (['print_file' => 'print', 'print_content' => 'print', 'download' => 'download'] as $method => $permission) {
    try { $controller->$method(123); throw new LogicException('Denied action was allowed.'); }
    catch (RuntimeException $error) { if ($error->getCode() !== 403) throw $error; }
    if (end($controller->checks) !== ['files', $permission] || $controller->File_model->reads !== 0)
        throw new RuntimeException($method . ' must reject a denied action before looking up the file.');
}
echo "Independent file print/download permissions block direct URLs before reading files.\n";
