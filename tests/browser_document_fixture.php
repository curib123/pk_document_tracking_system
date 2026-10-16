<?php
// CLI-only fixtures for the browser runner's newly created disposable database.
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('PK_TEST_DB') !== '1' ||
    !preg_match('/^pk_dts_browser_[a-f0-9]+_test$/', (string) getenv('DB_DATABASE'))) exit(1);
define('PK_ROOT', getenv('PK_BROWSER_ROOT') ?: dirname(__DIR__));
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', getenv('DB_DATABASE'));
$db->set_charset('utf8mb4');
function fixtureInsert($db, $table, $data): int {
    $columns = implode(',', array_map(static function ($key) { return '`'.$key.'`'; }, array_keys($data)));
    $statement = $db->prepare('INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',', array_fill(0, count($data), '?')).')');
    $values = array_values($data);
    $statement->bind_param(str_repeat('s', count($values)), ...$values);
    $statement->execute();
    return (int) $db->insert_id;
}
$hash = password_hash(getenv('PK_BROWSER_PASSWORD'), PASSWORD_DEFAULT);
foreach ([1=>'browser-admin',2=>'browser-staff'] as $id=>$username) fixtureInsert($db,'users',[
    'id'=>$id,'username'=>$username,'first_name'=>'Browser','last_name'=>$id===1?'Administrator':'Staff',
    'position_title'=>'Tester','role_id'=>$id===1?1:5,'password_hash'=>$hash,'active'=>1,'require_password_change'=>0
]);
fixtureInsert($db,'categories',['id'=>1,'name'=>'Browser Test Category','folder_name'=>'Browser Test Category','created_by'=>1]);
fixtureInsert($db,'areas',['id'=>1,'name'=>'Browser Test Area']);
fixtureInsert($db,'specifics',['id'=>1,'name'=>'Browser Test Specific','area_id'=>1]);
fixtureInsert($db,'locations',['id'=>1,'name'=>'Browser Test Location','sequence_number'=>1,'area_id'=>1,'specific_id'=>1]);
$blobs = [];
$successful = false;
register_shutdown_function(static function () use (&$blobs, &$successful) {
    if (!$successful) foreach ($blobs as $storage) @unlink(PK_ROOT.'/storage/documents/'.$storage);
});
$contents = ['txt'=>'Browser controlled text <script>alert("unsafe")</script>',
    'png'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jOzQAAAAASUVORK5CYII='),
    'docx'=>'Office browser fixture'];
