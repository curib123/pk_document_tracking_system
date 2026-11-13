<?php
/**
 * Regression tests for Signatories References (editable searchable dropdown data).
 * Run: php tools/tests/signatory_references.php
 */
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__, 2) . '/application/');

class FakeResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function result_array() { return $this->rows; }
}

class FakeDb
{
    public $exists = TRUE;
    public $saved = [];

    public function table_exists($name) { return $this->exists && $name === 'softcopy_signatory_references'; }

    /** Mimics GROUP BY <column> ORDER BY MAX(id) DESC on the saved rows. */
    public function query($sql)
    {
        preg_match('/`(signatory_\w+)` AS value/', $sql, $m);
        $column = $m[1];
        $groups = [];
        foreach ($this->saved as $id => $row) {
            // utf8mb4_general_ci groups case-insensitively; keep the first spelling seen.
            $k = mb_strtolower($row[$column], 'UTF-8');
            if ($row[$column] === '') continue;
            $groups[$k] = ['value' => $groups[$k]['value'] ?? $row[$column], 'latest' => $id + 1];
        }
        usort($groups, static function ($a, $b) { return $b['latest'] <=> $a['latest']; });
        return new FakeResult($groups);
    }

    public function insert($table, $row)
    {
        $this->saved[] = $row;
        return TRUE;
    }
}

$ci = new stdClass();
$ci->db = new FakeDb();
function &get_instance() { global $ci; return $ci; }
function log_message() {}

require_once APPPATH . 'services/softcopy/signatory_reference_service.php';

$failures = 0;
function check($label, $actual, $expected)
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: $label\n  expected: " . json_encode($expected) . "\n  actual:   " . json_encode($actual) . "\n";
    } else {
        echo "ok:   $label\n";
    }
}
function fails($label, callable $fn, $contains)
{
    global $failures;
    try {
        $fn();
        $failures++;
        echo "FAIL: $label (no exception)\n";
    } catch (DomainException $e) {
        if (strpos($e->getMessage(), $contains) === FALSE) {
            $failures++;
            echo "FAIL: $label (message: {$e->getMessage()})\n";
        } else {
            echo "ok:   $label\n";
        }
    }
}

$service = new Signatory_reference_service();

// Whitespace normalization keeps the text otherwise exactly as typed.
check('normalize collapses spaces', Signatory_reference_service::normalize("  Juan   Dela\t Cruz "), 'Juan Dela Cruz');
check('normalize keeps case', Signatory_reference_service::normalize('  prepared BY'), 'prepared BY');

// No data yet: the user can type immediately, so the options are simply empty.
check('options empty without data', $service->options(), ['title' => [], 'full_name' => [], 'position' => []]);

// No table (migration not applied) must not break the form.
$ci->db->exists = FALSE;
check('options empty without table', $service->options(), ['title' => [], 'full_name' => [], 'position' => []]);
$ci->db->exists = TRUE;

// Optional: nothing posted, or only blank rows, saves nothing.
check('nothing posted', $service->from_post([]), []);
check('blank rows ignored', $service->from_post([
    'signatory_title' => ['', '  '], 'signatory_full_name' => ['', ''], 'signatory_position' => ['', '']
]), []);

// All three fields are required for an entry.
fails('missing position', function () use ($service) {
    $service->from_post(['signatory_title' => ['Prepared By'], 'signatory_full_name' => ['Juan'], 'signatory_position' => [' ']]);
}, 'Signatory Position is required');
fails('missing title', function () use ($service) {
    $service->from_post(['signatory_title' => [''], 'signatory_full_name' => ['Juan'], 'signatory_position' => ['Officer']]);
}, 'Signatory Title is required');
fails('too long', function () use ($service) {
    $service->from_post(['signatory_title' => [str_repeat('a', 151)], 'signatory_full_name' => ['J'], 'signatory_position' => ['P']]);
}, '150 characters');
fails('too many rows', function () use ($service) {
    $n = Signatory_reference_service::MAX_ROWS + 1;
    $service->from_post([
        'signatory_title' => array_fill(0, $n, 'T'), 'signatory_full_name' => array_fill(0, $n, 'N'),
        'signatory_position' => array_fill(0, $n, 'P')
    ]);
}, 'at most');
check('non-array input ignored', $service->from_post(['signatory_title' => 'x']), []);

// First entry, typed manually.
$first = $service->from_post([
    'signatory_title' => ['Prepared By'], 'signatory_full_name' => ['Juan  Dela Cruz'],
    'signatory_position' => ['Documentation Officer']
]);
check('first entry normalized', $first, [
    ['title' => 'Prepared By', 'full_name' => 'Juan Dela Cruz', 'position' => 'Documentation Officer']
]);
check('save count', $service->save(1, 10, $first, 5), 1);
check('saved row', $ci->db->saved[0], [
    'softcopy_id' => 1, 'revision_id' => 10, 'sort_order' => 1,
    'signatory_title' => 'Prepared By', 'signatory_full_name' => 'Juan Dela Cruz',
    'signatory_position' => 'Documentation Officer', 'created_by' => 5
]);
check('empty list saves nothing', $service->save(1, 11, [], 5), 0);

// After saving, every value is available as an option, with no master tables.
check('options after first save', $service->options(), [
    'title' => ['Prepared By'], 'full_name' => ['Juan Dela Cruz'], 'position' => ['Documentation Officer']
]);

// Duplicates differing only by case or spacing collapse to one option;
// the three fields stay independent (same person, different capacity).
$ci->db->saved[] = ['signatory_title' => 'PREPARED  BY', 'signatory_full_name' => 'juan dela cruz',
    'signatory_position' => 'Quality Manager'];
$ci->db->saved[] = ['signatory_title' => 'Approved By', 'signatory_full_name' => 'Juan Dela Cruz',
    'signatory_position' => 'Plant Manager'];
$options = $service->options();
check('title options deduplicated', array_map('strtolower', array_map(['Signatory_reference_service', 'normalize'], $options['title'])),
    ['approved by', 'prepared by']);
check('one name option', count($options['full_name']), 1);
check('positions independent', $options['position'], ['Documentation Officer', 'Plant Manager', 'Quality Manager']);

echo $failures ? "\n$failures failure(s)\n" : "\nAll signatory reference tests passed\n";
exit($failures ? 1 : 0);
