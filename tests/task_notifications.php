<?php
declare(strict_types=1);
// All writes are isolated in temporary tables on this connection.
define('PK_ROOT',dirname(__DIR__));define('BASEPATH',PK_ROOT.'/vendor/codeigniter/framework/system/');
define('APPPATH',PK_ROOT.'/application/');define('ENVIRONMENT','testing');
function log_message($level,$message) {}
function show_error($message) {throw new RuntimeException((string)$message);}
function &get_instance() {return $GLOBALS['notificationContext'];}
require_once BASEPATH.'core/Common.php';require_once BASEPATH.'core/Model.php';require_once BASEPATH.'database/DB.php';
require APPPATH.'config/database.php';
$connection=DB($db['default'],TRUE);
function notificationCheck(bool $value,string $message):void {if (!$value) throw new RuntimeException($message);}
function notificationInsert($db,$table,$data):void {notificationCheck($db->insert($table,$data)!==FALSE,'Fixture failed: '.$table);}
foreach (['users','requests','workflow_steps','workflow_history'] as $table) {
    notificationCheck($connection->query('CREATE TEMPORARY TABLE notification_test_'.$table.' LIKE '.$table)!==FALSE &&
        $connection->query('ALTER TABLE notification_test_'.$table.' RENAME TO '.$table)!==FALSE,'Cannot isolate '.$table);
}
notificationCheck($connection->query('CREATE TEMPORARY TABLE task_notification_receipts (
    user_id BIGINT UNSIGNED NOT NULL,step_id BIGINT UNSIGNED NOT NULL,activation INT NOT NULL,
    shown_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,step_id,activation)) ENGINE=InnoDB')!==FALSE,
    'Cannot isolate notification receipts.');
$connection->data_cache['table_names']=$connection->list_tables();
$connection->data_cache['table_names'][]='task_notification_receipts';
$notificationContext=(object)['db'=>$connection];
require_once APPPATH.'models/Notification_model.php';require_once APPPATH.'models/Request_model.php';
$model=new Notification_model();$notificationContext->Request_model=new Request_model();
notificationCheck(method_exists($model,'pending_approvals') && method_exists($model,'claim_approvals'),
    'New approval tasks need durable once-only notification support.');
foreach ([20,21,30,40] as $id) notificationInsert($connection,'users',['id'=>$id,'username'=>'notification-'.$id,
    'first_name'=>'Reviewer','last_name'=>(string)$id,'position_title'=>'Reviewer','role_id'=>1,'password_hash'=>'unused']);
$user=['id'=>20,'role_id'=>5,'role_ids'=>[5,7],'name'=>'Reviewer 20','position_title'=>'Reviewer'];
$peer=['id'=>21,'role_id'=>7,'role_ids'=>[7],'name'=>'Reviewer 21','position_title'=>'Reviewer'];
$other=['id'=>30,'role_id'=>1,'role_ids'=>[1],'name'=>'Reviewer 30','position_title'=>'Reviewer'];
$graph=json_encode(['steps'=>[
    ['key'=>'step_1','name'=>'First review','approver'=>['type'=>'user','value'=>20]],
    ['key'=>'step_2','name'=>'Final review','approver'=>['type'=>'user','value'=>30]]
]]);
foreach ([42,51,52,53] as $id) notificationInsert($connection,'requests',['id'=>$id,'reference'=>'REQ-NOTIFY-'.$id,
    'type'=>'hardcopy_create','requested_by'=>40,'payload'=>'{}','status'=>$id===52?'draft':'submitted',
    'current_node'=>$id===53?'step_2':'step_1','snapshot'=>$graph]);
foreach ([101=>42,201=>51,202=>52,203=>53] as $step=>$request) notificationInsert($connection,'workflow_steps',[
    'id'=>$step,'request_id'=>$request,'node_key'=>'step_1','label'=>'Approval '.$step,'candidates'=>'[]',
    'assignment'=>$step===101?'{"type":"user","value":20}':'{"type":"role","value":7}',
    'assigned_user_id'=>$step===101?20:NULL,'status'=>'active']);
notificationInsert($connection,'workflow_steps',['id'=>102,'request_id'=>42,'node_key'=>'step_2','label'=>'Final review',
    'candidates'=>'[]','assignment'=>'{"type":"user","value":30}','assigned_user_id'=>30,'status'=>'pending']);
$pending=$model->pending_approvals($user);
notificationCheck(array_column($pending,'key')===['101:1','201:1'],
    'Only current active approvals assigned directly or through an assigned secondary role may notify.');
notificationCheck($model->pending_approvals($other)===[],
    'Administrator privileges must not notify for someone else approval assignment.');
notificationCheck(count($model->claim_approvals($user,['101:1','201:1','102:1','203:1','999:1']))===2,
    'Only the eligible tasks may be claimed as shown, even with forged keys.');
notificationCheck($model->pending_approvals($user)===[] && $model->claim_approvals($user,['101:1','201:1'])===[],
    'Refreshes, subsequent logins and duplicate concurrent claims must not repeat shown task notifications.');
notificationCheck(array_column($model->pending_approvals($peer),'key')===['201:1'] &&
    count($model->claim_approvals($peer,['201:1']))===1,'Shown state must be separate for every assigned approver.');
require_once APPPATH.'services/request/request_service.php';
$reflection=new ReflectionClass(Request_service::class);$service=$reflection->newInstanceWithoutConstructor();
$context=$reflection->getProperty('ci');$context->setAccessible(TRUE);$context->setValue($service,$notificationContext);
$service->decide('hardcopy',42,$user,'approved','First review done');
notificationCheck(array_column($model->pending_approvals($other),'key')===['102:2'],
    'Advancing the request must create a fresh notification for the next assigned approver.');
$model->claim_approvals($other,['102:2']);
$service->decide('hardcopy',42,$other,'returned','Recheck first review');
notificationCheck(array_column($model->pending_approvals($user),'key')===['101:2'],
    'A returned workflow creates a new approval occurrence at the previous step.');
notificationCheck($model->claim_approvals($user,['101:1'])===[], 'Old polling results must never claim a new activation.');
$model->claim_approvals($user,['101:2']);
$service->decide('hardcopy',42,$user,'approved','Rechecked');
notificationCheck(array_column($model->pending_approvals($other),'key')===['102:3'],
    'The returning approver must receive one fresh alert when required to review again.');
$service->decide('hardcopy',42,$other,'rejected','Closed');
notificationCheck($model->claim_approvals($other,['102:3'])===[] && $model->pending_approvals($other)===[],
    'Completed, rejected, returned and stale tasks must never be announced as awaiting approval.');
notificationCheck($connection->count_all('task_notification_receipts')===5,
    'Each shown occurrence must be saved once without erasing earlier shown records.');
$connection->close();
echo "Assigned approval notifications, persistent once-only receipts, separate users, replay and stale task checks passed.\n";
