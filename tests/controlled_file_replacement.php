<?php
declare(strict_types=1);
// Real database transactions, connection-local temporary tables, and owned test
// blobs only. Also accepts a multipart upload under a local PHP test server.
define('PK_ROOT',dirname(__DIR__));define('BASEPATH',PK_ROOT.'/vendor/codeigniter/framework/system/');
define('APPPATH',PK_ROOT.'/application/');define('ENVIRONMENT','testing');
function log_message($level,$message) {}
function show_error($message) {throw new RuntimeException((string)$message);}
function &get_instance() {return $GLOBALS['replacementContext'];}
require_once BASEPATH.'core/Common.php';require_once BASEPATH.'core/Model.php';require_once BASEPATH.'database/DB.php';
require APPPATH.'config/database.php';$connection=DB($db['default'],TRUE);
function replacementCheck(bool $value,string $message):void {if (!$value) throw new RuntimeException($message);}
function replacementReject(callable $action,string $message):void {
    try {$action();}catch(DomainException $error){return;}throw new RuntimeException($message);
}
function replacementInsert($db,$table,$data):void {replacementCheck($db->insert($table,$data)!==FALSE,'Fixture failed: '.$table);}
foreach (['users','categories','softcopy_documents','softcopy_revisions','files','requests','workflow_steps','workflow_history','status_history','disposals'] as $table) {
    replacementCheck($connection->query('CREATE TEMPORARY TABLE replacement_test_'.$table.' LIKE '.$table)!==FALSE &&
        $connection->query('ALTER TABLE replacement_test_'.$table.' RENAME TO '.$table)!==FALSE,'Cannot isolate '.$table);
}
require_once APPPATH.'models/Request_model.php';require_once APPPATH.'models/File_model.php';
require_once APPPATH.'models/Document_model.php';require_once APPPATH.'services/request/request_service.php';
require_once APPPATH.'services/files/file_service.php';require_once APPPATH.'services/softcopy/softcopy_operation_service.php';
$replacementContext=(object)['db'=>$connection,'load'=>new class {public function model($name) {}},
    'Permission_model'=>new class {public function for_user($user){return ['*'=>TRUE];}public function allowed($user,$module,$action){return TRUE;}},
    'Identity_model'=>new class {public function active_user($id){return replacementActor((int)$id);}}];
$replacementContext->Request_model=new Request_model();$replacementContext->Document_model=new Document_model();
$fileModel=new File_model();$files=new File_service();$operation=new Softcopy_operation_service();
$reflection=new ReflectionClass(Request_service::class);$service=$reflection->newInstanceWithoutConstructor();
$property=$reflection->getProperty('ci');$property->setAccessible(TRUE);$property->setValue($service,$replacementContext);
$ownedBlobs=[];$paths=[];
register_shutdown_function(static function()use(&$ownedBlobs) {
    foreach ($ownedBlobs as $path) if (is_file($path)) unlink($path);
});
function replacementActor(int $id):array {return ['id'=>$id,'role_id'=>1,'role_ids'=>[1],
    'name'=>'Reviewer '.$id,'position_title'=>'Reviewer','require_password_change'=>0];}