require PK_ROOT.'/vendor/autoload.php';
$pdf = new FPDF();
for ($i=1;$i<=2;$i++) { $pdf->AddPage(); $pdf->SetFont('Arial','',14); $pdf->Cell(0,10,'Controlled PDF - Page '.$i); }
$contents['pdf']=$pdf->Output('S');
$manifest = [];
foreach ($contents as $extension=>$bytes) {
    $id=fixtureInsert($db,'softcopy_documents',['document_number'=>'BROWSER-'.strtoupper($extension),
        'title'=>'Browser '.strtoupper($extension).' controlled document','category_id'=>1,'created_by'=>1,
        'creation_reason'=>'Browser fixture','creation_source'=>'direct']);
    $storage=bin2hex(random_bytes(32));
    file_put_contents(PK_ROOT.'/storage/documents/'.$storage,$bytes);$blobs[]=$storage;
    $fileId=fixtureInsert($db,'files',['original_name'=>'controlled-'.$extension.'.'.$extension,'storage_name'=>$storage,
        'size'=>strlen($bytes),'mime_type'=>['txt'=>'text/plain','png'=>'image/png','pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$extension],
        'fingerprint'=>hash('sha256',$bytes),'extension'=>$extension,'purpose'=>'creation','domain'=>'softcopy',
        'document_id'=>$id,'uploaded_by'=>1,'status'=>'approved','approved_by'=>1]);
    $today=date('Y-m-d');
    $revision=fixtureInsert($db,'softcopy_revisions',['document_id'=>$id,'revision_number'=>1,'reason'=>'Browser fixture',
        'effective_date'=>$today,'new_effective_date'=>$today,'new_revision_level'=>'00','date_received'=>$today,
        'date_released'=>$today,'approval_date'=>$today,'file_id'=>$fileId,'uploaded_by'=>1,'approved_by'=>1,'page_number'=>2]);
    $db->query('UPDATE softcopy_documents SET current_revision_id='.$revision.' WHERE id='.$id);
    $manifest[$extension]=['document_id'=>$id,'file_id'=>$fileId,'storage_name'=>$storage];
}
fixtureInsert($db,'hardcopy_documents',['title'=>'Browser hardcopy document','sequence_number'=>'BROWSER-HC','area_id'=>1,
    'specific_id'=>1,'location_id'=>1,'holder_id'=>1,'created_by'=>1,'creation_reason'=>'Browser fixture']);
fixtureInsert($db,'hardcopy_documents',['title'=>'Older hardcopy document','sequence_number'=>'BROWSER-OLD','area_id'=>1,
    'specific_id'=>1,'location_id'=>1,'holder_id'=>1,'created_by'=>1,'creation_reason'=>'Browser fixture','created_at'=>'2025-01-15 08:00:00']);
$storage=bin2hex(random_bytes(32));$bytes='Rejected proposal fixture';
file_put_contents(PK_ROOT.'/storage/documents/'.$storage,$bytes);$blobs[]=$storage;
$pending=fixtureInsert($db,'files',['original_name'=>'rejected-proposal.txt','storage_name'=>$storage,'size'=>strlen($bytes),
    'mime_type'=>'text/plain','fingerprint'=>hash('sha256',$bytes),'extension'=>'txt','purpose'=>'revision','domain'=>'softcopy',
    'document_id'=>$manifest['txt']['document_id'],'uploaded_by'=>2,'status'=>'pending']);
$workflow=fixtureInsert($db,'workflows',['name'=>'Browser Revision','workflow_key'=>'browser_revision','request_type'=>'softcopy_revise','created_by'=>1]);
$graph=['steps'=>[['key'=>'step_1','name'=>'Review','approver'=>['type'=>'user','value'=>1]]]];
$version=fixtureInsert($db,'workflow_versions',['workflow_id'=>$workflow,'version_number'=>1,'status'=>'published','is_default'=>1,'graph'=>json_encode($graph),'created_by'=>1]);
$request=fixtureInsert($db,'requests',['reference'=>'BROWSER-REQUEST','type'=>'softcopy_revise','requested_by'=>2,
    'softcopy_id'=>$manifest['txt']['document_id'],'payload'=>json_encode(['subject'=>'Browser revision proposal','title'=>'Browser proposed title','revision_file_id'=>$pending,'new_revision_level'=>'01','page_number'=>1]),
    'status'=>'submitted','workflow_version_id'=>$version,'snapshot'=>json_encode($graph),'current_node'=>'step_1']);
fixtureInsert($db,'workflow_steps',['request_id'=>$request,'node_key'=>'step_1','label'=>'Review','assignment'=>json_encode($graph['steps'][0]['approver']),
    'assigned_user_id'=>1,'candidates'=>'[1]','status'=>'active']);
fixtureInsert($db,'requests',['reference'=>'BROWSER-DRAFT','type'=>'softcopy_cancel','requested_by'=>1,
    'softcopy_id'=>$manifest['txt']['document_id'],'payload'=>json_encode(['subject'=>'Browser draft']),'status'=>'draft']);
$manifest['request']=['id'=>$request,'storage_name'=>$storage];
$manifest['blobs']=$blobs;
$manifest['cleanup_marker']='.controlled-file-cleanup-'.substr(hash('sha256',(getenv('DB_HOST') ?: '127.0.0.1').':'.getenv('DB_DATABASE')),0,16);
$successful = true;
echo json_encode($manifest);
