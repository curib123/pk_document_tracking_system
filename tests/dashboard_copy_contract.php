<?php
declare(strict_types=1);

/* Dashboard UX is a presentation-only change. Every existing visible label,
 * dataset and accessible detail must remain; no new copy is introduced.
 */
$root=dirname(__DIR__);
$view=file_get_contents($root.'/application/view/pages/dashboard/index.php');
$styles=file_get_contents($root.'/public/assets/css/dashboard.css');
$header=file_get_contents($root.'/application/view/layout/header.php');
$chartJs=file_get_contents($root.'/public/assets/js/dashboard.js');

$originalLabels=[
 'PK Document Control Workspace',
 "Welcome back. Here's your document activity and requests at a glance.",
 'Accessible documents','All authorized records',
 'Created by you','Within your document access',
 'Pending approvals','Assigned to you or your role',
 'Outstanding handoffs','Your dispatch / receipt tasks',
 'Your document register','All time · same access rules as the document registers',
 'My request status','% of finalized requests approved or completed',
 'Recent document activity','Document','Type','Action','Date',
 'Count','Share','No accessible documents yet.','No requests submitted yet.',
 'No recent document activity in your authorized scope.'
];
foreach ($originalLabels as $phrase) {
    if (strpos(preg_replace('/\\s+/', ' ', $view),preg_replace('/\\s+/', ' ', $phrase))===FALSE)
        throw new RuntimeException('Original dashboard copy was lost: '.$phrase);
}
$requiredTokens=[
 'dashboard-page','dashboard-welcome','dashboard-welcome-date',
 'dashboard-stat-card','dashboard-stat-icon','dashboard-chart-card',
 'dashboard-chart-box','dashboard-breakdown-table','dashboard-status-summary',
 'dashboard-activity-card','dashboard-activity-table',
 'id="documentTypeChart"','id="requestChart"',
 'data-metric="<?= $key ?>"',
 '<?= (int)$metrics[$key] ?>',
 '<?= html_escape($activity[\'title\']) ?>',
 '<?= html_escape($activity[\'created_at\']) ?>',
 '<?= (int)$metrics[\'completion_rate\'] ?>',
 '<?= html_escape(json_encode(array_values($document_counts))) ?>',
 '<?= html_escape(json_encode(array_values($stats))) ?>'
];
foreach ($requiredTokens as $token) {
    if (strpos(preg_replace('/\\s+/', '', $view),preg_replace('/\\s+/', '', $token))===FALSE)
        throw new RuntimeException('Dashboard data/markup contract missing: '.$token);
}
if (strpos($header,'assets/css/dashboard.css')===FALSE)
    throw new RuntimeException('Scoped dashboard CSS must be loaded.');
if (strpos($styles,'.dashboard-page .dashboard-stat-card')===FALSE ||
    strpos($styles,'.dashboard-page .dashboard-chart-card')===FALSE ||
    strpos($styles,'.dashboard-page .dashboard-activity-table')===FALSE)
    throw new RuntimeException('Dashboard-only CSS selectors must exist.');
if (strpos($chartJs,'dataset.chartLabels')===FALSE ||
    strpos($chartJs,'dataset.chartCounts')===FALSE ||
    strpos($chartJs,"window.Chart")===FALSE)
    throw new RuntimeException('Chart.js data bindings must remain intact.');
echo "Dashboard original copy, metrics and scoped design contracts passed.\n";
