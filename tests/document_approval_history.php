<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
class CI_Model { public $db, $load, $Document_model, $Permission_model; }
require APPPATH . 'models/Document_detail_model.php';
function site_url($path) { return '/' . $path; }

$documents = [
    ['id'=>9, 'title'=>'Policy', 'document_number'=>'SC-9', 'status'=>'active'],
    ['id'=>10, 'title'=>'Guide', 'document_number'=>'SC-10', 'status'=>'active']
];
foreach ($documents as &$document) {
    $document += ['creator_name'=>'Requester', 'created_at'=>'2026-10-14 09:00:00', 'updated_at'=>'2026-10-15 11:00:00', 'version'=>1];
}
unset($document);
$events = [
    ['request_id'=>42, 'step_id'=>101, 'step_label'=>'Manager Review', 'action'=>'returned',
        'user_name'=>'Manager', 'position_title'=>'Plant Manager', 'comments'=>'Please correct <script>', 'created_at'=>'2026-10-15 10:00:00'],
    ['request_id'=>42, 'step_id'=>101, 'step_label'=>'Manager Review', 'action'=>'approved',
        'user_name'=>'Manager', 'position_title'=>'Plant Manager', 'comments'=>'Rechecked', 'created_at'=>'2026-10-15 11:00:00'],
    ['request_id'=>43, 'step_id'=>102, 'step_label'=>'Officer Review', 'action'=>'approved',
        'user_name'=>'Officer', 'position_title'=>'Records Officer', 'comments'=>'Other document', 'created_at'=>'2026-10-15 12:00:00']
];
$detail = new Document_detail_model();
$detail->load = new class { function model($name) {} };
$detail->Document_model = new class {
    function config($domain) { return ['table'=>'softcopy_documents']; }
    function scope($domain, $viewer) { return 'd.created_by=20'; }
};
$detail->Permission_model = new class { function for_user($viewer) { return []; } };
$detail->db = new class($events) {
    public $calls = [], $events;
    function __construct($events) { $this->events = $events; }
    function query($sql, $params) {
        $this->calls[] = [$sql, $params];
        $rows = strpos($sql, 'FROM requests r') !== false ? [
            ['id'=>42, 'document_id'=>9, 'reference'=>'REQ-42', 'type'=>'softcopy_revise', 'status'=>'completed'],
            ['id'=>43, 'document_id'=>10, 'reference'=>'REQ-43', 'type'=>'softcopy_revise', 'status'=>'completed']
        ] : (strpos($sql, 'FROM workflow_history h') !== false ? $this->events : []);
        return new class($rows) {
            private $rows;
            function __construct($rows) { $this->rows = $rows; }
            function result_array() { return $this->rows; }
        };
    }
};
$display = $detail->for_page('softcopy', $documents, ['id'=>20, 'role_id'=>5], []);
foreach ([9=>[42, 'REQ-42'], 10=>[43, 'REQ-43']] as $id=>$request) {
    $approval = array_column($display[$id]['sections'], null, 'key')['approvals'];
    $expected = array_values(array_filter($events, static function($event) use ($request) { return $event['request_id']===$request[0]; }));
    foreach ($expected as &$event) $event['request_title'] = 'Request #' . $request[0];
    unset($event);
    if (($approval['events'] ?? null) !== $expected)
        throw new RuntimeException('Approval History must expose each scoped event with its request, step, actor, timestamp and remarks.');
}
if (count($detail->db->calls)!==2 || $detail->db->calls[1][1]!==[42,43])
    throw new RuntimeException('History must stay batched to requests on the authorized document page.');
if (strpos($detail->db->calls[0][0], 'd.created_by=20')===false || strpos($detail->db->calls[0][0], 'r.requested_by=20')===false)
    throw new RuntimeException('Document and request permission scopes must be retained.');
$detail->db->events = [];
$empty = $detail->for_page('softcopy', [$documents[0]], ['id'=>20, 'role_id'=>5], []);
if (array_column($empty[9]['sections'], null, 'key')['approvals']['events']!==[])
    throw new RuntimeException('Documents without recorded decisions must receive an empty timeline.');
echo "Document approval timelines retain scoped requests, repeated decisions and complete event metadata.\n";
