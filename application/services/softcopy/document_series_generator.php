<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Document-specific series configuration. Normal document numbers still belong
 * to categories, but each *existing document number* configures its own series.
 * Call next() only inside the caller's database transaction.
 */
class Document_series_generator
{
    public static function validate_pattern(string $pattern): array
    {
        $pattern = trim($pattern);
        if ($pattern === '' || mb_strlen($pattern) > 100
            || preg_match('/[\r\n]/', $pattern)
            || !preg_match('/^(.+-)([0-9]+)$/D', $pattern, $match)
                && !preg_match('/^()([0-9]+)$/D', $pattern, $match)) {
            throw new DomainException('Enter a Series No. Code Pattern ending in digits after its final dash, such as sample-series-002 (numeric-only 001 is also supported).');
        }
        $digits = ltrim($match[2], '0');
        $limit = (string) PHP_INT_MAX;
        if ($digits === '' || strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) >= 0)) {
            throw new DomainException('Series code pattern must contain a valid positive starting number.');
        }
        return [$match[1], (int) $digits, strlen($match[2])];
    }

    public static function format_number(string $prefix, int $value, int $width): string
    {
        $digits = (string) $value;
        $result = $prefix . str_pad($digits, max($width, strlen($digits)), '0', STR_PAD_LEFT);
        if (mb_strlen($result) > 100) {
            throw new DomainException('Generated series exceeds 100 characters. Use a shorter series code pattern.');
        }
        return $result;
    }

    /**
     * Returns [original series, first new series].
     * A configured first-series pattern is the number for the *new* record.
     * The original document takes the preceding number (001 -> 000).
     */
    public static function initial_pair(string $pattern): array
    {
        [$prefix, $first, $width] = self::validate_pattern($pattern);
        return [
            self::format_number($prefix, $first - 1, $width),
            self::format_number($prefix, $first, $width)
        ];
    }

    /**
     * Authoritative allocation on approval/direct creation, serialized by
     * locking the same category row used by document-number generation.
     */
    public static function next($db, int $categoryId, string $number, string $submittedPattern = '', int $actorId = 0): string
    {
        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 100) {
            throw new DomainException('Select an existing Document No. before setting up a series.');
        }
        $category = $db->query(
            'SELECT id,category_type FROM categories WHERE id=? AND active=1 FOR UPDATE',
            [$categoryId]
        )->row_array();
        if (!$category || ($category['category_type'] ?? 'pattern') !== 'pattern') {
            throw new DomainException('Choose an active Document Number Pattern category.');
        }

        $documents = $db->query(
            'SELECT id,series_number,status FROM softcopy_documents WHERE category_id=? AND document_number=? ORDER BY id FOR UPDATE',
            [$categoryId, $number]
        )->result_array();
        if (!$documents) {
            throw new DomainException('Select an existing Document No. from the selected category.');
        }

        $configured = $db->query(
            'SELECT code_pattern FROM softcopy_document_series_patterns WHERE category_id=? AND document_number=? FOR UPDATE',
            [$categoryId, $number]
        )->row_array();
        $pattern = $configured
            ? (string) $configured['code_pattern']
            : trim($submittedPattern);
        if ($pattern === '') {
            throw new DomainException('Set up the Series No. Code Pattern for this Document No. before its first series is created.');
        }
        [$prefix, $start, $width] = self::validate_pattern($pattern);

        if (!$configured) {
            // The original previously had no series. Allocate its predecessor
            // and update every revision in the same transaction as the new file.
            $unnumbered = array_values(array_filter($documents, static function ($row) {
                return $row['series_number'] === NULL || $row['series_number'] === '';
            }));
            if (count($unnumbered) > 1) {
                throw new DomainException('Multiple unnumbered records share this Document No. Resolve them before setting up series numbering.');
            }
            if ($unnumbered) {
                $previous = self::format_number($prefix, $start - 1, $width);
                foreach ($documents as $existing) {
                    if ($existing['series_number'] !== NULL
                        && strcasecmp((string) $existing['series_number'], $previous) === 0) {
                        throw new DomainException('The original document series number already exists. Choose a different first series code pattern.');
                    }
                }
                $baseId = (int) $unnumbered[0]['id'];
                $db->set('version', 'version + 1', FALSE)->where('id', $baseId)
                    ->update('softcopy_documents', ['series_number' => $previous]);
                $db->where('document_id', $baseId)->update('softcopy_revisions', ['series_number' => $previous]);
                if ($actorId > 0) {
                    $oldStatus = (string) ($unnumbered[0]['status'] ?? 'active');
                    $db->insert('status_history', [
                        'domain' => 'softcopy',
                        'document_id' => $baseId,
                        'previous_status' => $oldStatus,
                        'new_status' => $oldStatus,
                        'action' => 'series_initialized',
                        'user_id' => $actorId,
                        'remarks' => 'Assigned original Series No. ' . $previous . ' before creating a new series for ' . $number
                    ]);
                }
                if ($db->trans_status() === FALSE) {
                    throw new DomainException('Could not assign the original document its preceding series number.');
                }
                foreach ($documents as &$existing) {
                    if ((int) $existing['id'] === $baseId) {
                        $existing['series_number'] = $previous;
                        break;
                    }
                }
                unset($existing);
            }
            if (!$db->insert('softcopy_document_series_patterns', [
                'category_id' => $categoryId,
                'document_number' => $number,
                'code_pattern' => $pattern
            ])) {
                throw new DomainException('Unable to save the initial Series No. Code Pattern.');
            }
        }

        // Width follows the widest matching suffix ever seen, for consistent
        // padding, even though the next value no longer does (see below).
        foreach ($documents as $existing) {
            $value = (string) ($existing['series_number'] ?? '');
            if (strncasecmp($value, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $suffix = substr($value, strlen($prefix));
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }
            $digits = ltrim($suffix, '0');
            $digits = $digits === '' ? '0' : $digits;
            $limit = (string) PHP_INT_MAX;
            if (strlen($digits) > strlen($limit)
                || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) >= 0)) {
                throw new DomainException('An existing series exceeds the supported sequence range.');
            }
            $width = max($width, strlen($suffix));
        }

        // Continue from the most recently created record for this Document
        // No. (documents is ordered by id), not simply the highest value
        // ever seen historically. A manual correction on the latest record
        // is what later auto-numbering builds on top of.
        $latest = end($documents);
        $latestValue = $start - 1;
        if ($latest) {
            $value = (string) ($latest['series_number'] ?? '');
            if (strncasecmp($value, $prefix, strlen($prefix)) === 0) {
                $suffix = substr($value, strlen($prefix));
                if ($suffix !== '' && ctype_digit($suffix)) {
                    $digits = ltrim($suffix, '0');
                    $latestValue = (int) ($digits === '' ? '0' : $digits);
                }
            }
        }
        $next = max($start, $latestValue + 1);
        $newSeries = self::format_number($prefix, $next, $width);
        foreach ($documents as $existing) {
            if ($existing['series_number'] !== NULL
                && strcasecmp((string) $existing['series_number'], $newSeries) === 0) {
                throw new DomainException('Generated Series No. is already used for this Document No.');
            }
        }
        return $newSeries;
    }
}
