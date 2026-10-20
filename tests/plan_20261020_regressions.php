<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
class CI_Model { public $db; }
require_once APPPATH . 'models/Request_model.php';
require_once APPPATH . 'services/presentation/request_title.php';
function fixCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
foreach ([
    ['access', [], 'Original', 'Original'],
    ['assignment', ['title' => 'Stale'], 'Stored', 'Stored'],
    ['transfer', ['subject' => 'Move it'], 'Archive', 'Archive'],
    ['disposal', [], 'Disposed file', 'Disposed file'],
    ['softcopy_cancel', [], 'Policy', 'Policy'],
    ['hardcopy_update', ['title' => 'New'], 'Old', 'New'],
    ['softcopy_revise', ['title' => 'Revised'], 'Original', 'Revised'],
    ['hardcopy_create', ['title' => 'Created'], '', 'Created'],
    ['access', ['subject' => 'View request'], '', 'View request'],
    ['access', [], '', 'Request #12']
] as [$type, $payload, $source, $expected]) {
    fixCheck(Request_title::resolve($type, $payload, $source, 12) === $expected,
        'Title fallback failed for ' . $type);
}
final class Plan20261020DB
{
    public $calls = [];
    public $count = 1;
    public function __call($method, $arguments)
    {
        $this->calls[] = [$method, $arguments];
        return $this;
    }
    public function count_all_results(): int { return $this->count; }
}
$model = new Request_model();
$model->db = new Plan20261020DB();
$db = $model->db;
fixCheck(!$model->has_submitted_related_request('disposal', 0, 5, 23),
    'Other request actions must not be blocked');
fixCheck($db->calls === [], 'Other types must avoid the conflict query');
fixCheck($model->has_submitted_related_request('access', 11, 0, 23, 18),
    'Existing submitted request must block the same requester');
$serialized = json_encode($db->calls);
foreach (['r.softcopy_id', 'r.requested_by', 'r.status', 'submitted',
    'access', 'assignment', 'transfer', 'r.id !='] as $part)
    fixCheck(strpos($serialized, $part) !== FALSE, 'Missing query scope: ' . $part);
fixCheck(strpos($serialized, 'r.hardcopy_id') === FALSE,
    'Same numeric ID in a different document domain must not conflict');
$db->calls = [];
$db->count = 0;
fixCheck(!$model->has_submitted_related_request('transfer', 0, 11, 23),
    'No matching submitted request should allow a new submission');
fixCheck(strpos(json_encode($db->calls), 'r.hardcopy_id') !== FALSE &&
    strpos(json_encode($db->calls), 'r.softcopy_id') === FALSE,
    'Hardcopy request must query only the hardcopy domain');

$root = dirname(__DIR__);
$requestService = file_get_contents($root . '/application/services/request/request_service.php');
$transferService = file_get_contents($root . '/application/services/transfer/transfer_service.php');
$handoffModel = file_get_contents($root . '/application/models/Transfer_model.php');
$view = file_get_contents($root . '/application/view/pages/request/shared_index.php');
fixCheck(substr_count($requestService, '$this->assert_no_submitted_related_request(') === 2,
    'Draft save and submit must both check pending conflicts');
fixCheck(strpos($requestService, 'WHERE id=? FOR UPDATE') !== FALSE,
    'Document row lock is required to serialize concurrent submissions');
fixCheck(strpos($transferService, "(int) \$request['requested_by'] !== (int) \$actor['id']") !== FALSE,
    'Dispatch must verify the initiating requester');
fixCheck(strpos($transferService, "(int) \$transfer['recipient_id'] !== (int) \$actor['id']") !== FALSE,
    'Accept must verify the destination recipient');
fixCheck(strpos($handoffModel, "where('r.requested_by'") !== FALSE &&
    strpos($handoffModel, "where('t.recipient_id'") !== FALSE,
    'Handoff list must be stage/actor scoped');
fixCheck(strpos($view, "'title' => \$headerTitle") !== FALSE &&
    strpos($view, 'Request_title::resolve(') !== FALSE,
    'Request/Task title cell and modal must use the same title');
fixCheck(strpos($view, '$isRequester') !== FALSE, 'Dispatch button must be requester-scoped');
echo "October 20 plan and document-title regression contracts passed.\n";
