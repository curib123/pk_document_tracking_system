<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
class CI_Model {public $db;}
require_once APPPATH.'models/Request_model.php';
require_once APPPATH.'models/Notification_model.php';
require_once APPPATH.'services/request/request_policy.php';
require_once APPPATH.'services/request/request_service.php';

function disposalExpect(bool $pass,string $message): void
{
    if (!$pass) throw new RuntimeException($message);
}
function disposalReject(callable $operation,string $message): void
{
    try {$operation();} catch (DomainException $e) {return;}
    throw new RuntimeException($message);
}
class DisposalQueryResult
{
    private $rows;
    public function __construct(array $rows) {$this->rows=$rows;}
    public function result_array(): array {return $this->rows;}
    public function row_array(): ?array {return $this->rows[0]??NULL;}
}
class DisposalFakeDB
{
    public $document;
    public $calls=[];
    public $writes=[];
    public $results=[];
    public function __call($method,$arguments) {$this->calls[]=[$method,$arguments];return $this;}
    public function get(): DisposalQueryResult {return new DisposalQueryResult(array_shift($this->results)??[]);}
    public function count_all_results(): int {return 0;}
    public function get_where($table,$where): DisposalQueryResult {return new DisposalQueryResult($this->document?[$this->document]:[]);}
    public function query($sql,$arguments): DisposalQueryResult {$this->calls[]=['query',[$sql,$arguments]];return new DisposalQueryResult($this->document?[$this->document]:[]);}
    public function insert($table,$data): bool {$this->writes[]=[$table,$data];return TRUE;}
    public function update($table,$data): bool {$this->writes[]=[$table,$data];return TRUE;}
}

$model=new Request_model();
disposalExpect($model->types('hardcopy-disposal')===['disposal'],'Disposal must have its own tab.');
disposalExpect(Request_policy::module('disposal')==='disposal','Disposal must use its existing action permission.');
$scope=(new ReflectionClass($model))->getMethod('scope');$scope->setAccessible(TRUE);
$model->db=new DisposalFakeDB();
$scope->invoke($model,'hardcopy-disposal',['id'=>8,'role_id'=>5],FALSE,'','');
disposalExpect(in_array(['where',['r.requested_by',8]],$model->db->calls,TRUE),'My Requests must remain scoped to the requester.');
$model->db->calls=[];
$scope->invoke($model,'hardcopy-disposal',['id'=>8,'role_id'=>5],TRUE,'','');
disposalExpect(in_array(['where',['ws.assigned_user_id',8]],$model->db->calls,TRUE),'My Tasks must require the active user assignment.');
disposalExpect(strpos(json_encode($model->db->calls),'ws.assignment')!==FALSE,'Role assignments must also remain scoped to the user role.');

$notifications=new Notification_model();$notifications->db=new DisposalFakeDB();
$notifications->db->results=[
    [['type'=>'disposal','total'=>8],['type'=>'hardcopy_update','total'=>2]],
    [['type'=>'disposal','total'=>3],['type'=>'softcopy_create','total'=>1]]
];
$counts=$notifications->for_user(['id'=>8,'role_id'=>5]);
disposalExpect($counts['requests']['hardcopy-disposal']===8 && $counts['requests_total']===10,'Owned disposal drafts must contribute to tab and sidebar badges.');
disposalExpect($counts['tasks']['hardcopy-disposal']===3 && $counts['tasks_total']===4,'Assigned disposal approvals must contribute to tab and sidebar badges.');

$reflection=new ReflectionClass(Request_service::class);
$service=$reflection->newInstanceWithoutConstructor();
$context=$reflection->getProperty('ci');$context->setAccessible(TRUE);
$db=new DisposalFakeDB();$context->setValue($service,(object)['db'=>$db]);
$apply=$reflection->getMethod('applyApproved');$apply->setAccessible(TRUE);
$request=['id'=>42,'type'=>'disposal','requested_by'=>8,'softcopy_id'=>NULL,'hardcopy_id'=>9,
    'payload'=>json_encode(['disposal_reason'=>'shred','disposal_description'=>'tampered description'])];
$db->document=['id'=>9,'status'=>'active','version'=>4,'location_id'=>3];
$result=$apply->invoke($service,$request,12);
disposalExpect($result===['disposal'=>'recorded'],'Final approval must record the disposal.');
disposalExpect(strpos(json_encode($db->calls),'FOR UPDATE')!==FALSE,'Final approval must lock the selected hardcopy.');
$writes=array_column($db->writes,1,0);
disposalExpect($writes['hardcopy_documents']['status']==='disposed' && $writes['hardcopy_documents']['location_id']===NULL && $writes['hardcopy_documents']['version']===5,'Disposal must clear storage and advance the document version.');
disposalExpect($writes['disposals']['request_id']===42 && $writes['disposals']['disposed_by']===12 && $writes['disposals']['remarks']==='Shred','Disposal history must use the approved reason and acting approver.');
disposalExpect($writes['status_history']['previous_status']==='active' && $writes['status_history']['new_status']==='disposed','Status history must record the actual transition.');
disposalExpect($writes['access_grants']['status']==='revoked','Disposed document access grants must be revoked.');
foreach (['disposed','archived','cancelled'] as $status) {
    $db->document=['id'=>9,'status'=>$status,'version'=>4];$db->writes=[];
    disposalReject(static function() use($apply,$service,$request) {$apply->invoke($service,$request,12);},'A document that became '.$status.' before approval must be refused.');
    disposalExpect(!$db->writes,'A refused disposal must not write domain records.');
}
$db->document=NULL;
disposalReject(static function() use($apply,$service,$request) {$apply->invoke($service,$request,12);},'A missing document must not be disposed.');
$db->document=['id'=>9,'status'=>'active','version'=>4];
$request['payload']=json_encode(['disposal_reason'=>'other','disposal_other'=>'']);
disposalReject(static function() use($apply,$service,$request) {$apply->invoke($service,$request,12);},'Final approval must validate the disposal reason again.');
echo "Disposal tab/scoping, notifications, locked final approval, reason validation and access revocation tests passed.\n";
