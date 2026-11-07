<?php
/**
 * Standalone regression tests for auto-generated Softcopy Series No.
 * Run: php tools/tests/softcopy_series_number_generator.php
 * A database connection and CodeIgniter bootstrap are not required.
 */
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/services/softcopy/document_number_generator.php';

class SeriesFakeResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function row_array() { return $this->rows[0] ?? NULL; }
    public function result_array() { return $this->rows; }
}

class SeriesFakeDb
{
    public $categories = [];
    public $documents = [];

    public function query($sql, $parameters)
    {
        if (strpos($sql, 'FROM categories') !== FALSE) {
            $row = $this->categories[(int) $parameters[0]] ?? NULL;
            return new SeriesFakeResult($row && $row['active'] ? [$row] : []);
        }

        $categoryId = (int) $parameters[0];
        $documentNumber = (string) $parameters[1];
        $matching = array_values(array_filter($this->documents, static function ($row) use ($categoryId, $documentNumber) {
            return $row['category_id'] === $categoryId && $row['document_number'] === $documentNumber;
        }));

        if (strpos($sql, 'SELECT id FROM softcopy_documents') !== FALSE) {
            return new SeriesFakeResult($matching ? [['id' => $matching[0]['id']]] : []);
        }
        if (strpos($sql, 'SELECT series_number FROM softcopy_documents') !== FALSE) {
            return new SeriesFakeResult(array_values(array_map(static function ($row) {
                return ['series_number' => $row['series_number']];
            }, array_filter($matching, static function ($row) {
                return $row['series_number'] !== NULL;
            }))));
        }
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
}

function assertSameSeries($expected, $actual, $label)
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, TRUE) . ', got ' . var_export($actual, TRUE));
    }
}

function assertSeriesError(callable $action, $label)
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('Expected validation error: ' . $label);
}

$db = new SeriesFakeDb();
$db->categories = [
    1 => ['id' => 1, 'active' => 1, 'series_code_pattern' => '001'],
    2 => ['id' => 2, 'active' => 1, 'series_code_pattern' => 'SER-005'],
    3 => ['id' => 3, 'active' => 1, 'series_code_pattern' => '']
];
$db->documents = [
    ['id' => 1, 'category_id' => 1, 'document_number' => 'PPM-001', 'series_number' => NULL],
    ['id' => 2, 'category_id' => 1, 'document_number' => 'PPM-001', 'series_number' => '001'],
    ['id' => 3, 'category_id' => 1, 'document_number' => 'PPM-001', 'series_number' => '002'],
    ['id' => 4, 'category_id' => 1, 'document_number' => 'PPM-002', 'series_number' => NULL],
    ['id' => 5, 'category_id' => 2, 'document_number' => 'ABC-001', 'series_number' => NULL],
    ['id' => 6, 'category_id' => 2, 'document_number' => 'ABC-001', 'series_number' => 'SER-009'],
    ['id' => 9, 'category_id' => 3, 'document_number' => 'BARE-001', 'series_number' => NULL]
];

assertSameSeries('003', Document_number_generator::next_series($db, 1, 'PPM-001'), 'Existing series increments');
assertSameSeries('001', Document_number_generator::next_series($db, 1, 'PPM-002'), 'Independent per document');
assertSameSeries('SER-010', Document_number_generator::next_series($db, 2, 'ABC-001'), 'Prefix and starting sequence');
assertSeriesError(static function () use ($db) {
    Document_number_generator::next_series($db, 3, 'BARE-001');
}, 'Blank optional series pattern disables series allocation');
$db->documents[] = ['id' => 7, 'category_id' => 1, 'document_number' => 'PPM-001', 'series_number' => '007'];
assertSameSeries('008', Document_number_generator::next_series($db, 1, 'PPM-001'), 'Highest historical series wins');
$db->documents[] = ['id' => 8, 'category_id' => 1, 'document_number' => 'PPM-001', 'series_number' => 'legacy'];
assertSameSeries('008', Document_number_generator::next_series($db, 1, 'PPM-001'), 'Nonmatching series is ignored');
assertSeriesError(static function () use ($db) {
    Document_number_generator::next_series($db, 1, 'PPM-999');
}, 'Document must exist');
assertSeriesError(static function () {
    Document_number_generator::validate_series_pattern('SER-000');
}, 'Zero start disallowed');
assertSeriesError(static function () {
    Document_number_generator::validate_series_pattern('SER');
}, 'Missing sequence disallowed');
assertSeriesError(static function () {
    Document_number_generator::validate_series_pattern("SER-\n001");
}, 'Invalid line breaks');

echo "Softcopy series pattern tests passed.\n";
