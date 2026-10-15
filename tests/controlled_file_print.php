<?php
declare(strict_types=1);
define('PK_ROOT', dirname(__DIR__));
define('APPPATH', PK_ROOT . '/application/');
define('BASEPATH', __DIR__);
class MY_Controller {
    protected $user = ['id' => 1];
    protected function authenticate() {}
    protected function require_permission($module, $action = 'view') {}
}
function show_error($message, $status = 500) { throw new RuntimeException($message, $status); }
function show_404() { throw new RuntimeException('Not found', 404); }
require APPPATH . 'controllers/Files.php';
$reflection = new ReflectionClass(Files::class);
$controller = $reflection->newInstanceWithoutConstructor();
$controller->File_model = new class {
    public $permitted = true;
    public $file;
    public function approved_file($id) { return $this->file; }
    public function allowed($user, $file) { return $this->permitted; }
};
$controller->load = new class {
    public $template;
    public $data;
    public function view($template, $data) { $this->template = $template; $this->data = $data; }
};
$directory = PK_ROOT . '/storage/documents';
if (!is_dir($directory)) mkdir($directory, 0700, TRUE);
$path = $directory . '/' . bin2hex(random_bytes(32));
file_put_contents($path, '<script>Untrusted text</script>');
$completed = false;
register_shutdown_function(static function () use ($path, &$completed) {
    if (is_file($path)) unlink($path);
    if (!$completed) throw new RuntimeException('Print must return an HTML viewer instead of exiting with raw file bytes.');
});
$controller->File_model->file = ['id' => 3, 'original_name' => 'controlled.txt', 'mime_type' => 'text/plain',
    'storage_name' => basename($path), 'document_id' => 5];
$controller->print_file(3);
if ($controller->load->template !== 'pages/files/print' || $controller->load->data['text'] !== file_get_contents($path))
    throw new RuntimeException('Print must render a same-origin HTML viewer, including plain text.');
$controller->File_model->permitted = false;
try { $controller->print_file(3); throw new LogicException('Forbidden file was printed.'); }
catch (RuntimeException $e) { if ($e->getCode() !== 403) throw $e; }
$controller->File_model->permitted = true;
$controller->File_model->file['storage_name'] = '../outside.txt';
try { $controller->print_file(3); throw new LogicException('Unsafe storage path was printed.'); }
catch (RuntimeException $e) { if ($e->getCode() !== 404) throw $e; }
$completed = true;
echo "Protected HTML print viewer, text and storage validation passed.\n";
