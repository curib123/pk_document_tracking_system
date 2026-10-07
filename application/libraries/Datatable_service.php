<?php
declare(strict_types=1);
use Pk\Core\Rules;
class Datatable_service
{
    // Kani nga service mao ang business-rule layer; controllers thin ra para easy i-follow.
    public static function normalize(array $query,array $columns): array
    {
        $integer=static fn($value,$fallback)=>filter_var($value,FILTER_VALIDATE_INT)!==false?(int)$value:$fallback;
        $draw=max(0,$integer($query['draw'] ?? 0,0));
        $length=$integer($query['length'] ?? $query['limit'] ?? 25,25);
        $length=in_array($length,[10,25,50,100],true)?$length:25;
        $page=max(1,min(100000,$integer($query['page'] ?? 1,1)));
        $offset=array_key_exists('start',$query)?max(0,min(10000000,$integer($query['start'],0))):($page-1)*$length;
        $search=$query['q'] ?? (is_array($query['search'] ?? null)?($query['search']['value'] ?? ''):'');
        $search=Rules::text(['q'=>$search],'q',150,false) ?? '';
        $order=is_array($query['order'][0] ?? null)?$query['order'][0]:[];
        $column=$integer($order['column'] ?? -1,-1);
        $sort=$query['sort'] ?? ($columns[$column] ?? $columns[0]);
        if (!is_string($sort) || !in_array($sort,$columns,true)) $sort=$columns[0];
        $direction=($query['direction'] ?? $order['dir'] ?? 'desc')==='asc'?'asc':'desc';
        return ['draw'=>$draw,'offset'=>$offset,'page'=>(int)floor($offset/$length)+1,'limit'=>$length,'q'=>$search,'sort'=>$sort,'direction'=>$direction];
    }
    public static function payload(array $rows,int $unfiltered,int $filtered,array $request): array
    {
        return ['rows'=>$rows,'total'=>$filtered,'page'=>$request['page'],'limit'=>$request['limit'],'pages'=>max(1,(int)ceil($filtered/$request['limit'])),
            'draw'=>$request['draw'],'recordsTotal'=>$unfiltered,'recordsFiltered'=>$filtered,'data'=>$rows];
    }
}
