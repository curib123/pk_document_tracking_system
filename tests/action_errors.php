<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
class CI_Controller {}
function log_message($level, $message) { $GLOBALS['error_logs'][] = [$level, $message]; }
require dirname(__DIR__) . '/application/core/MY_Controller.php';
class ErrorHarness extends MY_Controller {
    public $notices = [];
    public function __construct() {}
    protected function notice($message, $type = 'success') { $this->notices[] = [$message, $type]; }
    public function fail(Throwable $error) { $this->error_notice($error); }
}
$harness = new ErrorHarness();
$harness->fail(new DomainException('This request was updated. Refresh and try again.'));
$harness->fail(new RuntimeException('Database secret details'));
if ($harness->notices[0] !== ['This request was updated. Refresh and try again.', 'danger'])
    throw new RuntimeException('Validation feedback must remain actionable.');
if ($harness->notices[1][1] !== 'danger' || strpos($harness->notices[1][0], 'secret') !== FALSE)
    throw new RuntimeException('Internal error details must never be displayed to users.');
if (count($GLOBALS['error_logs'] ?? []) !== 1 || strpos($GLOBALS['error_logs'][0][1], 'Database secret details') === FALSE)
    throw new RuntimeException('Unexpected failures must be recorded in protected server logs.');
echo "Expected validation errors stay readable; unexpected exceptions are logged and shown safely.\n";
