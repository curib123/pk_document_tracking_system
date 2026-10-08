<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\{Context, Database, Problem};

if (getenv('PK_TEST_DB') !== '1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    throw new RuntimeException('Use an isolated *_test database.');
}

$db = Database::connect();
$ctx = new Context($db);
$checks = 0;
function discoveryCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($name);
    ++$checks;
    echo "PASS $name\n";
}
function discoveryDenied(callable $action, string $name): void
{
    try { $action(); } catch (Problem $error) {
        discoveryCheck(in_array($error->status, [403, 404], true), $name);
        return;
    }
    throw new RuntimeException('Expected denial: ' . $name);
}

$db->begin();
try {
    $db->transaction(function () use ($db, $ctx): void {
        $admin = (int) $db->one("SELECT id FROM users WHERE username='admin'")['id'];
        $staffRole = (int) $db->one("SELECT id FROM roles WHERE name='Staff'")['id'];
        $ctx->identify($admin);
        $catalog = new Catalog_service($ctx);
        $staff = $catalog->save('users', [
            'username' => 'picker_' . bin2hex(random_bytes(4)),
            'first_name' => 'Picker', 'last_name' => 'Staff',
            'position_title' => 'Clerk', 'role_id' => $staffRole,
        ]);
        $category = $catalog->save('categories', [
            'name' => 'Picker test', 'folder_name' => 'picker-' . bin2hex(random_bytes(4)),
        ]);
        $document = $db->insert('softcopy_documents', [
            'document_number' => 'PICK-' . bin2hex(random_bytes(5)),
            'title' => 'Requestable private document', 'category_id' => $category['id'],
            'created_by' => $admin, 'creation_source' => 'direct', 'creation_reason' => 'Fixture',
        ]);
        $ctx->identify((int) $staff['id']);
        $reader = new Read_service($ctx);
        discoveryCheck($reader->lookups(['kind' => 'softcopy', 'selected' => $document])['options'] === [], 'ordinary lookup still hides private records');
        $options = $reader->lookups([
            'kind' => 'softcopy', 'purpose' => 'request', 'request_type' => 'access',
            'selected' => $document, 'q' => 'Requestable private',
        ])['options'];
        discoveryCheck(count($options) === 1 && (int) $options[0]['id'] === $document, 'access request picker can discover eligible ungranted document');
        $keys = array_keys($options[0]); sort($keys);
        discoveryCheck($keys === ['id', 'label'], 'request catalog returns only ID and display label');
        $options = $reader->lookups([
            'kind' => 'softcopy', 'purpose' => 'request', 'request_type' => 'assignment', 'selected' => $document,
        ])['options'];
        discoveryCheck(in_array($document, array_map('intval', array_column($options, 'id')), true), 'assignment request picker can discover eligible document');
        discoveryDenied(fn() => $reader->detail('softcopy', $document), 'discovery does not authorize document detail');
        discoveryCheck(!(new Document_service($ctx))->canRead('softcopy', $document), 'discovery never grants content access');
        discoveryCheck($reader->listing('softcopy', [])['total'] === 0, 'ordinary document list remains private');
        $db->update('softcopy_documents', $document, ['status' => 'disposed']);
        discoveryCheck($reader->lookups(['kind' => 'softcopy', 'purpose' => 'request', 'request_type' => 'access', 'selected' => $document])['options'] === [], 'selected ID cannot restore an inactive request candidate');
        discoveryDenied(fn() => $reader->lookups(['kind' => 'hardcopy', 'purpose' => 'request', 'request_type' => 'assignment']), 'assignment discovery rejects unsupported hardcopy domain');
    });
} finally {
    $db->rollback();
}
echo "$checks request discovery assertions passed.\n";
