<?php
define('BASEPATH',__DIR__);
require dirname(__DIR__).'/application/services/presentation/query_state.php';
$state=Query_state::parse(['q'=>'  Needle  ','page'=>'-7','limit'=>'999','dir'=>'desc','layout'=>'grid'],'updated_at');
if ($state['q']!=='Needle' || $state['page']!==1 || $state['limit']!==10 || $state['dir']!=='DESC' || $state['layout']!=='grid') throw new RuntimeException('Query normalization failed');
$state=Query_state::parse(['from'=>'2024-02-29','to'=>'2026-10-09','limit'=>'25','owner'=>'9','sort'=>'title'],'updated_at');
if ($state['from']!=='2024-02-29' || $state['owner']!==9 || $state['limit']!==25 || $state['sort']!=='title') throw new RuntimeException('Valid filters rejected');
foreach ([['from'=>'2025-02-29'],['from'=>'2026-10-10','to'=>'2026-10-09'],['q'=>['bad']],['owner'=>'1 OR 1=1']] as $bad) {
    try {Query_state::parse($bad); throw new RuntimeException('Invalid filter accepted');}
    catch (DomainException $expected) {}
}
echo "Query state passed (6 normalization and validation cases).\n";
