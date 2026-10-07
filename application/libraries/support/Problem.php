<?php
declare(strict_types=1);
namespace Pk\Core;
final class Problem extends \RuntimeException
{
    public int $status;
    public array $fields;
    public function __construct(string $message, int $status = 422, array $fields = []) { $this->status=$status; $this->fields=$fields; parent::__construct($message); }
}
