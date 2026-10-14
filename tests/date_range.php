<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/services/presentation/query_state.php';
function rangeCheck(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$all=Query_state::parse(['period'=>'all','from'=>'2026-01-01','to'=>'2026-01-31']);
rangeCheck($all['period']==='all' && $all['from']==='' && $all['to']==='', 'All time must clear stale custom dates.');
$day=Query_state::parse(['period'=>'day','from'=>'invalid','to'=>'invalid']);
$now=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));
rangeCheck($day['from']===$now->format('Y-m-d') && $day['to']===$day['from'], 'Presets must ignore stale date inputs.');
$starts=['week'=>$now->modify('monday this week'), 'month'=>$now->modify('first day of this month'),
    'year'=>$now->setDate((int)$now->format('Y'),1,1)];
foreach ($starts as $period=>$start) {
    $state=Query_state::parse(['period'=>$period]);
    rangeCheck($state['from']===$start->format('Y-m-d') && $state['to']===$now->format('Y-m-d'), 'Calendar period mismatch: '.$period);
}
$legacy=Query_state::parse(['from'=>'2026-01-01']);
rangeCheck($legacy['period']==='custom' && $legacy['from']==='2026-01-01', 'Existing date-filter links must continue to work.');
foreach ([['from'=>'2026-02-30'], ['from'=>'2026-02-03','to'=>'2026-02-01']] as $dates) {
    try { Query_state::parse($dates+['period'=>'custom']); throw new LogicException('Invalid range accepted.'); }
    catch (DomainException $expected) {}
}
echo "Date presets, reset, legacy links and custom validation passed.\n";
