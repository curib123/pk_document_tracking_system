<?php
/**
 * Regression tests for final-dash numeric document numbering and folder-only guards.
 * Run: php tools/tests/softcopy_document_number_generator.php
 */
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/services/softcopy/document_number_generator.php';

class FakeNumberResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function row_array() { return $this->rows[0] ?? NULL; }
    public function result_array() { return $this->rows; }
}

class FakeNumberDb
{
    public $categories = [];
    public $documents = [];

    public function query($sql, $params)
    {
        if (strpos($sql, 'FROM categories') !== FALSE) {
            $category = $this->categories[(int) $params[0]] ?? NULL;
            return new FakeNumberResult($category && $category['active'] ? [$category] : []);
        }
        if (strpos($sql, 'FROM softcopy_documents') !== FALSE) {
            $categoryId = (int) $params[0];
            $records = array_values(array_filter($this->documents, static function ($row) use ($categoryId) {
                return $row['category_id'] === $categoryId;
            }));
            return new FakeNumberResult(array_map(static function ($row) {
                return ['document_number' => $row['document_number']];
            }, $records));
        }
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
}

function checkNumber($expected, $actual, $label)
{
    if ($actual !== $expected)
        throw new RuntimeException($label . ' expected ' . $expected . ', received ' . $actual);
}
function checkNumberRejected(callable $callback, $label)
{
    try { $callback(); }
    catch (DomainException $e) { return; }
    throw new RuntimeException('Expected a validation error: ' . $label);
}

$db = new FakeNumberDb();
$db->categories = [
    1 => ['id' => 1, 'active' => 1, 'category_type' => 'pattern', 'code_pattern' => 'OPS-2026-001'],
    2 => ['id' => 2, 'active' => 1, 'category_type' => 'folder', 'code_pattern' => ''],
    3 => ['id' => 3, 'active' => 1, 'category_type' => 'pattern', 'code_pattern' => 'PPM-009']
];
$db->documents = [
    ['category_id' => 1, 'document_number' => 'OPS-2026-001'],
    ['category_id' => 1, 'document_number' => 'OPS-2026-002'],
    ['category_id' => 1, 'document_number' => 'OPS-2025-999'],
    ['category_id' => 3, 'document_number' => 'PPM-010']
];

checkNumber('OPS-2026-003', Document_number_generator::preview($db, 1),
    'Increment only suffix after final dash, not embedded year');
checkNumber('OPS-2026-003', Document_number_generator::next($db, 1),
    'Direct document generator matches preview');
checkNumber('PPM-011', Document_number_generator::preview($db, 3),
    'Independent numbered category increments');
checkNumberRejected(static function () use ($db) {
    Document_number_generator::preview($db, 2);
}, 'Parent folder may not generate a document number');

echo "Softcopy document code pattern tests passed.\n";
