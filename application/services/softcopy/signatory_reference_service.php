<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Signatories References for softcopy revisions.
 *
 * Each signatory is three free-text values (title, full name, position) kept
 * exactly as entered apart from whitespace normalization. There are no master
 * tables: the dropdown options for each field are simply the distinct values
 * already saved in softcopy_signatory_references.
 *
 * Rows are append-only. This service offers no update or delete path.
 */
class Signatory_reference_service
{
    const TABLE = 'softcopy_signatory_references';
    const MAX_LENGTH = 150;
    const MAX_ROWS = 20;
    const OPTION_LIMIT = 500;

    /** Form field => table column. The three dropdowns are independent. */
    const FIELDS = [
        'title' => 'signatory_title',
        'full_name' => 'signatory_full_name',
        'position' => 'signatory_position'
    ];

    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    /** Trim and collapse any run of whitespace (including NBSP) to one space. */
    public static function normalize($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $value));
    }

    /** Case-insensitive key used to detect duplicate values. */
    public static function key(string $value): string
    {
        return mb_strtolower(self::normalize($value), 'UTF-8');
    }

    /**
     * Read the signatory rows posted by the form (parallel arrays
     * signatory_title[], signatory_full_name[], signatory_position[]).
     *
     * Adding signatories is optional, so completely blank rows are ignored.
     * A row with any value must have all three.
     *
     * @return array<int, array{title:string, full_name:string, position:string}>
     */
    public function from_post(array $post): array
    {
        $rows = [];
        $columns = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $values = $post['signatory_' . $field] ?? [];
            $columns[$field] = is_array($values) ? array_values($values) : [];
        }
        $count = max(array_map('count', $columns));
        if ($count > self::MAX_ROWS * 4) {
            throw new DomainException('Too many signatories were submitted.');
        }
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach (array_keys(self::FIELDS) as $field) {
                $row[$field] = self::normalize($columns[$field][$i] ?? '');
            }
            $rows[] = $row;
        }
        return $this->validate($rows);
    }

    /** @return array<int, array{title:string, full_name:string, position:string}> */
    public function validate(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $entry = [];
            foreach (array_keys(self::FIELDS) as $field) {
                $entry[$field] = self::normalize($row[$field] ?? '');
            }
            if (implode('', $entry) === '') {
                continue;
            }
            $number = count($clean) + 1;
            foreach (['title' => 'Signatory Title', 'full_name' => 'Signatory Full Name',
                'position' => 'Signatory Position'] as $field => $label) {
                if ($entry[$field] === '') {
                    throw new DomainException("Signatory {$number}: {$label} is required.");
                }
                if (mb_strlen($entry[$field], 'UTF-8') > self::MAX_LENGTH) {
                    throw new DomainException(
                        "Signatory {$number}: {$label} must be " . self::MAX_LENGTH . ' characters or fewer.'
                    );
                }
            }
            $clean[] = $entry;
        }
        if (count($clean) > self::MAX_ROWS) {
            throw new DomainException('A revision can list at most ' . self::MAX_ROWS . ' signatories.');
        }
        return $clean;
    }

    /**
     * Dropdown options for all three fields, built from saved data only.
     * Duplicates are removed ignoring case and whitespace differences; the
     * most recently saved spelling is the one offered.
     *
     * @return array{title:string[], full_name:string[], position:string[]}
     */
    public function options(): array
    {
        $options = [];
        foreach (self::FIELDS as $field => $column) {
            $options[$field] = $this->distinct($column);
        }
        return $options;
    }

    private function distinct(string $column): array
    {
        if (!in_array($column, self::FIELDS, TRUE)) {
            return [];
        }
        $db = $this->ci->db;
        if (!$db->table_exists(self::TABLE)) {
            return [];
        }
        $rows = $db->query(
            "SELECT `{$column}` AS value, MAX(id) AS latest FROM `" . self::TABLE . "`
             WHERE `{$column}` <> '' GROUP BY `{$column}` ORDER BY latest DESC"
        )->result_array();
        $seen = [];
        foreach ($rows as $row) {
            $value = self::normalize($row['value']);
            $key = self::key($value);
            if ($value !== '' && !isset($seen[$key])) {
                $seen[$key] = $value;
            }
        }
        $values = array_values($seen);
        usort($values, static function ($a, $b) {
            return strnatcasecmp($a, $b);
        });
        return array_slice($values, 0, self::OPTION_LIMIT);
    }

    /**
     * Save the signatories of a newly created revision. The caller owns the
     * transaction. Nothing is written when the list is empty.
     */
    public function save(int $softcopyId, int $revisionId, array $rows, int $actorId): int
    {
        $rows = $this->validate($rows);
        if (!$rows) {
            return 0;
        }
        if (!$this->ci->db->table_exists(self::TABLE)) {
            throw new DomainException('Signatories References are not installed. Apply the 20261113 database migration.');
        }
        $order = 0;
        foreach ($rows as $row) {
            $ok = $this->ci->db->insert(self::TABLE, [
                'softcopy_id' => $softcopyId,
                'revision_id' => $revisionId,
                'sort_order' => ++$order,
                'signatory_title' => $row['title'],
                'signatory_full_name' => $row['full_name'],
                'signatory_position' => $row['position'],
                'created_by' => $actorId
            ]);
            if (!$ok) {
                log_message('error', 'Signatory reference insert failed: ' . json_encode($this->ci->db->error()));
                throw new DomainException('Unable to save the Signatories References.');
            }
        }
        return $order;
    }

    /**
     * Authorized correction: add signatories to an existing document, or replace
     * the list recorded with its current revision. The file, revision level and
     * every other detail are untouched, and a locked file does not block this.
     * The previous and new lists are written to status_history, so the change
     * stays auditable. Opens and ends its own transaction.
     *
     * @return array{added:int, changed:bool}
     */
    public function replace(int $softcopyId, array $rows, int $actorId, string $remarks = ''): array
    {
        $rows = $this->validate($rows);
        $db = $this->ci->db;
        if (!$db->table_exists(self::TABLE)) {
            throw new DomainException('Signatories References are not installed. Apply the 20261113 database migration.');
        }
        $db->trans_begin();
        try {
            $doc = $db->query(
                'SELECT id, status, current_revision_id FROM softcopy_documents WHERE id=? FOR UPDATE',
                [$softcopyId]
            )->row_array();
            if (!$doc || $doc['status'] !== 'active' || !(int) $doc['current_revision_id']) {
                throw new DomainException('Select an active softcopy document.');
            }
            $revisionId = (int) $doc['current_revision_id'];
            $before = [];
            foreach ($db->order_by('sort_order')->order_by('id')
                ->get_where(self::TABLE, ['revision_id' => $revisionId])->result_array() as $row) {
                $before[] = [
                    'title' => $row['signatory_title'],
                    'full_name' => $row['signatory_full_name'],
                    'position' => $row['signatory_position']
                ];
            }
            if ($before === $rows) {
                throw new DomainException('No changes to save.');
            }
            $db->where('revision_id', $revisionId)->delete(self::TABLE);
            $this->save($softcopyId, $revisionId, $rows, $actorId);
            $db->insert('status_history', [
                'domain' => 'softcopy',
                'document_id' => $softcopyId,
                'previous_status' => 'active',
                'new_status' => 'active',
                'action' => 'signatories_update',
                'user_id' => $actorId,
                'remarks' => trim(
                    ($remarks !== '' ? $remarks . ' | ' : '') .
                    'Signatories before: ' . $this->describe($before) . ' | after: ' . $this->describe($rows)
                )
            ]);
            if ($db->trans_status() === FALSE) {
                throw new DomainException('The signatories could not be saved.');
            }
            if ($db->trans_commit() === FALSE) {
                throw new DomainException('The signatories could not be committed.');
            }
        } catch (Throwable $e) {
            $db->trans_rollback();
            if ($e instanceof DomainException) {
                throw $e;
            }
            log_message('error', 'Signatory update failed: ' . $e->getMessage());
            throw new DomainException('Unable to update the signatories.');
        }
        return ['added' => count($rows), 'changed' => TRUE];
    }

    private function describe(array $rows): string
    {
        if (!$rows) {
            return '(none)';
        }
        return implode('; ', array_map(static function ($row) {
            return $row['title'] . ': ' . $row['full_name'] . ' (' . $row['position'] . ')';
        }, $rows));
    }

    /**
     * Signatories of each document's current revision, keyed by document id.
     *
     * @param int[] $softcopyIds
     */
    public function current_for_documents(array $softcopyIds): array
    {
        $softcopyIds = array_values(array_filter(array_map('intval', $softcopyIds)));
        if (!$softcopyIds || !$this->ci->db->table_exists(self::TABLE)) {
            return [];
        }
        $rows = $this->ci->db->select('s.softcopy_id,s.signatory_title,s.signatory_full_name,s.signatory_position')
            ->from(self::TABLE . ' s')
            ->join('softcopy_documents d', 'd.id=s.softcopy_id AND d.current_revision_id=s.revision_id')
            ->where_in('s.softcopy_id', $softcopyIds)
            ->order_by('s.softcopy_id')->order_by('s.sort_order')->order_by('s.id')
            ->get()->result_array();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['softcopy_id']][] = [
                'title' => $row['signatory_title'],
                'full_name' => $row['signatory_full_name'],
                'position' => $row['signatory_position']
            ];
        }
        return $result;
    }
}
