<?php
function html_escape($s) {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function site_url($s) {return '/'.$s;}
$user=['first_name'=>'Viewer','username'=>'viewer'];
$stats=['draft'=>1,'submitted'=>2,'completed'=>3,'rejected'=>1];
$document_counts=['hardcopy'=>2,'softcopy'=>3];
$metrics=['documents'=>5,'created'=>2,'pending_tasks'=>1,'transfers'=>2,'completion_rate'=>75];
$recent_activities=[];
ob_start();include dirname(__DIR__).'/application/view/pages/dashboard/index.php';$html=ob_get_clean();
foreach (['documentTypeChart','data-chart-counts="[2,3]"','75%','No recent document activity','Accessible documents'] as $needle) {
    if (!str_contains($html,$needle)) throw new RuntimeException('Missing dashboard behavior: '.$needle);
}
$document_counts=['hardcopy'=>0,'softcopy'=>0];$metrics['documents']=0;
ob_start();include dirname(__DIR__).'/application/view/pages/dashboard/index.php';$empty=ob_get_clean();
if (!str_contains($empty,'No accessible documents yet')) throw new RuntimeException('Missing empty chart state');
echo "Dashboard rendering passed: real counts, percentage and empty states.\n";