function replacementFixture($db):array {
    global $paths,$ownedBlobs;
    foreach ($ownedBlobs as $path) if (is_file($path)) unlink($path);
    $ownedBlobs=[];$paths=[];
    foreach (['disposals','workflow_history','workflow_steps','requests','status_history','softcopy_revisions','softcopy_documents','files','users','categories'] as $table)
        replacementCheck($db->query('DELETE FROM '.$table)!==FALSE,'Cannot reset '.$table);
    foreach ([20,30,40] as $id) replacementInsert($db,'users',['id'=>$id,'username'=>'replacement-'.$id,
        'first_name'=>'Reviewer','last_name'=>(string)$id,'position_title'=>'Reviewer','role_id'=>1,'password_hash'=>'unused']);
    replacementInsert($db,'categories',['id'=>7,'name'=>'Replacement test','folder_name'=>'Replacement test','created_by'=>20]);
    foreach ([9,21] as $id) replacementInsert($db,'softcopy_documents',['id'=>$id,'document_number'=>'SC-REPLACE-'.$id,
        'title'=>'Test document '.$id,'category_id'=>7,'created_by'=>20,'creation_source'=>'request','creation_reason'=>'Test',
        'current_revision_id'=>$id===9?601:NULL]);
    $directory=PK_ROOT.'/storage/documents';if (!is_dir($directory)) mkdir($directory,0700,TRUE);
    foreach ([699=>'approved',700=>'approved',710=>'pending',711=>'pending',720=>'approved'] as $id=>$status) {
        $storage=bin2hex(random_bytes(32));$path=$directory.'/'.$storage;
        $handle=fopen($path,'x');replacementCheck($handle!==FALSE,'Cannot create owned test blob.');
        fwrite($handle,'Controlled test file '.$id);fclose($handle);$ownedBlobs[]=$path;$paths[$id]=$path;
        replacementInsert($db,'files',['id'=>$id,'original_name'=>'test-'.$id.'.txt','storage_name'=>$storage,
            'size'=>filesize($path),'mime_type'=>'text/plain','fingerprint'=>hash_file('sha256',$path),'extension'=>'txt',
            'purpose'=>$status==='approved'?'creation':'revision','domain'=>'softcopy','document_id'=>$id===720?21:9,
            'uploaded_by'=>20,'status'=>$status,'approved_by'=>$status==='approved'?40:NULL]);
    }
    foreach ([600=>699,601=>700] as $id=>$file) replacementInsert($db,'softcopy_revisions',['id'=>$id,'document_id'=>9,
        'revision_number'=>$id===600?1:2,'reason'=>'Earlier approval','effective_date'=>'2026-10-01','page_number'=>1,
        'document_title'=>'Test document 9','new_revision_level'=>$id===600?'01':'02','new_effective_date'=>'2026-10-01',
        'date_received'=>'2026-10-01','date_released'=>'2026-10-01','approval_date'=>'2026-10-01',
        'file_id'=>$file,'uploaded_by'=>20,'approved_by'=>40]);
    $payload=['subject'=>'Revise test document','title'=>'Revised test document','new_revision_level'=>'03',
        'page_number'=>3,'effective_date'=>'2026-10-13','revision_file_id'=>710,'remarks'=>'Revision reason'];
    $graph=['steps'=>[]];foreach ([20,30,40] as $index=>$actor) $graph['steps'][]=[
        'key'=>'step_'.($index+1),'name'=>'Approval '.($index+1),'approver'=>['type'=>'user','value'=>$actor]];
    replacementInsert($db,'requests',['id'=>42,'reference'=>'REQ-REPLACEMENT','type'=>'softcopy_revise','requested_by'=>20,
        'softcopy_id'=>9,'payload'=>json_encode($payload),'snapshot'=>json_encode($graph),'status'=>'submitted','current_node'=>'step_1']);
    foreach ($graph['steps'] as $index=>$step) replacementInsert($db,'workflow_steps',['id'=>$index+101,'request_id'=>42,
        'node_key'=>$step['key'],'label'=>$step['name'],'assignment'=>json_encode($step['approver']),
        'candidates'=>'[]','assigned_user_id'=>$step['approver']['value'],'status'=>$index===0?'active':'pending']);
    return $payload;
}
function replacementReachFinal($service):void {
    $service->decide('softcopy',42,replacementActor(20),'approved','First approved');
    $service->decide('softcopy',42,replacementActor(30),'approved','Second approved');
}
function replacementOriginalIntact($db):void {
    global $paths;
    replacementCheck(is_file($paths[699]) && is_file($paths[700]),'Original Document Files must remain until successful final approval.');
    replacementCheck($db->get_where('files',['id'=>700])->row_array()['status']==='approved' &&
        (int)$db->get_where('softcopy_documents',['id'=>9])->row_array()['current_revision_id']===601,
        'Unfinished or failed requests must retain the original active file and revision.');
}

if (PHP_SAPI==='cli-server') {
    replacementCheck($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['attachment']),'Multipart upload required.');
    $payload=replacementFixture($connection);
    require_once APPPATH.'services/softcopy/softcopy_direct_service.php';
    $result=(new Softcopy_direct_service())->execute($payload+['type'=>'softcopy_revise','softcopy_id'=>9],20,$_FILES['attachment']);
    $new=$connection->get_where('files',['id'=>$result['file_id']])->row_array();
    $newPath=PK_ROOT.'/storage/documents/'.$new['storage_name'];$ownedBlobs[]=$newPath;
    replacementCheck(!is_file($paths[699]) && !is_file($paths[700]) && is_file($newPath),
        'Direct approval must replace the old controlled bytes after its transaction commits.');
    replacementCheck($connection->count_all('softcopy_revisions')===3 && $connection->count_all('workflow_history')===0,
        'Direct approval must preserve revision records without creating a request approval history.');
    echo "Real multipart direct revision replaces old files after approval and preserves revision history.\n";
    exit;
}

// Intermediate approvals, returns and rejections leave the original file intact.
replacementFixture($connection);
$service->decide('softcopy',42,replacementActor(20),'approved','First approved');replacementOriginalIntact($connection);
$service->decide('softcopy',42,replacementActor(30),'returned','Recheck');replacementOriginalIntact($connection);
$service->decide('softcopy',42,replacementActor(20),'approved','Rechecked');
$service->decide('softcopy',42,replacementActor(30),'rejected','Rejected');replacementOriginalIntact($connection);
replacementCheck(!is_file($paths[710]) && is_file($paths[711]),
    'Rejecting a revision must delete only its proposed attachment, preserving the current file and other pending files.');

