<?php
/**
 * Source-of-truth lock derived ONLY from the user-supplied 2026-10-09
 * phpMyAdmin dump. Never copies exported users, password hashes, or sessions.
 */
$root = dirname(__DIR__);
$schema = file_get_contents($root.'/database/pk_dts.sql');
$contract = json_decode(file_get_contents($root.'/database/source_schema_contract.json'), true);
$errors = [];
if (!is_array($contract) || !isset($contract['tables'])) {
    fwrite(STDERR, "Invalid source schema contract.\n");
    exit(1);
}
$found = [];
preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\)\s*(?:ENGINE=|;)/s', $schema, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    $columns = [];
    preg_match_all('/^\s*`([^`]+)` (.+?)(?:,)?$/m', $match[2], $defs, PREG_SET_ORDER);
    foreach ($defs as $def) {
        $columns[$def[1]] = rtrim($def[2], ',');
    }
    $found[$match[1]] = $columns;
}
foreach ($contract['tables'] as $table) {
    $name = $table['name'];
    if (!array_key_exists($name, $found)) {
        $errors[] = "Missing source table $name";
        continue;
    }
    foreach ($table['columns'] as $column => $definition) {
        if (!array_key_exists($column, $found[$name])) $errors[] = "Missing column $name.$column";
        elseif ($found[$name][$column] !== $definition) $errors[] = "Unexpected column type for $name.$column";
    }
    if (count($found[$name]) !== count($table['columns'])) $errors[] = "Unexpected columns in $name";
}
if (count($found) !== count($contract['tables'])) $errors[] = 'Source table count changed';
// Preserve all source primary keys, UNIQUE constraints, and regular indexes.
foreach ($contract['indexStatements'] ?? [] as $statement) {
    $exact = 'ALTER TABLE `'.$statement['table']."`\n  ".$statement['body'].';';
    if (strpos($schema, $exact) === false) {
        $errors[] = 'Missing source index declaration for '.$statement['table'];
    }
}
foreach ($contract['foreignKeys'] as $foreignKey) {
    $constraint = 'ADD CONSTRAINT `'.$foreignKey['name'].'` FOREIGN KEY (`'.
        $foreignKey['column'].'`) REFERENCES `'.$foreignKey['references'].'` (`'.
        $foreignKey['reference_column'].'`)';
    if (strpos($schema, $constraint) === false) $errors[] = "Missing FK ".$foreignKey['name'];
}
foreach (['database/pk_dts.sql','database/seed.sql','database/seed_workflows.sql'] as $name) {
    $source = file_get_contents($root.'/'.$name);
    if (preg_match('/INSERT\s+INTO\s+`(?:users|login_attempts)`/i', $source)) {
        $errors[] = "Forbidden account or authentication record seed: $name";
    }
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors)."\n");
    exit(1);
}
echo 'Source schema contract passed: '.count($found).' tables, '.
    count($contract['foreignKeys']).' foreign keys, '.
    count($contract['indexStatements'])." index declarations, unchanged column definitions.\n";
