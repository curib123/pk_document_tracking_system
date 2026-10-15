<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');
class CI_Model { public $db, $load, $Document_model, $Permission_model; }
require APPPATH.'models/Document_detail_model.php';
function site_url($path) { return '/'.$path; }
$detail = new Document_detail_model();
$detail->load = new class { function model($name) {} };
$detail->Document_model = new class {
    function config($domain) { return ['table'=>$domain.'_documents']; }
    function scope($domain, $viewer) { return 'd.created_by=20'; }
};
$detail->Permission_model = new class {
    public $permissions = ['transfer'=>['view'=>true]];
    function for_user($viewer) { return $this->permissions; }
};
$detail->db = new class {
    public $calls = [], $transfers = [
        ['id'=>70,'hardcopy_id'=>9,'holder'=>'John','recipient'=>'Paul','status'=>'accepted',
            'created_at'=>'2026-10-15 09:00:00','transferred_at'=>'2026-10-15 10:00:00','accepted_at'=>'2026-10-16 11:00:00',
            'reason'=>'Archive <script>','comments'=>'Handle carefully'],
        ['id'=>71,'hardcopy_id'=>10,'holder'=>'Paul','recipient'=>'Records','status'=>'for_transfer',
            'created_at'=>'2026-10-16 12:00:00','transferred_at'=>null,'accepted_at'=>null,'reason'=>'Filing','comments'=>null]
    ];
    function query($sql, $args) {
        $this->calls[] = [$sql, $args];
        return new class(strpos($sql, 'FROM transfers t')!==false ? $this->transfers : []) {
            private $rows;
            function __construct($rows) { $this->rows=$rows; }
            function result_array() { return $this->rows; }
        };
    }
};
$documents=[];
foreach ([9,10] as $id) $documents[]=['id'=>$id,'title'=>'Manual','status'=>'active','sequence_number'=>'HC-'.$id,
    'creator_name'=>'John','created_at'=>'2026-10-15 08:00:00','updated_at'=>'2026-10-16 12:00:00','version'=>1];
$viewer=['id'=>20,'role_id'=>5];
$display=$detail->for_page('hardcopy',$documents,$viewer,[]);
$sections=array_column($display[9]['sections'],null,'key');
$events=$sections['transfers']['events']??[];
if (array_column($events,'action')!==['for_transfer','in_transit','accepted'] ||
    array_column($events,'created_at')!==['2026-10-15 09:00:00','2026-10-15 10:00:00','2026-10-16 11:00:00'])
    throw new RuntimeException('Transfer History must preserve all recorded milestone timestamps.');
if ($events[0]['step_label']!=='John → Paul' || $events[2]['user_name']!=='Paul' ||
    $events[0]['transfer_reference']!=='Transfer #70' || strpos($events[0]['comments'],'Archive <script>')===false ||
    strpos($events[0]['comments'],'Handle carefully')===false)
    throw new RuntimeException('Timeline must preserve transfer identity, holder, recipient, reason and comments.');
$other=array_column($display[10]['sections'],null,'key')['transfers']['events'];
if (count($other)!==1 || $other[0]['transfer_reference']!=='Transfer #71')
    throw new RuntimeException('Transfers must stay scoped to their own document without invented milestones.');
$query=$detail->db->calls[1];
if ($query[1]!==[9,10] || strpos($query[0],'d.created_by=20')===false || strpos($query[0],'t.current_holder_id=20 OR t.recipient_id=20')===false)
    throw new RuntimeException('Batched document and transfer permission scopes must be retained.');
$detail->db->transfers[0]['accepted_at']=null;
$missing=$detail->for_page('hardcopy',$documents,$viewer,[]);
$events=array_column($missing[9]['sections'],null,'key')['transfers']['events'];
if ($events[2]['action']!=='accepted' || $events[2]['created_at']!==null)
    throw new RuntimeException('Known acceptance with no recorded date must retain its status without inventing a timestamp.');
$detail->Permission_model->permissions=[];
$denied=$detail->for_page('hardcopy',$documents,$viewer,[]);
if (isset(array_column($denied[9]['sections'],null,'key')['transfers']))
    throw new RuntimeException('Transfer history must remain hidden when view permission is denied.');
echo "Transfer timelines preserve recorded milestones, document ownership, timestamps and permission scopes.\n";
