<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Context,Database,Problem};
if (getenv('PK_TEST_DB') !== '1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) { fwrite(STDERR,"Use PK_TEST_DB=1 and a dedicated database whose name ends in _test.\n"); exit(2); }
if (!extension_loaded('mysqli')) { fwrite(STDERR,"BLOCKED: MySQLi extension is unavailable; integration tests did not run.\n"); exit(2); }
$db=Database::connect(); $ctx=new Context($db); $admin=$db->one("SELECT id FROM users WHERE username='admin'");
if (!$admin) throw new RuntimeException('Run the installer first.');
$ctx->identify((int)$admin['id']);
$checks=0;
function check(bool $condition,string $name): void { global $checks; if (!$condition) throw new RuntimeException($name); ++$checks; echo "PASS $name\n"; }
function denied(callable $fn,string $name): void { global $db; $db->query('SAVEPOINT expected_denial'); try { $fn(); } catch(Problem $e) { $db->query('ROLLBACK TO SAVEPOINT expected_denial'); $db->query('RELEASE SAVEPOINT expected_denial'); check(true,$name); return; } throw new RuntimeException('Expected denial: '.$name); }
$db->begin();
$db->transaction(function() use($db,$ctx) {
    $catalog=new Catalog_service($ctx);
    $staffRole=(int)$db->one("SELECT id FROM roles WHERE name='Staff'")['id'];
    $staff=$catalog->save('users',['username'=>'staff_'.bin2hex(random_bytes(3)),'first_name'=>'Test','last_name'=>'Staff','position_title'=>'Clerk','role_id'=>$staffRole]);
    check(isset($staff['initial_password']),'generated password shown once');
    $row=$db->row('users',$staff['id']); check(password_verify($staff['initial_password'],$row['password_hash']),'initial password hashed');
    check((int)$row['require_password_change']===1,'new user forced password change');
    $area=$catalog->save('areas',['name'=>'QA '.uniqid()]);
    $specific=$catalog->save('specifics',['name'=>'Room','area_id'=>$area['id']]);
    $asset=$catalog->save('assets',['asset_number'=>'CAB-'.uniqid(),'specific_id'=>$specific['id']]);
    $location=$catalog->save('locations',['name'=>'Shelf A','code'=>'LOC-'.uniqid(),'specific_id'=>$specific['id'],'asset_id'=>$asset['id']]);
    $destination=$catalog->save('locations',['name'=>'Shelf B','code'=>'LOC-'.uniqid(),'specific_id'=>$specific['id'],'asset_id'=>$asset['id']]);
    $category=$catalog->save('categories',['name'=>'Forms','folder_name'=>'forms']);

    $reader=new Read_service($ctx);
    $specificOptions=$reader->lookups(['kind'=>'specifics','selected'=>$specific['id']])['options'];
    $specificOption=current(array_filter($specificOptions,fn(array $option)=>(int)$option['id']===(int)$specific['id']));
    check(
        (int)$specificOption['area_id']===(int)$area['id'],
        'specific lookup exposes its predefined area'
    );

    $assetOptions=$reader->lookups(['kind'=>'assets','selected'=>$asset['id']])['options'];
    $assetOption=current(array_filter($assetOptions,fn(array $option)=>(int)$option['id']===(int)$asset['id']));
    check(
        (int)$assetOption['specific_id']===(int)$specific['id']
        && (int)$assetOption['area_id']===(int)$area['id'],
        'asset lookup exposes its predefined specific and area'
    );

    $locationOptions=$reader->lookups(['kind'=>'locations','selected'=>$location['id']])['options'];
    $locationOption=current(array_filter($locationOptions,fn(array $option)=>(int)$option['id']===(int)$location['id']));
    check(
        (int)$locationOption['asset_id']===(int)$asset['id']
        && (int)$locationOption['specific_id']===(int)$specific['id']
        && (int)$locationOption['area_id']===(int)$area['id'],
        'location lookup exposes its complete predefined hierarchy'
    );
    $documents=new Document_service($ctx);
    $hard=$documents->direct('hardcopy',['title'=>'Test physical record','area_id'=>$area['id'],'specific_id'=>$specific['id'],'asset_id'=>$asset['id'],'location_id'=>$location['id'],'holder_id'=>$staff['id'],'reason'=>'Initial registration']);
    check((int)$db->row('hardcopy_documents',$hard['id'])['location_id']===$location['id'],'direct hardcopy created');
    denied(fn()=>$documents->direct('hardcopy',['title'=>'Duplicate storage','area_id'=>$area['id'],'specific_id'=>$specific['id'],'asset_id'=>$asset['id'],'location_id'=>$location['id'],'holder_id'=>$staff['id'],'reason'=>'Test']), 'one document per current location');
    $adminId=$ctx->id(); $ctx->identify($staff['id']);
    denied(fn()=>$documents->direct('hardcopy',[]),'staff cannot bypass direct-create authorization');
    $requests=new Request_service($ctx);
    $request=$requests->save(['type'=>'transfer','hardcopy_id'=>$hard['id'],'payload'=>['area_id'=>$area['id'],'specific_id'=>$specific['id'],'asset_id'=>$asset['id'],'location_id'=>$destination['id'],'recipient_id'=>$adminId,'document_copy_number'=>'COPY-1','reason'=>'Move to document control']]);
    check($db->row('requests',$request['id'])['status']==='draft','request is independent draft');
    $requests->submit(['id'=>$request['id'],'version'=>1]);
    $r=$db->row('requests',$request['id']); $snapshot=$r['snapshot'];
    check($r['status']==='pending','submitted request waiting for workflow');
    $ctx->identify($adminId);
    $step=$db->one("SELECT * FROM workflow_steps WHERE request_id=? AND status='pending'",[$request['id']]);
    $requests->decide(['id'=>$request['id'],'version'=>(int)$r['version'],'step_id'=>$step['id'],'decision'=>'approve','comments'=>'Approved']);
    $transfer=$db->one('SELECT * FROM transfers WHERE request_id=?',[$request['id']]);
    check($transfer['status']==='for_transfer','approval creates transfer awaiting physical movement');
    check((int)$db->row('hardcopy_documents',$hard['id'])['location_id']===$location['id'],'approval does not change current location');
    $ctx->identify($staff['id']);
    $transfers=new Transfer_service($ctx);
    $transfers->dispatch(['id'=>$transfer['id'],'version'=>(int)$transfer['version'],'comments'=>'Delivered']);
    $transfer=$db->row('transfers',(int)$transfer['id']);
    denied(fn()=>$transfers->receive(['id'=>$transfer['id'],'version'=>(int)$transfer['version'],'decision'=>'accepted','comments'=>'Not the recipient']),'sender cannot accept for recipient');
    check((int)$db->row('hardcopy_documents',$hard['id'])['location_id']===$location['id'],'dispatch does not change current location');
    $ctx->identify($adminId);
    $transfers->receive(['id'=>$transfer['id'],'version'=>(int)$transfer['version'],'decision'=>'accepted','comments'=>'Received']);
    check((int)$db->row('hardcopy_documents',$hard['id'])['location_id']===$destination['id'],'recipient acceptance changes current location');
    denied(fn()=>$transfers->receive(['id'=>$transfer['id'],'version'=>(int)$transfer['version'],'decision'=>'accepted','comments'=>'Duplicate']),'duplicate receipt rejected');
    check($db->row('requests',$request['id'])['snapshot']===$snapshot,'workflow snapshot remains unchanged');
    check(count($db->all('SELECT * FROM workflow_history WHERE request_id=?',[$request['id']]))>=4,'workflow and transfer history retained');
    check(count($db->all("SELECT * FROM status_history WHERE domain='hardcopy' AND document_id=?",[$hard['id']]))>=2,'document status history retained');
    check(count($db->all('SELECT * FROM notifications WHERE user_id=?',[$staff['id']]))>0,'notifications created');
    $workflowService=new Workflow_service($ctx);
    $adminRole=(int)$db->one("SELECT role_id FROM users WHERE id=?",[$adminId])['role_id'];

    // First publication must become default directly, without unrelated sequence writes.
    $freshWorkflowId=$db->insert('workflows',[
        'workflow_key'=>'test_first_publish_'.bin2hex(random_bytes(4)),
        'name'=>'First publish regression',
        'description'=>'Regression coverage for first usable workflow publication.',
        'request_type'=>'test_first_publish_'.bin2hex(random_bytes(3)),
        'created_by'=>$adminId,
    ]);
    $firstDraftResult=$workflowService->version([
        'workflow_id'=>$freshWorkflowId,
        'graph'=>['steps'=>[
            ['name'=>'Administrator review','approver'=>['type'=>'role','value'=>$adminRole]]
        ]]
    ]);
    $firstDraft=$db->row('workflow_versions',(int)$firstDraftResult['id']);
    $workflowService->publish([
        'id'=>(int)$firstDraft['id'],
        'version'=>(int)$firstDraft['version'],
        'reason'=>'Publish first usable workflow'
    ]);
    $firstPublished=$db->row('workflow_versions',(int)$firstDraft['id']);
    $freshWorkflow=$db->row('workflows',$freshWorkflowId);
    check(
        $firstPublished['status']==='published'
        && (int)$firstPublished['is_default']===1
        && (int)$freshWorkflow['active']===1,
        'first workflow publish becomes default without sequence side effects'
    );

    $removableDraftResult=$workflowService->version([
        'workflow_id'=>$freshWorkflowId,
        'graph'=>['steps'=>[
            ['name'=>'Second administrator review','approver'=>['type'=>'role','value'=>$adminRole]]
        ]]
    ]);
    $removableDraft=$db->row('workflow_versions',(int)$removableDraftResult['id']);
    $workflowService->deleteVersion([
        'id'=>(int)$removableDraft['id'],
        'version'=>(int)$removableDraft['version'],
        'reason'=>'Discard test draft'
    ]);
    check(
        $db->one(
            'SELECT id FROM workflow_versions WHERE id=?',
            [(int)$removableDraft['id']]
        )===null,
        'unpublished workflow draft can be removed'
    );
    denied(
        fn()=>$workflowService->deleteVersion([
            'id'=>(int)$firstPublished['id'],
            'version'=>(int)$firstPublished['version'],
            'reason'=>'Must not delete published version'
        ]),
        'published workflow version cannot be removed'
    );

    $transferWorkflow=$db->one("SELECT * FROM workflows WHERE request_type='transfer' AND active=1 LIMIT 1");
    $oldDefault=$db->one("SELECT * FROM workflow_versions WHERE workflow_id=? AND is_default=1 LIMIT 1",[$transferWorkflow['id']]);
    $draftResult=$workflowService->version(['workflow_id'=>(int)$transferWorkflow['id'],'graph'=>['steps'=>[
        ['name'=>'Transfer manager review','approver'=>['type'=>'role','value'=>$adminRole]]
    ]]]);
    $draft=$db->row('workflow_versions',(int)$draftResult['id']);
    check($draft['status']==='draft' && (int)$draft['version_number']>(int)$oldDefault['version_number'],'new workflow version starts as a later draft');
    $workflowService->publish(['id'=>(int)$draft['id'],'version'=>(int)$draft['version'],'reason'=>'Publish candidate routing']);
    $published=$db->row('workflow_versions',(int)$draft['id']);
    check($published['status']==='published' && (int)$published['is_default']===0,'later published version does not replace the default automatically');
    $workflowService->setDefault(['id'=>(int)$published['id'],'version'=>(int)$published['version'],'reason'=>'Use reviewed routing for new requests']);
    $selected=$db->row('workflow_versions',(int)$published['id']);
    check((int)$selected['is_default']===1,'published workflow version can be selected as default');
    check((int)$db->one("SELECT COUNT(*) n FROM workflow_versions WHERE workflow_id=? AND status='published' AND is_default=1",[$transferWorkflow['id']])['n']===1,'exactly one published default version remains');
    check($db->row('requests',$request['id'])['snapshot']===$snapshot,'changing the default workflow cannot alter old request snapshots');
    // A synthetic private PDF record tests revision transactions; browser upload is tested separately.
    $ctx->identify($adminId);
    $file=$db->insert('files',['original_name'=>'form.pdf','storage_name'=>bin2hex(random_bytes(16)).'.pdf','size'=>10,'mime_type'=>'application/pdf','fingerprint'=>hash('sha256','fixture'),'extension'=>'pdf','uploaded_by'=>$adminId]);
    $soft=$documents->direct('softcopy',['title'=>'Quality form','category_id'=>$category['id'],'file_id'=>$file,'reason'=>'Initial controlled document','effective_date'=>date('Y-m-d'),'page_number'=>1]);
    $before=$db->row('softcopy_documents',$soft['id']);
    $file2=$db->insert('files',['original_name'=>'form-v2.pdf','storage_name'=>bin2hex(random_bytes(16)).'.pdf','size'=>10,'mime_type'=>'application/pdf','fingerprint'=>hash('sha256','fixture2'),'extension'=>'pdf','uploaded_by'=>$adminId]);
    $documents->direct('softcopy',['id'=>$soft['id'],'version'=>(int)$before['version'],'title'=>'Quality form revised','category_id'=>$category['id'],'file_id'=>$file2,'reason'=>'Updated','effective_date'=>date('Y-m-d'),'page_number'=>2]);
    check(count($db->all('SELECT * FROM softcopy_revisions WHERE document_id=?',[$soft['id']]))===2,'all revision records preserved');
    check((int)$db->row('softcopy_documents',$soft['id'])['current_revision_id']!==(int)$before['current_revision_id'],'exactly one current revision pointer advanced');
    denied(fn()=>$documents->direct('softcopy',['id'=>$soft['id'],'version'=>(int)$before['version'],'reason'=>'Stale update']),'stale document edit rejected');
    $ctx->identify($staff['id']);
    check(!$documents->canRead('softcopy',$soft['id']),'unassigned staff cannot read controlled files');
    $access=$requests->save(['type'=>'access','softcopy_id'=>$soft['id'],'payload'=>['reason'=>'Read the current form','expiration_date'=>date('Y-m-d')]]);
    $requests->submit(['id'=>$access['id'],'version'=>1]);
    $r=$db->row('requests',$access['id']); $accessSnapshot=$r['snapshot']; $accessVersion=$r['workflow_version_id'];
    $ctx->identify($adminId); $step=$db->one("SELECT * FROM workflow_steps WHERE request_id=? AND status='pending'",[$access['id']]);
    $requests->decide(['id'=>$r['id'],'version'=>$r['version'],'step_id'=>$step['id'],'decision'=>'return','comments'=>'Clarify the purpose']);
    check($db->row('requests',$r['id'])['status']==='returned','return decision keeps request for correction');
    $ctx->identify($staff['id']); $r=$db->row('requests',$r['id']);
    $requests->save(['id'=>$r['id'],'version'=>$r['version'],'type'=>'access','softcopy_id'=>$soft['id'],'payload'=>['reason'=>'Corrected purpose for audit','expiration_date'=>date('Y-m-d')]]);
    $r=$db->row('requests',$r['id']); $requests->submit(['id'=>$r['id'],'version'=>$r['version']]);
    $r=$db->row('requests',$r['id']);
    check($r['snapshot']===$accessSnapshot && $r['workflow_version_id']===$accessVersion,'correction resubmits against original workflow snapshot');
    $ctx->identify($adminId); $step=$db->one("SELECT * FROM workflow_steps WHERE request_id=? AND status='pending'",[$r['id']]);
    $requests->decide(['id'=>$r['id'],'version'=>$r['version'],'step_id'=>$step['id'],'decision'=>'approve','comments'=>'Approved corrected purpose']);
    $ctx->identify($staff['id']); check($documents->canRead('softcopy',$soft['id']),'approved access grant permits controlled file access');
    $grant=$db->one('SELECT * FROM access_grants WHERE request_id=?',[$r['id']]);
    $requests->revoke(['id'=>$grant['id'],'version'=>$grant['version'],'reason'=>'Finished reviewing']);
    check(!$documents->canRead('softcopy',$soft['id']),'returned access immediately denies content');
    $ctx->identify($adminId);
    $retain=$documents->direct('hardcopy',['title'=>'Retention fixture','area_id'=>$area['id'],'specific_id'=>$specific['id'],'asset_id'=>$asset['id'],'location_id'=>$location['id'],'holder_id'=>$staff['id'],'retention_enabled'=>1,'retention_start_date'=>date('Y-m-d'),'retention_end_date'=>date('Y-m-d',strtotime('+1 year')),'reason'=>'Register retention-controlled copy']);
    $ctx->identify($staff['id']);
    denied(fn()=>$requests->save(['type'=>'disposal','hardcopy_id'=>$retain['id'],'payload'=>['reason'=>'Too early','disposal_action'=>'shred']]),'retention prevents premature disposal');
    $ctx->identify($adminId); $record=$db->row('hardcopy_documents',$retain['id']);
    $documents->direct('hardcopy',array_merge($record,['retention_start_date'=>date('Y-m-d',strtotime('-2 years')),'retention_end_date'=>date('Y-m-d',strtotime('-1 day')),'reason'=>'Correct recorded retention dates']));
    $ctx->identify($staff['id']);
    $dispose=$requests->save(['type'=>'disposal','hardcopy_id'=>$retain['id'],'payload'=>['reason'=>'Retention ended','disposal_action'=>'shred']]);
    $requests->submit(['id'=>$dispose['id'],'version'=>1]);
    $r=$db->row('requests',$dispose['id']); $step=$db->one("SELECT * FROM workflow_steps WHERE request_id=? AND status='pending'",[$r['id']]);
    $ctx->identify($adminId); $requests->decide(['id'=>$r['id'],'version'=>$r['version'],'step_id'=>$step['id'],'decision'=>'approve','comments'=>'Dispose as requested']);
    $disposed=$db->row('hardcopy_documents',$retain['id']);
    check($disposed['status']==='disposed' && $disposed['previous_status']==='active','disposal preserves previous document status');
    check($disposed['location_id']===null,'disposal releases current physical location while retaining history');
    $disposal=$db->one('SELECT * FROM disposals WHERE request_id=?',[$dispose['id']]);
    check((int)json_decode($disposal['previous_state'],true)['location_id']===$location['id'],'disposal preserves complete physical record snapshot');
    $ctx->identify($staff['id']);
    $auditAttempt=new \Read_service($ctx);
    denied(fn()=>$auditAttempt->listing('audit',[]),'ordinary staff cannot query global audit records');
    $ctx->identify($adminId);
    try { $db->update('audit_logs',1,['action'=>'tampered'],false); throw new RuntimeException('Audit update unexpectedly allowed'); } catch (LogicException $e) { check(true,'audit records append-only'); }
});
$db->rollback();
echo "$checks integration assertions passed.\n";
