<?php
declare(strict_types=1);
namespace Pk\Core;
final class Rules
{
    // Ari ta mag-normalize ug validate input para service code dili balik-balik ug checks.
    public static function text(array $data, string $key, int $max = 255, bool $required = true): ?string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) throw new Problem("Invalid $key.", 422, [$key => 'Enter text.']);
        $value = trim((string)$value);
        if (($required && $value === '') || strlen($value) > $max) throw new Problem("Check $key (required, maximum $max bytes).", 422, [$key => 'Invalid length.']);
        return $value === '' ? null : $value;
    }
    public static function id(array $data, string $key = 'id', bool $required = true): ?int
    {
        if (!$required && (!isset($data[$key]) || $data[$key] === '')) return null;
        $n = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($n === false || $n < 1) throw new Problem("Select a valid $key.", 422, [$key => 'Required positive ID.']);
        return $n;
    }
    public static function date(array $data, string $key, bool $required = true): ?string
    {
        $value = self::text($data, $key, 10, $required);
        if ($value === null) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new Problem("Invalid $key; use YYYY-MM-DD.");
        return $value;
    }
    public static function boolean(mixed $value): int
    {
        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($result === null) throw new Problem('Invalid boolean value.');
        return $result ? 1 : 0;
    }
    public static function choice(array $data, string $key, array $options): string
    {
        $value = self::text($data, $key);
        if (!in_array($value, $options, true)) throw new Problem("Invalid $key.");
        return $value;
    }
    public static function password(string $value, string $confirmation): string
    {
        if (strlen($value) < 12 || strlen($value) > 72) throw new Problem('Password must contain 12–72 bytes.');
        if (!hash_equals($value, $confirmation)) throw new Problem('Passwords do not match.');
        return password_hash($value, PASSWORD_DEFAULT);
    }
    public static function page(array $query): array { return [max(1, min(100000, (int)($query['page'] ?? 1))), max(1, min(100, (int)($query['limit'] ?? 25)))]; }
    public static function json(mixed $value): array
    {
        if (is_array($value)) return $value;
        try { $result = json_decode((string)$value, true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new Problem('Invalid JSON.'); }
        if (!is_array($result)) throw new Problem('Expected a JSON object or array.');
        return $result;
    }
    public static function fileType(string $name, string $mime): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $types = [
            'pdf' => ['application/pdf'], 'txt' => ['text/plain'], 'csv' => ['text/plain','text/csv'],
            'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
        ];
        if (!isset($types[$extension]) || !in_array($mime, $types[$extension], true)) throw new Problem('Unsupported file type or extension/MIME mismatch. Use PDF, DOCX, XLSX, TXT, CSV, PNG or JPEG.');
        return $extension;
    }
}
