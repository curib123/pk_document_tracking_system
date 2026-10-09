<?php
/**
 * Read-only CLI verification of an existing DTS database's schema.
 * It never copies, alters or exports any records.
 *
 * Env: PK_LEGACY_HOST, PK_LEGACY_PORT, PK_LEGACY_DB,
 *      PK_LEGACY_USER, PK_LEGACY_PASSWORD.
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");

$host = getenv('PK_LEGACY_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PK_LEGACY_PORT') ?: 3306);
$name = (string) (getenv('PK_LEGACY_DB') ?: '');
$user = (string) (getenv('PK_LEGACY_USER') ?: '');
$pass = (string) (getenv('PK_LEGACY_PASSWORD') ?: '');
if ($name === '' || $user === '') {
    fwrite(STDERR, "Provide PK_LEGACY_DB and PK_LEGACY_USER via environment variables.\n");
    exit(2);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
    $stmt = $db->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $found = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $found[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }
    $expected = [
        'users' => ['user_id', 'firstname', 'lastname', 'username', 'password', 'role_id'],
        'roles' => ['role_id', 'role_name'],
        'permissions' => ['permission_id', 'module_key', 'action_key'],
        'role_permissions' => ['role_permission_id', 'role_id', 'permission_id'],
        'areas' => ['area_id', 'area_name'],
        'specifics' => ['specific_id', 'specific_name', 'area_id'],
        'locations' => ['location_id', 'location_name'],
        'asset_numbers' => ['asset_id', 'asset_number'],
        'sequences' => ['sequence_id', 'sequence_code'],
        'documents' => ['document_id', 'document_title', 'document_type', 'status'],
        'workflow_definitions' => [],
        'workflow_versions' => [],
    ];
    $issues = 0;
    echo "Read-only legacy database schema compatibility check\n";
    foreach ($expected as $table => $columns) {
        if (!isset($found[$table])) {
            echo "[MISSING] $table\n";
            $issues++;
            continue;
        }
        $missing = array_values(array_diff($columns, $found[$table]));
        if ($missing) {
            echo "[DIFFERENT] $table: missing " . implode(', ', $missing) . "\n";
            $issues++;
        } else {
            echo "[FOUND] $table\n";
        }
    }
    echo "Found " . count($found) . " tables; " . $issues . " baseline mismatches.\n";
    echo "No data was copied or modified. This is NOT migration approval.\n";
    $db->close();
    exit($issues ? 1 : 0);
} catch (Throwable $error) {
    // Do not log or print credentials or database contents.
    fwrite(STDERR, "Cannot read legacy schema. Check connection and read-only privileges.\n");
    exit(2);
}
