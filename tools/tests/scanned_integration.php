<?php
/** Real MySQL approval integration, using an isolated fixture database with no copied records.
 * php tools/tests/scanned_integration.php [--keep] (keep fixtures for browser checks).
 */
if (PHP_SAPI !== 'cli') exit(1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__, 2);
$sourceName = getenv('DB_DATABASE') ?: 'pk_dts';
$source = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', $sourceName);
function cleanup_fixture(mysqli $source, string $name, string $root): void {
    if (!preg_match('/^scanned_[a-f0-9]{10}_test$/D', $name)) throw new RuntimeException('Invalid fixture database.');
    $exists = $source->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$name'")->fetch_row();
    if (!$exists) return;
    $hasFiles = $source->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='files'")->fetch_row();
    if ($hasFiles) foreach ($source->query("SELECT storage_name FROM `$name`.files WHERE domain='scanned'") as $row) {
        if (!preg_match('/^[a-f0-9]{64}$/D', $row['storage_name'])) continue;
        $path = $root . '/storage/documents/' . $row['storage_name'];
        if (is_file($path)) unlink($path);
    }
    $source->query('DROP DATABASE `' . $name . '`');
}
if (in_array('--cleanup', $argv, TRUE)) {
    $marker = $root . '/storage/scanned-test-database.txt';
    if (!is_file($marker)) exit(0);
    cleanup_fixture($source, trim(file_get_contents($marker)), $root); unlink($marker); exit(0);
}
$name = 'scanned_' . bin2hex(random_bytes(5)) . '_test';
$source->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$keepFixture = FALSE;
register_shutdown_function(static function () use ($source, $name, $root, &$keepFixture) {
    if (!$keepFixture) cleanup_fixture($source, $name, $root);
});
$fixture = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', $name);
$fixture->query('SET FOREIGN_KEY_CHECKS=0');
foreach ($source->query('SHOW FULL TABLES WHERE Table_type="BASE TABLE"') as $table) {
    $table = array_values($table)[0];
    if (in_array($table, ['scanned_documents', 'scanned_categories', 'scanned_category_allocations'], TRUE)) continue;
    $ddl = $source->query('SHOW CREATE TABLE `' . $table . '`')->fetch_row()[1];
    $fixture->query($ddl);
}
$fixture->query('SET FOREIGN_KEY_CHECKS=1');
$fixture->query("INSERT INTO roles(id,name) VALUES(1,'Administrator'),(2,'Document Control Officer'),(3,'Uploader'),(4,'Outsider')");
$password = $fixture->real_escape_string(password_hash('Scanned-Test-2026!', PASSWORD_DEFAULT));
foreach ([1 => 'admin', 2 => 'dco', 3 => 'uploader', 4 => 'outsider'] as $id => $login)
    $fixture->query("INSERT INTO users(id,username,first_name,last_name,role_id,password_hash,require_password_change) VALUES($id,'$login','$login','Fixture',$id,'$password',0)");
foreach (['view','add','edit','submit','cancel'] as $action)
    $fixture->query("INSERT INTO permissions(name,module_key,module_label,action_key,action_label) VALUES('Requests $action','requests','Requests','$action','$action')");
foreach (['view','request'] as $action)
    $fixture->query("INSERT INTO permissions(name,module_key,module_label,action_key,action_label) VALUES('Softcopy $action','softcopy','Softcopy','$action','$action')");
