<?php
declare(strict_types=1);
use Pk\Core\Problem;
class Endpoint_registry
{
    private array $definitions;
    public function __construct() { $this->definitions=require PK_ROOT.'/application/config/endpoints.php'; }
    public function resolve(string $operation,array $input): array
    {
        $selector=match($operation) { 'list','detail','catalog.save','catalog.delete'=>'module','documents.direct'=>'domain',default=>null };
        $key=$operation;
        if ($selector) {
            if (!is_string($input[$selector] ?? null)) throw new Problem('Unknown endpoint selector.',404);
            $key.='@'.$input[$selector];
        }
        return $this->definitions[$key] ?? throw new Problem('Unknown operation or module.',404);
    }
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
