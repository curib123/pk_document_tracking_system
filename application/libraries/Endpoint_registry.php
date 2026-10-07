<?php
declare(strict_types=1);

use Pk\Core\Problem;

class Endpoint_registry
{
    // Native CI3 routes ra; one registry para browser ug controller always same contract.
    private array $definitions;

    public function __construct()
    {
        $this->definitions = require
            PK_ROOT
            . '/application/config/endpoints.php';
    }

    public function byPath(string $path): array
    {
        foreach ($this->definitions as $definition) {
            if ($definition['path'] === $path) {
                return $definition;
            }
        }

        throw new Problem(
            'Unknown endpoint.',
            404
        );
    }

    public function browserRoutes(): array
    {
        $routes = [];

        foreach (
            $this->definitions
            as $key => $definition
        ) {
            $routes[$key] =
                $definition['path'];
        }

        return $routes;
    }
}