$fixture->query("INSERT INTO role_permissions(role_id,permission_id) SELECT 3,id FROM permissions");
function migrate(mysqli $db, string $sql): void {
    $db->multi_query($sql); do { if ($result = $db->store_result()) $result->free(); } while ($db->more_results() && $db->next_result());
}
$migration = file_get_contents($root . '/database/migrations/20261117_scanned_documents.sql');
migrate($fixture, $migration); migrate($fixture, $migration);
$allocationMigration = file_get_contents($root . '/database/migrations/20261117_scanned_category_allocations.sql');
migrate($fixture, $allocationMigration); migrate($fixture, $allocationMigration);
$descriptionMigration = file_get_contents($root . '/database/migrations/20261117_scanned_document_descriptions.sql');
migrate($fixture, $descriptionMigration); migrate($fixture, $descriptionMigration);
$fixture->query("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 3,id FROM permissions WHERE module_key='scanned_documents'");
putenv('PK_TEST_DB=1'); putenv('DB_DATABASE=' . $name);
define('PK_ROOT', $root); define('APPPATH', $root . '/application/');
require APPPATH . 'bootstrap.php';
define('BASEPATH', $root . '/vendor/codeigniter/framework/system/'); define('ENVIRONMENT', 'testing'); define('FCPATH', $root . '/public/');
require APPPATH . 'config/constants.php'; require BASEPATH . 'core/Common.php'; require BASEPATH . 'core/Model.php'; require BASEPATH . 'database/DB.php';
class FixtureLoader {
    public function model($name) { global $ci; if (!isset($ci->$name)) { require_once APPPATH . 'models/' . $name . '.php'; $ci->$name = new $name(); } }
}
$ci = new stdClass(); $ci->load = new FixtureLoader();
function &get_instance() { global $ci; return $ci; }
require APPPATH . 'config/database.php';
$ci->db = DB($db['default'], TRUE); $ci->load->model('Identity_model');
function site_url($uri = '') { return 'http://127.0.0.1:8098/' . $uri; }
require APPPATH . 'services/request/request_service.php'; require APPPATH . 'services/documents/scanned_document_service.php';
$service = new Request_service(); $scanned = new Scanned_document_service();
$uploader = $ci->Identity_model->active_user(3); $dco = $ci->Identity_model->active_user(2); $outsider = $ci->Identity_model->active_user(4);
$count = 0;
function verify($ok, $message) { global $count; if (!$ok) throw new RuntimeException($message); $count++; echo "PASS $message\n"; }
function denied($fn, $message) { try { $fn(); } catch (DomainException $e) { verify(TRUE, $message); return; } verify(FALSE, $message); }
function proposal($type, $extra = []) { return $extra + ['type' => $type, 'subject' => 'Fixture approval test', 'title' => 'Fixture title']; }
function approve($id) {
    global $service, $dco, $outsider, $fixture;
    $service->submit('scanned', $id, $GLOBALS['uploader']);
    $step = $fixture->query("SELECT s.assignment,r.type FROM workflow_steps s JOIN requests r ON r.id=s.request_id WHERE r.id=$id AND s.status='active'")->fetch_assoc();
    $assignment = json_decode($step['assignment'], TRUE);
    verify($assignment['type'] === 'role' && (int) $assignment['value'] === (int) $dco['role_id'], $step['type'] . ' routes to DCO by default');
    denied(fn() => $service->decide('scanned', $id, $outsider, 'approved', 'Not assigned'), $step['type'] . ' refuses an unassigned approver');
    $service->decide('scanned', $id, $dco, 'approved', '');
}
$paths = [];
try {
    verify($fixture->query("SELECT COUNT(*) FROM workflows WHERE request_type LIKE 'scanned_%'")->fetch_row()[0] == 4, 'migration reruns without duplicate workflows');
    foreach (Scanned_policy::TYPES as $type) {
        $workflow = $ci->Request_model->active_workflow($type);
        $graph = json_decode($workflow['graph'] ?? '{}', TRUE);
        $steps = $graph['steps'] ?? [];
        $last = $steps ? $steps[count($steps) - 1]['approver'] : [];
        verify(($last['type'] ?? '') === 'role' && (int) ($last['value'] ?? 0) === (int) $dco['role_id'], $type . ' has a published DCO default');
    }
    $categoryRequest = $service->save('scanned', proposal('scanned_category_create'), $uploader);
    verify($fixture->query('SELECT COUNT(*) FROM scanned_categories')->fetch_row()[0] == 0, 'pending root category cannot be used');
    $service->submit('scanned', $categoryRequest, $uploader);
    denied(fn() => $service->decide('scanned', $categoryRequest, $outsider, 'approved', 'Forged'), 'unassigned user cannot approve');
    $service->decide('scanned', $categoryRequest, $dco, 'approved', '');
    verify(TRUE, 'DCO approves without remarks');
    $category = (int) $fixture->query('SELECT id FROM scanned_categories')->fetch_row()[0];
    $childRequest = $service->save('scanned', proposal('scanned_category_create', ['title' => 'Nested', 'parent_id' => $category]), $uploader);
    approve($childRequest);
    verify(strpos($scanned->categories()[1]['path'], '/') !== FALSE, 'nested approved folder has full path');
    denied(fn() => $service->save('scanned', proposal('scanned_create', ['scanned_category_id' => 99999]), $uploader), 'unapproved category refused');
    denied(fn() => $service->save('scanned', proposal('scanned_create', ['scanned_category_id' => $category, 'retention_start_date' => '2026-01-01', 'retention_end_date' => '2026-12-31']), $uploader), 'new document requires an upload');
    $storage = bin2hex(random_bytes(32)); $path = $root . '/storage/documents/' . $storage;
    file_put_contents($path, "Scanned fixture\n"); $paths[] = $path;
    $ci->db->insert('files', ['original_name' => 'fixture.txt', 'storage_name' => $storage, 'size' => filesize($path), 'mime_type' => 'text/plain',
        'extension' => 'txt', 'fingerprint' => hash_file('sha256', $path), 'domain' => 'scanned', 'purpose' => 'creation', 'uploaded_by' => 3, 'status' => 'pending']);
    $file = (int) $ci->db->insert_id();
    $p = proposal('scanned_create', ['description'=>'Archive scan for audit', 'scanned_category_id' => $category, 'retention_start_date' => '2026-01-01', 'retention_end_date' => '2026-11-17', 'scan_due_date' => '2026-11-17']);
    $ci->db->insert('requests', ['reference' => 'SCANNED-FIXTURE', 'type' => 'scanned_create', 'requested_by' => 3, 'status' => 'draft', 'payload' => json_encode(['scanned_file_ids' => [$file]])]);
    $id = (int) $ci->db->insert_id(); $p['id'] = $id;
    $service->save('scanned', $p, $uploader);
    verify($fixture->query('SELECT COUNT(*) FROM scanned_documents')->fetch_row()[0] == 0, 'pending upload does not register a document');
    verify($fixture->query("SELECT status FROM files WHERE id=$file")->fetch_row()[0] === 'pending', 'uploaded files stay pending before DCO approval');
    approve($id);
    $document = $fixture->query('SELECT * FROM scanned_documents')->fetch_assoc(); $documentId = (int) $document['id'];
    verify($document['title'] === 'Fixture title' && (int) $document['approved_by'] === 2, 'approved upload registers document and DCO');
    verify($document['description'] === 'Archive scan for audit', 'description saved on approved upload');
    $update = proposal('scanned_update', ['description'=>'Updated document contents', 'scanned_document_id' => $documentId, 'document_version' => 1, 'title' => 'Updated title', 'scanned_category_id' => $category, 'retention_start_date' => '2026-01-01', 'retention_end_date' => '2026-11-17', 'scan_due_date' => '2026-11-17']);
    $updateId = $service->save('scanned', $update, $uploader);
    verify($fixture->query('SELECT title FROM scanned_documents')->fetch_row()[0] === 'Fixture title', 'pending update leaves original untouched');
    verify($fixture->query('SELECT description FROM scanned_documents')->fetch_row()[0] === 'Archive scan for audit', 'pending description change leaves approved record untouched');
    approve($updateId);
    verify($fixture->query('SELECT description FROM scanned_documents')->fetch_row()[0] === 'Updated document contents', 'description changes after DCO approval');
    verify($fixture->query('SELECT title FROM scanned_documents')->fetch_row()[0] === 'Updated title', 'metadata update applied after DCO approval');
    verify($fixture->query("SELECT status FROM files WHERE id=$file")->fetch_row()[0] === 'approved', 'metadata update keeps approved attachments');
    denied(fn() => $service->save('scanned', $update, $uploader), 'stale document version refused');
    denied(fn() => $service->save('scanned', array_replace($update, ['document_version' => 2]), $outsider), 'outside scope update refused');
    $legacyUpdate = array_replace($update, ['document_version'=>2]);
    unset($legacyUpdate['description']);
    $legacyId = $service->save('scanned', $legacyUpdate, $uploader);
    approve($legacyId);
    verify($fixture->query('SELECT description FROM scanned_documents')->fetch_row()[0] === 'Updated document contents', 'older update form preserves description when omitted');
    $clearId = $service->save('scanned', array_replace($update, ['document_version'=>3, 'description'=>'']), $uploader);
    approve($clearId);
    verify($fixture->query('SELECT description FROM scanned_documents')->fetch_row()[0] === '', 'description can be explicitly cleared');
    $before = $scanned->alerts($uploader, new DateTimeImmutable('2026-11-17 15:59:59', new DateTimeZone('Asia/Manila')));
    $at = $scanned->alerts($uploader, new DateTimeImmutable('2026-11-17 16:00:00', new DateTimeZone('Asia/Manila')));
    verify(count($before) === 0 && count($at) === 1, 'one backup reminder starts at 4 PM Manila without per-document due dates');
    verify(strpos($at[0]['label'], 'backup') !== FALSE, 'daily alarm is a backup upload reminder');
    $expired = $scanned->alerts($uploader, new DateTimeImmutable('2026-11-18 00:00:00', new DateTimeZone('Asia/Manila')));
    verify(count($expired) === 1 && str_starts_with($expired[0]['key'], 'scanned-expired:'), 'expiration popup starts after full end date');
    $delete = $service->save('scanned', proposal('scanned_delete', ['scanned_document_id' => $documentId, 'document_version' => 4]), $uploader);
    verify($fixture->query('SELECT status FROM scanned_documents')->fetch_row()[0] === 'active', 'pending deletion leaves document active');
    approve($delete);
    verify($fixture->query('SELECT status FROM scanned_documents')->fetch_row()[0] === 'deleted', 'DCO deletion applied');
    verify($fixture->query("SELECT status FROM files WHERE id=$file")->fetch_row()[0] === 'deleted', 'deleted files no longer approved');
    verify(count($scanned->alerts($uploader, new DateTimeImmutable('2026-11-18 16:00:00', new DateTimeZone('Asia/Manila')))) === 1, 'deleted document excluded from reminders');
    require_once APPPATH . 'services/places/scanned_category_service.php';
    $folders = new Scanned_category_service();
    $rootFolder = $folders->save(['name'=>'Allocated Root', 'save_allocations'=>'1', 'user_ids'=>[3]], $dco);
    $nestedFolder = $folders->save(['name'=>'Nested Folder', 'parent_id'=>$rootFolder], $dco);
    $privateFolder = $folders->save(['name'=>'Private Child', 'parent_id'=>$rootFolder, 'save_allocations'=>'1', 'user_ids'=>[4]], $dco);
    verify(in_array($nestedFolder, $folders->usable_ids($uploader), TRUE), 'Master Data allocation covers nested folders');
    verify(!in_array($rootFolder, $folders->usable_ids($outsider), TRUE) && in_array($privateFolder, $folders->usable_ids($outsider), TRUE), 'nested allocation can be assigned to another user');
    verify(!in_array($privateFolder, $folders->usable_ids($uploader), TRUE), 'child allocation overrides inherited allocation');
    verify(!in_array($privateFolder, array_map('intval', array_column($scanned->categories($uploader), 'id')), TRUE), 'upload selector excludes another user folder');
    denied(fn() => $service->save('scanned', proposal('scanned_category_create', ['title'=>'Forged Child', 'parent_id'=>$privateFolder]), $uploader), 'forged category parent outside allocation refused');
    denied(fn() => $service->save('scanned', proposal('scanned_create', ['scanned_category_id'=>$privateFolder, 'retention_start_date'=>'2026-01-01', 'retention_end_date'=>'2026-12-31']), $uploader), 'forged upload category outside allocation refused');
    $pendingFolder = $service->save('scanned', proposal('scanned_category_create', ['title'=>'Pending Allocated Child', 'parent_id'=>$rootFolder]), $uploader);
    $service->submit('scanned', $pendingFolder, $uploader);
    $folders->save(['id'=>$rootFolder, 'version'=>1, 'name'=>'Allocated Root', 'save_allocations'=>'1', 'user_ids'=>[4]], $dco);
    denied(fn() => $service->decide('scanned', $pendingFolder, $dco, 'approved', 'Allocation changed'), 'final approval rechecks revoked parent allocation');
    $folders->save(['id'=>$rootFolder, 'version'=>2, 'name'=>'Allocated Root', 'save_allocations'=>'1', 'user_ids'=>[3]], $dco);
    denied(fn() => $folders->save(['name'=>'Unauthorized'], $uploader), 'request permission does not allow direct folder creation');
    denied(fn() => $folders->save(['id'=>$rootFolder, 'version'=>1, 'name'=>'Allocated Root', 'user_ids'=>[4]], $uploader), 'allocation permission is enforced in service');
    denied(fn() => $folders->save(['id'=>$rootFolder, 'version'=>3, 'name'=>'Allocated Root', 'parent_id'=>$nestedFolder], $dco), 'folder cannot move beneath its descendant');
    denied(fn() => $folders->save(['name'=>'Inactive target', 'user_ids'=>[999999]], $dco), 'unknown user allocation rolls back');
    $folders->save(['id'=>$rootFolder, 'version'=>3, 'name'=>'Renamed Root'], $dco);
    denied(fn() => $folders->save(['id'=>$rootFolder, 'version'=>1, 'name'=>'Stale Root'], $dco), 'stale folder edits are refused');
    verify(in_array($nestedFolder, $folders->usable_ids($uploader), TRUE), 'editing folder preserves allocations when allocation fields omitted');
    $folders->save(['id'=>$rootFolder, 'version'=>4, 'name'=>'Renamed Root', 'save_allocations'=>'1'], $dco);
    verify(in_array($rootFolder, $folders->usable_ids($outsider), TRUE), 'clearing root allocation makes it shared');
    verify($fixture->query("SELECT request_id FROM scanned_categories WHERE id=$rootFolder")->fetch_row()[0] === NULL, 'direct DCO folder creation requires no request');
    $fixture->query("INSERT INTO roles(id,name) VALUES(5,'Folder Creator'),(6,'Folder Allocator')");
    $fixture->query("INSERT INTO users(id,username,first_name,last_name,role_id,password_hash,require_password_change) VALUES(5,'creator','Creator','Fixture',5,'$password',0),(6,'allocator','Allocator','Fixture',6,'$password',0)");
    $fixture->query("INSERT INTO role_permissions(role_id,permission_id) SELECT 5,id FROM permissions WHERE module_key='scanned_categories' AND action_key IN ('view','add','edit')");
    $fixture->query("INSERT INTO role_permissions(role_id,permission_id) SELECT 6,id FROM permissions WHERE module_key='scanned_categories' AND action_key IN ('view','allocate')");
    $creator = $ci->Identity_model->active_user(5); $allocator = $ci->Identity_model->active_user(6);
    $creatorFolder = $folders->save(['name'=>'Creator Folder'], $creator);
    denied(fn() => $folders->save(['id'=>$creatorFolder, 'version'=>1, 'name'=>'Creator Folder', 'user_ids'=>[3]], $creator), 'create and edit grants do not include allocation');
    $folders->save(['id'=>$creatorFolder, 'version'=>1, 'name'=>'Creator Folder', 'user_ids'=>[3]], $allocator);
    verify(in_array($creatorFolder, $folders->usable_ids($uploader), TRUE), 'allocate-only account can allocate an existing folder');
    denied(fn() => $folders->save(['id'=>$creatorFolder, 'version'=>2, 'name'=>'Renamed without permission', 'user_ids'=>[3]], $allocator), 'allocate-only account cannot rename folders');
    echo "$count integration checks passed\n";
    if (in_array('--keep', $argv, TRUE)) {
        $keepFixture = TRUE;
        file_put_contents($root . '/storage/scanned-test-database.txt', $name); echo "Fixture database: $name\n"; exit(0);
    }
} finally {
    if (!in_array('--keep', $argv, TRUE)) {
        foreach ($paths as $path) if (is_file($path)) unlink($path);
    }
}