replacementFixture($connection);
$connection->where('id',710)->update('files',['purpose'=>'creation','document_id'=>NULL]);
$connection->where('id',42)->update('requests',['type'=>'softcopy_create','softcopy_id'=>NULL,
    'payload'=>json_encode(['controlled_file_id'=>710])]);
$service->decide('softcopy',42,replacementActor(20),'rejected','Rejected creation');
replacementCheck(!is_file($paths[710]) && is_file($paths[700]),'Rejected creation must remove its unattached file only.');

replacementFixture($connection);
$connection->where('id',42)->update('requests',['status'=>'draft']);
$service->cancel('softcopy',42,replacementActor(20));
replacementCheck(!is_file($paths[710]) && is_file($paths[700]),'Cancelling a draft must delete its attachment only.');

require_once APPPATH.'services/documents/document_service.php';
replacementFixture($connection);
(new Document_service())->dispose('softcopy',9,20,'No longer required','other');
replacementCheck(!is_file($paths[699]) && !is_file($paths[700]) && !is_file($paths[710]) &&
    !is_file($paths[711]) && is_file($paths[720]),'Disposal must delete all connected files after commit, preserving other documents.');
replacementCheck($connection->count_all('softcopy_revisions')===2 && $connection->count_all('disposals')===1,
    'Deleting file bytes must retain revision and disposal history.');

replacementFixture($connection);
$connection->trans_begin();
$operation->apply('softcopy_cancel',['remarks'=>'Remove'],9,20,20,NULL);
replacementCheck(is_file($paths[700]) && $files->cleanup_retired()===0,'No file may be deleted before commit.');
$connection->trans_rollback();replacementOriginalIntact($connection);

// Cancellation removes controlled bytes on both direct and final approval paths.
replacementFixture($connection);
require_once APPPATH.'services/softcopy/softcopy_direct_service.php';
(new Softcopy_direct_service())->execute(['type'=>'softcopy_cancel','softcopy_id'=>9,'subject'=>'Remove document'],20);
replacementCheck(!is_file($paths[700]) && !is_file($paths[710]) && is_file($paths[720]),
    'Direct cancellation must remove connected bytes after commit.');
replacementFixture($connection);
$connection->where('id',42)->update('requests',['type'=>'softcopy_cancel','payload'=>json_encode(['remarks'=>'Remove document'])]);
replacementReachFinal($service);$service->decide('softcopy',42,replacementActor(40),'approved','Cancel approved');
replacementCheck(!is_file($paths[700]) && is_file($paths[720]),'Final cancellation approval must delete controlled bytes.');

// Removed documents become cleanup candidates; active documents cannot be retired.
replacementFixture($connection);
$files->retire_document(9);replacementOriginalIntact($connection);
$connection->where('id',9)->delete('softcopy_documents');
$files->retire_document(9);$files->cleanup_retired(9);
replacementCheck(!is_file($paths[700]) && is_file($paths[720]),'Removed document files must be deleted without touching unrelated storage.');

// Only final approval retires/deletes old bytes. Pending and unrelated files survive.
replacementFixture($connection);replacementReachFinal($service);replacementOriginalIntact($connection);
$service->decide('softcopy',42,replacementActor(40),'approved','Final approved');
replacementCheck(!is_file($paths[699]) && !is_file($paths[700]),'Final approval must remove the previous Document Files.');
replacementCheck(is_file($paths[710]) && is_file($paths[711]) && is_file($paths[720]),
    'Replacement, pending revisions and other documents must survive cleanup.');
replacementCheck($connection->get_where('files',['id'=>700])->row_array()['status']==='superseded' &&
    $connection->count_all('softcopy_revisions')===3 && $connection->count_all('workflow_history')===3,
    'File/revision metadata and every approval must remain recorded after deleting old bytes.');
replacementCheck($fileModel->approved_file(700)===NULL && $fileModel->approved_file(710)!==NULL,
    'Old Document FileURLs must stop downloading while the current approved file stays available.');
$history=$fileModel->histories_for_documents([9],replacementActor(20))[9];
replacementCheck(count($history)===3 && count(array_filter($history,static function($file){return (bool)$file['is_current'];}))===1,
    'File history must preserve superseded records and identify only one current download.');
replacementReject(static function()use($service){$service->decide('softcopy',42,replacementActor(40),'approved','Duplicate');},
    'Duplicate final approval must be refused.');

