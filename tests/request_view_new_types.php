<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function site_url($value) { return '/' . $value; }
class RequestViewFixtureComplete extends RuntimeException {}
class RequestViewFixture
{
    public $load;
    public $captured;
    public $columns;
    public $sortable;
    public function __construct() { $this->load = $this; }
    public function view($view, $data)
    {
        if ($view === 'reusable_components/reusable_datatable') {
            $this->captured = $data['dt_rows'][0] ?? NULL;
            $this->columns = $data['dt_columns'];
            $this->sortable = $data['dt_sortable'];
            throw new RequestViewFixtureComplete();
        }
    }
    public function render($tab, $type, $payload)
    {
        $task = FALSE;
        $history = [];
        $approval_routes = [];
        $navigation_counts = [];
        $permissions = ['requests' => ['view' => TRUE]];
        $user = ['name' => 'New requester'];
        $total = 1;
        $table = [
            'q' => '', 'status' => '', 'page' => 1, 'limit' => 25,
            'sort' => 'updated_at', 'dir' => 'DESC', 'type' => '', 'from' => '', 'to' => ''
        ];
        $rows = [[
            'id' => 501, 'reference' => 'REQ-NEW-501', 'type' => $type,
            'payload' => json_encode($payload), 'status' => 'draft',
            'softcopy_id' => NULL, 'hardcopy_id' => NULL, 'requester_name' => 'New requester',
            'source_title' => NULL, 'source_reference' => NULL,
            'version' => 1, 'workflow_version_id' => NULL, 'submitted_at' => NULL,
            'created_at' => '2026-10-16 10:00:00',
            'updated_at' => '2026-10-16 10:00:00', 'detail_labels' => []
        ]];
        $allowed_request_types = [$type];
        ob_start();
        try { include APPPATH . 'view/pages/request/shared_index.php'; }
        catch (RequestViewFixtureComplete $e) {}
        finally { ob_end_clean(); }
        if (!$this->captured)
            throw new RuntimeException('New ' . $type . ' request did not create a viewable row.');
        $display = $this->captured['display'] ?? NULL;
        if (!is_array($display) || !is_array($display['sections'] ?? NULL))
            throw new RuntimeException('Missing request modal data for ' . $type);
        if (($display['header']['title'] ?? '') === '')
            throw new RuntimeException('Blank request heading for ' . $type);
        $json = json_encode($display, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
            JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === FALSE || !is_array(json_decode($json, TRUE)))
            throw new RuntimeException('Invalid request modal JSON for ' . $type);
        return $display;
    }
}
$cases = [
    ['softcopy', 'softcopy_create'], ['softcopy', 'softcopy_revise'],
    ['softcopy', 'softcopy_cancel'], ['hardcopy', 'hardcopy_create'],
    ['hardcopy', 'hardcopy_update'], ['hardcopy-transfer', 'transfer'],
    ['hardcopy-disposal', 'disposal'], ['access-grant', 'access'],
    ['document-assign', 'assignment']
];
$view = new RequestViewFixture();
foreach ($cases as [$tab, $type]) {
    $display = $view->render($tab, $type, ['subject' => 'New draft ' . $type]);
    $sections = array_column($display['sections'], NULL, 'key');
    if (!isset($sections['overview'], $sections['approvals'], $sections['audit']))
        throw new RuntimeException('Draft request sections incomplete for ' . $type);
    if (($display['header']['status'] ?? '') !== 'draft')
        throw new RuntimeException('New request status missing for ' . $type);
    if (($view->columns['reference'] ?? '') !== 'Request Reference' ||
        ($view->captured['cells']['reference'] ?? '') !== 'REQ-NEW-501' ||
        ($sections['overview']['fields']['Request Reference'] ?? '') !== 'REQ-NEW-501' ||
        !in_array('reference', $view->sortable, TRUE))
        throw new RuntimeException('Request Reference must be visible and sortable in every request/task type.');
}
echo "All 9 new request types produce complete, valid View modal payloads.\n";
