<?php
declare(strict_types=1);
use Pk\Core\Problem;
class Endpoint_registry
{
    // Kani nga service mao ang business-rule layer; controllers thin ra para easy i-follow.
    private array $definitions;
    public function __construct() { $this->definitions=require PK_ROOT.'/application/config/endpoints.php'; }
    public function byPath(string $path): array
    {
        foreach($this->definitions as $definition) if ($definition['path']===$path) return $definition;
        throw new Problem('Unknown endpoint.',404);
    }
    public function browserRoutes(): array
    {
        $result=[];
        foreach($this->definitions as $key=>$definition) $result[$key]=$definition['path'];
        return $result;
    }
}
