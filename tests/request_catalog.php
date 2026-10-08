<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/bootstrap.php';
use Pk\Core\{Context, Database, Problem};
if (getenv('PK_TEST_DB') !== '1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    exit("Use an isolated *_test database.\n");
}
function catalogCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function catalogDenied(callable $fn, int $status): void {
    try { $fn(); } catch (Problem $e) { catalogCheck($e->status === $status, 'denied with expected status ' . $status); return; }
    throw new RuntimeException('Expected an authorization failure');
}
$db = Database::connect();
$ctx = new Context($db);
$db->begin();
try {
    $db->transaction(function () use ($db, $ctx): void {
        $adminId = (int) $db->one("SELECT id FROM users WHERE username='admin'")['id'];
        $roleId = (int) $db->one("SELECT id FROM roles WHERE name='Staff'")['id'];
        $ctx->identify($adminId);
        $catalog = new Catalog_service($ctx);
        $staff = $catalog->save('users', ['username'=>'picker_'.bin2hex(random_bytes(4)), 'first_name'=>'Picker', 'last_name'=>'Staff', 'position_title'=>'Clerk', 'role_id'=>$roleId]);
        $permission = $db->one("SELECT id FROM permissions WHERE module_key='documents' AND action_key='request_catalog'");
        $permissionId = $permission ? (int)$permission['id'] : $db->insert('permissions', ['name'=>'Documents: Request Catalog', 'module_key'=>'documents','module_label'=>'Documents','action_key'=>'request_catalog','action_label'=>'Request Catalog','description'=>'Limited request-only document catalog.']);
        $db->query('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$roleId,$permissionId]);
        $category = $catalog->save('categories', ['name'=>'Picker fixture '.bin2hex(random_bytes(3)), 'folder_name'=>'picker-fixture']);
        $id = $db->insert('softcopy_documents', ['document_number'=>'PICK-'.bin2hex(random_bytes(4)), 'title'=>'Requestable but private', 'category_id'=>$category['id'], 'created_by'=>$adminId,'creation_source'=>'direct','creation_reason'=>'Isolated fixture']);
        $ctx->identify((int)$staff['id']);
        $reader = new Read_service($ctx);
        $documents = new Document_service($ctx);
        catalogCheck($reader->listing('softcopy',[])['total'] === 0, 'Staff still cannot browse unassigned documents');
        $options = $reader->lookups(['kind'=>'softcopy','request_type'=>'access'])['options'];
        catalogCheck(count($options) === 1 && (int)$options[0]['id'] === $id, 'access request can select a private document from the limited catalog');
        catalogCheck(array_keys($options[0]) === ['id','label'], 'catalog exposes only internal selection ID and human-readable label');
        catalogDenied(fn() => $reader->detail('softcopy',$id),404);
        catalogCheck(!$documents->canRead('softcopy',$id), 'request discovery does not grant content access');
        catalogCheck($reader->lookups(['kind'=>'softcopy'])['options'] === [], 'ordinary dropdowns stay restricted');
        catalogDenied(fn() => $reader->lookups(['kind'=>'softcopy','request_type'=>'transfer']),422);
        catalogDenied(fn() => $reader->lookups(['kind'=>'hardcopy','request_type'=>'assignment']),422);
        $requests = new Request_service($ctx);
        $request = $requests->save(['type'=>'assignment','softcopy_id'=>$id,'payload'=>['user_id'=>$staff['id'],'reason'=>'Need this for assigned duties']]);
        catalogCheck(!$documents->canRead('softcopy',$id), 'saving an assignment request grants nothing');
        $draft = $db->row('requests',(int)$request['id']);
        $requests->submit(['id'=>$draft['id'],'version'=>$draft['version']]);
        catalogCheck(!$documents->canRead('softcopy',$id), 'pending approval grants nothing');
        $ctx->identify($adminId);
        $pending = $db->row('requests',(int)$draft['id']);
        $step = $db->one("SELECT id FROM workflow_steps WHERE request_id=? AND status='pending'",[$draft['id']]);
        $requests->decide(['id'=>$pending['id'],'version'=>$pending['version'],'step_id'=>$step['id'],'decision'=>'approve']);
        $ctx->identify((int)$staff['id']);
        catalogCheck($documents->canRead('softcopy',$id), 'only final approval activates the assignment');
        $db->update('softcopy_documents',$id,['status'=>'disposed']);
        catalogCheck($reader->lookups(['kind'=>'softcopy','request_type'=>'access','selected'=>$id])['options'] === [], 'inactive selected IDs cannot bypass catalog restrictions');
        $db->query('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?',[$roleId,$permissionId]);
        $ctx->identify((int)$staff['id']);
        catalogCheck($reader->lookups(['kind'=>'softcopy','request_type'=>'access'])['options'] === [], 'removing catalog permission falls back to assigned/granted visibility');
    });
} finally { $db->rollback(); }
echo "Request catalog and approval isolation checks passed.\n";
