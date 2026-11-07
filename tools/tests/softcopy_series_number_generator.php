<?php
/**
 * Regression checks for first-time series setup on a specific Document No.
 * Run: php tools/tests/softcopy_series_number_generator.php
 */
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/services/softcopy/document_series_generator.php';

class FakeSeriesResult
{
    private $rows;
    public function __construct($rows) { $this->rows = $rows; }
    public function row_array() { return $this->rows[0] ?? NULL; }
    public function result_array() { return $this->rows; }
}

class FakeSeriesDb
{
    public $documents = [];
    public $revisions = [];
    public $config = [];
    public $history = [];
    private $conditions = [];
    public function query($sql, $params)
    {
        if (strpos($sql, 'FROM categories') !== FALSE) {
            return new FakeSeriesResult((int) $params[0] > 0 ? [[
                'id' => (int) $params[0],
                'category_type' => (int) $params[0] === 99 ? 'folder' : 'pattern'
            ]] : []);
        }
        $cat = (int) $params[0];
        $number = (string) $params[1];
        if (strpos($sql, 'FROM softcopy_documents') !== FALSE) {
            return new FakeSeriesResult(array_values(array_filter(
                $this->documents,
                static function ($row) use ($cat, $number) {
                    return $row['category_id'] === $cat && $row['document_number'] === $number;
                }
            )));
        }
        if (strpos($sql, 'FROM softcopy_document_series_patterns') !== FALSE) {
            $pattern = $this->config[$cat . ':' . $number] ?? NULL;
            return new FakeSeriesResult($pattern === NULL ? [] : [['code_pattern' => $pattern]]);
        }
        throw new RuntimeException('Unexpected fake query: ' . $sql);
    }
    public function set($field, $value, $escape = TRUE) { return $this; }
    public function where($field, $value) { $this->conditions[$field] = $value; return $this; }
    public function update($table, $changes)
    {
        if ($table === 'softcopy_documents') {
            foreach ($this->documents as &$document) {
                if ($document['id'] === $this->conditions['id']) {
                    $document = array_merge($document, $changes);
                }
            }
            unset($document);
        } elseif ($table === 'softcopy_revisions') {
            foreach ($this->revisions as &$revision) {
                if ($revision['document_id'] === $this->conditions['document_id']) {
                    $revision = array_merge($revision, $changes);
                }
            }
            unset($revision);
        } else {
            throw new RuntimeException('Unexpected fake update: ' . $table);
        }
        $this->conditions = [];
        return TRUE;
    }
    public function insert($table, $data)
    {
        if ($table === 'softcopy_document_series_patterns') {
            $this->config[$data['category_id'] . ':' . $data['document_number']] = $data['code_pattern'];
            return TRUE;
        }
        if ($table === 'status_history') {
            $this->history[] = $data;
            return TRUE;
        }
        throw new RuntimeException('Unexpected fake insert: ' . $table);
    }
    public function trans_status() { return TRUE; }
}

function same($expected, $actual, $label)
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, TRUE) . ', got ' . var_export($actual, TRUE));
    }
}
function rejected(callable $callback, $label)
{
    try { $callback(); }
    catch (DomainException $e) { return; }
    throw new RuntimeException($label . ': expected rejection');
}

$db = new FakeSeriesDb();
$db->documents = [
    ['id' => 1, 'category_id' => 1, 'document_number' => 'DOC-001', 'series_number' => NULL, 'status' => 'active'],
    ['id' => 2, 'category_id' => 1, 'document_number' => 'DOC-002', 'series_number' => NULL, 'status' => 'active'],
    ['id' => 3, 'category_id' => 1, 'document_number' => 'DOC-003', 'series_number' => '001', 'status' => 'active'],
    ['id' => 4, 'category_id' => 1, 'document_number' => 'DOC-004', 'series_number' => NULL, 'status' => 'active'],
    ['id' => 5, 'category_id' => 1, 'document_number' => 'DOC-004', 'series_number' => NULL, 'status' => 'active']
];
$db->revisions = [
    ['document_id' => 1, 'series_number' => NULL],
    ['document_id' => 2, 'series_number' => NULL]
];

rejected(static function () use ($db) {
    Document_series_generator::next($db, 1, 'DOC-001');
}, 'First setup requires an explicit document pattern');
same(['000', '001'], Document_series_generator::initial_pair('001'), 'Numeric initial series pair');
same(['SER-004', 'SER-005'], Document_series_generator::initial_pair('SER-005'), 'Prefixed initial series pair');
same(['sample-series-001', 'sample-series-002'], Document_series_generator::initial_pair('sample-series-002'), 'Final-dash numeric decrement preserves both prefix components');
same('sample-series-003', Document_series_generator::format_number('sample-series-', 3, 3), 'Next series after 002');
$db->documents[] = [
    'id' => 7, 'category_id' => 1, 'document_number' => 'DOC-005',
    'series_number' => NULL, 'status' => 'active'
];
same('sample-series-002', Document_series_generator::next($db, 1, 'DOC-005', 'sample-series-002', 25),
    'First series assigned from multi-dash pattern');
same('sample-series-001', $db->documents[count($db->documents) - 1]['series_number'],
    'Original receives multi-dash predecessor');
$db->documents[] = [
    'id' => 8, 'category_id' => 1, 'document_number' => 'DOC-005',
    'series_number' => 'sample-series-002', 'status' => 'active'
];
same('sample-series-003', Document_series_generator::next($db, 1, 'DOC-005', '', 25),
    'Next creation increments only digits following the last dash');
rejected(static function () use ($db) {
    Document_series_generator::next($db, 99, 'DOC-005', 'sample-series-002', 25);
}, 'Folder-only category cannot allocate a document series');
rejected(static function () {
    Document_series_generator::validate_pattern('sample-series002');
}, 'Non-dash letter-to-number prefix must not be accepted');
same('001', Document_series_generator::next($db, 1, 'DOC-001', '001', 25), 'First new series');
same('000', $db->documents[0]['series_number'], 'Original document decremented');
same('000', $db->revisions[0]['series_number'], 'Original revision series updated');
same('001', $db->config['1:DOC-001'], 'Pattern stored per document');
same('series_initialized', $db->history[0]['action'], 'Initial series audit event');
$db->documents[] = ['id' => 6, 'category_id' => 1, 'document_number' => 'DOC-001', 'series_number' => '001', 'status' => 'active'];
same('002', Document_series_generator::next($db, 1, 'DOC-001', ''), 'Next new series without new setup');
same('SER-005', Document_series_generator::next($db, 1, 'DOC-002', 'SER-005'), 'Second document independent pattern');
same('SER-004', $db->documents[1]['series_number'], 'Second document original is predecessor');
same('002', Document_series_generator::next($db, 1, 'DOC-003', '001'), 'Legacy numbered document continues from max');
rejected(static function () use ($db) {
    Document_series_generator::next($db, 1, 'DOC-004', '001');
}, 'Ambiguous multiple unnumbered originals');
rejected(static function () {
    Document_series_generator::validate_pattern('SER-000');
}, 'Zero first series');
rejected(static function () {
    Document_series_generator::validate_pattern("SER-\n001");
}, 'Line breaks invalid');
rejected(static function () {
    Document_series_generator::validate_pattern('SER');
}, 'Missing digits');
echo "Document-specific series tests passed.\n";
