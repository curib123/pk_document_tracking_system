<?php
declare(strict_types=1);
namespace Pk\Core;
final class Problem extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly array $fields = []) { parent::__construct($message); }
}
