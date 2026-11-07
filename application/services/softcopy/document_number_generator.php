<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_number_generator
{
    public static function next($db, int $categoryId): string
    {
        $category = $db->query(
            'SELECT id,code_pattern,category_type FROM categories WHERE id=? AND active=1 FOR UPDATE',
            [$categoryId]
        )->row_array();
        return self::calculate($db, $categoryId, $category);
    }

    public static function preview($db, int $categoryId): string
    {
        $category = $db->query(
            'SELECT id,code_pattern,category_type FROM categories WHERE id=? AND active=1',
            [$categoryId]
        )->row_array();
        return self::calculate($db, $categoryId, $category);
    }

    private static function calculate($db, int $categoryId, ?array $category): string
    {
        if (!$category || ($category['category_type'] ?? 'pattern') !== 'pattern') {
            throw new DomainException('Choose an active Document Number Pattern category. Folder-only categories cannot generate document numbers.');
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
