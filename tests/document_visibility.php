<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\{Context, Database, Problem};

if (getenv('PK_TEST_DB') !== '1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    fwrite(STDERR, "Use a dedicated *_test database with PK_TEST_DB=1.\n");
    exit(2);
}

$db = Database::connect();
$ctx = new Context($db);
$checks = 0;
function visibilityCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($name);
    ++$checks;
    echo "PASS $name\n";
}
function visibilityDenied(callable $action, int $status, string $name): void
{
    try { $action(); } catch (Problem $error) {
        visibilityCheck($error->status === $status, $name);
        return;
    }
    throw new RuntimeException('Expected denial: ' . $name);
}
function documentIds(array $rows): array
{
    $ids = array_map('intval', array_column($rows, 'id'));
    sort($ids);
    return $ids;
}

$db->begin();
$path = null;
try {
    $db->transaction(function () use ($db, $ctx, &$path): void {
        $adminId = (int) $db->one("SELECT id FROM users WHERE username='admin'")['id'];
        $staffRole = (int) $db->one("SELECT id FROM roles WHERE name='Staff'")['id'];
        $ctx->identify($adminId);
        $catalog = new Catalog_service($ctx);
        $staff = $catalog->save('users', [
            'username' => 'visibility_' . bin2hex(random_bytes(4)),
            'first_name' => 'Visibility', 'last_name' => 'Staff',
            'position_title' => 'Clerk', 'role_id' => $staffRole,
        ]);
        $staffId = (int) $staff['id'];
        $other = $catalog->save('users', [
            'username' => 'other_' . bin2hex(random_bytes(4)),
            'first_name' => 'Other', 'last_name' => 'Staff',
            'position_title' => 'Clerk', 'role_id' => $staffRole,
        ]);
        $otherId = (int) $other['id'];
        $category = $catalog->save('categories', ['name' => 'Visibility fixtures', 'folder_name' => 'visibility-fixtures']);
        $makeSoftcopy = static function (string $title, int $creator) use ($db, $category): int {
            return $db->insert('softcopy_documents', [
                'document_number' => 'VIS-' . bin2hex(random_bytes(5)),
                'title' => $title, 'category_id' => $category['id'],
                'created_by' => $creator, 'creation_source' => 'direct',
                'creation_reason' => 'Isolated visibility fixture',
            ]);
        };
        $assignedId = $makeSoftcopy('Assigned document', $adminId);
        $grantedId = $makeSoftcopy('Granted document', $adminId);
        $hiddenId = $makeSoftcopy('Private document', $adminId);
        $createdId = $makeSoftcopy('Created but not assigned', $staffId);
        $ctx->identify($staffId);
        $reader = new Read_service($ctx);
        $documents = new Document_service($ctx);
        $files = new File_service($ctx);

        // Red regression: module View alone used to enumerate all documents.
        visibilityCheck($reader->listing('softcopy', [])['total'] === 0, 'Staff cannot enumerate documents without an assignment or grant');
        visibilityCheck(!$documents->canRead('softcopy', $createdId), 'creating a document alone does not grant Staff access');
        visibilityDenied(fn() => $reader->detail('softcopy', $hiddenId), 404, 'guessed document detail ID is unavailable');
        visibilityCheck($reader->lookups(['kind' => 'softcopy', 'selected' => $hiddenId])['options'] === [], 'selected lookup ID cannot bypass visibility');
        visibilityCheck($reader->listing('softcopy', ['q' => 'Private document'])['total'] === 0, 'search totals do not reveal hidden documents');
        visibilityCheck(($reader->dashboard()['softcopy'] ?? []) === [], 'dashboard counts exclude hidden documents');

        $assignmentId = $db->insert('assignments', ['softcopy_id' => $assignedId, 'user_id' => $staffId, 'assigned_by' => $adminId]);
        $db->insert('assignments', ['softcopy_id' => $hiddenId, 'user_id' => $otherId, 'assigned_by' => $adminId]);
        $grantId = $db->insert('access_grants', [
            'request_id' => null, 'domain' => 'softcopy', 'document_id' => $grantedId,
            'user_id' => $staffId, 'granted_by' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'reason' => '',
        ]);
        $expected = [$assignedId, $grantedId]; sort($expected);
        visibilityCheck(documentIds($reader->listing('softcopy', [])['rows']) === $expected, 'Staff sees the union of own assignments and live grants only');
        visibilityCheck(documentIds($reader->lookups(['kind' => 'softcopy'])['options']) === $expected, 'document lookups use the same permission scope');
        visibilityCheck((int) $reader->detail('softcopy', $assignedId)['row']['id'] === $assignedId, 'assigned document detail is accessible');
        visibilityCheck($documents->canRead('softcopy', $grantedId), 'live granted document content is accessible');
        visibilityCheck(!$documents->canRead('softcopy', $hiddenId), 'another users assignment does not authorize this user');

        $duplicateGrant = $db->insert('access_grants', [
            'request_id' => null, 'domain' => 'softcopy', 'document_id' => $grantedId,
            'user_id' => $staffId, 'granted_by' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', time() + 7200), 'reason' => '',
        ]);
        $page = $reader->listing('softcopy', ['start' => 0, 'length' => 1, 'draw' => 4]);
        visibilityCheck($page['recordsTotal'] === 2 && count($page['rows']) === 1, 'overlapping grants do not duplicate rows or inflate pagination');
        $db->update('access_grants', $duplicateGrant, ['status' => 'returned']);

        $directory = PK_ROOT . '/storage/files';
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        $storageName = bin2hex(random_bytes(24)) . '.txt';
        $path = $directory . '/' . $storageName;
        file_put_contents($path, 'Private document fixture');
        $fileId = $db->insert('files', [
            'original_name' => 'private-fixture.txt', 'storage_name' => $storageName,
            'size' => filesize($path), 'mime_type' => 'text/plain',
            'fingerprint' => hash_file('sha256', $path), 'extension' => 'txt',
            'uploaded_by' => $staffId, 'purpose' => 'attachment',
            'domain' => 'softcopy', 'document_id' => $grantedId, 'status' => 'approved',
        ]);
        visibilityCheck($files->download($fileId)['path'] === $path, 'authorized file download uses the document grant');
        $db->update('access_grants', $grantId, ['expires_at' => date('Y-m-d H:i:s', time() - 60)]);
        visibilityCheck(!$documents->canRead('softcopy', $grantedId), 'grant expiration is enforced immediately without maintenance');
        visibilityDenied(fn() => $reader->detail('softcopy', $grantedId), 404, 'expired grant no longer opens the document detail');
        visibilityDenied(fn() => $files->download($fileId), 403, 'expired grant blocks a saved file download URL');
        $db->update('files', $fileId, ['status' => 'pending']);
        visibilityDenied(fn() => $files->download($fileId), 403, 'uploader ownership does not bypass expired document access');
        visibilityCheck($reader->listing('files', [])['total'] === 0, 'file metadata cannot leak a no-longer-visible linked document');
        $db->update('access_grants', $grantId, ['expires_at' => date('Y-m-d H:i:s', time() + 3600), 'revoked_at' => date('Y-m-d H:i:s')]);
        visibilityCheck(!$documents->canRead('softcopy', $grantedId), 'revoked timestamp wins even with an active status and future expiry');
        $db->update('access_grants', $grantId, ['status' => 'revoked', 'revoked_at' => null]);
        visibilityCheck(!$documents->canRead('softcopy', $grantedId), 'revoked grant status denies access');
        $db->update('assignments', $assignmentId, ['active' => 0]);
        visibilityCheck($reader->listing('softcopy', [])['total'] === 0, 'removing assignment revokes visibility immediately');

        $hardId = $db->insert('hardcopy_documents', [
            'title' => 'Held hardcopy', 'holder_id' => $staffId, 'created_by' => $adminId,
            'creation_source' => 'direct', 'creation_reason' => 'Visibility fixture',
        ]);
        visibilityCheck($documents->canRead('hardcopy', $hardId), 'named hardcopy holder is an assigned user');
        $db->update('hardcopy_documents', $hardId, ['holder_id' => $otherId]);
        visibilityCheck(!$documents->canRead('hardcopy', $hardId), 'previous hardcopy holder loses access');
        $hardGrant = $db->insert('access_grants', [
            'request_id' => null, 'domain' => 'hardcopy', 'document_id' => $hardId,
            'user_id' => $staffId, 'granted_by' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'reason' => '',
        ]);
        visibilityCheck($documents->canRead('hardcopy', $hardId), 'hardcopy can also be shared by an explicit grant');
        $db->update('hardcopy_documents', $hardId, ['status' => 'disposed']);
        visibilityCheck(!$documents->canRead('hardcopy', $hardId), 'disposed documents remain hidden despite a grant');

        $capabilities = [];
        foreach ($db->all('SELECT id,module_key,action_key FROM permissions') as $permission) {
            $capabilities[$permission['module_key'] . '.' . $permission['action_key']] = (int) $permission['id'];
        }
        foreach (['documents.view_assigned', 'documents.view_granted', 'documents.view_all'] as $key) {
            visibilityCheck(isset($capabilities[$key]), 'editable permission exists: ' . $key);
        }
        $roleId = $db->insert('roles', ['name' => 'Visibility reviewer ' . bin2hex(random_bytes(3))]);
        foreach (['softcopy.view', 'hardcopy.view', 'files.view', 'documents.view_granted'] as $key) {
            $db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$roleId, $capabilities[$key]]);
        }
        $db->update('users', $staffId, ['role_id' => $roleId]);
        $db->update('access_grants', $grantId, ['status' => 'access_granted', 'revoked_at' => null]);
        $db->update('assignments', $assignmentId, ['active' => 1]);
        $ctx->identify($staffId);
        visibilityCheck(documentIds($reader->listing('softcopy', [])['rows']) === [$grantedId], 'custom role can enable granted visibility without assigned visibility');
        $db->query('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?', [$roleId, $capabilities['documents.view_granted']]);
        $db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$roleId, $capabilities['documents.view_assigned']]);
        $ctx->identify($staffId);
        visibilityCheck(documentIds($reader->listing('softcopy', [])['rows']) === [$assignedId], 'custom role can enable assigned visibility independently');
        $db->query('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?', [$roleId, $capabilities['documents.view_assigned']]);
        $ctx->identify($staffId);
        visibilityCheck($reader->listing('softcopy', [])['total'] === 0, 'no visibility capability fails closed');
        $db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$roleId, $capabilities['documents.view_all']]);
        $ctx->identify($staffId);
        visibilityCheck($reader->listing('softcopy', [])['total'] === 4, 'view-all is an explicit metadata override for any role');
        visibilityCheck(!$documents->canRead('softcopy', $hiddenId), 'view-all metadata is not unrestricted file-content access');
        $ctx->identify($adminId);
        visibilityCheck($reader->listing('softcopy', [])['total'] === 4, 'existing administrative all-document access is preserved');
        visibilityCheck($documents->canRead('softcopy', $hiddenId), 'administrative content access remains explicit');
    });
} finally {
    $db->rollback();
    if ($path && is_file($path)) unlink($path);
}
echo "$checks document visibility assertions passed; fixture data rolled back.\n";
