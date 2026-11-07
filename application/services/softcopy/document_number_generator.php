<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_number_generator
{
    public static function next($db, int $categoryId): string
    {
        $category = $db->query(
            'SELECT id,code_pattern FROM categories WHERE id=? AND active=1 FOR UPDATE',
            [$categoryId]
        )->row_array();
        return self::calculate($db, $categoryId, $category);
    }

    public static function preview($db, int $categoryId): string
    {
        $category = $db->query(
            'SELECT id,code_pattern FROM categories WHERE id=? AND active=1',
            [$categoryId]
        )->row_array();
        return self::calculate($db, $categoryId, $category);
    }

    private static function calculate($db, int $categoryId, ?array $category): string
    {
        if (!$category) {
            throw new DomainException('Choose an active softcopy category.');
        }

        $pattern = trim((string) $category['code_pattern']);
        if (mb_strlen($pattern) > 100 || preg_match('/[\r\n]/', $pattern)) {
            throw new DomainException('The selected category has an invalid document code pattern.');
        }
        if (preg_match('/^(.*[^0-9])([0-9]+)$/D', $pattern, $parts)) {
            $prefix = $parts[1];
            $initial = (int) $parts[2];
            $width = strlen($parts[2]);
        } elseif (preg_match('/^.+-$/D', $pattern)) {
            // Existing installations may still have the original prefix-only pattern.
            $prefix = $pattern;
            $initial = 1;
            $width = 3;
        } else {
            throw new DomainException('The selected category has an invalid document code pattern.');
        }
        if ($prefix === '' || mb_strlen($prefix) > 97 || $initial < 1) {
            throw new DomainException('The selected category code pattern must end with a positive sequence number.');
        }

        $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix) . '%';
        $rows = $db->query(
            "SELECT document_number FROM softcopy_documents
             WHERE category_id=? AND document_number LIKE ? ESCAPE '!'",
            [$categoryId, $like]
        )->result_array();

        $maximum = 0;
        $hasMatchingNumber = FALSE;
        foreach ($rows as $row) {
            $number = (string) $row['document_number'];
            if (strncasecmp($number, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $suffix = substr($number, strlen($prefix));
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }
            $hasMatchingNumber = TRUE;
            $value = (int) $suffix;
            if ($value > $maximum) {
                $maximum = $value;
                $width = max($width, strlen($suffix));
            } elseif ($value === $maximum) {
                $width = max($width, strlen($suffix));
            }
        }

        $next = $hasMatchingNumber ? max($initial, $maximum + 1) : $initial;
        $number = $prefix . str_pad((string) $next, max($width, strlen((string) $next)), '0', STR_PAD_LEFT);
        if (mb_strlen($number) > 100) {
            throw new DomainException('The generated document number exceeds the 100-character limit. Shorten the category code pattern.');
        }
        return $number;
    }


    /**
     * Allocate a series for an existing document number at approval time.
     * The caller must have an open transaction; the category row serializes
     * simultaneous allocations, just like document-number generation.
     */
    public static function next_series($db, int $categoryId, string $number): string
    {
        $category = $db->query(
            'SELECT id,series_code_pattern FROM categories WHERE id=? AND active=1 FOR UPDATE',
            [$categoryId]
        )->row_array();
        return self::calculate_series($db, $categoryId, trim($number), $category);
    }

    /**
     * Shared validation for Softcopy Category setup and actual allocation.
     * Both a numeric pattern (001) and a prefixed pattern (SER-001) work.
     */
    public static function validate_series_pattern(string $pattern): array
    {
        $pattern = trim($pattern);
        if ($pattern === '' || mb_strlen($pattern) > 100 || preg_match('/[\r\n]/', $pattern)
            || !preg_match('/^(.*?)([0-9]+)$/D', $pattern, $parts)) {
            throw new DomainException('Series code pattern must end with a positive sequence, for example 001 or SER-001 (maximum 100 characters).');
        }

        $numeric = ltrim($parts[2], '0');
        $maximum = (string) PHP_INT_MAX;
        if ($numeric === '' || strlen($numeric) > strlen($maximum)
            || (strlen($numeric) === strlen($maximum) && strcmp($numeric, $maximum) > 0)) {
            throw new DomainException('Series code pattern must end with a valid positive sequence number.');
        }
        return [$parts[1], (int) $numeric, strlen($parts[2])];
    }

    private static function calculate_series($db, int $categoryId, string $number, ?array $category): string
    {
        if (!$category) {
            throw new DomainException('Choose an active softcopy category.');
        }
        if ($number === '' || mb_strlen($number) > 100) {
            throw new DomainException('Choose an existing document number in the selected category.');
        }
        $seriesPattern = trim((string) ($category['series_code_pattern'] ?? ''));
        if ($seriesPattern === '') {
            throw new DomainException('This Softcopy Category does not use series numbers. Configure an optional Series No. Code Pattern before creating a document with a series.');
        }
        [$prefix, $initial, $width] = self::validate_series_pattern($seriesPattern);

        $document = $db->query(
            'SELECT id FROM softcopy_documents WHERE category_id=? AND document_number=? LIMIT 1 FOR UPDATE',
            [$categoryId, $number]
        )->row_array();
        if (!$document) {
            throw new DomainException('Choose a document number already used in the selected category.');
        }

        // Include historic manually entered series and cancelled documents.
        // Never recycle a previously used matching series.
        $rows = $db->query(
            'SELECT series_number FROM softcopy_documents WHERE category_id=? AND document_number=? AND series_number IS NOT NULL FOR UPDATE',
            [$categoryId, $number]
        )->result_array();

        $highest = 0;
        $matching = FALSE;
        foreach ($rows as $row) {
            $series = (string) $row['series_number'];
            if (strncasecmp($series, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $suffix = substr($series, strlen($prefix));
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }
            $digits = ltrim($suffix, '0');
            if ($digits === '') {
                $digits = '0';
            }
            $maxInt = (string) PHP_INT_MAX;
            if (strlen($digits) > strlen($maxInt)
                || (strlen($digits) === strlen($maxInt) && strcmp($digits, $maxInt) > 0)) {
                throw new DomainException('An existing series number exceeds the supported sequence range.');
            }
            $matching = TRUE;
            $value = (int) $digits;
            $highest = max($highest, $value);
            $width = max($width, strlen($suffix));
        }
        if ($matching && $highest >= PHP_INT_MAX) {
            throw new DomainException('Series number sequence has reached its limit.');
        }

        $next = $matching ? max($initial, $highest + 1) : $initial;
        $numericPart = (string) $next;
        $result = $prefix . str_pad($numericPart, max($width, strlen($numericPart)), '0', STR_PAD_LEFT);
        if (mb_strlen($result) > 100) {
            throw new DomainException('Generated series number exceeds 100 characters. Shorten the category series code pattern.');
        }
        return $result;
    }

    public static function require_existing($db, int $categoryId, string $number): void
    {
        $number = trim($number);
        $category = $db->query(
            'SELECT id FROM categories WHERE id=? AND active=1 FOR UPDATE',
            [$categoryId]
        )->row_array();
        if (!$category) {
            throw new DomainException('Choose an active softcopy category.');
        }
        $document = $db->query(
            'SELECT id FROM softcopy_documents WHERE category_id=? AND document_number=? LIMIT 1',
            [$categoryId, $number]
        )->row_array();
        if (!$document) {
            throw new DomainException('Choose a document number already used in the selected category.');
        }
    }

}
