<?php
declare(strict_types=1);
namespace Pk\Core;
/** Keeps native MySQL error details in server logs, not in public responses. */
final class Database_error extends \RuntimeException
{
    public function __construct(string $message, public readonly int $driverCode = 0) { parent::__construct($message, $driverCode); }
    public function isConflict(): bool { return in_array($this->driverCode, [1062, 1205, 1213, 1451, 1452, 3819, 4025], true); }
}