// A failed commit must roll back revision/approval state before any old-file unlink.
replacementFixture($connection);replacementReachFinal($service);
$replacementContext->db=new class($connection) {
    private $db;public function __construct($db){$this->db=$db;}
    public function __call($name,$args){return $this->db->$name(...$args);}
    public function trans_commit(){return FALSE;}
};
replacementReject(static function()use($service){$service->decide('softcopy',42,replacementActor(40),'approved','Commit failure');},
    'A failed final commit must not be reported as successful.');
$replacementContext->db=$connection;replacementOriginalIntact($connection);
replacementCheck($connection->count_all('softcopy_revisions')===2,'Failed commits must not register a replacement revision.');

foreach (['missing','changed','invalid_name'] as $failure) {
    replacementFixture($connection);replacementReachFinal($service);
    if ($failure==='missing') unlink($paths[710]);
    if ($failure==='changed') file_put_contents($paths[710],str_repeat('x',filesize($paths[710])));
    if ($failure==='invalid_name') $connection->where('id',710)->update('files',['storage_name'=>'../outside.txt']);
    replacementReject(static function()use($service){$service->decide('softcopy',42,replacementActor(40),'approved','Bad attachment');},
        'Unavailable or changed replacement bytes must prevent final approval.');
    replacementOriginalIntact($connection);
}

// A nested transaction cannot unlink old bytes before its outer transaction commits.
$payload=replacementFixture($connection);$connection->trans_begin();
$operation->apply('softcopy_revise',$payload,9,40,20,NULL);
replacementCheck(is_file($paths[700]),'Applying a revision inside a transaction must not delete bytes yet.');
replacementCheck($files->cleanup_superseded(9)===0 && is_file($paths[700]),'Cleanup must defer while any transaction remains active.');
$connection->trans_rollback();replacementOriginalIntact($connection);
$connection->trans_begin();$operation->apply('softcopy_revise',$payload,9,40,20,NULL);$connection->trans_commit();
replacementCheck($files->cleanup_superseded(9)===2 && !is_file($paths[700]),'Cleanup must delete committed superseded files only.');
replacementCheck($files->cleanup_superseded(9)===0,'Cleanup must be idempotent.');

// Shared storage must survive, and malformed old paths must never escape storage.
replacementFixture($connection);
$currentStorage=basename($paths[710]);$connection->where('id',700)->update('files',['storage_name'=>$currentStorage]);
replacementReachFinal($service);$service->decide('softcopy',42,replacementActor(40),'approved','Shared storage');
replacementCheck(is_file($paths[710]),'A superseded record must never delete bytes shared with the approved replacement.');
replacementFixture($connection);
$outside=PK_ROOT.'/storage/replacement-outside-'.bin2hex(random_bytes(8)).'.txt';
file_put_contents($outside,'Outside document storage');$ownedBlobs[]=$outside;
$connection->where('id',700)->update('files',['storage_name'=>'../'.basename($outside)]);
replacementReachFinal($service);$service->decide('softcopy',42,replacementActor(40),'approved','Malformed old path');
replacementCheck(is_file($outside),'Old storage paths must never delete a file outside controlled document storage.');
// Reconciliation catches legacy terminal records and retries idempotently.
replacementFixture($connection);
$connection->where('id',9)->update('softcopy_documents',['status'=>'disposed']);
replacementCheck($files->reconcile_retired()===4 && is_file($paths[720]),
    'Daily reconciliation must remove files on legacy disposed documents.');
replacementCheck($files->reconcile_retired()===0,'Repeated reconciliation must be harmless.');
replacementFixture($connection);
$connection->where('id',42)->update('requests',['status'=>'rejected']);
replacementCheck($files->reconcile_retired()===1 && is_file($paths[700]) && is_file($paths[711]),
    'Legacy rejection cleanup must preserve active files and other pending requests.');
foreach (['reject','dispose','cancel_draft'] as $action) {
    replacementFixture($connection);
    if ($action==='cancel_draft') $connection->where('id',42)->update('requests',['status'=>'draft']);
    $replacementContext->db=new class($connection) {
        private $db;public function __construct($db){$this->db=$db;}
        public function __call($name,$args){return $this->db->$name(...$args);}
        public function trans_commit(){return FALSE;}
    };
    replacementReject(static function() use($action,$service) {
        if ($action==='reject') $service->decide('softcopy',42,replacementActor(20),'rejected','Commit failure');
        elseif ($action==='dispose') (new Document_service())->dispose('softcopy',9,20,'Commit failure','other');
        else $service->cancel('softcopy',42,replacementActor(20));
    },'Failed commit must refuse retirement: '.$action);
    $replacementContext->db=$connection;
    replacementOriginalIntact($connection);
    replacementCheck(is_file($paths[710]) && $connection->get_where('files',['id'=>710])->row_array()['status']==='pending',
        'Rejected, disposed or cancelled bytes must survive failed commits.');
}
$connection->close();
echo "Final approval, returns/rejections, file replacement, rollback, protected downloads and retained history passed.\n";
