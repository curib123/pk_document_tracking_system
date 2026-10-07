<?php
declare(strict_types=1);
/** Real CI3/MySQLi/Query Builder integration; never uses the recording test driver. */
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Context,Database,Database_error,Problem};
if (getenv('PK_TEST_DB')!=='1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    fwrite(STDERR,"Use PK_TEST_DB=1 and an installed dedicated *_test database.\n"); exit(2);
}
if (!extension_loaded('mysqli')) { fwrite(STDERR,"BLOCKED: mysqli is unavailable; no real database tests ran.\n"); exit(2); }
$db=Database::connect();$ctx=new Context($db);$checks=0;
function qb_assert(bool $condition,string $name): void { global $checks;if(!$condition)throw new RuntimeException($name);++$checks;echo "PASS $name\n"; }
function qb_problem(callable $work): Problem { try{$work();}catch(Problem $e){return $e;}throw new RuntimeException('Expected domain rejection'); }
$admin=$db->first($db->builder->reset_query()->where('username','admin')->get('users'));
if(!$admin)throw new RuntimeException('Install the dedicated test database first.');
$ctx->identify((int)$admin['id']);$catalog=new Catalog_service($ctx);
qb_assert($db->builder instanceof CI_DB_mysqli_driver,'uses actual CI3 mysqli driver');
$failedName='qb-rollback-'.bin2hex(random_bytes(6));
try {
    $db->transaction(function()use($catalog,$failedName){$catalog->save('areas',['name'=>$failedName]);$catalog->save('areas',['name'=>$failedName]);});
    throw new RuntimeException('Duplicate key unexpectedly committed.');
} catch(Database_error $e) { qb_assert($e->driverCode===1062,'native duplicate-key code retained'); }
qb_assert($db->first($db->builder->reset_query()->where('name',$failedName)->get('areas'))===null,'failed transaction rolls back earlier successful insert');
$prefix='qb-'.bin2hex(random_bytes(5));
$db->begin();
try {
    $name=$prefix." O'Reilly 100%_000";
    $area=$catalog->save('areas',['name'=>$name]);
    qb_assert(is_int($area['id']),'insert ID stays numeric');
    $saved=$db->row('areas',$area['id']);
    qb_assert($saved['name']===$name,'quotes, percent, underscore and leading zeros round-trip unchanged');
    qb_assert(is_int($saved['id']) && is_int($saved['version']),'native result metadata normalizes integer columns');
    $read=$ctx->model(Read_model::class);
    $data=$read->listing('areas',['q'=>'100%_000','limit'=>10]);
    qb_assert(count($data['rows'])===1 && $data['rows'][0]['name']===$name,'literal wildcard search is escaped by actual Query Builder');
    $catalog->save('areas',['name'=>$prefix.' zero0']);
    qb_assert($read->listing('areas',['q'=>'zero0'])['total']>=1,'zero-containing search works');
    $empty=$read->listing('areas',['q'=>'not-existing-'.$prefix,'draw'=>12,'length'=>10]);
    qb_assert($empty['draw']===12 && $empty['recordsFiltered']===0 && $empty['recordsTotal']>=2,'DataTables filtered count and draw preserved');
    $catalog->save('areas',['id'=>$area['id'],'version'=>$saved['version'],'name'=>$name.' edited']);
    $error=qb_problem(fn()=>$catalog->save('areas',['id'=>$area['id'],'version'=>$saved['version'],'name'=>'stale']));
    qb_assert($error->status===409,'stale update rejected on locked real row');
    $specific=$catalog->save('specifics',['name'=>$prefix,'area_id'=>$area['id']]);
    $asset=$catalog->save('assets',['asset_number'=>$prefix,'specific_id'=>$specific['id']]);
    $location=$catalog->save('locations',['name'=>$prefix,'code'=>$prefix,'asset_id'=>$asset['id'],'specific_id'=>$specific['id']]);
    $role=$db->first($db->builder->reset_query()->where('name','Staff')->get('roles'));
    $staff=$catalog->save('users',['username'=>$prefix,'first_name'=>'Query','last_name'=>'Staff','position_title'=>'Tester','role_id'=>$role['id']]);
    $documents=new Document_service($ctx);
    $hard=$documents->direct('hardcopy',['title'=>$prefix,'area_id'=>$area['id'],'specific_id'=>$specific['id'],'asset_id'=>$asset['id'],'location_id'=>$location['id'],'holder_id'=>$admin['id'],'reason'=>'Native Query Builder test']);
    $ctx->identify($staff['id']);
    qb_assert(!$documents->canRead('hardcopy',$hard['id']),'unassigned staff has no file access');
    $requests=new Request_service($ctx);
    $request=$requests->save(['type'=>'access','hardcopy_id'=>$hard['id'],'payload'=>['reason'=>'Native approval test','expiration_date'=>date('Y-m-d')]]);
    $requests->submit(['id'=>$request['id'],'version'=>1]);
    $ctx->identify((int)$admin['id']);
    $tasks=$read->listing('my_tasks',[]);
    qb_assert(in_array($request['id'],array_column($tasks['rows'],'id'),true),'actual JSON_CONTAINS task filter finds assigned approval');
    $detail=$read->detail('requests',$request['id']);
    $step=array_values(array_filter($detail['related']['steps'],fn($step)=>$step['status']==='pending'))[0];
    $requests->decide(['id'=>$request['id'],'version'=>$detail['row']['version'],'step_id'=>$step['id'],'decision'=>'approve','comments'=>'Verified via native query builder']);
    $ctx->identify($staff['id']);
    qb_assert($documents->canRead('hardcopy',$hard['id']),'approved grant is effective with native joins and date predicate');
    qb_assert($read->listing('my_tasks',[])['total']===0,'staff never sees another approver task');
    $mine=$read->listing('my_requests',[]);
    qb_assert(count($mine['rows'])===1 && (int)$mine['rows'][0]['requested_by']===$staff['id'],'my-requests scope remains requester-only');
    $grant=$db->first($db->builder->reset_query()->where('request_id',$request['id'])->get('access_grants'));
    $db->update('access_grants',(int)$grant['id'],['expires_at'=>date('Y-m-d H:i:s',time()-60)]);
    qb_assert(!$documents->canRead('hardcopy',$hard['id']),'expired grants fail immediately without maintenance');
    $notifications=$read->listing('notifications',[]);
    foreach($notifications['rows']as$notice)qb_assert((int)$notice['user_id']===$staff['id'],'notification list does not leak another account');
} finally { $db->rollback(); }
echo "$checks real MySQLi/Query Builder assertions passed; fixture data rolled back.\n";
