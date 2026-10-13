<?php
declare(strict_types=1);

// Run against an installed schema. All writes use connection-local temporary
// tables which shadow the real tables and disappear when this process exits.
define('PK_ROOT',dirname(__DIR__));
define('BASEPATH',PK_ROOT.'/vendor/codeigniter/framework/system/');
define('APPPATH',PK_ROOT.'/application/');
define('ENVIRONMENT','testing');
function log_message($level,$message) {}
function show_error($message) {throw new RuntimeException((string)$message);}
function &get_instance() {return $GLOBALS['returnContext'];}
require_once BASEPATH.'core/Common.php';
require_once BASEPATH.'core/Model.php';
require_once BASEPATH.'database/DB.php';
require_once APPPATH.'models/Request_model.php';
require_once APPPATH.'models/Notification_model.php';
require_once APPPATH.'services/request/request_service.php';
require_once APPPATH.'services/request/request_policy.php';
require APPPATH.'config/database.php';
$connection=DB($db['default'],TRUE);

function returnCheck(bool $condition,string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function returnReject(callable $action,string $message): void
{
    try {$action();} catch (DomainException $e) {return;}
    throw new RuntimeException($message);
}
function returnWrite($db,string $table,array $data): void
{
    returnCheck($db->insert($table,$data)!==FALSE,'Fixture insert failed for '.$table);
}
foreach (['requests','workflow_steps','workflow_history','users','softcopy_documents',
    'hardcopy_documents','transfers','disposals','status_history','access_grants'] as $table) {
    returnCheck($connection->query('CREATE TEMPORARY TABLE `return_test_'.$table.'` LIKE `'.$table.'`')!==FALSE &&
        $connection->query('ALTER TABLE `return_test_'.$table.'` RENAME TO `'.$table.'`')!==FALSE,
        'Could not create isolated temporary table '.$table.': '.$connection->error()['message']);
}
$returnContext=(object)['db'=>$connection,'Request_model'=>new Request_model()];
$reflection=new ReflectionClass(Request_service::class);
$service=$reflection->newInstanceWithoutConstructor();
$context=$reflection->getProperty('ci');$context->setAccessible(TRUE);
$context->setValue($service,$returnContext);
$notifications=new Notification_model();

function returnActor(int $id,int $role=5): array
{
    return ['id'=>$id,'role_id'=>$role,'name'=>'Reviewer '.$id,'position_title'=>'Reviewer'];
}
foreach ([8,20,30,40,70] as $id) {
    returnWrite($connection,'users',['id'=>$id,'username'=>'return-test-'.$id,
        'first_name'=>'Reviewer','last_name'=>(string)$id,'position_title'=>'Reviewer',
        'role_id'=>$id===70?7:5,'password_hash'=>'unusable-test-fixture']);
}
returnWrite($connection,'hardcopy_documents',['id'=>9,'title'=>'Temporary return test',
    'holder_id'=>8,'created_by'=>8,'creation_source'=>'direct','creation_reason'=>'Test']);

function returnFixture($db,int $current=3,bool $oldRun=FALSE): void
{
    foreach (['workflow_history','workflow_steps','requests','disposals','status_history'] as $table)
        returnCheck($db->query('DELETE FROM `'.$table.'`')!==FALSE,'Fixture reset failed.');
    $db->where('id',9)->update('hardcopy_documents',['status'=>'active']);
    $graph=['steps'=>[
        ['key'=>'step_1','name'=>'Supervisor','approver'=>['type'=>'user','value'=>20]],
        ['key'=>'step_2','name'=>'Department','approver'=>['type'=>'role','value'=>7]],
        ['key'=>'step_3','name'=>'Records','approver'=>['type'=>'user','value'=>30]],
        ['key'=>'step_4','name'=>'Final approval','approver'=>['type'=>'user','value'=>40]]
    ]];
    returnWrite($db,'requests',['id'=>42,'reference'=>'REQ-RETURN-TEST','type'=>'disposal',
        'requested_by'=>8,'hardcopy_id'=>9,'payload'=>'{"disposal_reason":"shred"}',
        'workflow_version_id'=>3,'snapshot'=>json_encode($graph),'current_node'=>'step_'.$current,
        'status'=>'submitted','submitted_at'=>'2026-10-13 08:00:00']);
    if ($oldRun) foreach ($graph['steps'] as $i=>$step) {
        returnWrite($db,'workflow_steps',['id'=>$i+1,'request_id'=>42,'node_key'=>$step['key'],
            'label'=>$step['name'],'assignment'=>json_encode($step['approver']),
            'candidates'=>'[]','status'=>'approved','decision'=>'approved']);
    }
    foreach ($graph['steps'] as $i=>$step) {
        $approved=$i+1<$current;
        returnWrite($db,'workflow_steps',['id'=>[101,107,112,118][$i],'request_id'=>42,
            'node_key'=>$step['key'],'label'=>$step['name'],'assignment'=>json_encode($step['approver']),
            'candidates'=>'[]','assigned_user_id'=>[20,NULL,30,40][$i],
            'status'=>$approved?'approved':($i+1===$current?'active':'pending'),
            'decision'=>$approved?'approved':NULL,'comments'=>$approved?'Earlier approval':NULL,
            'acting_user_id'=>$approved?20:NULL,'acting_name'=>$approved?'Earlier reviewer':NULL,
            'acting_position'=>$approved?'Reviewer':NULL,'acted_at'=>$approved?'2026-10-13 08:01:00':NULL]);
    }
    foreach ([['step_id'=>NULL,'action'=>'submitted'],['step_id'=>101,'action'=>'approved'],
        ['step_id'=>107,'action'=>'approved']] as $event) {
        returnWrite($db,'workflow_history',$event+['request_id'=>42,'user_id'=>20,
            'user_name'=>'Earlier reviewer','position_title'=>'Reviewer']);
    }
}
function returnState($db): array
{
    return [$db->get_where('requests',['id'=>42])->row_array(),
        array_column($db->where('request_id',42)->order_by('id')->get('workflow_steps')->result_array(),NULL,'id'),
        $db->where('request_id',42)->order_by('id')->get('workflow_history')->result_array()];
}

// Catch the original bug: an intermediate return must route to the previous
// approver, remain submitted, and require that step to be approved again.
returnFixture($connection);
[$beforeRequest,$beforeSteps,$beforeHistory]=returnState($connection);
$service->decide('hardcopy-disposal',42,returnActor(30),'returned','  Check department approval  ');
[$request,$steps,$history]=returnState($connection);
returnCheck($request['status']==='submitted' && $request['current_node']==='step_2' &&
    $request['completed_at']===NULL,'An intermediate return must reverse one step, not return to the requester.');
returnCheck($steps[107]['status']==='active' && $steps[112]['status']==='returned' &&
    $steps[118]['status']==='pending','The previous approver must be the only active step.');
returnCheck($steps[101]===$beforeSteps[101],'Earlier approved steps must remain unchanged.');
foreach (['decision','comments','acting_user_id','acting_name','acting_position','acted_at'] as $field)
    returnCheck($steps[107][$field]===$beforeSteps[107][$field],'Reactivating an approver must retain their last '.$field.'.');
returnCheck($steps[112]['decision']==='returned' && $steps[112]['comments']==='Check department approval' &&
    (int)$steps[112]['acting_user_id']===30 && $steps[112]['acted_at']!==NULL,
    'The returning step must retain its decision, approver, comment and timestamp.');
$firstReturn=$steps[112];
returnCheck($request['snapshot']===$beforeRequest['snapshot'] &&
    $request['workflow_version_id']===$beforeRequest['workflow_version_id'] &&
    $request['payload']===$beforeRequest['payload'],'Returning must preserve the submitted route and proposal.');
returnCheck(array_slice($history,0,3)===$beforeHistory && count($history)===4 &&
    $history[3]['action']==='returned' && (int)$history[3]['step_id']===112 &&
    (int)$history[3]['user_id']===30 && $history[3]['comments']==='Check department approval',
    'The return comment and original approvals must remain in decision history.');
returnReject(static function() use($request) {
    Request_policy::editable($request,8,'disposal',['version'=>$request['version']]);
},'The requester must not be able to edit an intermediate return.');
[$tasks]=$returnContext->Request_model->listing('hardcopy-disposal',returnActor(70,7),TRUE,'','',1,20);
returnCheck(count($tasks)===1 && $tasks[0]['step_label']==='Department',
    'The previous role approver must see the returned request in My Tasks.');
[$tasks]=$returnContext->Request_model->listing('hardcopy-disposal',returnActor(30),TRUE,'','',1,20);
returnCheck(!$tasks,'The returning approver must lose their active task.');
returnCheck($notifications->for_user(returnActor(70,7))['tasks_total']===1 &&
    $notifications->for_user(returnActor(8))['requests_total']===0,
    'Badges must route the action to the previous approver, not the requester.');
returnCheck($connection->count_all('disposals')===0,'Returning must not apply the document operation.');

// Approval must replay the returning step and all remaining approvals before
// executing the final document operation, once.
$service->decide('hardcopy-disposal',42,returnActor(70,7),'approved','Department rechecked');
[$request,$steps]=returnState($connection);
returnCheck($request['current_node']==='step_3' && $steps[112]['status']==='active',
    'Approval after a return must revisit the step that returned it.');
foreach (['decision','comments','acting_user_id','acting_name','acting_position','acted_at'] as $field)
    returnCheck($steps[112][$field]===$firstReturn[$field],'Revisiting a returned step must retain its last decision details.');
$unchanged=returnState($connection);
returnReject(static function() use($service) {
    $service->decide('hardcopy-disposal',42,returnActor(70,7),'returned','Stale action');
},'A stale approver must not decide after the workflow advances.');
returnCheck(returnState($connection)===$unchanged,'A refused stale decision must not change workflow state.');
$service->decide('hardcopy-disposal',42,returnActor(30),'approved','Records rechecked');
returnCheck($connection->get_where('requests',['id'=>42])->row_array()['current_node']==='step_4' &&
    $connection->count_all('disposals')===0,'The last approver must still be required after a return.');
$service->decide('hardcopy-disposal',42,returnActor(40),'approved','Final approval');
returnCheck($connection->get_where('requests',['id'=>42])->row_array()['status']==='completed' &&
    $connection->count_all('disposals')===1,'Final approval must apply the operation exactly once.');
returnReject(static function() use($service) {
    $service->decide('hardcopy-disposal',42,returnActor(40),'approved','Duplicate final approval');
},'A completed request must reject duplicate final approval.');
returnCheck($connection->count_all('disposals')===1,'A duplicate decision must not repeat the operation.');

// Repeated returns reverse one step at a time. At the first step only, the
// request can go back to its requester; historical runs must not be reopened.
returnFixture($connection,3,TRUE);
$service->decide('hardcopy-disposal',42,returnActor(30),'returned','Back to department');
$service->decide('hardcopy-disposal',42,returnActor(70,7),'approved','Department rechecked');
$service->decide('hardcopy-disposal',42,returnActor(30),'returned','Back to department again');
$service->decide('hardcopy-disposal',42,returnActor(70,7),'returned','Back to supervisor');
[$request,$steps]=returnState($connection);
returnCheck($request['current_node']==='step_1' && $steps[101]['status']==='active',
    'A second return must move back exactly one further step.');
foreach ([1,2,3,4] as $oldId) returnCheck($steps[$oldId]['status']==='approved',
    'Returning must never reopen steps from an earlier submission.');
$service->decide('hardcopy-disposal',42,returnActor(20),'returned','Requester must correct');
[$request,$steps]=returnState($connection);
returnCheck($request['status']==='returned' && $request['current_node']===NULL && $request['completed_at']===NULL,
    'Only a return at the first workflow step may reach the requester.');
Request_policy::editable($request,8,'disposal',['version'=>$request['version']]);
returnCheck(!array_filter($steps,static function($step) {return $step['status']==='active';}),
    'Returning to the requester must leave no active approval task.');
returnCheck($notifications->for_user(returnActor(8))['requests_total']===1,
    'A first-step return must show an actionable requester badge.');

// Every return/approval cycle remains immutable, including returns by the
// final approver and further returns by earlier approvers.
foreach (['approved','rejected'] as $outcome) {
    returnFixture($connection);
    for ($cycle=1;$cycle<=4;$cycle++) {
        $service->decide('hardcopy-disposal',42,returnActor(30),'returned','Return '.$cycle);
        $service->decide('hardcopy-disposal',42,returnActor(70,7),'approved','Recheck '.$cycle);
    }
    $service->decide('hardcopy-disposal',42,returnActor(30),'approved','Ready for final review');
    $service->decide('hardcopy-disposal',42,returnActor(40),'returned','Final approver returned');
    $service->decide('hardcopy-disposal',42,returnActor(30),'returned','Earlier approver returned again');
    $service->decide('hardcopy-disposal',42,returnActor(70,7),'approved','Department rechecked again');
    $service->decide('hardcopy-disposal',42,returnActor(30),'approved','Records rechecked again');
    $service->decide('hardcopy-disposal',42,returnActor(40),$outcome,'Final decision');
    [$request,$steps,$history]=returnState($connection);
    returnCheck(count($history)===17,'Every repeated decision must remain recorded through the final decision.');
    $recordEvents=array_values(array_filter($history,static function($event){return (int)$event['step_id']===112;}));
    returnCheck(array_column($recordEvents,'action')===['returned','returned','returned','returned','approved','returned','approved'],
        'Repeated returns and approvals at the same step must retain their original order.');
    returnCheck(array_column(array_slice($recordEvents,0,4),'comments')===['Return 1','Return 2','Return 3','Return 4'],
        'Each return comment must remain intact after later approvals.');
    foreach (array_slice($history,3) as $event)
        returnCheck($event['user_name']!=='' && $event['created_at']!==NULL && $event['comments']!=='',
            'Every decision must retain its actor, time and comment.');
    $routes=$returnContext->Request_model->approval_routes([42])[42];
    $events=$returnContext->Request_model->histories([42])[42];
    returnCheck(array_map('intval',array_column($routes,'step_id'))===[101,107,112,118] && count($events)===17,
        'The request viewer must receive stable step IDs and every recorded decision.');
    returnCheck($request['status']===($outcome==='approved'?'completed':'rejected') &&
        $connection->count_all('disposals')===($outcome==='approved'?1:0),'Only final approval may apply the operation.');
}

// An invalid previous step must fail atomically, rather than skipping it or
// falling back to the requester.
foreach (['unapproved','missing','wrong_node','missing_snapshot'] as $invalid) {
    returnFixture($connection,2,TRUE);
    if ($invalid==='unapproved') $connection->where('id',101)->update('workflow_steps',['status'=>'pending']);
    if ($invalid==='missing') $connection->where('id',101)->delete('workflow_steps');
    if ($invalid==='wrong_node') $connection->where('id',101)->update('workflow_steps',['node_key'=>'step_4']);
    if ($invalid==='missing_snapshot') $connection->where('id',42)->update('requests',['snapshot'=>NULL]);
    $unchanged=returnState($connection);
    returnReject(static function() use($service) {
        $service->decide('hardcopy-disposal',42,returnActor(70,7),'returned','Invalid route');
    },'An invalid previous step or snapshot must prevent the return: '.$invalid);
    returnCheck(returnState($connection)===$unchanged,'A failed return must roll back decision and history writes.');
}

// Approval must follow the next snapshot step, never complete early because
// that step is missing or pick a pending/returned row from an older run.
foreach (['approved','missing','wrong_node','missing_snapshot'] as $invalid) {
    returnFixture($connection,3,TRUE);
    if ($invalid==='approved') $connection->where('id',118)->update('workflow_steps',['status'=>'approved']);
    if ($invalid==='missing') $connection->where('id',118)->delete('workflow_steps');
    if ($invalid==='wrong_node') $connection->where('id',118)->update('workflow_steps',['node_key'=>'step_2']);
    if ($invalid==='missing_snapshot') $connection->where('id',42)->update('requests',['snapshot'=>NULL]);
    $unchanged=returnState($connection);
    returnReject(static function() use($service) {
        $service->decide('hardcopy-disposal',42,returnActor(30),'approved','Invalid next step');
    },'An invalid next step or snapshot must prevent approval: '.$invalid);
    returnCheck(returnState($connection)===$unchanged && $connection->count_all('disposals')===0,
        'A failed approval must roll back decision/history writes and never apply the operation.');
}
returnFixture($connection,3,TRUE);
$connection->where('id',1)->update('workflow_steps',['status'=>'returned']);
$connection->where('id',2)->update('workflow_steps',['status'=>'pending']);
$service->decide('hardcopy-disposal',42,returnActor(30),'approved','Continue current run');
[$request,$steps]=returnState($connection);
returnCheck($request['current_node']==='step_4' && $steps[118]['status']==='active' &&
    $steps[1]['status']==='returned' && $steps[2]['status']==='pending',
    'Approval must continue its own submission and preserve every older return record.');

// The shared return behavior must apply to every request domain.
foreach (['softcopy_create'=>'softcopy','softcopy_revise'=>'softcopy','softcopy_cancel'=>'softcopy',
    'hardcopy_create'=>'hardcopy','hardcopy_update'=>'hardcopy','transfer'=>'hardcopy-transfer',
    'disposal'=>'hardcopy-disposal','access'=>'access-grant','assignment'=>'document-assign'] as $type=>$tab) {
    returnFixture($connection,2);
    $connection->where('id',42)->update('requests',['type'=>$type]);
    $service->decide($tab,42,returnActor(70,7),'returned','Reverse '.$type);
    [$request,$steps]=returnState($connection);
    returnCheck($request['status']==='submitted' && $request['current_node']==='step_1' &&
        $steps[101]['status']==='active','Every request type must reverse its workflow: '.$type);
}

// Rejection still closes a request and never reopens another approval step.
returnFixture($connection);
$service->decide('hardcopy-disposal',42,returnActor(30),'rejected','Rejected');
[$request,$steps]=returnState($connection);
returnCheck($request['status']==='rejected' && $request['current_node']===NULL &&
    $steps[107]['status']==='approved','Rejection must retain its terminal behavior.');

$connection->close();
echo "Workflow return, replay, role tasks/badges, audit history, rollback and final approval tests passed.\n";
